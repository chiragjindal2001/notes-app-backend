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
        if (!empty($sort['by']) && in_array($sort['by'], ['price', 'created_at', 'downloads'])) {
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
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $paramTypes, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $notes = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $notes[] = $row;
        }
        return $notes;
    }

    public function getById($id)
    {
        $stmt = mysqli_prepare($this->conn, 'SELECT * FROM notes WHERE id = ? AND is_active = TRUE');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        return mysqli_fetch_assoc($result);
    }

    public function getSubjects()
    {
        $result = mysqli_query($this->conn, "SELECT subject, COUNT(*) as note_count FROM notes WHERE status = 'active' AND is_active = TRUE GROUP BY subject ORDER BY note_count DESC");
        $subjects = [];
        while ($row = mysqli_fetch_assoc($result)) {
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
        $paramTypes = '';
        $idx = 0;
        
        foreach ($fields as $f) {
            if (isset($data[$f])) {
                $cols[] = $f;
                $placeholders[] = '?';
                $params[] = is_array($data[$f]) ? json_encode($data[$f]) : $data[$f];
                $paramTypes .= 's';
                $idx++;
            }
        }
        $sql = 'INSERT INTO notes (' . implode(',', $cols) . ') VALUES (' . implode(',', $placeholders) . ')';
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $paramTypes, ...$params);
        }
        mysqli_stmt_execute($stmt);
        
        // Get the inserted ID
        $insertedId = mysqli_insert_id($this->conn);
        
        // Fetch the inserted record
        $selectStmt = mysqli_prepare($this->conn, 'SELECT * FROM notes WHERE id = ?');
        mysqli_stmt_bind_param($selectStmt, 'i', $insertedId);
        mysqli_stmt_execute($selectStmt);
        $result = mysqli_stmt_get_result($selectStmt);
        return mysqli_fetch_assoc($result);
    }

    public function update($id, $data)
    {
        $fields = ['title','description','subject','price','tags','features','topics','status','preview_image','sample_pages'];
        $set = [];
        $params = [];
        $paramTypes = '';
        $idx = 0;
        
        foreach ($fields as $f) {
            if (isset($data[$f])) {
                $set[] = "$f = ?";
                $params[] = is_array($data[$f]) ? json_encode($data[$f]) : $data[$f];
                $paramTypes .= 's';
                $idx++;
            }
        }
        if (!$set) return false;
        
        $params[] = $id;
        $paramTypes .= 'i';
        $sql = 'UPDATE notes SET ' . implode(',', $set) . ' WHERE id = ?';
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $paramTypes, ...$params);
        }
        mysqli_stmt_execute($stmt);
        
        // Fetch the updated record
        $selectStmt = mysqli_prepare($this->conn, 'SELECT * FROM notes WHERE id = ?');
        mysqli_stmt_bind_param($selectStmt, 'i', $id);
        mysqli_stmt_execute($selectStmt);
        $result = mysqli_stmt_get_result($selectStmt);
        return mysqli_fetch_assoc($result);
    }

    public function delete($id)
    {
        $stmt = mysqli_prepare($this->conn, 'DELETE FROM notes WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        
        // Return the deleted ID
        return ['id' => $id];
    }

    public function updateStatus($id, $status)
    {
        $stmt = mysqli_prepare($this->conn, 'UPDATE notes SET status = ? WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'si', $status, $id);
        mysqli_stmt_execute($stmt);
        
        // Fetch the updated record
        $selectStmt = mysqli_prepare($this->conn, 'SELECT id, title, status FROM notes WHERE id = ?');
        mysqli_stmt_bind_param($selectStmt, 'i', $id);
        mysqli_stmt_execute($selectStmt);
        $result = mysqli_stmt_get_result($selectStmt);
        return mysqli_fetch_assoc($result);
    }

    public function count($filters = [])
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
        $sql = 'SELECT COUNT(*) as count FROM notes';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $paramTypes, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        return (int)$row['count'];
    }
}
