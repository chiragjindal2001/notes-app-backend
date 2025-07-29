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
        if (!is_array($input) || empty($input['subject']) || empty($input['message'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields: subject and message']);
            return;
        }

        // Try to get user info from JWT token first
        $user_name = null;
        $user_email = null;
        $user_id = null;
        
        require_once dirname(__DIR__, 2) . '/Controllers/BaseController.php';
        $baseController = new \BaseController();
        $token = $baseController->getBearerToken();
        
        if ($token) {
            try {
                $user = \Helpers\UserAuthHelper::validateJWT($token);
                if ($user && isset($user['user_id'])) {
                    $user_id = $user['user_id'];
                    
                    // Get user details from database
                    $config = require dirname(__DIR__, 2) . '/config/config.development.php';
                    require_once dirname(__DIR__, 2) . '/src/Db.php';
                    require_once dirname(__DIR__, 2) . '/models/User.php';
                    
                    $conn = Db::getConnection($config);
                    $userModel = new User($conn);
                    $userData = $userModel->getById($user_id);
                    
                    if ($userData) {
                        $user_name = $userData['name'];
                        $user_email = $userData['email'];
                    }
                }
            } catch (\Exception $e) {
                error_log('JWT validation error in contact: ' . $e->getMessage());
            }
        }

        // Use JWT user data if available, otherwise use form data
        $contactData = [
            'name' => $user_name ?? $input['name'] ?? '',
            'email' => $user_email ?? $input['email'] ?? '',
            'subject' => $input['subject'],
            'message' => $input['message'],
            'status' => 'new'
        ];

        // Validate that we have name and email (either from JWT or form)
        if (empty($contactData['name']) || empty($contactData['email'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Name and email are required']);
            return;
        }

        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        require_once dirname(__DIR__, 2) . '/models/Contact.php';
        
        try {
            $conn = Db::getConnection($config);
            $contactModel = new Contact($conn);
            
            $contact = $contactModel->create($contactData);
            
            if (!$contact) {
                throw new Exception('Failed to save contact message');
            }
            
            $response = [
                'success' => true,
                'message' => 'Contact message sent successfully',
                'data' => [
                    'id' => $contact['id'],
                    'name' => $contact['name'],
                    'email' => $contact['email'],
                    'subject' => $contact['subject'],
                    'status' => $contact['status'],
                    'created_at' => $contact['created_at']
                ]
            ];
            
        } catch (Exception $e) {
            http_response_code(500);
            $response = [
                'success' => false,
                'message' => 'Failed to send contact message: ' . $e->getMessage()
            ];
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
    }
}
