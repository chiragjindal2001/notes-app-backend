<?php
// Include required files
require_once __DIR__ . '/../config/config.development.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../src/Services/GoogleAuthService.php';

if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__));
}
require_once PROJECT_ROOT . '/src/Helpers/JwtService.php';

// Set content type to JSON
header('Content-Type: application/json');

// Initialize Google Client
$client = new Google_Client([
    'client_id' => $config['google']['client_id'],
    'client_secret' => $config['google']['client_secret'],
    'redirect_uri' => $config['google']['redirect_uri']
]);

// If we have a code from the OAuth 2.0 flow
if (isset($_GET['code'])) {
    try {
        // Exchange the authorization code for an access token
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        
        if (isset($token['error'])) {
            throw new Exception($token['error_description'] ?? 'Error fetching access token');
        }
        
        // Get the ID token from the response
        $idToken = $token['id_token'] ?? null;
        
        if (!$idToken) {
            throw new Exception('No ID token in response');
        }
        
        // Verify the ID token
        $payload = $client->verifyIdToken($idToken);
        
        if ($payload) {
            // User is authenticated
            $googleId = $payload['sub'];
            $email = $payload['email'];
            $name = $payload['name'] ?? '';
            $picture = $payload['picture'] ?? '';
            
            // Here you would typically find or create the user in your database
            // For testing, we'll just return the user info
            $userInfo = [
                'google_id' => $googleId,
                'email' => $email,
                'name' => $name,
                'picture' => $picture
            ];
            
            // Generate a JWT token
            $jwtToken = JwtService::generateToken([
                'user_id' => $googleId,
                'email' => $email
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Authentication successful',
                'user' => $userInfo,
                'token' => $jwtToken
            ]);
            
        } else {
            throw new Exception('Invalid ID token');
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    
} else {
    // Generate the URL to request access from Google's OAuth 2.0 server
    $authUrl = $client->createAuthUrl([
        'scope' => [
            'https://www.googleapis.com/auth/userinfo.email',
            'https://www.googleapis.com/auth/userinfo.profile'
        ]
    ]);
    
    // Redirect to Google's OAuth 2.0 server
    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
    exit;
}
