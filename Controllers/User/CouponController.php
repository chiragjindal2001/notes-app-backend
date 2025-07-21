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
        $couponModel = new Coupon($pdo);
        $coupon = $couponModel->validate($code, $total);
        if (!$coupon) {
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Coupon is invalid', 'data' => ['valid' => false]]);
            return;
        }
        $discount = 0.0;
        if ($coupon['type'] === 'percentage') {
            $discount = round($total * ($coupon['value'] / 100), 2);
        } elseif ($coupon['type'] === 'fixed') {
            $discount = min($coupon['value'], $total);
        }
        $final = max(0, $total - $discount);
        $response = [
            'success' => true,
            'message' => 'Coupon is valid',
            'data' => [
                'valid' => true,
                'coupon' => [
                    'code' => $coupon['code'],
                    'type' => $coupon['type'],
                    'value' => (float)$coupon['value']
                ],
                'discount_amount' => $discount,
                'final_amount' => $final
            ]
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    }
}
