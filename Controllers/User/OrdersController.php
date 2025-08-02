<?php

class OrdersController
{
    public static function getOrderById($orderId)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }

        // Extract user_id from JWT
        require_once dirname(__DIR__, 2) . '/Controllers/BaseController.php';
        $baseController = new \BaseController();
        $token = $baseController->getBearerToken();
        $user_id = null;
        
        if ($token) {
            try {
                $user = \Helpers\UserAuthHelper::validateJWT($token);
                if ($user && isset($user['user_id'])) {
                    $user_id = $user['user_id'];
                }
            } catch (\Exception $e) {
                error_log('JWT validation error: ' . $e->getMessage());
            }
        }
        
        if (!$user_id) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Authentication required']);
            return;
        }

        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        require_once dirname(__DIR__, 2) . '/models/Order.php';
        
        try {
            $conn = Db::getConnection($config);
            $orderModel = new \Models\Order($conn);
            
            $order = $orderModel->getById($orderId);
            
            if (!$order) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Order not found']);
                return;
            }
            
            // Verify the order belongs to the authenticated user
            if ($order['user_id'] != $user_id) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                return;
            }
            
            // Get user email
            $userSql = 'SELECT email FROM users WHERE id = ?';
            $userStmt = mysqli_prepare($conn, $userSql);
            mysqli_stmt_bind_param($userStmt, 'i', $user_id);
            mysqli_stmt_execute($userStmt);
            $userResult = mysqli_stmt_get_result($userStmt);
            $user = mysqli_fetch_assoc($userResult);
            mysqli_stmt_close($userStmt);
            
            $response = [
                'success' => true,
                'message' => 'Order details fetched successfully',
                'data' => [
                    'order_id' => $order['id'],
                    'customer_email' => $user['email'] ?? '',
                    'total_amount' => (float)$order['total_amount'],
                    'status' => $order['status'],
                    'created_at' => $order['created_at'],
                    'items' => array_map(function($item) {
                        return [
                            'title' => $item['title'],
                            'price' => (float)$item['price']
                        ];
                    }, $order['items'] ?? [])
                ]
            ];
            
        } catch (Exception $e) {
            http_response_code(500);
            $response = [
                'success' => false,
                'message' => 'Failed to fetch order details: ' . $e->getMessage()
            ];
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function getUserOrders()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }

        // Extract user_id from JWT
        require_once dirname(__DIR__, 2) . '/Controllers/BaseController.php';
        $baseController = new \BaseController();
        $token = $baseController->getBearerToken();
        $user_id = null;
        
        if ($token) {
            try {
                $user = \Helpers\UserAuthHelper::validateJWT($token);
                if ($user && isset($user['user_id'])) {
                    $user_id = $user['user_id'];
                }
            } catch (\Exception $e) {
                error_log('JWT validation error: ' . $e->getMessage());
            }
        }
        
        if (!$user_id) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Authentication required']);
            return;
        }

        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        require_once dirname(__DIR__, 2) . '/models/Order.php';
        
        try {
            $conn = Db::getConnection($config);
            $orderModel = new \Models\Order($conn);
            
            // Get user orders with pagination
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $offset = ($page - 1) * $limit;
            
            $orders = $orderModel->list(['user_id' => $user_id], ['limit' => $limit, 'offset' => $offset]);
            
            $response = [
                'success' => true,
                'message' => 'User orders fetched successfully',
                'data' => $orders
            ];
            
        } catch (Exception $e) {
            http_response_code(500);
            $response = [
                'success' => false,
                'message' => 'Failed to fetch user orders: ' . $e->getMessage()
            ];
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
    }
} 