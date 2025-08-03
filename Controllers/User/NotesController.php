<?php
class NotesController
{
    public static function getNotes()
    {
        try{
            $config = require dirname(__DIR__, 2) . '/config/config.development.php';
            require_once dirname(__DIR__, 2) . '/src/Db.php';
            require_once dirname(__DIR__, 2) . '/models/Note.php';
            $pdo = Db::getConnection($config);
            $noteModel = new Note($pdo);

            // Filters, pagination, sorting
            $filters = [
                'subject' => $_GET['subject'] ?? null,
                'search' => $_GET['search'] ?? null,
                'min_price' => isset($_GET['min_price']) ? (float)$_GET['min_price'] : null,
                'max_price' => isset($_GET['max_price']) ? (float)$_GET['max_price'] : null,
            ];
            $pagination = [
                'limit' => isset($_GET['limit']) ? (int)$_GET['limit'] : 12,
                'offset' => (isset($_GET['page']) ? ((int)$_GET['page'] - 1) : 0) * (isset($_GET['limit']) ? (int)$_GET['limit'] : 12),
            ];
            
            // Handle sort parameter - support both 'sort' and 'sort_by'/'sort_order'
            $sort = [];
            if (isset($_GET['sort'])) {
                // Handle single sort parameter like 'popular', 'newest', 'price_asc', etc.
                switch ($_GET['sort']) {
                    case 'popular':
                        $sort = ['by' => 'downloads', 'order' => 'DESC'];
                        break;
                    case 'newest':
                        $sort = ['by' => 'created_at', 'order' => 'DESC'];
                        break;
                    case 'price_asc':
                        $sort = ['by' => 'price', 'order' => 'ASC'];
                        break;
                    case 'price_desc':
                        $sort = ['by' => 'price', 'order' => 'DESC'];
                        break;
                    default:
                        $sort = ['by' => 'created_at', 'order' => 'DESC'];
                }
            } else {
                // Handle separate sort_by and sort_order parameters
                $sort = [
                    'by' => $_GET['sort_by'] ?? null,
                    'order' => $_GET['sort_order'] ?? null,
                ];
            }
            
            $rawNotes = $noteModel->getAll($filters, $pagination, $sort);
            
            // Process notes to add proper image URLs
            $notes = [];
            foreach ($rawNotes as $note) {
                $notes[] = [
                    'id' => (int)$note['id'],
                    'title' => $note['title'],
                    'description' => $note['description'],
                    'subject' => $note['subject'],
                    'price' => (float)$note['price'],
                    'tags' => json_decode($note['tags'] ?? '[]', true) ?: [],
                    'features' => json_decode($note['features'] ?? '[]', true) ?: [],
                    'topics' => json_decode($note['topics'] ?? '[]', true) ?: [],
                    'status' => $note['status'],
                    'downloads' => '250+',
                    'rating' => 5.0,
                    'preview_image' => $note['preview_image'] ? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost:8080') . $note['preview_image']) : null,
                    'file_url' => $note['file_url'],
                    'created_at' => $note['created_at'],
                    'is_active' => (bool)$note['is_active']
                ];
            }

            // For pagination
            $count = count($notes); // For now, just count returned; for real total, add a count method to model
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = $pagination['limit'];

            $response = [
                'success' => true,
                'message' => 'Notes fetched successfully',
                'data' => $notes,
                'pagination' => [
                    'total' => $count, // For true total, add count method to model
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($count / $limit)
                ]
            ];
            header('Content-Type: application/json');
            echo json_encode($response);
        }catch(Exception | Error $e){
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage(), 'trace' => "<pre>" . $e->getTraceAsString() . "</pre>"]);
        }
    }

    public static function getNoteById($id)
    {
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        require_once dirname(__DIR__, 2) . '/models/Note.php';
        $pdo = Db::getConnection($config);
        $noteModel = new Note($pdo);
        $note = $noteModel->getById($id);
        if (!$note) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Note not found']);
            return;
        }
        
        // Set rating and downloads for individual note view
        $note['rating'] = 5.0;
        $note['downloads'] = '250+';
        
        // Fetch author info, reviews, etc. as before if needed
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
        $pdo = Db::getConnection($config);
        $noteModel = new Note($pdo);
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
                'note_count' => isset($row['note_count']) ? (int)$row['note_count'] : 0,
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
        
        // Get all paid orders for this user
        $orderSql = 'SELECT o.order_id as order_id, oi.note_id FROM orders o JOIN order_items oi ON o.order_id = oi.order_id WHERE o.user_id = ? AND o.status = ?';
        $orderStmt = mysqli_prepare($conn, $orderSql);
        $status = 'paid';
        mysqli_stmt_bind_param($orderStmt, 'is', $user_id, $status);
        mysqli_stmt_execute($orderStmt);
        $orderResult = mysqli_stmt_get_result($orderStmt);
        $rows = [];
        while ($row = mysqli_fetch_assoc($orderResult)) {
            $rows[] = $row;
        }
        $note_ids = array_column($rows, 'note_id');
        if (empty($note_ids)) {
            echo json_encode(['success' => true, 'data' => []]);
            return;
        }
        
        // Fetch note details
        $placeholders = str_repeat('?,', count($note_ids) - 1) . '?';
        $noteSql = 'SELECT id, title, subject, price, preview_image FROM notes WHERE id IN (' . $placeholders . ') AND is_active = TRUE';
        $noteStmt = mysqli_prepare($conn, $noteSql);
        
        // Create array of references for bind_param
        $types = str_repeat('i', count($note_ids));
        $params = array();
        $params[] = $types;
        for($i = 0; $i < count($note_ids); $i++) {
            $params[] = &$note_ids[$i];
        }
        call_user_func_array('mysqli_stmt_bind_param', array_merge(array($noteStmt), $params));
        
        mysqli_stmt_execute($noteStmt);
        $noteResult = mysqli_stmt_get_result($noteStmt);
        $notes = [];
        while ($note = mysqli_fetch_assoc($noteResult)) {
            $notes[] = $note;
        }
        
        // Build download URLs and set rating/downloads (placeholder, you may want to generate secure links)
        $base_url = $config['base_url'] ?? 'https://sienna-cod-887616.hostingersite.com/';
        foreach ($notes as &$note) {
            $note['download_url'] = $base_url . "/api/downloads/" . $note['id'];
            $note['rating'] = 5.0;
            $note['downloads'] = '250+';
        }
        echo json_encode(['success' => true, 'data' => $notes]);
    }
}
