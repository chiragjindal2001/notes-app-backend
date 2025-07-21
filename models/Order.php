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
        try {
            $this->conn->beginTransaction();
            
            $sql = 'INSERT INTO orders (
                order_id, 
                customer_email, 
                customer_name, 
                phone,
                billing_address,
                total_amount, 
                status, 
                razorpay_order_id, 
                user_id, 
                created_at
            ) VALUES (
                :order_id, 
                :customer_email, 
                :customer_name, 
                :phone,
                :billing_address,
                :total_amount, 
                :status, 
                :razorpay_order_id, 
                :user_id, 
                NOW()
            ) RETURNING id, order_id';
            
            $stmt = $this->conn->prepare($sql);
            
            $params = [
                ':order_id' => $orderData['order_id'],
                ':customer_email' => $orderData['customer_email'],
                ':customer_name' => $orderData['customer_name'],
                ':phone' => $orderData['phone'] ?? null,
                ':billing_address' => $orderData['billing_address'] ?? null,
                ':total_amount' => $orderData['total_amount'],
                ':status' => $orderData['status'] ?? 'pending',
                ':razorpay_order_id' => $orderData['razorpay_order_id'] ?? null,
                ':user_id' => $orderData['user_id'] ?? null
            ];
            
            $stmt->execute($params);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                throw new PDOException('Failed to create order');
            }
            
            // Add order items
            $orderId = $order['id'];
            $itemSql = 'INSERT INTO order_items (order_id, note_id, quantity, price) VALUES (:order_id, :note_id, :quantity, :price)';
            $itemStmt = $this->conn->prepare($itemSql);
            
            // Calculate total amount from items
            $totalAmount = 0;
            
            foreach ($items as $item) {
                // Get note price
                $noteStmt = $this->conn->prepare('SELECT price FROM notes WHERE id = :note_id');
                $noteStmt->execute([':note_id' => $item['note_id']]);
                $note = $noteStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$note) {
                    throw new PDOException('Invalid note_id: ' . $item['note_id']);
                }
                
                $itemPrice = $note['price'];
                $totalAmount += $itemPrice * $item['quantity'];
                
                $itemStmt->execute([
                    ':order_id' => $orderId,
                    ':note_id' => $item['note_id'],
                    ':quantity' => $item['quantity'],
                    ':price' => $itemPrice
                ]);
            }
            
            // Update order total amount
            $updateSql = 'UPDATE orders SET total_amount = :total_amount WHERE id = :id';
            $updateStmt = $this->conn->prepare($updateSql);
            $updateStmt->execute([
                ':total_amount' => $totalAmount,
                ':id' => $orderId
            ]);
            
            $this->conn->commit();
            
            // Fetch the complete order with items
            return $this->getById($orderId);
            
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('Order creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getById($orderId)
    {
        try {
            // Get order
            $stmt = $this->conn->prepare('SELECT * FROM orders WHERE id = :id');
            $stmt->execute([':id' => $orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                return null;
            }
            
            // Get order items
            $itemStmt = $this->conn->prepare('SELECT oi.*, n.title, n.description FROM order_items oi JOIN notes n ON oi.note_id = n.id WHERE oi.order_id = :order_id');
            $itemStmt->execute([':order_id' => $orderId]);
            $order['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $order;
            
        } catch (PDOException $e) {
            error_log('Error fetching order: ' . $e->getMessage());
            throw $e;
        }
    }
    
    public function findByRazorpayOrderId($razorpayOrderId)
    {
        try {
            $stmt = $this->conn->prepare('SELECT * FROM orders WHERE razorpay_order_id = :razorpay_order_id');
            $stmt->execute([':razorpay_order_id' => $razorpayOrderId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log('Error finding order by Razorpay ID: ' . $e->getMessage());
            throw $e;
        }
    }
    
    public function updateStatusByRazorpayOrderId($razorpayOrderId, $status, $paymentData = [])
    {
        try {
            $sql = 'UPDATE orders SET status = :status, updated_at = NOW()';
            $params = [
                ':status' => $status,
                ':razorpay_order_id' => $razorpayOrderId
            ];
            
            // Add payment data if provided
            if (!empty($paymentData['razorpay_payment_id'])) {
                $sql .= ', razorpay_payment_id = :razorpay_payment_id';
                $params[':razorpay_payment_id'] = $paymentData['razorpay_payment_id'];
            }
            
            if (!empty($paymentData['razorpay_signature'])) {
                $sql .= ', razorpay_signature = :razorpay_signature';
                $params[':razorpay_signature'] = $paymentData['razorpay_signature'];
            }
            
            $sql .= ' WHERE razorpay_order_id = :razorpay_order_id';
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->rowCount() > 0;
            
        } catch (PDOException $e) {
            error_log('Error updating order status: ' . $e->getMessage());
            throw $e;
        }
    }
    
    public function list($filters = [], $pagination = [], $sort = [])
    {
        try {
            $where = [];
            $params = [];
            
            // Apply filters
            if (!empty($filters['status'])) {
                $where[] = 'status = :status';
                $params[':status'] = $filters['status'];
            }
            
            if (!empty($filters['user_id'])) {
                $where[] = 'user_id = :user_id';
                $params[':user_id'] = $filters['user_id'];
            }
            
            if (!empty($filters['email'])) {
                $where[] = 'customer_email = :email';
                $params[':email'] = $filters['email'];
            }
            
            // Build query
            $sql = 'SELECT * FROM orders';
            
            if (!empty($where)) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            
            // Apply sorting
            if (!empty($sort['field']) && !empty($sort['direction'])) {
                $validFields = ['id', 'created_at', 'total_amount', 'status'];
                $validDirections = ['ASC', 'DESC'];
                
                if (in_array($sort['field'], $validFields) && in_array(strtoupper($sort['direction']), $validDirections)) {
                    $sql .= ' ORDER BY ' . $sort['field'] . ' ' . strtoupper($sort['direction']);
                }
            } else {
                $sql .= ' ORDER BY created_at DESC';
            }
            
            // Apply pagination
            if (!empty($pagination['limit'])) {
                $sql .= ' LIMIT :limit';
                $params[':limit'] = (int)$pagination['limit'];
                
                if (!empty($pagination['offset'])) {
                    $sql .= ' OFFSET :offset';
                    $params[':offset'] = (int)$pagination['offset'];
                }
            }
            
            $stmt = $this->conn->prepare($sql);
            
            // Bind parameters
            foreach ($params as $key => $value) {
                $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue($key, $value, $paramType);
            }
            
            $stmt->execute();
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count for pagination
            $countSql = 'SELECT COUNT(*) as total FROM orders';
            if (!empty($where)) {
                $countSql .= ' WHERE ' . implode(' AND ', $where);
            }
            
            $countStmt = $this->conn->prepare($countSql);
            
            // Remove limit/offset params for count query
            $countParams = $params;
            unset($countParams[':limit'], $countParams[':offset']);
            
            $countStmt->execute($countParams);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            return [
                'data' => $orders,
                'total' => (int)$total,
                'limit' => $pagination['limit'] ?? null,
                'offset' => $pagination['offset'] ?? 0
            ];
            
        } catch (PDOException $e) {
            error_log('Error listing orders: ' . $e->getMessage());
            throw $e;
        }
    }
}
