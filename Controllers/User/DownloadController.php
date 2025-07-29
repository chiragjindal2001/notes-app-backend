<?php
use Helpers\UserAuthHelper;
use Helpers\Config;
use Helpers\Database;
class DownloadController
{
    // Secure PDF download endpoint: /api/download/pdf/{note_id}?token=...
    public static function downloadPdf($note_id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }
        
        // Extract user_id from JWT if present
        $user_id = null;
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? null);
        
        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            $user = \Helpers\UserAuthHelper::validateJWT($token);
            if ($user && isset($user['user_id'])) {
                $user_id = $user['user_id'];
            } else {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                return;
            }
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Missing or invalid Authorization header']);
            return;
        }

        // Check order/note access if needed (reuse logic from getDownloadLink if required)
        require_once dirname(__DIR__, 2) . '/models/Note.php';
        $conn = Database::getConnection();
        $noteModel = new Note($conn);
        $note = $noteModel->getById($note_id);
        if (!$note || empty($note['file_url'])) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'PDF not found for this note']);
            return;
        }
        $pdf_path = $note['file_url'];
        // file_url is stored as /private_uploads/pdfs/filename.pdf
        $abs_path = dirname(__DIR__, 2) . $pdf_path;
        if (!file_exists($abs_path)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'File not found']);
            return;
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($abs_path) . '"');
        header('Content-Length: ' . filesize($abs_path));
        readfile($abs_path);
        exit;
    }
    // New: Secure download endpoint: /api/downloads/{note_id}
    public static function downloadNote($note_id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }
        // Get JWT from Authorization header
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        if (!$authHeader || !preg_match('/Bearer\s(.+)/', $authHeader, $matches)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Missing or invalid Authorization header']);
            return;
        }
        $token = $matches[1];
        $user = \Helpers\UserAuthHelper::validateJWT($token);
        if (!$user || empty($user['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            return;
        }
        $user_id = $user['user_id'];
        // Check if user has purchased the note
        require_once dirname(__DIR__, 2) . '/models/Download.php';
        $pdo = Database::getConnection();
        $downloadModel = new Download($pdo);
        $row = $downloadModel->userHasPurchasedNote($user_id, $note_id);
        if (!$row) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You have not purchased this note or order not completed']);
            return;
        }
        $download_url = dirname(__DIR__, 2) .  $row['file_url'] ?? null;
        $filename = $row['title'] ? strtolower(str_replace(' ', '-', $row['title'])) . '-notes.pdf' : 'note.pdf';
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($download_url));
        readfile($download_url);
        exit;
    }
}
