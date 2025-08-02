<?php
class CouponController
{
    public static function validateCoupon()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input) || empty($input['code']) || !isset($input['total_amount'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing code or total_amount']);
            return;
        }
        
        $code = strtoupper(trim($input['code']));
        $total = (float)$input['total_amount'];
        
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        require_once dirname(__DIR__, 2) . '/models/Coupon.php';
        
        $conn = Db::getConnection($config);
        $couponModel = new Coupon($conn);
        $coupon = $couponModel->validate($code, $total);
        
        if (!$coupon) {
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Coupon is invalid', 'data' => ['valid' => false]]);
            return;
        }
        
        // Calculate discount based on percentage
        $discount = round($total * ($coupon['discount_percent'] / 100), 2);
        $final = max(0, $total - $discount);
        
        $response = [
            'success' => true,
            'message' => 'Coupon is valid',
            'data' => [
                'valid' => true,
                'coupon' => [
                    'code' => $coupon['code'],
                    'discount_percent' => (int)$coupon['discount_percent']
                ],
                'discount_amount' => $discount,
                'final_amount' => $final
            ]
        ];
        
        header('Content-Type: application/json');
        echo json_encode($response);
    }
}
