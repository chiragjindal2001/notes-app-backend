<?php
require_once dirname(__DIR__, 2) . '/src/Helpers/Config.php';

// BaseController is now included in routes.php
class AuthController extends BaseController {
    private $googleAuthService;

    public function __construct() {
        parent::__construct();
        // Load config and pass to GoogleAuthService
        $config = [
            'google' => \Helpers\Config::google(),
        ];
        $this->googleAuthService = new GoogleAuthService($config);
    }

    public function googleCallback() {
        try {
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                if (!isset($_GET['code'])) {
                    // Redirect to Google consent screen
                    $authUrl = $this->googleAuthService->getAuthUrl();
                    header('Location: ' . $authUrl);
                    exit;
                }
                $code = $_GET['code'];
                // Exchange code for tokens using GoogleAuthService
                $result = $this->googleAuthService->exchangeCodeForTokens($code);
                if (isset($result['error'])) {
                    throw new Exception($result['error']);
                }
                // Authenticate with Google using the ID token and access token
                $authResult = $this->googleAuthService->authenticate($result['id_token'], $result['access_token']);
                if (isset($authResult['error'])) {
                    throw new Exception($authResult['error']);
                }
                // Redirect to frontend with JWT token and user info in query params
                $userJson = urlencode(json_encode($authResult['user']));
                header('Location: http://localhost:3000/?token=' . urlencode($authResult['token']) . '&user=' . $userJson);
                exit;
            } else {
                throw new Exception('Invalid request method');
            }
        } catch (Exception $e) {
            error_log('Google OAuth Error: ' . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
