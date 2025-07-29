<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../models/User.php';

use Helpers\Config;
use Helpers\JwtService;
use Helpers\Database;
use Google_Client;
use Google_Service_Oauth2;

class GoogleAuthService {
    private $client;
    private $userModel;
    private $config;

    public function __construct() {
        $this->config = Config::google();
        
        $this->client = new \Google_Client([
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'redirect_uri' => $this->config['redirect_uri'],
            'http_client_options' => [
                'verify' => false // Disable SSL verification (for development only)
            ]
        ]);
        
        // Set the HTTP client to ignore SSL verification
        $this->client->setHttpClient(new \GuzzleHttp\Client([
            'verify' => false // Disable SSL verification (for development only)
        ]));
        
        // Initialize database connection and user model
        $db = Database::getConnection();
        $this->userModel = new User($db);
    }

    public function verifyIdToken($idToken) {
        try {
            $httpClient = new \GuzzleHttp\Client([
                'verify' => false // Disable SSL verification (for development only)
            ]);
            $this->client->setHttpClient($httpClient);
            
            $payload = $this->client->verifyIdToken($idToken);
            
            if (!$payload) {
                throw new \Exception('Failed to verify ID token');
            }
            
            return $payload;
            
        } catch (\Exception $e) {
            $error = 'Failed to verify ID token: ' . $e->getMessage();
            error_log($error);
            error_log('Stack trace: ' . $e->getTraceAsString());
            throw new \Exception($error);
        }
    }

    public function getUserInfo($accessToken) {
        try {
            $httpClient = new \GuzzleHttp\Client([
                'verify' => false // Disable SSL verification (for development only)
            ]);
            $this->client->setHttpClient($httpClient);
            $this->client->setAccessToken($accessToken);
            
            // Use Google OAuth2 service to get user info
            $oauth2Service = new Google_Service_Oauth2($this->client);
            $userInfo = $oauth2Service->userinfo->get();
            
            if (!$userInfo) {
                throw new \Exception('Failed to fetch user info');
            }
            
            return $userInfo;
            
        } catch (\Exception $e) {
            $error = 'Failed to get user info: ' . $e->getMessage();
            error_log($error);
            error_log('Stack trace: ' . $e->getTraceAsString());
            throw new \Exception($error);
        }
    }

    public function authenticate($idToken, $accessToken = '', $userData = []) {
        try {
            // Create a new HTTP client with SSL verification disabled
            $httpClient = new \GuzzleHttp\Client([
                'verify' => false // Disable SSL verification (for development only)
            ]);
            $this->client->setHttpClient($httpClient);
            
            // Verify the ID token
            $payload = $this->verifyIdToken($idToken);
            
            // Get additional user info using access token
            $userInfo = $this->getUserInfo($accessToken);
            
            if (empty($userInfo->email)) {
                $error = 'No email address returned from Google';
                error_log($error);
                throw new \Exception($error);
            }
            
            // Find or create user
            $user = $this->findOrCreateUser($userInfo);
            
            if (empty($user['id'])) {
                $error = 'Failed to create or retrieve user';
                error_log($error);
                throw new \Exception($error);
            }
            
            // Generate JWT token using the same format as regular login
            $config = require dirname(__DIR__, 2) . '/config/config.development.php';
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
            $jwtToken = "$header_encoded.$payload_encoded.$signature";
            
            if (empty($jwtToken)) {
                $error = 'Failed to generate JWT token';
                error_log($error);
                throw new \Exception($error);
            }
            
            $response = [
                'success' => true,
                'token' => $jwtToken,
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'image' => $user['image'] ?? null
                ]
            ];
            
            return $response;
            
        } catch (\Exception $e) {
            $error = 'Authentication failed: ' . $e->getMessage();
            error_log($error);
            error_log('Stack trace: ' . $e->getTraceAsString());
            throw new \Exception($error);
        }
    }
    
    public function exchangeCodeForTokens($code) {
        try {
            $this->client->setRedirectUri($this->config['redirect_uri']);
            $token = $this->client->fetchAccessTokenWithAuthCode($code);
            if (isset($token['error'])) {
                throw new \Exception('Error fetching access token: ' . $token['error_description']);
            }
            if (empty($token['id_token']) || empty($token['access_token'])) {
                throw new \Exception('Invalid token response from Google');
            }
            return $token;
        } catch (\Exception $e) {
            error_log('exchangeCodeForTokens error: ' . $e->getMessage());
            throw new \Exception('Failed to exchange code for tokens: ' . $e->getMessage());
        }
    }
    
    public function getAuthUrl() {
        // You can add scopes as needed
        $this->client->setScopes([
            'openid',
            'email',
            'profile',
        ]);
        return $this->client->createAuthUrl();
    }
    
    private function findOrCreateUser($userInfo) {
        // Try to find user by Google ID
        $user = $this->userModel->findByGoogleId($userInfo->id);
        
        if (!$user) {
            // If not found by Google ID, try by email
            $user = $this->userModel->getByEmail($userInfo->email);
            
            if ($user) {
                // Update existing user with Google ID and user info
                $this->userModel->updateGoogleId($user['id'], $userInfo->id, $userInfo);
            } else {
                // Create new user
                $userId = $this->userModel->create([
                    'google_id' => $userInfo->id,
                    'name' => $userInfo->name,
                    'email' => $userInfo->email,
                    'image' => $userInfo->picture ?? null,
                    'email_verified' => $userInfo->verifiedEmail ? 1 : 0,
                    'is_verified' => true, // Google users are automatically verified
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                $user = $this->userModel->getById($userId);
            }
        }
        
        return $user;
    }
}
