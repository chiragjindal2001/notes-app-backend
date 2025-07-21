<?php
class ReviewController
{
    public static function addReview()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($input['note_id'], $input['user_name'], $input['rating'], $input['comment'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            return;
        }
        $config = require dirname(__DIR__) . '/config/config.development.php';
        require_once dirname(__DIR__) . '/src/Db.php';
        require_once dirname(__DIR__) . '/models/Review.php';
        $pdo = Db::getConnection($config);
        $reviewModel = new Review($pdo);
        $review = $reviewModel->add($input);
        $response = [
            'success' => true,
            'message' => 'Review added',
            'data' => $review
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function getReviewsForNote($note_id)
    {
        $config = require dirname(__DIR__) . '/config/config.development.php';
        require_once dirname(__DIR__) . '/src/Db.php';
        require_once dirname(__DIR__) . '/models/Review.php';
        $pdo = Db::getConnection($config);
        $reviewModel = new Review($pdo);
        $reviews = $reviewModel->listForNote($note_id);
        $response = [
            'success' => true,
            'message' => 'Reviews fetched',
            'data' => $reviews
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function listAllReviews()
    {
        // Admin only
        AuthHelper::requireAdminAuth();
        $config = require dirname(__DIR__) . '/config/config.development.php';
        require_once dirname(__DIR__) . '/src/Db.php';
        require_once dirname(__DIR__) . '/models/Review.php';
        $pdo = Db::getConnection($config);
        $reviewModel = new Review($pdo);
        $reviews = $reviewModel->listAll();
        $response = [
            'success' => true,
            'message' => 'All reviews fetched',
            'data' => $reviews
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function deleteReview($id)
    {
        // Admin only
        AuthHelper::requireAdminAuth();
        $config = require dirname(__DIR__) . '/config/config.development.php';
        require_once dirname(__DIR__) . '/src/Db.php';
        require_once dirname(__DIR__) . '/models/Review.php';
        $pdo = Db::getConnection($config);
        $reviewModel = new Review($pdo);
        $deleted = $reviewModel->delete($id);
        if (!$deleted) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Review not found']);
            return;
        }
        $response = [
            'success' => true,
            'message' => 'Review deleted',
            'data' => $deleted
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    }
}
