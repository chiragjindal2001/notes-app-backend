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
        $sql = 'INSERT INTO reviews (note_id, user_name, rating, comment, created_at) VALUES ($1, $2, $3, $4, NOW()) RETURNING *';
        $params = [
            $data['note_id'],
            $data['user_name'],
            $data['rating'],
            $data['comment']
        ];
        $result = pg_query_params($this->conn, $sql, $params);
        return pg_fetch_assoc($result);
    }

    public function listForNote($note_id)
    {
        $stmt = pg_query_params('SELECT * FROM reviews WHERE note_id = :note_id ORDER BY created_at DESC');
        $stmt->execute([':note_id' => $note_id]);
        return $stmt->fetchAll();
    }

    public function listAll()
    {
        $stmt = pg_query('SELECT * FROM reviews ORDER BY created_at DESC');
        return $stmt->fetchAll();
    }

    public function delete($id)
    {
        $stmt = pg_query_params('DELETE FROM reviews WHERE id = :id RETURNING id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }
}
