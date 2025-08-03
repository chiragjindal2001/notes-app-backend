<?php
namespace Models;

use PDO;
use PDOException;

class Order
{
    private $conn;
    
    public function __construct($pdo)
    {
        $this->conn = $pdo;
    }

    public function create($orderData, $items)
    {
        // Begin transaction
        mysqli_query($this->conn, "START TRANSACTION");
        
        $sql = 'INSERT INTO orders (
            order_id, 
            customer_email, 
            customer_name, 
            phone,
            total_amount, 
            status, 
            razorpay_order_id, 
            user_id, 
            created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, NOW()
        )';
        $params = [
            $orderData['order_id'],
            $orderData['customer_email'],
            $orderData['customer_name'],
            $orderData['phone'] ?? null,
            $orderData['total_amount'],
            $orderData['status'] ?? 'pending',
            $orderData['razorpay_order_id'] ?? null,
            $orderData['user_id'] ?? null
        ];
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ssssdssi', 
            $params[0], $params[1], $params[2], $params[3], $params[4], $params[5], $params[6], $params[7]
        );
        mysqli_stmt_execute($stmt);
        
        // Get the inserted ID
        $insertedId = mysqli_insert_id($this->conn);
        
        // Fetch the inserted record
        $selectStmt = mysqli_prepare($this->conn, 'SELECT id, order_id FROM orders WHERE id = ?');
        mysqli_stmt_bind_param($selectStmt, 'i', $insertedId);
        mysqli_stmt_execute($selectStmt);
        $result = mysqli_stmt_get_result($selectStmt);
        $order = mysqli_fetch_assoc($result);
        
        if (!$order) {
            mysqli_query($this->conn, "ROLLBACK");
            error_log('Failed to create order');
            return false;
        }
        $orderId = $order['order_id'];
        $totalAmount = 0;
        foreach ($items as $item) {
            // Get note price
            $noteSql = 'SELECT price FROM notes WHERE id = ?';
            $noteStmt = mysqli_prepare($this->conn, $noteSql);
            $note_id = $item['note_id'];
            mysqli_stmt_bind_param($noteStmt, 'i', $note_id);
            mysqli_stmt_execute($noteStmt);
            $noteResult = mysqli_stmt_get_result($noteStmt);
            $note = mysqli_fetch_assoc($noteResult);
            if (!$note) {
                mysqli_query($this->conn, "ROLLBACK");
                error_log('Invalid note_id: ' . $item['note_id']);
                return false;
            }
            $itemPrice = $note['price'];
            $totalAmount += $itemPrice;
            $itemSql = 'INSERT INTO order_items (order_id, note_id, price) VALUES (?, ?, ?)';
            $itemStmt = mysqli_prepare($this->conn, $itemSql);
            mysqli_stmt_bind_param($itemStmt, 'sid', $orderId, $item['note_id'], $itemPrice);
            mysqli_stmt_execute($itemStmt);
        }
        // Update order total amount
        $updateSql = 'UPDATE orders SET total_amount = ? WHERE order_id = ?';
        $updateStmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($updateStmt, 'ds', $totalAmount, $orderId);
        mysqli_stmt_execute($updateStmt);
        mysqli_query($this->conn, "COMMIT");
        return $this->getById($orderId);
    }

    public function getById($orderId)
    {
        $sql = 'SELECT * FROM orders WHERE order_id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $orderId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $order = mysqli_fetch_assoc($result);
        if (!$order) {
            return null;
        }
        $itemSql = 'SELECT oi.*, n.title, n.description FROM order_items oi JOIN notes n ON oi.note_id = n.id WHERE oi.order_id = ?';
        $itemStmt = mysqli_prepare($this->conn, $itemSql);
        mysqli_stmt_bind_param($itemStmt, 's', $orderId);
        mysqli_stmt_execute($itemStmt);
        $itemResult = mysqli_stmt_get_result($itemStmt);
        $items = [];
        while ($row = mysqli_fetch_assoc($itemResult)) {
            $items[] = $row;
        }
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
        return mysqli_fetch_assoc($result);
    }

    public function updateStatusByRazorpayOrderId($razorpayOrderId, $status, $paymentData = [])
    {
        // Only update status in orders table
        $sql = 'UPDATE orders SET status = ?, updated_at = NOW() WHERE razorpay_order_id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ss', $status, $razorpayOrderId);
        mysqli_stmt_execute($stmt);
        return mysqli_stmt_affected_rows($stmt) > 0;
    }

    // New method to insert or update payment status
    public function upsertPaymentStatus($orderId, $razorpayPaymentId, $status, $method = null)
    {
        // Try to update first
        $updateSql = 'UPDATE payment_status SET status = ?, method = ?, modified_at = NOW() WHERE order_id = ? AND razorpay_payment_id = ?';
        $updateStmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($updateStmt, 'ssss', $status, $method, $orderId, $razorpayPaymentId);
        mysqli_stmt_execute($updateStmt);
        if (mysqli_stmt_affected_rows($updateStmt) > 0) {
            return true;
        }
        // If not updated, insert
        $insertSql = 'INSERT INTO payment_status (order_id, razorpay_payment_id, status, method, created_at, modified_at) VALUES (?, ?, ?, ?, NOW(), NOW())';
        $insertStmt = mysqli_prepare($this->conn, $insertSql);
        mysqli_stmt_bind_param($insertStmt, 'ssss', $orderId, $razorpayPaymentId, $status, $method);
        mysqli_stmt_execute($insertStmt);
        return mysqli_stmt_affected_rows($insertStmt) > 0;
    }

    public function list($filters = [], $pagination = [], $sort = [])
    {
        $where = [];
        $params = [];
        $paramTypes = '';
        $idx = 0;
        
        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
            $paramTypes .= 's';
            $idx++;
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = ?';
            $params[] = $filters['user_id'];
            $paramTypes .= 'i';
            $idx++;
        }
        if (!empty($filters['email'])) {
            $where[] = 'customer_email = ?';
            $params[] = $filters['email'];
            $paramTypes .= 's';
            $idx++;
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
            $idx++;
            if (!empty($pagination['offset'])) {
                $sql .= ' OFFSET ?';
                $params[] = (int)$pagination['offset'];
                $paramTypes .= 'i';
                $idx++;
            }
        }
        
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $paramTypes, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $orders = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $orders[] = $row;
        }
        
        $countSql = 'SELECT COUNT(*) as total FROM orders';
        if (!empty($where)) {
            $countSql .= ' WHERE ' . implode(' AND ', $where);
        }
        $countStmt = mysqli_prepare($this->conn, $countSql);
        if (!empty($params)) {
            mysqli_stmt_bind_param($countStmt, $paramTypes, ...$params);
        }
        mysqli_stmt_execute($countStmt);
        $countResult = mysqli_stmt_get_result($countStmt);
        $totalRow = mysqli_fetch_assoc($countResult);
        $total = $totalRow ? $totalRow['total'] : 0;
        return [
            'data' => $orders,
            'total' => (int)$total,
            'limit' => $pagination['limit'] ?? null,
            'offset' => $pagination['offset'] ?? 0
        ];
    }
}

