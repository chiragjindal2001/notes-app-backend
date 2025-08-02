<?php
require_once dirname(__DIR__, 2) . '/src/AuthHelper.php';
class AdminNotesController
{
    public static function createNote()
    {
        \Helpers\AuthHelper::requireAdminAuth();
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
        
        $params = [
            $data['title'],
            $data['description'],
            $data['subject'],
            $data['price'],
            $file_paths['note_file'] ?? null,
            $file_paths['note_file'] ? filesize($file_paths['note_file']) : null,
            $data['user_id'] ?? 1, // Default user ID
            'active'
        ];
        
        $sql = 'INSERT INTO notes (title, description, subject, price, file_path, file_size, user_id, status, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE)';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'sssdiiis', 
            $params[0], $params[1], $params[2], $params[3], 
            $params[4], $params[5], $params[6], $params[7]
        );
        mysqli_stmt_execute($stmt);
        $note_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        
        // Get the created note
        $noteModel = new \Models\Note($conn);
        $note = $noteModel->getById($note_id);
        
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
        \Helpers\AuthHelper::requireAdminAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        $conn = Db::getConnection($config);
        $result = mysqli_query($conn, 'SELECT id, title, subject, price, downloads, status, created_at, description FROM notes ORDER BY created_at DESC');
        $notes = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // Extract filename from file_path
            $file_path = $row['file_path'] ?? '';
            $filename = $file_path ? basename($file_path) : '';
            
            // Format the note data
            $note = [
                'id' => (int)$row['id'],
                'title' => $row['title'],
                'subject' => $row['subject'],
                'price' => (float)$row['price'],
                'description' => $row['description'],
                'rating' => 0.0, // Placeholder - implement rating system if needed
                'downloads' => (int)($row['downloads'] ?? 0),
                'download_count' => (int)($row['downloads'] ?? 0),
                'status' => $row['status'],
                'file_name' => $filename,
                'filename' => $filename,
                'file' => [
                    'name' => $filename,
                    'url' => $file_path ? ($_SERVER['HTTP_HOST'] ?? 'localhost') . $file_path : null
                ],
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
        \Helpers\AuthHelper::requireAdminAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }

        // Support both JSON and multipart/form-data
        $isMultipart = isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false;
        $input = $isMultipart ? $_POST : json_decode(file_get_contents('php://input'), true);
        if (!$input && empty($_FILES)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing request body']);
            return;
        }
        
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        $conn = Db::getConnection($config);
        
        // Build SQL with positional parameters for mysqli
        $fields = ['title','description','subject','price','file_path','file_size'];
        $set = [];
        $params = [];
        $paramTypes = '';
        
        foreach ($fields as $f) {
            if (isset($input[$f])) {
                $set[] = "$f = ?";
                $params[] = $input[$f];
                $paramTypes .= is_numeric($input[$f]) ? 'i' : 's';
            }
        }
        
        if (!$set) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
            return;
        }
        
        $params[] = $id;
        $paramTypes .= 'i';
        
        $sql = 'UPDATE notes SET ' . implode(',', $set) . ' WHERE id = ?';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, $paramTypes, ...$params);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        // Get the updated note
        $noteModel = new \Models\Note($conn);
        $note = $noteModel->getById($id);
        
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
        \Helpers\AuthHelper::requireAdminAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }
        
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        $conn = Db::getConnection($config);
        
        $sql = 'UPDATE notes SET is_active = FALSE WHERE id = ?';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $affected_rows = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        
        if ($affected_rows > 0) {
            $response = [
                'success' => true,
                'message' => 'Note deleted successfully'
            ];
        } else {
            http_response_code(404);
            $response = [
                'success' => false,
                'message' => 'Note not found'
            ];
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function updateNoteStatus($id)
    {
        \Helpers\AuthHelper::requireAdminAuth();
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
        
        $sql = 'UPDATE notes SET status = ? WHERE id = ?';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'si', $input['status'], $id);
        mysqli_stmt_execute($stmt);
        $affected_rows = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        
        if ($affected_rows > 0) {
            // Get the updated note
            $noteModel = new \Models\Note($conn);
            $note = $noteModel->getById($id);
            
            $response = [
                'success' => true,
                'message' => 'Note status updated successfully',
                'data' => $note
            ];
        } else {
            http_response_code(404);
            $response = [
                'success' => false,
                'message' => 'Note not found'
            ];
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
    }
}
