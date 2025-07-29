<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../models/User.php';

use Helpers\Config;
use Helpers\JwtService;
use Helpers\Database;

class GoogleAuthService {
    private $client;
    private $userModel;
    private $config;

    public function __construct() {
        $this->config = Config::google();
        
        $this->client = new Google_Client([
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
            error_log('Verifying Google ID token');
            // Create a new HTTP client with SSL verification disabled
            $httpClient = new \GuzzleHttp\Client([
                'verify' => false // Disable SSL verification (for development only)
            ]);
            
            // Set the HTTP client before verifying the token
            $this->client->setHttpClient($httpClient);
            
            $payload = $this->client->verifyIdToken($idToken);
            
            if (!$payload) {
                $error = 'Invalid ID token: Verification returned false';
                error_log($error);
                throw new \Exception($error);
            }
            
            error_log('Token verified successfully for user: ' . ($payload['email'] ?? 'unknown'));
            return $payload;
            
        } catch (\Exception $e) {
            $error = 'Token verification failed: ' . $e->getMessage();
            error_log($error);
            error_log('Stack trace: ' . $e->getTraceAsString());
            throw new \Exception($error);
        }
    }

    public function getUserInfo($accessToken) {
        try {
            error_log('Fetching user info from Google API');
            $this->client->setAccessToken($accessToken);
            $oauth2Service = new Google_Service_Oauth2($this->client);
            $userInfo = $oauth2Service->userinfo->get();
            
            if (!$userInfo) {
                $error = 'Failed to get user info: Empty response from Google API';
                error_log($error);
                throw new \Exception($error);
            }
            
            error_log('Successfully retrieved user info for: ' . ($userInfo->email ?? 'unknown'));
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
            error_log('Starting Google OAuth authentication');
            
            // Create a new HTTP client with SSL verification disabled
            $httpClient = new \GuzzleHttp\Client([
                'verify' => false // Disable SSL verification (for development only)
            ]);
            $this->client->setHttpClient($httpClient);
            
            // Verify the ID token
            $payload = $this->verifyIdToken($idToken);
            
            // Get additional user info using access token
            error_log('Fetching user info with access token');
            $userInfo = $this->getUserInfo($accessToken);
            
            if (empty($userInfo->email)) {
                $error = 'No email address returned from Google';
                error_log($error);
                throw new \Exception($error);
            }
            
            // Find or create user
            error_log('Finding or creating user in database');
            $user = $this->findOrCreateUser($userInfo);
            
            if (empty($user['id'])) {
                $error = 'Failed to create or retrieve user';
                error_log($error);
                throw new \Exception($error);
            }
            
            // Generate JWT token
            error_log('Generating JWT token for user ID: ' . $user['id']);
            
            if (!class_exists(JwtService::class)) {
                error_log('JwtHelper class not found');
                throw new \Exception('JWT Helper class not found');
            }
            
            $jwtToken = JwtService::generateToken(
                $user['id'],
                $user['email'],
                [
                    'name' => $user['name'],
                    'image' => $user['image'] ?? null
                ]
            );
            
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
            
            error_log('Authentication successful for user: ' . $user['email']);
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
            error_log('Exchanging code for tokens');
            $this->client->setRedirectUri($this->config['redirect_uri']);
            $token = $this->client->fetchAccessTokenWithAuthCode($code);
            if (isset($token['error'])) {
                throw new \Exception('Error fetching access token: ' . $token['error_description']);
            }
            if (empty($token['id_token']) || empty($token['access_token'])) {
                throw new \Exception('Missing id_token or access_token in token response');
            }
            return [
                'id_token' => $token['id_token'],
                'access_token' => $token['access_token'],
            ];
        } catch (\Exception $e) {
            error_log('exchangeCodeForTokens error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
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
