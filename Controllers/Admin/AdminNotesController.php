<?php
require_once dirname(__DIR__, 2) . '/src/AuthHelper.php';
class AdminNotesController
{
    public static function createNote()
    {
        AuthHelper::requireAdminAuth();
        // Log request body and headers for debugging
        $logData = [
            'time' => date('c'),
            'headers' => getallheaders(),
            'body_raw' => file_get_contents('php://input'),
            'post' => $_POST,
            'files' => $_FILES
        ];
        file_put_contents(dirname(__DIR__, 2) . '/src/admin_notes_request.log', print_r($logData, true) . "\n---\n", FILE_APPEND);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }
        // Handle JSON or multipart/form-data
        $fields = ['title','description','subject','price','tags','features','topics'];
        $data = [];
        if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
            $input = json_decode(file_get_contents('php://input'), true);
            foreach ($fields as $f) {
                $data[$f] = $input[$f] ?? null;
            }
        } else {
            foreach ($fields as $f) {
                $data[$f] = $_POST[$f] ?? null;
            }
            $data['price'] = isset($data['price']) ? (float)$data['price'] : 0.0;
            $data['tags'] = isset($data['tags']) ? explode(',', $data['tags']) : [];
            $data['features'] = isset($_POST['features']) ? (array)json_decode($_POST['features'],true) : [];
            $data['topics'] = isset($_POST['topics']) ? (array)json_decode($_POST['topics'],true) : [];
        }
        // Save files
        $file_fields = ['note_file','preview_image','sample_pages'];
        $file_paths = [];
        foreach ($file_fields as $f) {
            if (!empty($_FILES[$f]['name'])) {
                // Determine directory by file type
                $is_pdf = false;
                $is_image = false;
                $target_dir = dirname(__DIR__, 2) . '/public/uploads/';
                $url_prefix = '/uploads/';
                if ($f === 'note_file' || ($f === 'sample_pages' && is_array($_FILES[$f]['name']) && isset($_FILES[$f]['type'][0]) && strpos($_FILES[$f]['type'][0], 'pdf') !== false)) {
                    $is_pdf = true;
                    $target_dir .= 'pdfs/';
                    $url_prefix .= 'pdfs/';
                } elseif ($f === 'preview_image' || ($f === 'sample_pages' && is_array($_FILES[$f]['name']) && isset($_FILES[$f]['type'][0]) && strpos($_FILES[$f]['type'][0], 'image') !== false)) {
                    $is_image = true;
                    $target_dir .= 'images/';
                    $url_prefix .= 'images/';
                }
                if (!is_dir($target_dir)) mkdir($target_dir,0777,true);
                if ($f === 'sample_pages' && is_array($_FILES[$f]['name'])) {
                    $file_paths[$f] = [];
                    foreach ($_FILES[$f]['name'] as $idx=>$name) {
                        $tmp = $_FILES[$f]['tmp_name'][$idx];
                        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        if ($ext === 'pdf') {
                            $target_dir = dirname(__DIR__, 2) . '/private_uploads/pdfs/';
                            $url_prefix = '/private_uploads/pdfs/'; // Internal storage reference
                        } elseif (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                            $target_dir = dirname(__DIR__, 2) . '/public/uploads/images/';
                            $url_prefix = '/uploads/images/';
                        }
                        if (!is_dir($target_dir)) mkdir($target_dir,0777,true);
                        $dest = $target_dir . uniqid() . '-' . basename($name);
                        move_uploaded_file($tmp, $dest);
                        $file_paths[$f][] = $url_prefix . basename($dest);
                    }
                } else {
                    $name = $_FILES[$f]['name'];
                    $tmp = $_FILES[$f]['tmp_name'];
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    if ($ext === 'pdf') {
                        $target_dir = dirname(__DIR__, 2) . '/private_uploads/pdfs/';
                        $url_prefix = '/private_uploads/pdfs/'; // This is just a storage reference, not a public URL
                    } elseif (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                        $target_dir = dirname(__DIR__, 2) . '/public/uploads/images/';
                        $url_prefix = '/uploads/images/';
                    }
                    if (!is_dir($target_dir)) mkdir($target_dir,0777,true);
                    $dest = $target_dir . uniqid() . '-' . basename($name);
                    move_uploaded_file($tmp, $dest);
                    $file_paths[$f] = $url_prefix . basename($dest);
                }
            }
        }
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        $conn = Db::getConnection($config);
        $sql = 'INSERT INTO notes (title, description, subject, price, tags, features, topics, file_url, preview_image, sample_pages, status, created_at) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, NOW()) RETURNING id, title, subject, price, status, created_at, preview_image';
        $params = [
            $data['title'],
            $data['description'],
            $data['subject'],
            $data['price'],
            json_encode($data['tags']),
            json_encode($data['features']),
            json_encode($data['topics']),
            $file_paths['note_file'] ?? null,
            $file_paths['preview_image'] ?? null,
            isset($file_paths['sample_pages']) ? json_encode($file_paths['sample_pages']) : json_encode([]),
            'active'
        ];
        $result = pg_query_params($conn, $sql, array_slice($params, 0, 11));
        $note = pg_fetch_assoc($result);
        $response = [
            'success' => true,
            'message' => 'Note created successfully',
            'data' => $note
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    }
    public static function listNotes()
    {
        AuthHelper::requireAdminAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        $conn = Db::getConnection($config);
        $result = pg_query($conn, 'SELECT id, title, subject, price, downloads, status, created_at, preview_image, file_url, description, tags, features, topics FROM notes ORDER BY created_at DESC');
        $notes = [];
        while ($row = pg_fetch_assoc($result)) {
            // Parse JSON fields
            $tags = json_decode($row['tags'] ?? '[]', true) ?: [];
            $features = json_decode($row['features'] ?? '[]', true) ?: [];
            $topics = json_decode($row['topics'] ?? '[]', true) ?: [];
            
            // Extract filename from file_url
            $file_url = $row['file_url'] ?? '';
            $filename = $file_url ? basename($file_url) : '';
            
            // Format the note data
            $note = [
                'id' => (int)$row['id'],
                'title' => $row['title'],
                'subject' => $row['subject'],
                'price' => (float)$row['price'],
                'tags' => $tags,
                'description' => $row['description'],
                'rating' => 0.0, // Placeholder - implement rating system if needed
                'downloads' => (int)($row['downloads'] ?? 0),
                'download_count' => (int)($row['downloads'] ?? 0),
                'status' => $row['status'],
                'file_name' => $filename,
                'filename' => $filename,
                'file' => [
                    'name' => $filename,
                    'url' => $file_url ? ($_SERVER['HTTP_HOST'] ?? 'localhost') . $file_url : null
                ],
                'preview' => $row['preview_image'] ? ($_SERVER['HTTP_HOST'] ?? 'localhost') . $row['preview_image'] : null,
                'uploadDate' => date('Y-m-d', strtotime($row['created_at']))
            ];
            
            $notes[] = $note;
        }
        $response = [
            'success' => true,
            'message' => 'Notes fetched successfully',
            'data' => $notes
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function updateNote($id)
    {
        AuthHelper::requireAdminAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing request body']);
            return;
        }
        $fields = ['title','description','subject','price','tags','features','topics'];
        $set = [];
        $params = [':id' => $id];
        foreach ($fields as $f) {
            if (isset($input[$f])) {
                $set[] = "$f = :$f";
                $params[":$f"] = is_array($input[$f]) ? json_encode($input[$f]) : $input[$f];
            }
        }
        if (!$set) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
            return;
        }
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        $conn = Db::getConnection($config);
        
        // Build SQL with positional parameters for pg_query_params
        $sql_parts = [];
        $param_values = [];
        $param_index = 1;
        
        foreach ($fields as $f) {
            if (isset($input[$f])) {
                $sql_parts[] = "$f = $" . $param_index;
                $param_values[] = is_array($input[$f]) ? json_encode($input[$f]) : $input[$f];
                $param_index++;
            }
        }
        
        // Add id parameter at the end
        $param_values[] = $id;
        $sql = 'UPDATE notes SET ' . implode(', ', $sql_parts) . ' WHERE id = $' . $param_index . ' RETURNING id, title, subject, price, status, created_at, preview_image';
        
        $result = pg_query_params($conn, $sql, $param_values);
        $note = pg_fetch_assoc($result);
        if (!$note) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Note not found']);
            return;
        }
        $response = [
            'success' => true,
            'message' => 'Note updated successfully',
            'data' => $note
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function deleteNote($id)
    {
        AuthHelper::requireAdminAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        $conn = Db::getConnection($config);
        $result = pg_query_params($conn, 'DELETE FROM notes WHERE id = $1 RETURNING id', [$id]);
        $deleted = pg_fetch_assoc($result);
        if (!$deleted) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Note not found']);
            return;
        }
        $response = [
            'success' => true,
            'message' => 'Note deleted successfully',
            'data' => $deleted
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function updateNoteStatus($id)
    {
        AuthHelper::requireAdminAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['status'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing status']);
            return;
        }
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        $conn = Db::getConnection($config);
        $result = pg_query_params($conn, 'UPDATE notes SET status = $1 WHERE id = $2 RETURNING id, title, status', [$input['status'], $id]);
        $note = pg_fetch_assoc($result);
        if (!$note) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Note not found']);
            return;
        }
        $response = [
            'success' => true,
            'message' => 'Note status updated successfully',
            'data' => $note
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    }
}
