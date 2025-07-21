<?php
namespace Controllers\User;

use Helpers\Config;
use Helpers\Database;
use Models\Order;
use Helpers\UserAuthHelper;

class CheckoutController
{
    public static function createOrder()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }

        // Get database connection
        $pdo = Database::getConnection();
        $orderModel = new Order($pdo);
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!is_array($input) || empty($input['items']) || empty($input['customer_info']) || empty($input['billing_address'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            return;
        }

        // Extract user_id from JWT if present
        $user_id = null;
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? null);
        
        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            $user = UserAuthHelper::validateJWT($token);
            if ($user && isset($user['user_id'])) {
                $user_id = $user['user_id'];
            }
        }

        $orderData = [
            'customer_email' => $input['customer_info']['email'],
            'customer_name' => trim(($input['customer_info']['first_name'] ?? '') . ' ' . ($input['customer_info']['last_name'] ?? '')),
            'phone' => $input['customer_info']['phone'],
            'billing_address' => json_encode($input['billing_address']),
            'status' => 'pending',
            'user_id' => $user_id
        ];

        $items = $input['items'];
        $order = $orderModel->create($orderData, $items);
        
        if (!$order) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Failed to create order']);
            return;
        }

        $amount_in_paise = (int) round($order['total_amount'] * 100);
        $currency = 'INR';

        // Generate order_id
        $order_id = 'ORD-' . time() . '-' . rand(100,999);
        $customer = $input['customer_info'];
        $billing = $input['billing_address'];
        $customer_name = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));

        // Insert order
        $sql = 'INSERT INTO orders (order_id, customer_email, customer_name, phone, billing_address, status, total_amount, currency, created_at) VALUES (:order_id, :email, :name, :phone, :billing, :status, :total, :currency, NOW()) RETURNING id';
        $stmt = pg_query_params($sql);
        $stmt->execute([
            ':order_id' => $order_id,
            ':email' => $customer['email'],
            ':name' => $customer_name,
            ':phone' => $customer['phone'],
            ':billing' => json_encode($billing),
            ':status' => 'pending',
            ':total' => $amount,
            ':currency' => $currency
        ]);
        $db_order_id = $stmt->fetchColumn();
        // Insert order items
        $item_sql = 'INSERT INTO order_items (order_id, note_id, quantity) VALUES (:order_id, :note_id, :quantity)';
        $item_stmt = pg_query_params($item_sql);
        foreach ($input['items'] as $item) {
            $item_stmt->execute([
                ':order_id' => $db_order_id,
                ':note_id' => $item['note_id'],
                ':quantity' => $item['quantity']
            ]);
        }

        // Create Razorpay order
        $config = require dirname(__DIR__) . '/config/config.development.php';
        $razorpay = $config['razorpay'];
        $data = [
            'amount' => $amount_in_paise,
            'currency' => $currency,
            'receipt' => $order_id,
            'payment_capture' => 1
        ];
        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt($ch, CURLOPT_USERPWD, $razorpay['key_id'] . ':' . $razorpay['key_secret']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $razorpay_order = json_decode($result, true);
        if ($http_code !== 200 || empty($razorpay_order['id'])) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create Razorpay order', 'razorpay_response' => $razorpay_order]);
            return;
        }
        // Update order with razorpay_order_id
        pg_query_params('UPDATE orders SET razorpay_order_id = :rpid WHERE id = :id')->execute([
            ':rpid' => $razorpay_order['id'], ':id' => $db_order_id
        ]);

        $response = [
            'success' => true,
            'message' => 'Order created successfully',
            'data' => [
                'razorpay_order_id' => $razorpay_order['id'],
                'order_id' => $order_id,
                'amount' => $amount_in_paise,
                'currency' => $currency,
                'key' => $razorpay['key_id'],
                'customer' => [
                    'name' => $customer_name,
                    'email' => $customer['email']
                ]
            ]
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function verifyPayment()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['razorpay_payment_id']) || !isset($input['razorpay_order_id']) || !isset($input['razorpay_signature'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required payment data']);
            return;
        }

        // Get Razorpay config
        $razorpayConfig = Config::razorpay();
        $razorpay_key_secret = $razorpayConfig['key_secret'];

        // Verify the payment signature
        $generated_signature = hash_hmac('sha256', $input['razorpay_order_id'] . '|' . $input['razorpay_payment_id'], $razorpay_key_secret);

        if ($generated_signature === $input['razorpay_signature']) {
            // Payment signature is valid
            // Update your database here to mark the order as paid
            
            try {
                // Get database connection
                $pdo = Database::getConnection();
                $orderModel = new Order($pdo);
                
                // Update order status to 'paid'
                $updated = $orderModel->updateStatusByRazorpayOrderId(
                    $input['razorpay_order_id'],
                    'paid',
                    [
                        'razorpay_payment_id' => $input['razorpay_payment_id'],
                        'razorpay_signature' => $input['razorpay_signature']
                    ]
                );
                
                if ($updated) {
                    http_response_code(200);
                    echo json_encode([
                        'success' => true,
                        'message' => 'Payment verified and order updated successfully',
                        'payment_id' => $input['razorpay_payment_id']
                    ]);
                } else {
                    throw new \Exception('Failed to update order status');
                }
                
            } catch (\Exception $e) {
                error_log('Error updating order status: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Payment verified but failed to update order status',
                    'error' => $e->getMessage()
                ]);
            }
        } else {
            // Signature verification failed
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid payment signature'
            ]);
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            return;
        }
        $response = [
            'success' => true,
            'message' => 'Payment verified successfully',
            'data' => [
                'order' => [
                    'id' => $order['order_id'],
                    'status' => 'completed',
                    'total_amount' => (float)$order['total_amount'],
                    'customer_email' => $order['customer_email'],
                    'customer_name' => $order['customer_name'],
                    'completed_at' => $order['completed_at']
                ],
                'payment_id' => $input['razorpay_payment_id']
            ]
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function verifySignature()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }
        $input = json_decode(file_get_contents('php://input'), true);
        $required = ['razorpay_order_id', 'razorpay_payment_id', 'razorpay_signature'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing field: ' . $field]);
                return;
            }
        }
        $config = require dirname(__DIR__) . '/config/config.development.php';
        $key_secret = $config['razorpay']['key_secret'];
        $generated_signature = hash_hmac('sha256', $input['razorpay_order_id'] . '|' . $input['razorpay_payment_id'], $key_secret);
        $valid = ($generated_signature === $input['razorpay_signature']);
        $response = [
            'success' => $valid,
            'message' => $valid ? 'Payment signature is valid' : 'Invalid payment signature',
            'data' => ['valid' => $valid]
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    }
}
