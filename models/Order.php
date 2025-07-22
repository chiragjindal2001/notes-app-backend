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
        pg_query($this->conn, "BEGIN");
        
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
            $1, $2, $3, $4, $5, $6, $7, $8, NOW()
        ) RETURNING id, order_id';
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
        $result = pg_query_params($this->conn, $sql, $params);
        $order = pg_fetch_assoc($result);
        if (!$order) {
            pg_query($this->conn, "ROLLBACK");
            error_log('Failed to create order');
            return false;
        }
        $orderId = $order['order_id'];
        $totalAmount = 0;
        foreach ($items as $item) {
            // Get note price
            $noteSql = 'SELECT price FROM notes WHERE id = $1';
            $noteResult = pg_query_params($this->conn, $noteSql, [$item['note_id']]);
            $note = pg_fetch_assoc($noteResult);
            if (!$note) {
                pg_query($this->conn, "ROLLBACK");
                error_log('Invalid note_id: ' . $item['note_id']);
                return false;
            }
            $itemPrice = $note['price'];
            $totalAmount += $itemPrice;
            $itemSql = 'INSERT INTO order_items (order_id, note_id, price) VALUES ($1, $2, $3)';
            pg_query_params($this->conn, $itemSql, [$orderId, $item['note_id'], $itemPrice]);
        }
        // Update order total amount
        $updateSql = 'UPDATE orders SET total_amount = $1 WHERE order_id = $2';
        pg_query_params($this->conn, $updateSql, [$totalAmount, $orderId]);
        pg_query($this->conn, "COMMIT");
        return $this->getById($orderId);
    }

    public function getById($orderId)
    {
        $sql = 'SELECT * FROM orders WHERE order_id = $1';
        $result = pg_query_params($this->conn, $sql, [$orderId]);
        $order = pg_fetch_assoc($result);
        if (!$order) {
            return null;
        }
        $itemSql = 'SELECT oi.*, n.title, n.description FROM order_items oi JOIN notes n ON oi.note_id = n.id WHERE oi.order_id = $1';
        $itemResult = pg_query_params($this->conn, $itemSql, [$orderId]);
        $items = [];
        while ($row = pg_fetch_assoc($itemResult)) {
            $items[] = $row;
        }
        $order['items'] = $items;
        return $order;
    }

    public function findByRazorpayOrderId($razorpayOrderId)
    {
        $sql = 'SELECT * FROM orders WHERE razorpay_order_id = $1';
        $result = pg_query_params($this->conn, $sql, [$razorpayOrderId]);
        return pg_fetch_assoc($result);
    }

    public function updateStatusByRazorpayOrderId($razorpayOrderId, $status, $paymentData = [])
    {
        $sql = 'UPDATE orders SET status = $1, updated_at = NOW()';
        $params = [$status];
        $paramIndex = 2;
        if (!empty($paymentData['razorpay_payment_id'])) {
            $sql .= ', razorpay_payment_id = $' . $paramIndex;
            $params[] = $paymentData['razorpay_payment_id'];
            $paramIndex++;
        }
        if (!empty($paymentData['razorpay_signature'])) {
            $sql .= ', razorpay_signature = $' . $paramIndex;
            $params[] = $paymentData['razorpay_signature'];
            $paramIndex++;
        }
        $sql .= ' WHERE razorpay_order_id = $' . $paramIndex;
        $params[] = $razorpayOrderId;
        $result = pg_query_params($this->conn, $sql, $params);
        return pg_affected_rows($result) > 0;
    }

    public function list($filters = [], $pagination = [], $sort = [])
    {
        $where = [];
        $params = [];
        $paramIndex = 1;
        if (!empty($filters['status'])) {
            $where[] = 'status = $' . $paramIndex;
            $params[] = $filters['status'];
            $paramIndex++;
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = $' . $paramIndex;
            $params[] = $filters['user_id'];
            $paramIndex++;
        }
        if (!empty($filters['email'])) {
            $where[] = 'customer_email = $' . $paramIndex;
            $params[] = $filters['email'];
            $paramIndex++;
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
            $sql .= ' LIMIT $' . $paramIndex;
            $params[] = (int)$pagination['limit'];
            $paramIndex++;
            if (!empty($pagination['offset'])) {
                $sql .= ' OFFSET $' . $paramIndex;
                $params[] = (int)$pagination['offset'];
                $paramIndex++;
            }
        }
        $result = pg_query_params($this->conn, $sql, $params);
        $orders = [];
        while ($row = pg_fetch_assoc($result)) {
            $orders[] = $row;
        }
        $countSql = 'SELECT COUNT(*) as total FROM orders';
        if (!empty($where)) {
            $countSql .= ' WHERE ' . implode(' AND ', $where);
        }
        $countResult = pg_query_params($this->conn, $countSql, $params);
        $totalRow = pg_fetch_assoc($countResult);
        $total = $totalRow ? $totalRow['total'] : 0;
        return [
            'data' => $orders,
            'total' => (int)$total,
            'limit' => $pagination['limit'] ?? null,
            'offset' => $pagination['offset'] ?? 0
        ];
    }
}
