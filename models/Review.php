<?php
class Review
{
    private $conn;
    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function add($data)
    {
        $sql = 'INSERT INTO reviews (note_id, user_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iiis', 
            $data['note_id'],
            $data['user_id'],
            $data['rating'],
            $data['comment']
        );
        mysqli_stmt_execute($stmt);
        $insertId = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);
        
        return $this->getById($insertId);
    }

    public function getById($id)
    {
        $sql = 'SELECT * FROM reviews WHERE id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $row;
    }

    public function listForNote($note_id)
    {
        $sql = 'SELECT * FROM reviews WHERE note_id = ? ORDER BY created_at DESC';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $note_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $reviews = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $reviews[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $reviews;
    }

    public function listAll()
    {
        $sql = 'SELECT * FROM reviews ORDER BY created_at DESC';
        $result = mysqli_query($this->conn, $sql);
        $reviews = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $reviews[] = $row;
        }
        return $reviews;
    }

    public function delete($id)
    {
        $sql = 'DELETE FROM reviews WHERE id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $affectedRows = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        
        return $affectedRows > 0 ? ['id' => $id] : false;
    }
}
