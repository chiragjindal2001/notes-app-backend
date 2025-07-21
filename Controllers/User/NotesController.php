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
}
