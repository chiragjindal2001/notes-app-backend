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
        $paramTypes = '';
        $idx = 0;
        
        if (!empty($filters['subject'])) {
            $where[] = 'subject = ?';
            $params[] = $filters['subject'];
            $paramTypes .= 's';
            $idx++;
        }
        if (!empty($filters['search'])) {
            $where[] = '(title LIKE ? OR description LIKE ?)';
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
            $paramTypes .= 'ss';
            $idx += 2;
        }
        if (!empty($filters['min_price'])) {
            $where[] = 'price >= ?';
            $params[] = $filters['min_price'];
            $paramTypes .= 'd';
            $idx++;
        }
        if (!empty($filters['max_price'])) {
            $where[] = 'price <= ?';
            $params[] = $filters['max_price'];
            $paramTypes .= 'd';
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
        $sql .= ' LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;
        $paramTypes .= 'ii';
        
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!empty($paramTypes)) {
            mysqli_stmt_bind_param($stmt, $paramTypes, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $notes = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $notes[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $notes;
    }

    public function getById($id)
    {
        $sql = 'SELECT * FROM notes WHERE id = ? AND is_active = TRUE';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $row;
    }

    public function getSubjects()
    {
        $sql = "SELECT subject, COUNT(*) as count FROM notes WHERE status = 'active' AND is_active = TRUE GROUP BY subject ORDER BY count DESC";
        $result = mysqli_query($this->conn, $sql);
        $subjects = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $subjects[] = $row;
        }
        return $subjects;
    }

    // Admin methods
    public function create($data)
    {
        $fields = ['title','description','subject','price','file_path','file_size','user_id'];
        $cols = [];
        $placeholders = [];
        $params = [];
        $paramTypes = '';
        
        foreach ($fields as $f) {
            if (isset($data[$f])) {
                $cols[] = $f;
                $placeholders[] = '?';
                $params[] = is_array($data[$f]) ? json_encode($data[$f]) : $data[$f];
                $paramTypes .= is_numeric($data[$f]) ? 'i' : 's';
            }
        }
        
        $sql = 'INSERT INTO notes (' . implode(',', $cols) . ') VALUES (' . implode(',', $placeholders) . ')';
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!empty($paramTypes)) {
            mysqli_stmt_bind_param($stmt, $paramTypes, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $insertId = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);
        
        return $this->getById($insertId);
    }

    public function update($id, $data)
    {
        $fields = ['title','description','subject','price','file_path','file_size'];
        $set = [];
        $params = [];
        $paramTypes = '';
        
        foreach ($fields as $f) {
            if (isset($data[$f])) {
                $set[] = "$f = ?";
                $params[] = is_array($data[$f]) ? json_encode($data[$f]) : $data[$f];
                $paramTypes .= is_numeric($data[$f]) ? 'i' : 's';
            }
        }
        
        if (!$set) return false;
        
        $params[] = $id;
        $paramTypes .= 'i';
        
        $sql = 'UPDATE notes SET ' . implode(',', $set) . ' WHERE id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, $paramTypes, ...$params);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        return $this->getById($id);
    }

    public function delete($id)
    {
        $sql = 'DELETE FROM notes WHERE id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $affectedRows = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        
        return $affectedRows > 0 ? ['id' => $id] : false;
    }

    public function updateStatus($id, $status)
    {
        $sql = 'UPDATE notes SET status = ? WHERE id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'si', $status, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        return $this->getById($id);
    }

    public function count($filters = [])
    {
        $where = [];
        $params = [];
        $paramTypes = '';
        
        if (!empty($filters['subject'])) {
            $where[] = 'subject = ?';
            $params[] = $filters['subject'];
            $paramTypes .= 's';
        }
        if (!empty($filters['search'])) {
            $where[] = '(title LIKE ? OR description LIKE ?)';
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
            $paramTypes .= 'ss';
        }
        if (!empty($filters['min_price'])) {
            $where[] = 'price >= ?';
            $params[] = $filters['min_price'];
            $paramTypes .= 'd';
        }
        if (!empty($filters['max_price'])) {
            $where[] = 'price <= ?';
            $params[] = $filters['max_price'];
            $paramTypes .= 'd';
        }
        $where[] = 'status = \'active\'';
        
        $sql = 'SELECT COUNT(*) as count FROM notes';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!empty($paramTypes)) {
            mysqli_stmt_bind_param($stmt, $paramTypes, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        return (int)$row['count'];
    }
}
