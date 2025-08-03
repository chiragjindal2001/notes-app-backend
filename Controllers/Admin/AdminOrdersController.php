<?php
require_once dirname(__DIR__, 2) . '/src/AuthHelper.php';
class AdminOrdersController
{
    public static function listOrders()
    {
        AuthHelper::requireAdminAuth();
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        $conn = Db::getConnection($config);
        
        // Filters
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $status = $_GET['status'] ?? null;
        $search = $_GET['search'] ?? null;
        $date_from = $_GET['date_from'] ?? null;
        $date_to = $_GET['date_to'] ?? null;
        
        // Build WHERE clause and parameters
        $where_conditions = [];
        $param_values = [];
        $param_index = 1;
        
        if ($status) {
            $where_conditions[] = 'o.status = ?';
            $param_values[] = $status;
            $param_index++;
        }
        if ($search) {
            $where_conditions[] = '(LOWER(o.customer_email) LIKE ? OR LOWER(o.customer_name) LIKE ?)';
            $search_term = '%' . strtolower($search) . '%';
            $param_values[] = $search_term;
            $param_values[] = $search_term;
            $param_index += 2;
        }
        if ($date_from) {
            $where_conditions[] = 'o.created_at >= ?';
            $param_values[] = $date_from;
            $param_index++;
        }
        if ($date_to) {
            $where_conditions[] = 'o.created_at <= ?';
            $param_values[] = $date_to;
            $param_index++;
        }
        
        $where_sql = $where_conditions ? ('WHERE ' . implode(' AND ', $where_conditions)) : '';
        
        // Add LIMIT and OFFSET parameters
        $param_values[] = $limit;
        $param_values[] = $offset;
        
        $sql = "SELECT o.order_id, o.customer_email, o.customer_name, o.total_amount, o.status, o.created_at FROM orders o $where_sql ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
        
        $stmt = mysqli_prepare($conn, $sql);
        if (!empty($param_values)) {
            $types = str_repeat('s', count($param_values));
            mysqli_stmt_bind_param($stmt, $types, ...$param_values);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database query failed: ' . mysqli_error($conn)]);
            return;
        }
        
        $orders = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $orders[] = $row;
        }
        
        // Total count for pagination (reuse WHERE conditions but without LIMIT/OFFSET)
        $count_sql = "SELECT COUNT(*) FROM orders o $where_sql";
        $count_params = array_slice($param_values, 0, -2); // Remove LIMIT and OFFSET params
        $count_stmt = mysqli_prepare($conn, $count_sql);
        if (!empty($count_params)) {
            $count_types = str_repeat('s', count($count_params));
            mysqli_stmt_bind_param($count_stmt, $count_types, ...$count_params);
        }
        mysqli_stmt_execute($count_stmt);
        $count_result = mysqli_stmt_get_result($count_stmt);
        if ($count_result === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Count query failed: ' . mysqli_error($conn)]);
            return;
        }
        $total_items = (int)mysqli_fetch_row($count_result)[0];
        $total_pages = (int)ceil($total_items / $limit);
        // Fetch items for each order
        foreach ($orders as &$order) {
            $item_sql = 'SELECT oi.note_id, n.title, n.price FROM order_items oi JOIN notes n ON oi.note_id = n.id WHERE oi.order_id = (SELECT id FROM orders WHERE order_id = ?)';
            $item_stmt = mysqli_prepare($conn, $item_sql);
            mysqli_stmt_bind_param($item_stmt, 's', $order['order_id']);
            mysqli_stmt_execute($item_stmt);
            $item_result = mysqli_stmt_get_result($item_stmt);
            $items = [];
            if ($item_result !== false) {
                while ($item_row = mysqli_fetch_assoc($item_result)) {
                    $items[] = $item_row;
                }
            }
            $order['items'] = $items;
            $order['id'] = $order['order_id'];
            unset($order['order_id']);
        }
        $response = [
            'success' => true,
            'message' => 'Orders fetched successfully',
            'data' => [ 'items' => $orders ],
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_items' => $total_items,
                'items_per_page' => $limit
            ]
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function getOrderDetail($order_id)
    {
        AuthHelper::requireAdminAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        $pdo = Db::getConnection($config);
        $orderModel = new Order($pdo);
        $order = $orderModel->get($order_id);
        if (!$order) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            return;
        }
        $response = [
            'success' => true,
            'message' => 'Order fetched',
            'data' => $order
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function updateOrderStatus($order_id)
    {
        AuthHelper::requireAdminAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['status'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing status']);
            return;
        }
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        $pdo = Db::getConnection($config);
        $orderModel = new Order($pdo);
        $order = $orderModel->updateStatus($order_id, $input['status']);
        if (!$order) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            return;
        }
        $response = [
            'success' => true,
            'message' => 'Order status updated',
            'data' => $order
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function processRefund()
    {
        AuthHelper::requireAdminAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input) || empty($input['order_id']) || empty($input['amount'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing order_id or amount']);
            return;
        }
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        $pdo = Db::getConnection($config);
        $orderModel = new Order($pdo);
        $refund = $orderModel->refund($input['order_id'], $input['amount'], $input['reason'] ?? null);
        $orderModel->updateStatus($input['order_id'], 'refunded');
        // For demo, refund by Razorpay order_id (in real use, use payment_id)
        $razorpay = $config['razorpay'];
        $refund_data = [
            'amount' => (int)round($input['amount'] * 100),
            'speed' => 'normal',
            'notes' => [ 'reason' => $input['reason'] ?? '' ]
            ];
            $ch = curl_init('https://api.razorpay.com/v1/payments/' . $order['razorpay_order_id'] . '/refund');
            curl_setopt($ch, CURLOPT_USERPWD, $razorpay['key_id'] . ':' . $razorpay['key_secret']);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($refund_data));
            $result = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $refund = json_decode($result, true);
            if ($http_code !== 200 || empty($refund['id'])) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to process refund', 'razorpay_response' => $refund]);
                return;
            }
            // Update order status
            pg_query_params('UPDATE orders SET status = :status WHERE order_id = :oid')->execute([
                ':status' => 'refunded', ':oid' => $input['order_id']
            ]);
            $response = [
                'success' => true,
                'message' => 'Refund processed successfully',
                'data' => [
                    'refund_id' => $refund['id'],
                    'amount' => $refund['amount'] / 100.0,
                    'status' => $refund['status'] ?? 'processed',
                    'order' => [ 'id' => $input['order_id'], 'status' => 'refunded' ]
                ]
            ];
            header('Content-Type: application/json');
            echo json_encode($response);
            return;
    }

}