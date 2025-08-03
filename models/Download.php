<?php
class Download
{
    private $conn;
    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getDownloadData($order_id, $note_id)
    {
        $sql = 'SELECT o.status, n.title, n.file_url, n.preview_image FROM orders o JOIN order_items oi ON o.id = oi.order_id JOIN notes n ON oi.note_id = n.id WHERE o.order_id = ? AND n.id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'si', $order_id, $note_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        return $row;
    }

    public function userHasPurchasedNote($user_id, $note_id)
    {
        $sql = 'SELECT o.status, n.title, n.file_url, n.preview_image FROM orders o JOIN order_items oi ON o.order_id = oi.order_id JOIN notes n ON oi.note_id = n.id WHERE o.user_id = ? AND n.id = ? AND o.status = ? LIMIT 1';
        $stmt = mysqli_prepare($this->conn, $sql);
        $status = 'paid';
        mysqli_stmt_bind_param($stmt, 'iis', $user_id, $note_id, $status);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        return $row;
    }
}
