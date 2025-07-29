<?php
class Note
{
    private $conn;
    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getAll($filters = [], $pagination = [], $sort = [])
    {
        $where = [];
        $params = [];
        $paramTypes = [];
        $idx = 1;
        if (!empty($filters['subject'])) {
            $where[] = 'subject = $' . $idx;
            $params[] = $filters['subject'];
            $idx++;
        }
        if (!empty($filters['search'])) {
            $where[] = '(title ILIKE $' . $idx . ' OR description ILIKE $' . $idx . ')';
            $params[] = '%' . $filters['search'] . '%';
            $idx++;
        }
        if (!empty($filters['min_price'])) {
            $where[] = 'price >= $' . $idx;
            $params[] = $filters['min_price'];
            $idx++;
        }
        if (!empty($filters['max_price'])) {
            $where[] = 'price <= $' . $idx;
            $params[] = $filters['max_price'];
            $idx++;
        }
        $where[] = 'status = \'active\'';
        $where[] = 'is_active = TRUE';
        $sql = 'SELECT * FROM notes';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $order = 'created_at DESC';
        if (!empty($sort['by']) && in_array($sort['by'], ['price', 'created_at'])) {
            $order = $sort['by'] . ' ' . (strtoupper($sort['order'] ?? 'DESC'));
        }
        $sql .= ' ORDER BY ' . $order;
        $limit = (int)($pagination['limit'] ?? 12);
        $offset = (int)($pagination['offset'] ?? 0);
        $sql .= ' LIMIT $' . $idx . ' OFFSET $' . ($idx + 1);
        $params[] = $limit;
        $params[] = $offset;
        $result = pg_query_params($this->conn, $sql, $params);
        $notes = [];
        while ($row = pg_fetch_assoc($result)) {
            $notes[] = $row;
        }
        return $notes;
    }

    public function getById($id)
    {
        $result = pg_query_params($this->conn, 'SELECT * FROM notes WHERE id = $1 AND is_active = TRUE', [$id]);
        return pg_fetch_assoc($result);
    }

    public function getSubjects()
    {
        $result = pg_query($this->conn, "SELECT subject, COUNT(*) as count FROM notes WHERE status = 'active' AND is_active = TRUE GROUP BY subject ORDER BY count DESC");
        $subjects = [];
        while ($row = pg_fetch_assoc($result)) {
            $subjects[] = $row;
        }
        return $subjects;
    }

    // Admin methods
    public function create($data)
    {
        $fields = ['title','description','subject','price','tags','features','topics','status','preview_image','sample_pages'];
        $cols = [];
        $placeholders = [];
        $params = [];
        $idx = 1;
        foreach ($fields as $f) {
            if (isset($data[$f])) {
                $cols[] = $f;
                $placeholders[] = '$' . $idx;
                $params[] = is_array($data[$f]) ? json_encode($data[$f]) : $data[$f];
                $idx++;
            }
        }
        $sql = 'INSERT INTO notes (' . implode(',', $cols) . ') VALUES (' . implode(',', $placeholders) . ') RETURNING *';
        $result = pg_query_params($this->conn, $sql, $params);
        return pg_fetch_assoc($result);
    }

    public function update($id, $data)
    {
        $fields = ['title','description','subject','price','tags','features','topics','status','preview_image','sample_pages'];
        $set = [];
        $params = [];
        $idx = 1;
        foreach ($fields as $f) {
            if (isset($data[$f])) {
                $set[] = "$f = $" . $idx;
                $params[] = is_array($data[$f]) ? json_encode($data[$f]) : $data[$f];
                $idx++;
            }
        }
        if (!$set) return false;
        $params[] = $id;
        $sql = 'UPDATE notes SET ' . implode(',', $set) . ' WHERE id = $' . $idx . ' RETURNING *';
        $result = pg_query_params($this->conn, $sql, $params);
        return pg_fetch_assoc($result);
    }

    public function delete($id)
    {
        $result = pg_query_params($this->conn, 'DELETE FROM notes WHERE id = $1 RETURNING id', [$id]);
        return pg_fetch_assoc($result);
    }

    public function updateStatus($id, $status)
    {
        $result = pg_query_params($this->conn, 'UPDATE notes SET status = $1 WHERE id = $2 RETURNING id, title, status', [$status, $id]);
        return pg_fetch_assoc($result);
    }

    public function count($filters = [])
    {
        $where = [];
        $params = [];
        if (!empty($filters['subject'])) {
            $where[] = 'subject = :subject';
            $params[':subject'] = $filters['subject'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(title ILIKE :search OR description ILIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['min_price'])) {
            $where[] = 'price >= :min_price';
            $params[':min_price'] = $filters['min_price'];
        }
        if (!empty($filters['max_price'])) {
            $where[] = 'price <= :max_price';
            $params[':max_price'] = $filters['max_price'];
        }
        $where[] = 'status = \'active\'';
        $sql = 'SELECT COUNT(*) FROM notes';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $stmt = pg_query_params($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }
}
