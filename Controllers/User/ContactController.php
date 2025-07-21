<?php
class ContactController
{
    public static function submitContact()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input) || empty($input['name']) || empty($input['email']) || empty($input['subject']) || empty($input['message'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            return;
        }
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__) . '/src/Db.php';
        require_once dirname(__DIR__) . '/models/Contact.php';
        $pdo = Db::getConnection($config);
        $sql = 'INSERT INTO contacts (name, email, subject, message, status, created_at) VALUES (:name, :email, :subject, :message, :status, NOW()) RETURNING id, name, email, subject, status, created_at';
        $stmt = pg_query_params($sql);
        $stmt->execute([
            ':name' => $input['name'],
            ':email' => $input['email'],
            ':subject' => $input['subject'],
            ':message' => $input['message'],
            ':status' => 'new'
        ]);
        $contact = $stmt->fetch();
        $response = [
            'success' => true,
            'message' => 'Contact message sent successfully',
            'data' => $contact
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    }
}
