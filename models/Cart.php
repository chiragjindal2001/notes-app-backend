<?php
class Cart
{
    private $conn;
    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function addItem($data)
    {
        // Requires: user_id, note_id
        if (empty($data['user_id'])) {
            throw new Exception('User ID is required');
        }
        // Check for existing item
        $checkSql = 'SELECT * FROM cart_items WHERE user_id = ? AND note_id = ?';
        $checkStmt = mysqli_prepare($this->conn, $checkSql);
        mysqli_stmt_bind_param($checkStmt, 'ii', $data['user_id'], $data['note_id']);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);
        $existing = mysqli_fetch_assoc($checkResult);
        mysqli_stmt_close($checkStmt);
        
        if ($existing) {
            // Already in cart, return existing item
            return $existing;
        }
        
        $sql = 'INSERT INTO cart_items (user_id, note_id) VALUES (?, ?)';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ii', $data['user_id'], $data['note_id']);
        mysqli_stmt_execute($stmt);
        $insertId = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);
        
        return $this->getItemById($insertId);
    }

    public function getItems($user_id)
    {
        if (empty($user_id)) {
            throw new Exception('User ID is required');
        }
        
        $sql = 'SELECT ci.*, n.title, n.price, n.description FROM cart_items ci JOIN notes n ON ci.note_id = n.id WHERE ci.user_id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $items = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $items[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $items;
    }

    public function updateItem($item_id)
    {
        // No quantity to update, just return the item
        return $this->getItemById($item_id);
    }

    public function getItemById($item_id)
    {
        $sql = 'SELECT * FROM cart_items WHERE id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $item_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$row) {
            throw new Exception('Database error: ' . mysqli_error($this->conn));
        }
        return $row;
    }
    
    public function deleteItem($item_id)
    {
        $sql = 'DELETE FROM cart_items WHERE id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $item_id);
        mysqli_stmt_execute($stmt);
        $affectedRows = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        
        if ($affectedRows === -1) {
            throw new Exception('Database error: ' . mysqli_error($this->conn));
        }
        return $affectedRows > 0;
    }
    
    public function clear($user_id)
    {
        if (empty($user_id)) {
            throw new Exception('User ID is required');
        }
        
        $sql = 'DELETE FROM cart_items WHERE user_id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $affectedRows = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        
        if ($affectedRows === -1) {
            throw new Exception('Database error: ' . mysqli_error($this->conn));
        }
        return $affectedRows;
    }
}
