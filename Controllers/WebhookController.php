<?php
class WebhookController
{
    public static function handleRazorpayWebhook()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }
        
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        $webhook_secret = $config['razorpay']['webhook_secret'] ?? null;
        $body = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';
        
        if (!$webhook_secret || !$signature) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing webhook secret or signature']);
            return;
        }
        
        $expected_signature = hash_hmac('sha256', $body, $webhook_secret);
        if (!hash_equals($expected_signature, $signature)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid webhook signature']);
            return;
        }
        
        $payload = json_decode($body, true);
        
        // Example: handle payment.captured event
        if ($payload['event'] === 'payment.captured') {
            $razorpay_order_id = $payload['payload']['payment']['entity']['notes']['order_id'] ?? null;
            
            if ($razorpay_order_id) {
                require_once dirname(__DIR__, 2) . '/src/Db.php';
                require_once dirname(__DIR__, 2) . '/models/Order.php';
                
                $conn = Db::getConnection($config);
                $orderModel = new Order($conn);
                
                // Update order status to completed
                $orderModel->updateStatusByRazorpayOrderId($razorpay_order_id, 'completed');
                
                // Update payment details
                $payment_id = $payload['payload']['payment']['entity']['id'] ?? null;
                $payment_method = $payload['payload']['payment']['entity']['method'] ?? null;
                
                if ($payment_id) {
                    $order = $orderModel->findByRazorpayOrderId($razorpay_order_id);
                    if ($order) {
                        $orderModel->upsertPaymentStatus($order['id'], $payment_id, 'captured', $payment_method);
                    }
                }
            }
        }
        
        $response = [
            'success' => true,
            'message' => 'Webhook processed successfully'
        ];
        
        header('Content-Type: application/json');
        echo json_encode($response);
    }
}