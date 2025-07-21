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
        // Requires: user_id, note_id, quantity
        if (empty($data['user_id'])) {
            throw new Exception('User ID is required');
        }
        
        $sql = 'INSERT INTO cart_items (user_id, note_id, quantity) VALUES ($1, $2, $3) RETURNING *';
        $params = [
            $data['user_id'],
            $data['note_id'],
            $data['quantity']
        ];
        $result = pg_query_params($this->conn, $sql, $params);
        return pg_fetch_assoc($result);
    }

    public function getItems($user_id)
    {
        if (empty($user_id)) {
            throw new Exception('User ID is required');
        }
        
        $sql = 'SELECT * FROM cart_items WHERE user_id = $1';
        $result = pg_query_params($this->conn, $sql, [$user_id]);
        $items = [];
        while ($row = pg_fetch_assoc($result)) {
            $items[] = $row;
        }
        return $items;
    }

    public function updateItem($item_id, $quantity)
    {
        $sql = 'UPDATE cart_items SET quantity = $1 WHERE id = $2 RETURNING *';
        $result = pg_query_params($this->conn, $sql, [$quantity, $item_id]);
        return pg_fetch_assoc($result);
    }

    public function getItemById($item_id)
    {
        $sql = 'SELECT * FROM cart_items WHERE id = $1';
        $result = pg_query_params($this->conn, $sql, [$item_id]);
        if (!$result) {
            throw new Exception('Database error: ' . pg_last_error($this->conn));
        }
        return pg_fetch_assoc($result);
    }
    
    public function deleteItem($item_id)
    {
        $sql = 'DELETE FROM cart_items WHERE id = $1';
        $result = pg_query_params($this->conn, $sql, [$item_id]);
        if (!$result) {
            throw new Exception('Database error: ' . pg_last_error($this->conn));
        }
        return pg_affected_rows($result) > 0;
    }
    
    public function clear($user_id)
    {
        if (empty($user_id)) {
            throw new Exception('User ID is required');
        }
        
        $sql = 'DELETE FROM cart_items WHERE user_id = $1';
        $result = pg_query_params($this->conn, $sql, [$user_id]);
        if (!$result) {
            throw new Exception('Database error: ' . pg_last_error($this->conn));
        }
        return pg_affected_rows($result);
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
        $checkSql = 'SELECT COUNT(*) as count FROM cart_items WHERE session_id = $1';
        $checkResult = pg_query_params($this->conn, $checkSql, [$session_id]);
        $count = pg_fetch_assoc($checkResult)['count'];
        
        if ($count == 0) {
            return false; // No items to migrate
        }
        
        // Update all items with this session_id to have the user_id
        $updateSql = 'UPDATE cart_items SET user_id = $1, session_id = NULL WHERE session_id = $2';
        $result = pg_query_params($this->conn, $updateSql, [$user_id, $session_id]);
        
        return $result !== false;
    }
}
