<?php
namespace Models;

class Order
{
    private $conn;
    
    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function create($orderData, $items)
    {
        // Begin transaction
        mysqli_begin_transaction($this->conn);
        
        $sql = 'INSERT INTO orders (
            user_id, 
            total_amount, 
            status, 
            razorpay_order_id, 
            created_at
        ) VALUES (
            ?, ?, ?, ?, NOW()
        )';
        $params = [
            $orderData['user_id'] ?? null,
            $orderData['total_amount'],
            $orderData['status'] ?? 'pending',
            $orderData['razorpay_order_id'] ?? null
        ];
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'idss', 
            $params[0], $params[1], $params[2], $params[3]
        );
        mysqli_stmt_execute($stmt);
        $orderId = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);
        
        if (!$orderId) {
            mysqli_rollback($this->conn);
            error_log('Failed to create order');
            return false;
        }
        
        $totalAmount = 0;
        foreach ($items as $item) {
            // Get note price
            $noteSql = 'SELECT price FROM notes WHERE id = ?';
            $noteStmt = mysqli_prepare($this->conn, $noteSql);
            mysqli_stmt_bind_param($noteStmt, 'i', $item['note_id']);
            mysqli_stmt_execute($noteStmt);
            $noteResult = mysqli_stmt_get_result($noteStmt);
            $note = mysqli_fetch_assoc($noteResult);
            mysqli_stmt_close($noteStmt);
            
            if (!$note) {
                mysqli_rollback($this->conn);
                error_log('Invalid note_id: ' . $item['note_id']);
                return false;
            }
            
            $itemPrice = $note['price'];
            $totalAmount += $itemPrice;
            
            $itemSql = 'INSERT INTO order_items (order_id, note_id, price) VALUES (?, ?, ?)';
            $itemStmt = mysqli_prepare($this->conn, $itemSql);
            mysqli_stmt_bind_param($itemStmt, 'iid', $orderId, $item['note_id'], $itemPrice);
            mysqli_stmt_execute($itemStmt);
            mysqli_stmt_close($itemStmt);
        }
        
        // Update order total amount
        $updateSql = 'UPDATE orders SET total_amount = ? WHERE id = ?';
        $updateStmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($updateStmt, 'di', $totalAmount, $orderId);
        mysqli_stmt_execute($updateStmt);
        mysqli_stmt_close($updateStmt);
        
        mysqli_commit($this->conn);
        return $this->getById($orderId);
    }

    public function getById($orderId)
    {
        $sql = 'SELECT * FROM orders WHERE id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $orderId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $order = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$order) {
            return null;
        }
        
        $itemSql = 'SELECT oi.*, n.title, n.description FROM order_items oi JOIN notes n ON oi.note_id = n.id WHERE oi.order_id = ?';
        $itemStmt = mysqli_prepare($this->conn, $itemSql);
        mysqli_stmt_bind_param($itemStmt, 'i', $orderId);
        mysqli_stmt_execute($itemStmt);
        $itemResult = mysqli_stmt_get_result($itemStmt);
        $items = [];
        while ($row = mysqli_fetch_assoc($itemResult)) {
            $items[] = $row;
        }
        mysqli_stmt_close($itemStmt);
        
        $order['items'] = $items;
        return $order;
    }

    public function findByRazorpayOrderId($razorpayOrderId)
    {
        $sql = 'SELECT * FROM orders WHERE razorpay_order_id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $razorpayOrderId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $row;
    }

    public function updateStatusByRazorpayOrderId($razorpayOrderId, $status, $paymentData = [])
    {
        // Only update status in orders table
        $sql = 'UPDATE orders SET status = ?, updated_at = NOW() WHERE razorpay_order_id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ss', $status, $razorpayOrderId);
        mysqli_stmt_execute($stmt);
        $affectedRows = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        return $affectedRows > 0;
    }

    // New method to insert or update payment status
    public function upsertPaymentStatus($orderId, $razorpayPaymentId, $status, $method = null)
    {
        // Try to update first
        $updateSql = 'UPDATE orders SET status = ?, payment_method = ?, razorpay_payment_id = ?, updated_at = NOW() WHERE id = ?';
        $updateStmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($updateStmt, 'sssi', $status, $method, $razorpayPaymentId, $orderId);
        mysqli_stmt_execute($updateStmt);
        $affectedRows = mysqli_stmt_affected_rows($updateStmt);
        mysqli_stmt_close($updateStmt);
        
        return $affectedRows > 0;
    }

    public function list($filters = [], $pagination = [], $sort = [])
    {
        $where = [];
        $params = [];
        $paramTypes = '';
        
        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
            $paramTypes .= 's';
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = ?';
            $params[] = $filters['user_id'];
            $paramTypes .= 'i';
        }
        if (!empty($filters['email'])) {
            $where[] = 'user_id IN (SELECT id FROM users WHERE email = ?)';
            $params[] = $filters['email'];
            $paramTypes .= 's';
        }
        
        $sql = 'SELECT * FROM orders';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        
        if (!empty($sort['field']) && !empty($sort['direction'])) {
            $validFields = ['id', 'created_at', 'total_amount', 'status'];
            $validDirections = ['ASC', 'DESC'];
            if (in_array($sort['field'], $validFields) && in_array(strtoupper($sort['direction']), $validDirections)) {
                $sql .= ' ORDER BY ' . $sort['field'] . ' ' . strtoupper($sort['direction']);
            }
        } else {
            $sql .= ' ORDER BY created_at DESC';
        }
        
        if (!empty($pagination['limit'])) {
            $sql .= ' LIMIT ?';
            $params[] = (int)$pagination['limit'];
            $paramTypes .= 'i';
            
            if (!empty($pagination['offset'])) {
                $sql .= ' OFFSET ?';
                $params[] = (int)$pagination['offset'];
                $paramTypes .= 'i';
            }
        }
        
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!empty($paramTypes)) {
            mysqli_stmt_bind_param($stmt, $paramTypes, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $orders = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $orders[] = $row;
        }
        mysqli_stmt_close($stmt);
        
        // Get total count
        $countSql = 'SELECT COUNT(*) as total FROM orders';
        if (!empty($where)) {
            $countSql .= ' WHERE ' . implode(' AND ', $where);
        }
        
        $countStmt = mysqli_prepare($this->conn, $countSql);
        if (!empty($paramTypes)) {
            // Remove limit/offset parameters for count query
            $countParams = array_slice($params, 0, count($params) - (isset($pagination['limit']) ? 1 : 0) - (isset($pagination['offset']) ? 1 : 0));
            $countParamTypes = substr($paramTypes, 0, strlen($paramTypes) - (isset($pagination['limit']) ? 1 : 0) - (isset($pagination['offset']) ? 1 : 0));
            if (!empty($countParamTypes)) {
                mysqli_stmt_bind_param($countStmt, $countParamTypes, ...$countParams);
            }
        }
        mysqli_stmt_execute($countStmt);
        $countResult = mysqli_stmt_get_result($countStmt);
        $totalRow = mysqli_fetch_assoc($countResult);
        mysqli_stmt_close($countStmt);
        
        $total = $totalRow ? $totalRow['total'] : 0;
        return [
            'data' => $orders,
            'total' => (int)$total,
            'limit' => $pagination['limit'] ?? null,
            'offset' => $pagination['offset'] ?? 0
        ];
    }
}

