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
            $where_conditions[] = 'o.status = $' . $param_index;
            $param_values[] = $status;
            $param_index++;
        }
        if ($search) {
            $where_conditions[] = '(LOWER(o.customer_email) LIKE $' . $param_index . ' OR LOWER(o.customer_name) LIKE $' . ($param_index + 1) . ')';
            $search_term = '%' . strtolower($search) . '%';
            $param_values[] = $search_term;
            $param_values[] = $search_term;
            $param_index += 2;
        }
        if ($date_from) {
            $where_conditions[] = 'o.created_at >= $' . $param_index;
            $param_values[] = $date_from;
            $param_index++;
        }
        if ($date_to) {
            $where_conditions[] = 'o.created_at <= $' . $param_index;
            $param_values[] = $date_to;
            $param_index++;
        }
        
        $where_sql = $where_conditions ? ('WHERE ' . implode(' AND ', $where_conditions)) : '';
        
        // Add LIMIT and OFFSET parameters
        $param_values[] = $limit;
        $param_values[] = $offset;
        
        $sql = "SELECT o.id, o.total_amount, o.status, o.created_at, u.email as customer_email, u.name as customer_name FROM orders o LEFT JOIN users u ON o.user_id = u.id $where_sql ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
        
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database query failed: ' . mysqli_error($conn)]);
            return;
        }
        
        // Create parameter types string
        $types = str_repeat('s', count($param_values) - 2) . 'ii'; // All strings except last two which are integers (limit, offset)
        mysqli_stmt_bind_param($stmt, $types, ...$param_values);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $orders = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $orders[] = $row;
        }
        mysqli_stmt_close($stmt);
        
        // Total count for pagination (reuse WHERE conditions but without LIMIT/OFFSET)
        $count_sql = "SELECT COUNT(*) as total FROM orders o LEFT JOIN users u ON o.user_id = u.id $where_sql";
        $count_params = array_slice($param_values, 0, -2); // Remove LIMIT and OFFSET params
        $count_stmt = mysqli_prepare($conn, $count_sql);
        if ($count_stmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Count query failed: ' . mysqli_error($conn)]);
            return;
        }
        
        if (!empty($count_params)) {
            $count_types = str_repeat('s', count($count_params));
            mysqli_stmt_bind_param($count_stmt, $count_types, ...$count_params);
        }
        mysqli_stmt_execute($count_stmt);
        $count_result = mysqli_stmt_get_result($count_stmt);
        $total_row = mysqli_fetch_assoc($count_result);
        $total_items = (int)$total_row['total'];
        mysqli_stmt_close($count_stmt);
        
        $total_pages = (int)ceil($total_items / $limit);
        
        // Fetch items for each order
        foreach ($orders as &$order) {
            $item_sql = 'SELECT oi.note_id, n.title, n.price FROM order_items oi JOIN notes n ON oi.note_id = n.id WHERE oi.order_id = ?';
            $item_stmt = mysqli_prepare($conn, $item_sql);
            mysqli_stmt_bind_param($item_stmt, 'i', $order['id']);
            mysqli_stmt_execute($item_stmt);
            $item_result = mysqli_stmt_get_result($item_stmt);
            $items = [];
            while ($item_row = mysqli_fetch_assoc($item_result)) {
                $items[] = $item_row;
            }
            mysqli_stmt_close($item_stmt);
            $order['items'] = $items;
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
        $conn = Db::getConnection($config);
        $orderModel = new Order($conn);
        $order = $orderModel->getById($order_id);
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
        $conn = Db::getConnection($config);
        
        // Update order status
        $sql = 'UPDATE orders SET status = ? WHERE id = ?';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'si', $input['status'], $order_id);
        mysqli_stmt_execute($stmt);
        $affected_rows = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        
        if ($affected_rows > 0) {
            $response = [
                'success' => true,
                'message' => 'Order status updated successfully'
            ];
        } else {
            http_response_code(404);
            $response = [
                'success' => false,
                'message' => 'Order not found or no changes made'
            ];
        }
        
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
        $conn = Db::getConnection($config);
        $orderModel = new Order($conn);
        
        // Get order details first
        $order = $orderModel->getById($input['order_id']);
        if (!$order) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            return;
        }
        
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
        
        // Update order status using mysqli
        $sql = 'UPDATE orders SET status = ? WHERE id = ?';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'si', 'refunded', $input['order_id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
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
    }

}