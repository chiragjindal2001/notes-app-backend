<?php
require_once dirname(__DIR__, 2) . '/src/AuthHelper.php';
class AdminAuthController
{
    public static function login()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input) || empty($input['username']) || empty($input['password'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing username or password']);
            return;
        }
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        $conn = Db::getConnection($config);
        require_once dirname(__DIR__, 2) . '/models/Admin.php';
        $adminModel = new Admin($conn);
        $stmt = mysqli_prepare($conn, 'SELECT * FROM admins WHERE username = ?');
        mysqli_stmt_bind_param($stmt, 's', $input['username']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $admin = mysqli_fetch_assoc($result);
        if (!$admin || !password_verify($input['password'], $admin['password_hash'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
            return;
        }
        // JWT generation
        $payload = [
            'id' => $admin['id'],
            'username' => $admin['username'],
            'role' => 'admin',
            'iat' => time(),
            'exp' => time() + 86400
        ];
        $jwt_secret = $config['jwt_secret'] ?? 'changeme';
        $header = rtrim(strtr(base64_encode(json_encode(['alg'=>'HS256','typ'=>'JWT'])), '+/', '-_'), '=');
        $payload_enc = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$payload_enc", $jwt_secret, true)), '+/', '-_'), '=');
        $token = "$header.$payload_enc.$sig";
        $response = [
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $admin['id'],
                    'username' => $admin['username'],
                    'role' => 'admin'
                ]
            ]
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    }
}
