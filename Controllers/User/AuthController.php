<?php
require_once dirname(__DIR__, 2) . '/src/AuthHelper.php';

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
            echo json_encode(['success' => false, 'message' => 'Missing required fields: email, password, and name']);
            return;
        }

        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        $conn = Db::getConnection($config);

        // Check if email already exists
        $check_sql = 'SELECT id FROM users WHERE email = ?';
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, 's', $input['email']);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        if (mysqli_num_rows($check_result) > 0) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'This email is already registered. Please try logging in or use a different email address.']);
            mysqli_stmt_close($check_stmt);
            return;
        }
        mysqli_stmt_close($check_stmt);

        // Hash password
        $password_hash = password_hash($input['password'], PASSWORD_DEFAULT);

        // Generate verification code
        $verification_code = sprintf('%06d', mt_rand(0, 999999));
        $verification_expires = date('Y-m-d H:i:s', time() + (10 * 60)); // 10 minutes from now
        
        // Insert new user with verification code
        $insert_sql = 'INSERT INTO users (email, password_hash, name, email_verification_token, email_verification_expires_at, email_verified, created_at) VALUES (?, ?, ?, ?, ?, FALSE, NOW())';
        $insert_stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($insert_stmt, 'sssss', 
            $input['email'], 
            $password_hash, 
            $input['name'], 
            $verification_code, 
            $verification_expires
        );
        
        if (!mysqli_stmt_execute($insert_stmt)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create user: ' . mysqli_error($conn)]);
            mysqli_stmt_close($insert_stmt);
            return;
        }

        $user_id = mysqli_insert_id($conn);
        mysqli_stmt_close($insert_stmt);
        
        // Get the created user
        $user_sql = 'SELECT id, email, name, created_at FROM users WHERE id = ?';
        $user_stmt = mysqli_prepare($conn, $user_sql);
        mysqli_stmt_bind_param($user_stmt, 'i', $user_id);
        mysqli_stmt_execute($user_stmt);
        $user_result = mysqli_stmt_get_result($user_stmt);
        $user = mysqli_fetch_assoc($user_result);
        mysqli_stmt_close($user_stmt);
        
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

        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        $conn = Db::getConnection($config);

        // Find user by email
        $sql = 'SELECT id, email, password_hash, name, email_verified FROM users WHERE email = ?';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $input['email']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$user || !password_verify($input['password'], $user['password_hash'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
            return;
        }

        // Check if email is verified
        if (!$user['email_verified']) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Please verify your email before logging in']);
            return;
        }

        // Generate JWT token
        $payload = [
            'user_id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'iat' => time(),
            'exp' => time() + (24 * 60 * 60) // 24 hours
        ];

        $jwt_secret = $config['jwt_secret'] ?? 'changeme';
        $header = rtrim(strtr(base64_encode(json_encode(['alg'=>'HS256','typ'=>'JWT'])), '+/', '-_'), '=');
        $payload_enc = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$payload_enc", $jwt_secret, true)), '+/', '-_'), '=');
        $token = "$header.$payload_enc.$sig";

        // Generate refresh token
        $refresh_token = bin2hex(random_bytes(32));
        $refresh_expires = date('Y-m-d H:i:s', time() + (7 * 24 * 60 * 60)); // 7 days

        // Store refresh token
        require_once dirname(__DIR__, 2) . '/models/RefreshToken.php';
        $refreshTokenModel = new RefreshToken($conn);
        $refreshTokenModel->create($user['id'], $refresh_token, $refresh_expires);

        $response = [
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'refresh_token' => $refresh_token,
                'user' => [
                    'id' => (int)$user['id'],
                    'email' => $user['email'],
                    'name' => $user['name']
                ]
            ]
        ];

        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function me()
    {
        $token = AuthHelper::getBearerToken();
        if (!$token) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'No token provided']);
            return;
        }

        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        $jwt_secret = $config['jwt_secret'] ?? 'changeme';

        try {
            $payload = AuthHelper::verifyJWT($token, $jwt_secret);
            if (!$payload) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Invalid token']);
                return;
            }

            require_once dirname(__DIR__, 2) . '/src/Db.php';
            $conn = Db::getConnection($config);

            // Get user details
            $sql = 'SELECT id, email, name, email_verified, created_at FROM users WHERE id = ?';
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'i', $payload['user_id']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if (!$user) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'User not found']);
                return;
            }

            $response = [
                'success' => true,
                'data' => [
                    'id' => (int)$user['id'],
                    'email' => $user['email'],
                    'name' => $user['name'],
                    'email_verified' => (bool)$user['email_verified'],
                    'created_at' => $user['created_at']
                ]
            ];

            header('Content-Type: application/json');
            echo json_encode($response);

        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid token']);
        }
    }

    public static function logout()
    {
        $token = AuthHelper::getBearerToken();
        if (!$token) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'No token provided']);
            return;
        }

        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        $conn = Db::getConnection($config);

        // Revoke refresh token
        require_once dirname(__DIR__, 2) . '/models/RefreshToken.php';
        $refreshTokenModel = new RefreshToken($conn);
        $refreshTokenModel->revoke($token);

        $response = [
            'success' => true,
            'message' => 'Logout successful'
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
            echo json_encode(['success' => false, 'message' => 'Refresh token required']);
            return;
        }

        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        $conn = Db::getConnection($config);

        // Verify refresh token
        require_once dirname(__DIR__, 2) . '/models/RefreshToken.php';
        $refreshTokenModel = new RefreshToken($conn);
        $refreshToken = $refreshTokenModel->getByToken($input['refresh_token']);

        if (!$refreshToken || $refreshToken['revoked'] || strtotime($refreshToken['expires_at']) < time()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid or expired refresh token']);
            return;
        }

        // Get user details
        $sql = 'SELECT id, email, name FROM users WHERE id = ?';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $refreshToken['user_id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            return;
        }

        // Generate new JWT token
        $payload = [
            'user_id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'iat' => time(),
            'exp' => time() + (24 * 60 * 60) // 24 hours
        ];

        $jwt_secret = $config['jwt_secret'] ?? 'changeme';
        $header = rtrim(strtr(base64_encode(json_encode(['alg'=>'HS256','typ'=>'JWT'])), '+/', '-_'), '=');
        $payload_enc = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$payload_enc", $jwt_secret, true)), '+/', '-_'), '=');
        $token = "$header.$payload_enc.$sig";

        $response = [
            'success' => true,
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => (int)$user['id'],
                    'email' => $user['email'],
                    'name' => $user['name']
                ]
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
            echo json_encode(['success' => false, 'message' => 'Email and verification code are required']);
            return;
        }

        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        $conn = Db::getConnection($config);

        // Find user by email and verification code
        $sql = 'SELECT id, email, name FROM users WHERE email = ? AND email_verification_token = ? AND email_verification_expires_at > NOW()';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ss', $input['email'], $input['code']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$user) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code']);
            return;
        }

        // Update user as verified
        $update_sql = 'UPDATE users SET email_verified = TRUE, email_verification_token = NULL, email_verification_expires_at = NULL WHERE id = ?';
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, 'i', $user['id']);
        
        if (!mysqli_stmt_execute($update_stmt)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to verify email: ' . mysqli_error($conn)]);
            mysqli_stmt_close($update_stmt);
            return;
        }
        mysqli_stmt_close($update_stmt);

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
            echo json_encode(['success' => false, 'message' => 'Email is required']);
            return;
        }

        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        $conn = Db::getConnection($config);

        // Find user by email
        $sql = 'SELECT id, email, name, email_verified FROM users WHERE email = ?';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $input['email']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            return;
        }

        if ($user['email_verified']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email is already verified']);
            return;
        }

        // Generate new verification code
        $verification_code = sprintf('%06d', mt_rand(0, 999999));
        $verification_expires = date('Y-m-d H:i:s', time() + (10 * 60)); // 10 minutes from now

        // Update verification code
        $update_sql = 'UPDATE users SET email_verification_token = ?, email_verification_expires_at = ? WHERE id = ?';
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, 'ssi', $verification_code, $verification_expires, $user['id']);
        
        if (!mysqli_stmt_execute($update_stmt)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update verification code: ' . mysqli_error($conn)]);
            mysqli_stmt_close($update_stmt);
            return;
        }
        mysqli_stmt_close($update_stmt);

        // Send verification email
        try {
            require_once dirname(__DIR__, 2) . '/src/Services/EmailService.php';
            $emailService = new \Services\EmailService();
            $emailSent = $emailService->sendVerificationEmail($user['email'], $user['name'], $verification_code);
            
            if (!$emailSent) {
                error_log("Failed to send verification email to: " . $user['email']);
            }
        } catch (Exception $e) {
            error_log("Error sending verification email: " . $e->getMessage());
        }

        $response = [
            'success' => true,
            'message' => 'Verification code sent successfully! Please check your email.'
        ];

        // In development mode, include verification code for testing (only if email fails)
        if (($config['APP_DEBUG'] ?? false) && !$emailSent) {
            $response['verification_code'] = $verification_code;
            $response['message'] = 'Verification code sent! Email sending failed, but here is your verification code for testing.';
        }

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
            echo json_encode(['success' => false, 'message' => 'Email is required']);
            return;
        }

        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        $conn = Db::getConnection($config);

        // Find user by email
        $sql = 'SELECT id, email, name FROM users WHERE email = ?';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $input['email']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$user) {
            // Don't reveal if user exists or not
            $response = [
                'success' => true,
                'message' => 'If an account with this email exists, a password reset link has been sent.'
            ];
            header('Content-Type: application/json');
            echo json_encode($response);
            return;
        }

        // Generate reset token
        $resetToken = bin2hex(random_bytes(32));
        $resetExpires = date('Y-m-d H:i:s', time() + (60 * 60)); // 1 hour from now

        // Update user with reset token
        $update_sql = 'UPDATE users SET password_reset_token = ?, password_reset_expires_at = ? WHERE id = ?';
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, 'ssi', $resetToken, $resetExpires, $user['id']);
        
        if (!mysqli_stmt_execute($update_stmt)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update reset token: ' . mysqli_error($conn)]);
            mysqli_stmt_close($update_stmt);
            return;
        }
        mysqli_stmt_close($update_stmt);

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

        // In development mode, include reset token for testing (only if email fails)
        if (($config['APP_DEBUG'] ?? false) && !$emailSent) {
            $response['reset_token'] = $resetToken;
            $response['message'] = 'Password reset link sent! Email sending failed, but here is your reset token for testing.';
        }

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

        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        $conn = Db::getConnection($config);

        // Find user by reset token
        $sql = 'SELECT id, email, name FROM users WHERE password_reset_token = ? AND password_reset_expires_at > NOW()';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $input['token']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$user) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid or expired reset token']);
            return;
        }

        // Hash new password
        $password_hash = password_hash($input['password'], PASSWORD_DEFAULT);

        // Update password and clear reset token
        $update_sql = 'UPDATE users SET password_hash = ?, password_reset_token = NULL, password_reset_expires_at = NULL WHERE id = ?';
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, 'si', $password_hash, $user['id']);
        
        if (!mysqli_stmt_execute($update_stmt)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update password: ' . mysqli_error($conn)]);
            mysqli_stmt_close($update_stmt);
            return;
        }
        mysqli_stmt_close($update_stmt);

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

        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        $conn = Db::getConnection($config);

        // Check if reset token is valid
        $sql = 'SELECT id FROM users WHERE password_reset_token = ? AND password_reset_expires_at > NOW()';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $input['token']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$user) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid or expired reset token']);
            return;
        }

        $response = [
            'success' => true,
            'message' => 'Reset token is valid'
        ];

        header('Content-Type: application/json');
        echo json_encode($response);
    }
}
