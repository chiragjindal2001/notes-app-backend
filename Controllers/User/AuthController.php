<?php
require_once dirname(__DIR__, 2) . '/src/Helpers/Config.php';

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

        $config = [
            'db' => \Helpers\Config::database(),
            'APP_DEBUG' => \Helpers\Config::get('APP_DEBUG', true),
        ];
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        $conn = Db::getConnection($config);

        // Check if email already exists
        $check_sql = 'SELECT id FROM users WHERE email = $1';
        $check_result = pg_query_params($conn, $check_sql, [$input['email']]);
        if (pg_num_rows($check_result) > 0) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'This email is already registered. Please try logging in or use a different email address.']);
            return;
        }

        // Hash password
        $password_hash = password_hash($input['password'], PASSWORD_DEFAULT);

        // Generate verification code
        $verification_code = sprintf('%06d', mt_rand(0, 999999));
        $verification_expires = date('Y-m-d H:i:s', time() + (10 * 60)); // 10 minutes from now
        
        // Insert new user with verification code
        $insert_sql = 'INSERT INTO users (email, password_hash, name, verification_code, verification_code_expires_at, is_verified, created_at) VALUES ($1, $2, $3, $4, $5, FALSE, NOW()) RETURNING id, email, name, created_at';
        $insert_result = pg_query_params($conn, $insert_sql, [$input['email'], $password_hash, $input['name'], $verification_code, $verification_expires]);
        
        if ($insert_result === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create user: ' . pg_last_error($conn)]);
            return;
        }

        $user = pg_fetch_assoc($insert_result);
        
        // Send verification email
        try {
            require_once dirname(__DIR__, 2) . '/src/Services/EmailService.php';
            $emailService = new \Services\EmailService();
            $emailSent = $emailService->sendVerificationEmail($input['email'], $input['name'], $verification_code);
            
            if (!$emailSent) {
                error_log("Failed to send verification email to: " . $input['email']);
            }
        } catch (Exception $e) {
            error_log("Error sending verification email: " . $e->getMessage());
        }
        
        $response = [
            'success' => true,
            'message' => 'Registration successful! Please check your email for verification code.',
            'user' => [
                'id' => (int)$user['id'],
                'email' => $user['email'],
                'name' => $user['name']
            ]
        ];
        
        // In development mode, include verification code for testing (only if email fails)
        if (($config['APP_DEBUG'] ?? false) && !$emailSent) {
            $response['verification_code'] = $verification_code;
            $response['message'] = 'Registration successful! Email sending failed, but here is your verification code for testing.';
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function login()
    {
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

        $config = [
            'db' => \Helpers\Config::database(),
            'APP_DEBUG' => \Helpers\Config::get('APP_DEBUG', true),
        ];
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        $conn = Db::getConnection($config);

        // Find user by email
        $sql = 'SELECT id, email, password_hash, name, is_verified FROM users WHERE email = $1';
        $result = pg_query_params($conn, $sql, [$input['email']]);
        $user = pg_fetch_assoc($result);

        if (!$user || !password_verify($input['password'], $user['password_hash'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
            return;
        }

        // Check if email is verified
        if ($user['is_verified'] !== 't') {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Please verify your email address before logging in. Check your inbox for a verification email or use the resend option.']);
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
        $jwt_secret = \Helpers\Config::get('JWT_SECRET', 'changeme');

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
        $config = [
            'db' => \Helpers\Config::database(),
        ];
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

    public static function verifyEmail()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input) || empty($input['email']) || empty($input['code'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields: email, code']);
            return;
        }

        $config = [
            'db' => \Helpers\Config::database(),
        ];
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        $conn = Db::getConnection($config);

        // Find user by email and verification code
        $sql = 'SELECT id, email, name, verification_code, verification_code_expires_at, is_verified FROM users WHERE email = $1 AND verification_code = $2';
        $result = pg_query_params($conn, $sql, [$input['email'], $input['code']]);
        $user = pg_fetch_assoc($result);

        if (!$user) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid email or verification code']);
            return;
        }

        if ($user['is_verified'] === 't') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email is already verified']);
            return;
        }

        // Check if verification code has expired
        if (strtotime($user['verification_code_expires_at']) < time()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Verification code has expired. Please request a new one.']);
            return;
        }

        // Mark user as verified and clear verification code
        $update_sql = 'UPDATE users SET is_verified = TRUE, verification_code = NULL, verification_code_expires_at = NULL WHERE id = $1';
        $update_result = pg_query_params($conn, $update_sql, [$user['id']]);

        if ($update_result === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to verify email: ' . pg_last_error($conn)]);
            return;
        }

        $response = [
            'success' => true,
            'message' => 'Email verified successfully! You can now log in.',
            'user' => [
                'id' => (int)$user['id'],
                'email' => $user['email'],
                'name' => $user['name']
            ]
        ];

        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function resendVerification()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input) || empty($input['email'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing email address']);
            return;
        }

        $config = [
            'db' => \Helpers\Config::database(),
        ];
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        $conn = Db::getConnection($config);

        // Find user by email
        $sql = 'SELECT id, email, name, is_verified FROM users WHERE email = $1';
        $result = pg_query_params($conn, $sql, [$input['email']]);
        $user = pg_fetch_assoc($result);

        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            return;
        }

        if ($user['is_verified'] === 't') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email is already verified']);
            return;
        }

        // Generate new verification code
        $verification_code = sprintf('%06d', mt_rand(0, 999999));
        $verification_expires = date('Y-m-d H:i:s', time() + (10 * 60)); // 10 minutes from now

        // Update user with new verification code
        $update_sql = 'UPDATE users SET verification_code = $1, verification_code_expires_at = $2 WHERE id = $3';
        $update_result = pg_query_params($conn, $update_sql, [$verification_code, $verification_expires, $user['id']]);

        if ($update_result === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update verification code: ' . pg_last_error($conn)]);
            return;
        }

        // Send new verification email
        try {
            require_once dirname(__DIR__, 2) . '/src/Services/EmailService.php';
            $emailService = new \Services\EmailService();
            $emailSent = $emailService->sendVerificationEmail($user['email'], $user['name'], $verification_code);
            
            if (!$emailSent) {
                error_log("Failed to send verification email to: " . $user['email']);
                // In development mode, we'll still return success but inform about the failure
                if ($config['APP_DEBUG'] ?? false) {
                    $response = [
                        'success' => true,
                        'message' => 'Email sending failed, but here is your verification code for testing.',
                        'verification_code' => $verification_code // Only in development
                    ];
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Failed to send verification email']);
                    return;
                }
            } else {
                $response = [
                    'success' => true,
                    'message' => 'Verification code sent successfully! Please check your email.'
                ];
            }
        } catch (Exception $e) {
            error_log("Error sending verification email: " . $e->getMessage());
            // In development mode, we'll still return success but inform about the failure
            if ($config['APP_DEBUG'] ?? false) {
                $response = [
                    'success' => true,
                    'message' => 'Email sending failed, but here is your verification code for testing.',
                    'verification_code' => $verification_code // Only in development
                ];
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to send verification email']);
                return;
            }
        }

        $response = [
            'success' => true,
            'message' => 'Verification code sent successfully! Please check your email.'
        ];

        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function forgotPassword()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input) || empty($input['email'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email address is required']);
            return;
        }

        // Validate email format
        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid email format']);
            return;
        }

        $config = [
            'db' => \Helpers\Config::database(),
        ];
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        $conn = Db::getConnection($config);

        // Check if user exists
        $sql = 'SELECT id, email, name FROM users WHERE email = $1';
        $result = pg_query_params($conn, $sql, [$input['email']]);
        $user = pg_fetch_assoc($result);

        // Always return success to prevent email enumeration attacks
        if (!$user) {
            $response = [
                'success' => true,
                'message' => 'If an account with this email exists, a password reset link has been sent.'
            ];
            header('Content-Type: application/json');
            echo json_encode($response);
            return;
        }

        // Generate secure reset token
        $resetToken = bin2hex(random_bytes(32));
        $resetExpires = date('Y-m-d H:i:s', time() + (60 * 60)); // 1 hour from now

        // Update user with reset token
        $update_sql = 'UPDATE users SET password_reset_token = $1, password_reset_expires_at = $2 WHERE id = $3';
        $update_result = pg_query_params($conn, $update_sql, [$resetToken, $resetExpires, $user['id']]);

        if ($update_result === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to process password reset request']);
            return;
        }

        // Send password reset email
        try {
            require_once dirname(__DIR__, 2) . '/src/Services/EmailService.php';
            $emailService = new \Services\EmailService();
            $emailSent = $emailService->sendPasswordResetEmail($user['email'], $user['name'], $resetToken);
            
            if (!$emailSent) {
                error_log("Failed to send password reset email to: " . $user['email']);
            }
        } catch (Exception $e) {
            error_log("Error sending password reset email: " . $e->getMessage());
        }

        $response = [
            'success' => true,
            'message' => 'If an account with this email exists, a password reset link has been sent.'
        ];

        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function resetPassword()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input) || empty($input['token']) || empty($input['password'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Token and new password are required']);
            return;
        }

        // Validate password length
        if (strlen($input['password']) < 6) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']);
            return;
        }

        $config = [
            'db' => \Helpers\Config::database(),
        ];
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        $conn = Db::getConnection($config);

        // Find user by reset token
        $sql = 'SELECT id, email, name, password_reset_expires_at FROM users WHERE password_reset_token = $1';
        $result = pg_query_params($conn, $sql, [$input['token']]);
        $user = pg_fetch_assoc($result);

        if (!$user) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid or expired reset token']);
            return;
        }

        // Check if token has expired
        if (strtotime($user['password_reset_expires_at']) < time()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Reset token has expired. Please request a new one.']);
            return;
        }

        // Hash new password
        $password_hash = password_hash($input['password'], PASSWORD_DEFAULT);

        // Update password and clear reset token
        $update_sql = 'UPDATE users SET password_hash = $1, password_reset_token = NULL, password_reset_expires_at = NULL WHERE id = $2';
        $update_result = pg_query_params($conn, $update_sql, [$password_hash, $user['id']]);

        if ($update_result === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to reset password']);
            return;
        }

        $response = [
            'success' => true,
            'message' => 'Password reset successfully! You can now log in with your new password.'
        ];

        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function validateResetToken()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input) || empty($input['token'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Token is required']);
            return;
        }

        $config = [
            'db' => \Helpers\Config::database(),
        ];
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        $conn = Db::getConnection($config);

        // Find user by reset token
        $sql = 'SELECT id, email, name, password_reset_expires_at FROM users WHERE password_reset_token = $1';
        $result = pg_query_params($conn, $sql, [$input['token']]);
        $user = pg_fetch_assoc($result);

        if (!$user) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid reset token']);
            return;
        }

        // Check if token has expired
        if (strtotime($user['password_reset_expires_at']) < time()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Reset token has expired']);
            return;
        }

        $response = [
            'success' => true,
            'message' => 'Token is valid',
            'user' => [
                'email' => $user['email'],
                'name' => $user['name']
            ]
        ];

        header('Content-Type: application/json');
        echo json_encode($response);
    }
}
