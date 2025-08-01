<?php
require_once dirname(__DIR__, 2) . '/src/Helpers/Config.php';

class NotesController
{
    public static function getNotes()
    {
        try{
            $config = [
                'db' => \Helpers\Config::database(),
            ];
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
            $sort = [
                'by' => $_GET['sort_by'] ?? null,
                'order' => $_GET['sort_order'] ?? null,
            ];
            $notes = $noteModel->getAll($filters, $pagination, $sort);

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
        $config = [
            'db' => \Helpers\Config::database(),
        ];
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
        $config = [
            'db' => \Helpers\Config::database(),
        ];
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
        $jwt_secret = \Helpers\Config::get('JWT_SECRET', 'changeme');
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
        $config = [
            'db' => \Helpers\Config::database(),
        ];
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        require_once dirname(__DIR__, 2) . '/models/Note.php';
        $conn = Db::getConnection($config);
        // Get all paid orders for this user
        $orderSql = 'SELECT o.order_id as order_id, oi.note_id FROM orders o JOIN order_items oi ON o.order_id = oi.order_id WHERE o.user_id = $1 AND o.status = $2';
        $orderResult = pg_query_params($conn, $orderSql, [$user_id, 'paid']);
        $rows = [];
        while ($row = pg_fetch_assoc($orderResult)) {
            $rows[] = $row;
        }
        $note_ids = array_column($rows, 'note_id');
        if (empty($note_ids)) {
            echo json_encode(['success' => true, 'data' => []]);
            return;
        }
        // Fetch note details
        $in = implode(',', array_fill(0, count($note_ids), '$' . ($i = 1))); $i = 1;
        foreach ($note_ids as &$id) { $id = (int)$id; }
        $noteSql = 'SELECT id, title, subject, price, preview_image FROM notes WHERE id IN (' . implode(',', array_map(function($i){static $j=1;return '$'.($j++);},$note_ids)) . ') AND is_active = TRUE';
        $noteResult = pg_query_params($conn, $noteSql, $note_ids);
        $notes = [];
        while ($note = pg_fetch_assoc($noteResult)) {
            $notes[] = $note;
        }
        // Build download URLs (placeholder, you may want to generate secure links)
        $base_url = \Helpers\Config::get('BASE_URL', 'http://localhost:8080');
        foreach ($notes as &$note) {
            $note['download_url'] = $base_url . "/api/downloads/" . $note['id'];
        }
        echo json_encode(['success' => true, 'data' => $notes]);
    }
}
