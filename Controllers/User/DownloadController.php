<?php
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
        $token = $_GET['token'] ?? null;
        if (!$token) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing token']);
            return;
        }
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        $jwt_secret = $config['jwt_secret'] ?? 'changeme';
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid token']);
            return;
        }
        $header = $parts[0];
        $payload = $parts[1];
        $signature = $parts[2];
        $expected_sig = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$payload", $jwt_secret, true)), '+/', '-_'), '=');
        if (!hash_equals($expected_sig, $signature)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid token signature']);
            return;
        }
        $payload_data = json_decode(base64_decode($payload), true);
        if (!$payload_data || $payload_data['note_id'] != $note_id) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token does not match note']);
            return;
        }
        // Check order/note access if needed (reuse logic from getDownloadLink if required)
        require_once dirname(__DIR__) . '/src/Db.php';
        require_once dirname(__DIR__) . '/models/Note.php';
        $conn = Db::getConnection($config);
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
    public static function getDownloadLink($order_id, $note_id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }
        $token = $_GET['token'] ?? null;
        if (!$token) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing token']);
            return;
        }
        // JWT validation (simple, for demo)
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        $jwt_secret = $config['jwt_secret'] ?? 'changeme';
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid token']);
            return;
        }
        $header = $parts[0];
        $payload = $parts[1];
        $signature = $parts[2];
        $expected_sig = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$payload", $jwt_secret, true)), '+/', '-_'), '=');
        if (!hash_equals($expected_sig, $signature)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid token signature']);
            return;
        }
        $payload_data = json_decode(base64_decode($payload), true);
        if (!$payload_data || $payload_data['order_id'] !== $order_id || $payload_data['note_id'] !== (int)$note_id) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token does not match order or note']);
            return;
        }
        // Check order and note access
        require_once dirname(__DIR__) . '/src/Db.php';
        require_once dirname(__DIR__) . '/models/Download.php';
        $pdo = Db::getConnection($config);
        $downloadModel = new Download($pdo);
        $row = $downloadModel->getDownloadData($order_id, $note_id);
        if (!$row || $row['status'] !== 'completed') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Order not found or not completed']);
            return;
        }
        // Generate secure download URL (placeholder)
        $download_url = $row['file_url'] ?? 'https://storage.example.com/notes/file.pdf';
        $expires_at = gmdate('Y-m-d\TH:i:s\Z', time() + 3600);
        $filename = $row['title'] ? strtolower(str_replace(' ', '-', $row['title'])) . '-notes.pdf' : 'note.pdf';
        $response = [
            'success' => true,
            'message' => 'Download link generated successfully',
            'data' => [
                'download_url' => $download_url,
                'expires_at' => $expires_at,
                'filename' => $filename
            ]
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    }
}
