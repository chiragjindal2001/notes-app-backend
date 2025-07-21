<?php
class AuthController
{
    public static function register()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input) || empty($input['email']) || empty($input['password']) || empty($input['name'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields: email, password, name']);
            return;
        }

        // Validate email format
        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid email format']);
            return;
        }

        // Validate password length
        if (strlen($input['password']) < 6) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']);
            return;
        }

        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        $conn = Db::getConnection($config);

        // Check if email already exists
        $check_sql = 'SELECT id FROM users WHERE email = $1';
        $check_result = pg_query_params($conn, $check_sql, [$input['email']]);
        if (pg_num_rows($check_result) > 0) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            return;
        }

        // Hash password
        $password_hash = password_hash($input['password'], PASSWORD_DEFAULT);

        // Insert new user
        $insert_sql = 'INSERT INTO users (email, password_hash, name, created_at) VALUES ($1, $2, $3, NOW()) RETURNING id, email, name, created_at';
        $insert_result = pg_query_params($conn, $insert_sql, [$input['email'], $password_hash, $input['name']]);
        
        if ($insert_result === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create user: ' . pg_last_error($conn)]);
            return;
        }

        $user = pg_fetch_assoc($insert_result);
        
        $response = [
            'success' => true,
            'user' => [
                'id' => (int)$user['id'],
                'email' => $user['email'],
                'name' => $user['name']
            ]
        ];
        
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function login()
    {
        // Debug logging
        $logData = [
            'time' => date('c'),
            'method' => $_SERVER['REQUEST_METHOD'],
            'headers' => getallheaders(),
            'body_raw' => file_get_contents('php://input'),
            'post' => $_POST
        ];
        file_put_contents(dirname(__DIR__, 2) . '/src/user_auth_request.log', print_r($logData, true) . "\n---\n", FILE_APPEND);
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input) || empty($input['email']) || empty($input['password'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing email or password']);
            return;
        }

        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        $conn = Db::getConnection($config);

        // Find user by email
        $sql = 'SELECT id, email, password_hash, name FROM users WHERE email = $1';
        $result = pg_query_params($conn, $sql, [$input['email']]);
        $user = pg_fetch_assoc($result);
        
        // Debug logging
        $debugInfo = [
            'email_searched' => $input['email'],
            'user_found' => $user ? 'yes' : 'no',
            'user_data' => $user ? ['id' => $user['id'], 'email' => $user['email'], 'name' => $user['name']] : null
        ];
        if ($user) {
            $debugInfo['password_verify'] = password_verify($input['password'], $user['password_hash']) ? 'success' : 'failed';
        }
        file_put_contents(dirname(__DIR__, 2) . '/src/user_auth_request.log', "DEBUG: " . print_r($debugInfo, true) . "\n---\n", FILE_APPEND);

        if (!$user || !password_verify($input['password'], $user['password_hash'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
            return;
        }

        // Generate JWT token
        $jwt_secret = $config['jwt_secret'] ?? 'changeme';
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_id' => (int)$user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'exp' => time() + (24 * 60 * 60) // 24 hours
        ]);

        $header_encoded = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
        $payload_encoded = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header_encoded.$payload_encoded", $jwt_secret, true)), '+/', '-_'), '=');
        $token = "$header_encoded.$payload_encoded.$signature";

        // After generating $token and $response, add refresh token logic:
        require_once dirname(__DIR__, 2) . '/models/RefreshToken.php';
        $refreshModel = new RefreshToken($conn);
        $refresh_token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 days
        $refreshModel->create($user['id'], $refresh_token, $expires_at);
        $response['refresh_token'] = $refresh_token;

        $response = [
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => (int)$user['id'],
                'email' => $user['email'],
                'name' => $user['name']
            ]
        ];

        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function me()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }

        // Get token from Authorization header
        $headers = getallheaders();
        $auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (!$auth_header || !preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Not authenticated']);
            return;
        }

        $token = $matches[1];
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        $jwt_secret = $config['jwt_secret'] ?? 'changeme';

        // Validate JWT token
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
        if (!$payload_data || $payload_data['exp'] < time()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Token expired']);
            return;
        }

        $response = [
            'success' => true,
            'user' => [
                'id' => $payload_data['user_id'],
                'email' => $payload_data['email'],
                'name' => $payload_data['name']
            ]
        ];

        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function logout()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }

        // For JWT tokens, logout is handled client-side by removing the token
        // Server-side logout would require token blacklisting (optional enhancement)
        
        $response = [
            'success' => true,
            'message' => 'Logged out'
        ];

        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function refresh() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input) || empty($input['refresh_token'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing refresh_token']);
            return;
        }
        $refresh_token = $input['refresh_token'];
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        require_once dirname(__DIR__, 2) . '/models/RefreshToken.php';
        $conn = Db::getConnection($config);
        $refreshModel = new RefreshToken($conn);
        $tokenRow = $refreshModel->getByToken($refresh_token);
        if (!$tokenRow || strtotime($tokenRow['expires_at']) < time() || $tokenRow['revoked'] === 't') {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid or expired refresh token']);
            return;
        }
        // Get user info
        require_once dirname(__DIR__, 2) . '/models/User.php';
        $userModel = new User($conn);
        $user = $userModel->getById($tokenRow['user_id']);
        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            return;
        }
        // Issue new JWT
        $jwt_secret = $config['jwt_secret'] ?? 'changeme';
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_id' => (int)$user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'exp' => time() + (24 * 60 * 60)
        ]);
        $header_encoded = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
        $payload_encoded = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header_encoded.$payload_encoded", $jwt_secret, true)), '+/', '-_'), '=');
        $token = "$header_encoded.$payload_encoded.$signature";
        // Optionally, rotate refresh token (issue new one and revoke old)
        $refreshModel->revoke($refresh_token);
        $new_refresh_token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 days
        $refreshModel->create($user['id'], $new_refresh_token, $expires_at);
        $response = [
            'success' => true,
            'token' => $token,
            'refresh_token' => $new_refresh_token,
            'user' => [
                'id' => (int)$user['id'],
                'email' => $user['email'],
                'name' => $user['name']
            ]
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    }
}
