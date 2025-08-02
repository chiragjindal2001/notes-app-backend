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
        $sql = 'INSERT INTO contacts (name, email, subject, message, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'sssss', 
            $data['name'],
            $data['email'],
            $data['subject'],
            $data['message'],
            $data['status'] ?? 'pending'
        );
        mysqli_stmt_execute($stmt);
        $insertId = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);
        
        return $this->getById($insertId);
    }

    public function list($only_unread = false)
    {
        $sql = 'SELECT * FROM contacts';
        if ($only_unread) {
            $sql .= ' WHERE status = "pending"';
        }
        $sql .= ' ORDER BY created_at DESC';
        $result = mysqli_query($this->conn, $sql);
        $contacts = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $contacts[] = $row;
        }
        return $contacts;
    }

    public function markAsRead($id)
    {
        $sql = 'UPDATE contacts SET status = "read" WHERE id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        return $this->getById($id);
    }

    public function updateStatus($id, $status)
    {
        $sql = 'UPDATE contacts SET status = ?, updated_at = NOW() WHERE id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'si', $status, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        return $this->getById($id);
    }

    public function getById($id)
    {
        $sql = 'SELECT * FROM contacts WHERE id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $row;
    }
}
