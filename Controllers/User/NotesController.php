<?php
class NotesController
{
    public static function getNotes()
    {
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        require_once dirname(__DIR__, 2) . '/models/Note.php';
        
        $conn = Db::getConnection($config);
        $noteModel = new Note($conn);
        
        // Get query parameters
        $filters = [];
        if (isset($_GET['subject'])) $filters['subject'] = $_GET['subject'];
        if (isset($_GET['search'])) $filters['search'] = $_GET['search'];
        if (isset($_GET['min_price'])) $filters['min_price'] = (float)$_GET['min_price'];
        if (isset($_GET['max_price'])) $filters['max_price'] = (float)$_GET['max_price'];
        
        $pagination = [];
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
        $pagination['limit'] = $limit;
        $pagination['offset'] = ($page - 1) * $limit;
        
        $sort = [];
        if (isset($_GET['sort_by'])) $sort['by'] = $_GET['sort_by'];
        if (isset($_GET['sort_order'])) $sort['order'] = $_GET['sort_order'];
        
        $notes = $noteModel->getAll($filters, $pagination, $sort);
        $total = $noteModel->count($filters);
        
        $response = [
            'success' => true,
            'message' => 'Notes fetched successfully',
            'data' => [
                'notes' => $notes,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]
        ];
        
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function getNoteById($id)
    {
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        require_once dirname(__DIR__, 2) . '/models/Note.php';
        
        $conn = Db::getConnection($config);
        $noteModel = new Note($conn);
        
        $note = $noteModel->getById($id);
        
        if (!$note) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Note not found']);
            return;
        }
        
        $response = [
            'success' => true,
            'message' => 'Note fetched successfully',
            'data' => $note
        ];
        
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function getSubjects()
    {
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        require_once dirname(__DIR__, 2) . '/models/Note.php';
        $conn = Db::getConnection($config);
        $noteModel = new Note($conn);
        $subjects_raw = $noteModel->getSubjects();
        $icons = [
            // Example icon mapping
            'math' => 'math.png',
            'science' => 'science.png',
            'default' => 'default.png'
        ];
        $subjects = [];
        foreach ($subjects_raw as $row) {
            $name = $row['subject'];
            $slug = strtolower(preg_replace('/\s+/', '-', $name));
            $icon = $icons[$slug] ?? $icons[strtolower($name)] ?? $icons['default'];
            $subjects[] = [
                'name' => $name,
                'slug' => $slug,
                'note_count' => isset($row['count']) ? (int)$row['count'] : 0,
                'icon' => $icon
            ];
        }
        $response = [
            'success' => true,
            'message' => 'Subjects fetched successfully',
            'data' => [
                'subjects' => $subjects
            ]
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function getMyNotes()
    {
        // Authenticate user via JWT
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            return;
        }
        $token = $matches[1];
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        $jwt_secret = $config['jwt_secret'] ?? 'changeme';
        $payload = null;
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) throw new \Exception('Invalid token');
            $payload = json_decode(base64_decode($parts[1]), true);
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid token']);
            return;
        }
        $user_id = $payload['user_id'] ?? $payload['sub'] ?? null;
        if (!$user_id) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            return;
        }
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        require_once dirname(__DIR__, 2) . '/models/Note.php';
        $conn = Db::getConnection($config);
        
        // Get all completed orders for this user
        $orderSql = 'SELECT o.id as order_id, oi.note_id FROM orders o JOIN order_items oi ON o.id = oi.order_id WHERE o.user_id = ? AND o.status = ?';
        $orderStmt = mysqli_prepare($conn, $orderSql);
        mysqli_stmt_bind_param($orderStmt, 'is', $user_id, 'completed');
        mysqli_stmt_execute($orderStmt);
        $orderResult = mysqli_stmt_get_result($orderStmt);
        $rows = [];
        while ($row = mysqli_fetch_assoc($orderResult)) {
            $rows[] = $row;
        }
        mysqli_stmt_close($orderStmt);
        
        $note_ids = array_column($rows, 'note_id');
        if (empty($note_ids)) {
            echo json_encode(['success' => true, 'data' => []]);
            return;
        }
        
        // Fetch note details
        $placeholders = str_repeat('?,', count($note_ids) - 1) . '?';
        $noteSql = 'SELECT id, title, subject, price, description FROM notes WHERE id IN (' . $placeholders . ') AND is_active = TRUE';
        $noteStmt = mysqli_prepare($conn, $noteSql);
        
        // Create parameter types string
        $types = str_repeat('i', count($note_ids));
        mysqli_stmt_bind_param($noteStmt, $types, ...$note_ids);
        mysqli_stmt_execute($noteStmt);
        $noteResult = mysqli_stmt_get_result($noteStmt);
        
        $notes = [];
        while ($note = mysqli_fetch_assoc($noteResult)) {
            $notes[] = $note;
        }
        mysqli_stmt_close($noteStmt);
        
        // Build download URLs (placeholder, you may want to generate secure links)
        $base_url = $config['base_url'] ?? 'http://localhost:8080';
        foreach ($notes as &$note) {
            $note['download_url'] = $base_url . "/api/downloads/" . $note['id'];
        }
        echo json_encode(['success' => true, 'data' => $notes]);
    }
}
