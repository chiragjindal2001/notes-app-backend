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
        $sql = 'SELECT o.status, n.title, n.file_url, n.preview_image FROM orders o JOIN order_items oi ON o.id = oi.order_id JOIN notes n ON oi.note_id = n.id WHERE o.order_id = $1 AND n.id = $2';
        $result = pg_query_params($this->conn, $sql, [$order_id, $note_id]);
        $row = pg_fetch_assoc($result);
        return $row;
    }

    public function userHasPurchasedNote($user_id, $note_id)
    {
        $sql = 'SELECT o.status, n.title, n.file_url, n.preview_image FROM orders o JOIN order_items oi ON o.order_id = oi.order_id JOIN notes n ON oi.note_id = n.id WHERE o.user_id = $1 AND n.id = $2 AND o.status = $3 LIMIT 1';
        $result = pg_query_params($this->conn, $sql, [$user_id, $note_id, 'paid']);
        $row = pg_fetch_assoc($result);
        return $row;
    }
}
