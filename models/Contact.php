<?php
class Contact
{
    private $conn;
    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function create($data)
    {
        $sql = 'INSERT INTO contacts (name, email, message, created_at, is_read) VALUES ($1, $2, $3, NOW(), false) RETURNING *';
        $params = [
            $data['name'],
            $data['email'],
            $data['message']
        ];
        $result = pg_query_params($this->conn, $sql, $params);
        return pg_fetch_assoc($result);
    }

    public function list($only_unread = false)
    {
        $sql = 'SELECT * FROM contacts';
        if ($only_unread) {
            $sql .= ' WHERE is_read = false';
        }
        $sql .= ' ORDER BY created_at DESC';
        $stmt = pg_query($sql);
        return $stmt->fetchAll();
    }

    public function markAsRead($id)
    {
        $stmt = pg_query_params('UPDATE contacts SET is_read = true WHERE id = :id RETURNING *');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }
}
