<?php
class ReviewController
{
    public static function addReview()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($input['note_id'], $input['rating'], $input['comment'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            return;
        }
        
        // Get user_id from JWT token
        require_once dirname(__DIR__, 2) . '/Controllers/BaseController.php';
        $baseController = new \BaseController();
        $token = $baseController->getBearerToken();
        
        $user_id = null;
        if ($token) {
            try {
                $user = \Helpers\UserAuthHelper::validateJWT($token);
                if ($user && isset($user['user_id'])) {
                    $user_id = $user['user_id'];
                }
            } catch (\Exception $e) {
                error_log('JWT validation error: ' . $e->getMessage());
            }
        }
        
        if (!$user_id) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Authentication required']);
            return;
        }
        
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        require_once dirname(__DIR__, 2) . '/models/Review.php';
        
        $conn = Db::getConnection($config);
        $reviewModel = new Review($conn);
        
        $reviewData = [
            'note_id' => $input['note_id'],
            'user_id' => $user_id,
            'rating' => $input['rating'],
            'comment' => $input['comment']
        ];
        
        $review = $reviewModel->add($reviewData);
        
        $response = [
            'success' => true,
            'message' => 'Review added successfully',
            'data' => $review
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function getReviewsForNote($note_id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }
        
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        require_once dirname(__DIR__, 2) . '/models/Review.php';
        
        $conn = Db::getConnection($config);
        $reviewModel = new Review($conn);
        $reviews = $reviewModel->listForNote($note_id);
        
        $response = [
            'success' => true,
            'message' => 'Reviews fetched successfully',
            'data' => $reviews
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function listAllReviews()
    {
        // Admin only
        \Helpers\AuthHelper::requireAdminAuth();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }
        
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        require_once dirname(__DIR__, 2) . '/models/Review.php';
        
        $conn = Db::getConnection($config);
        $reviewModel = new Review($conn);
        $reviews = $reviewModel->listAll();
        
        $response = [
            'success' => true,
            'message' => 'All reviews fetched successfully',
            'data' => $reviews
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function deleteReview($id)
    {
        // Admin only
        \Helpers\AuthHelper::requireAdminAuth();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }
        
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        require_once dirname(__DIR__, 2) . '/models/Review.php';
        
        $conn = Db::getConnection($config);
        $reviewModel = new Review($conn);
        $deleted = $reviewModel->delete($id);
        
        if (!$deleted) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Review not found']);
            return;
        }
        
        $response = [
            'success' => true,
            'message' => 'Review deleted successfully'
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    }
}
