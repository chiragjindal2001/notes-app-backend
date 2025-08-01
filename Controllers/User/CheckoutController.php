<?php
namespace Controllers\User;

use Helpers\Config;
use Helpers\Database;
use Models\Order;
use Helpers\UserAuthHelper;
use Services\EmailService;

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
        
        if (!is_array($input) || empty($input['items']) || empty($input['customer_info'])) {
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
            else{
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                return;
            }
        }

        // Generate order_id
        $order_id = $user_id . '-ORD-' . time() . '-' . rand(100,999);
        $customer_details = $input['customer_info'];
        $customer_name = trim(($customer_details['first_name'] ?? '') . ' ' . ($customer_details['last_name'] ?? ''));
        $orderData = [
            'order_id' => $order_id,
            'customer_email' => $customer_details['email'],
            'customer_name' => $customer_name,
            'phone' => $input['customer_info']['phone'],
            'total_amount' => 0, // will be updated in the model
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

        // Create Razorpay order
        $razorpay = \Helpers\Config::razorpay();
        $data = [
            'amount' => $amount_in_paise,
            'currency' => 'INR',
            'receipt' => $order_id,
            'payment_capture' => 1,
            'notes' => [
                'order_id' => $order_id
            ]
        ];
        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt($ch, CURLOPT_USERPWD, $razorpay['key_id'] . ':' . $razorpay['key_secret']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        // Define custom log file for Razorpay debug
        
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
        pg_query_params($pdo, 'UPDATE orders SET razorpay_order_id = $1 WHERE order_id = $2', [$razorpay_order['id'], $order_id]);

        $response = [
            'success' => true,
            'message' => 'Order created successfully',
            'data' => [
                'razorpay_order_id' => $razorpay_order['id'],
                'order_id' => $order_id,
                'amount' => $amount_in_paise,
                'key' => $razorpay['key_id'],
                'customer' => [
                    'name' => $customer_name,
                    'email' => $customer_details['email'],
                    'phone' => $customer_details['phone'] ?? null
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
        $razorpay = \Helpers\Config::razorpay();
        $razorpay_key_secret = $razorpay['key_secret'];

        // Verify the payment signature
        $generated_signature = hash_hmac('sha256', $input['razorpay_order_id'] . '|' . $input['razorpay_payment_id'], $razorpay_key_secret);

        if ($generated_signature === $input['razorpay_signature']) {
            // Fetch payment details from Razorpay
            $payment_id = $input['razorpay_payment_id'];
            $razorpay_order_id = $input['razorpay_order_id'];
            $razorpay_key_id = $razorpay['key_id'];
            $ch = curl_init("https://api.razorpay.com/v1/payments/$payment_id");
            curl_setopt($ch, CURLOPT_USERPWD, $razorpay_key_id . ':' . $razorpay_key_secret);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $payment_result = curl_exec($ch);
            curl_close($ch);
            $payment = json_decode($payment_result, true);
            if (
                $payment &&
                isset($payment['status'], $payment['order_id']) &&
                $payment['status'] === 'captured' &&
                $payment['order_id'] === $razorpay_order_id
            ) {
                try {
                    // Get database connection
                    $pdo = Database::getConnection();
                    pg_query($pdo, 'BEGIN');
                    $orderModel = new Order($pdo);
                    // Update order status to 'paid'
                    $updated = $orderModel->updateStatusByRazorpayOrderId(
                        $razorpay_order_id,
                        'paid'
                    );
                    // Fetch order_id from orders table
                    $orderRow = $orderModel->findByRazorpayOrderId($razorpay_order_id);
                    if ($orderRow && isset($orderRow['order_id'])) {
                        $orderModel->upsertPaymentStatus($orderRow['order_id'], $payment_id, $payment['status'], $payment['method'] ?? null);
                    }
                    if ($updated) {
                        // Send payment confirmation email
                        try {
                            $emailService = new EmailService();
                            $orderDetails = $orderModel->findByRazorpayOrderId($razorpay_order_id);
                            
                            if ($orderDetails && isset($orderDetails['user_id'])) {
                                $userEmail = $emailService->getUserEmail($orderDetails['user_id']);
                                $userName = $emailService->getUserName($orderDetails['user_id']);
                                
                                if ($userEmail) {
                                    $emailData = [
                                        'order_id' => $orderDetails['order_id'],
                                        'payment_id' => $input['razorpay_payment_id'],
                                        'total_amount' => $orderDetails['total_amount']
                                    ];
                                    
                                    $emailService->sendPaymentConfirmation($userEmail, $userName, $emailData);
                                }
                            }
                        } catch (\Exception $emailError) {
                            // Log email error but don't fail the payment verification
                            error_log('Failed to send payment confirmation email: ' . $emailError->getMessage());
                        }
                        
                        pg_query($pdo, 'COMMIT');
                        http_response_code(200);
                        echo json_encode([
                            'success' => true,
                            'message' => 'Payment verified and order updated successfully',
                            'payment_id' => $input['razorpay_payment_id']
                        ]);
                        return;
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
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Payment not captured or order_id mismatch',
                    'payment_status' => $payment['status'] ?? null,
                    'payment_order_id' => $payment['order_id'] ?? null,
                    'expected_order_id' => $razorpay_order_id
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
        return;
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
        $razorpay = \Helpers\Config::razorpay();
        $key_secret = $razorpay['key_secret'];
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
