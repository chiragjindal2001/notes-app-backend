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
        $sql = 'INSERT INTO contacts (name, email, subject, message, status, created_at) VALUES ($1, $2, $3, $4, $5, NOW()) RETURNING *';
        $params = [
            $data['name'],
            $data['email'],
            $data['subject'],
            $data['message'],
            $data['status'] ?? 'new'
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
        $result = pg_query($this->conn, $sql);
        $contacts = [];
        while ($row = pg_fetch_assoc($result)) {
            $contacts[] = $row;
        }
        return $contacts;
    }

    public function markAsRead($id)
    {
        $sql = 'UPDATE contacts SET is_read = true WHERE id = $1 RETURNING *';
        $result = pg_query_params($this->conn, $sql, [$id]);
        return pg_fetch_assoc($result);
    }

    public function updateStatus($id, $status)
    {
        $sql = 'UPDATE contacts SET status = $1, updated_at = NOW() WHERE id = $2 RETURNING *';
        $result = pg_query_params($this->conn, $sql, [$status, $id]);
        return pg_fetch_assoc($result);
    }

    public function getById($id)
    {
        $sql = 'SELECT * FROM contacts WHERE id = $1';
        $result = pg_query_params($this->conn, $sql, [$id]);
        return pg_fetch_assoc($result);
    }
}
