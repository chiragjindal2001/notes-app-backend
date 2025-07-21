<?php
require_once dirname(__DIR__) . '/src/AuthHelper.php';
class AdminCouponsController
{
    public static function createCoupon()
    {
        AuthHelper::requireAdminAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }
        $input = json_decode(file_get_contents('php://input'), true);
        $fields = ['code','type','value','min_amount','max_uses','expires_at'];
        foreach ($fields as $f) {
            if (empty($input[$f])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing field: ' . $f]);
                return;
            }
        }
        $config = require dirname(__DIR__) . '/config/config.development.php';
        require_once dirname(__DIR__) . '/src/Db.php';
        require_once dirname(__DIR__) . '/models/Coupon.php';
        $conn = Db::getConnection($config);
        $couponModel = new Coupon($conn);
        $coupon = $couponModel->create($input);
        $response = [
            'success' => true,
            'message' => 'Coupon created successfully',
            'data' => $coupon
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    }
    public static function listCoupons()
    {
        AuthHelper::requireAdminAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }
        $config = require dirname(__DIR__) . '/config/config.development.php';
        require_once dirname(__DIR__) . '/src/Db.php';
        require_once dirname(__DIR__) . '/models/Coupon.php';
        $pdo = Db::getConnection($config);
        $couponModel = new Coupon($pdo);
        $coupons = $couponModel->list();
        $response = [
            'success' => true,
            'message' => 'Coupons fetched successfully',
            'data' => $coupons
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    }
}
