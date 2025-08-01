<?php
require_once dirname(__DIR__) . '/src/AuthHelper.php';
require_once dirname(__DIR__, 2) . '/src/Helpers/Config.php';

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
        $config = [
            'db' => \Helpers\Config::database(),
        ];
        require_once dirname(__DIR__, 2) . '/src/Db.php';
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
