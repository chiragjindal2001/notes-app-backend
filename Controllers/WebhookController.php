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
            $order_id = $payload['payload']['payment']['entity']['notes']['order_id'] ?? null;
            if ($order_id) {
                require_once dirname(__DIR__) . '/src/Db.php';
                require_once dirname(__DIR__) . '/models/Order.php';
                $pdo = Db::getConnection($config);
                $orderModel = new Order($pdo);
                $orderModel->updateStatus($order_id, 'completed');
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



order_id
razorpay_order_id

notes = {
    order_id: razorpay_order_id,
}

mappping

options = {

    api_key: 
    ordeR_id = razorpay_order_id,
    prefill ,
    status_hanler = funciotn() {
        window.location.href = "/payment/?order_id=" . order_id;
    }


}
 new razorpay(options;)



 backedn:
 order_id = razorpay_order_id,

 orderdetails = 

 details fetch 
 payments = 

}


order_i  is_active;


rdirect = success
faile d= failed
pending