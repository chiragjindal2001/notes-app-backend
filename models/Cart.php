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
        $user_id = $data['user_id'];
        $note_id = $data['note_id'];
        mysqli_stmt_bind_param($checkStmt, 'ii', $user_id, $note_id);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);
        $existing = mysqli_fetch_assoc($checkResult);
        if ($existing) {
            // Already in cart, return existing item
            return $existing;
        }
        $sql = 'INSERT INTO cart_items (user_id, note_id) VALUES (?, ?)';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ii', $user_id, $note_id);
        mysqli_stmt_execute($stmt);
        
        // Get the inserted ID
        $insertedId = mysqli_insert_id($this->conn);
        
        // Fetch the inserted record
        $selectStmt = mysqli_prepare($this->conn, 'SELECT * FROM cart_items WHERE id = ?');
        mysqli_stmt_bind_param($selectStmt, 'i', $insertedId);
        mysqli_stmt_execute($selectStmt);
        $result = mysqli_stmt_get_result($selectStmt);
        return mysqli_fetch_assoc($result);
    }

    public function getItems($user_id)
    {
        if (empty($user_id)) {
            throw new Exception('User ID is required');
        }
        
        $sql = 'SELECT ci.*, n.title, n.price, n.preview_image FROM cart_items ci JOIN notes n ON ci.note_id = n.id WHERE ci.user_id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $items = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $items[] = $row;
        }
        return $items;
    }

    public function updateItem($item_id)
    {
        // No quantity to update, just return the item
        $sql = 'SELECT * FROM cart_items WHERE id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $item_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        return mysqli_fetch_assoc($result);
    }

    public function getItemById($item_id)
    {
        $sql = 'SELECT * FROM cart_items WHERE id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $item_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if (!$result) {
            throw new Exception('Database error: ' . mysqli_error($this->conn));
        }
        return mysqli_fetch_assoc($result);
    }
    
    public function deleteItem($item_id)
    {
        $sql = 'DELETE FROM cart_items WHERE id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $item_id);
        mysqli_stmt_execute($stmt);
        if (!$stmt) {
            throw new Exception('Database error: ' . mysqli_error($this->conn));
        }
        return mysqli_stmt_affected_rows($stmt) > 0;
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
        if (!$stmt) {
            throw new Exception('Database error: ' . mysqli_error($this->conn));
        }
        return mysqli_stmt_affected_rows($stmt);
    }
    
    /**
     * Migrate cart items from session to user ID
     * 
     * @param string $session_id The session ID to migrate from
     * @param int $user_id The user ID to migrate to
     * @return bool True if migration was successful
     */
    private function migrateSessionToUser($session_id, $user_id)
    {
        // First, check if there are any items for this session
        $checkSql = 'SELECT COUNT(*) as count FROM cart_items WHERE session_id = ?';
        $checkStmt = mysqli_prepare($this->conn, $checkSql);
        mysqli_stmt_bind_param($checkStmt, 's', $session_id);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);
        $count = mysqli_fetch_assoc($checkResult)['count'];
        
        if ($count == 0) {
            return false; // No items to migrate
        }
        
        // Update all items with this session_id to have the user_id
        $updateSql = 'UPDATE cart_items SET user_id = ?, session_id = NULL WHERE session_id = ?';
        $updateStmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($updateStmt, 'is', $user_id, $session_id);
        $result = mysqli_stmt_execute($updateStmt);
        
        return $result !== false;
    }
}
