<?php
require_once dirname(__DIR__) . '/src/AuthHelper.php';
class AdminDashboardController
{
    public static function stats()
    {
        AuthHelper::requireAdminAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__) . '/src/Db.php';
        $conn = Db::getConnection($config);
        require_once dirname(__DIR__) . '/models/Dashboard.php';
        $dashboardModel = new Dashboard($conn);
        $stats = $dashboardModel->getStats();
        $response = [
            'success' => true,
            'message' => 'Dashboard stats fetched successfully',
            'data' => $stats
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    }
}
