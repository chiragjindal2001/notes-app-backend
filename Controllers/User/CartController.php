<?php
class CartController
{
    // Ensure UserAuthHelper is loaded once
    // (If using Composer autoload, this is not needed)
    // require_once dirname(__DIR__, 2) . '/src/UserAuthHelper.php'; // Moved inside methods

    public static function addToCart()
    {
        // Only allow POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid JSON body']);
            return;
        }
        
        // Extract user_id from JWT if present
        require_once dirname(__DIR__, 2) . '/Controllers/BaseController.php';
        $baseController = new \BaseController();
        $token = $baseController->getBearerToken();
        $user_id = null;
        if ($token) {
            try {
                $user = \Helpers\UserAuthHelper::validateJWT($token);
                if ($user && (isset($user['sub']) || isset($user['user_id']))) {
                    $user_id = $user['sub'] ?? $user['user_id'];
                }
            } catch (\Exception $e) {
                error_log('JWT validation error: ' . $e->getMessage());
            }
        }
        // Require user to be logged in for cart operations
        if (!$user_id) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Authentication required']);
            return;
        }
        
        // Check required fields
        $required = ['note_id', 'quantity'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Missing field: $field"]);
                return;
            }
        }

        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        require_once dirname(__DIR__, 2) . '/models/Cart.php';
        
        try {
            $conn = Db::getConnection($config);
            $cartModel = new Cart($conn);
            
            // Prepare data for cart operation
            $data = [
                'note_id' => $input['note_id'],
                'quantity' => $input['quantity'],
                'user_id' => $user_id
            ];
            
            // Add item to cart
            $item = $cartModel->addItem($data);
            
            $response = [
                'success' => true,
                'message' => 'Item added to cart successfully',
                'data' => $item
            ];
            http_response_code(201);
        } catch (Exception $e) {
            http_response_code(500);
            $response = [
                'success' => false,
                'message' => 'Failed to add item to cart: ' . $e->getMessage()
            ];
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function getCartItems()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }
        // Require user to be logged in
        require_once dirname(__DIR__, 2) . '/Controllers/BaseController.php';
        $baseController = new \BaseController();
        $token = $baseController->getBearerToken();
        $user_id = null;
        if ($token) {
            try {
                $user = \Helpers\UserAuthHelper::validateJWT($token);
                if ($user && (isset($user['sub']) || isset($user['user_id']))) {
                    $user_id = $user['sub'] ?? $user['user_id'];
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
        require_once dirname(__DIR__, 2) . '/models/Cart.php';
        
        try {
            $conn = Db::getConnection($config);
            $cartModel = new Cart($conn);
            
            $items = $cartModel->getItems($user_id);
            $response = [
                'success' => true,
                'message' => 'Cart items fetched successfully',
                'data' => $items
            ];
        } catch (Exception $e) {
            http_response_code(500);
            $response = [
                'success' => false,
                'message' => 'Failed to fetch cart items: ' . $e->getMessage()
            ];
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function updateCartItem($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }

        // Get user ID from JWT
        $user_id = null;
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            try {
                $user = \Helpers\UserAuthHelper::validateJWT($token);
                if ($user && isset($user['sub'])) {  // 'sub' contains the user ID in JWT
                    $user_id = $user['sub'];
                }
            } catch (\Exception $e) {
                error_log('JWT validation error in updateCartItem: ' . $e->getMessage());
            }
        }
        
        if (!$user_id) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Authentication required']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input) || !isset($input['quantity'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing or invalid quantity']);
            return;
        }
        
        $quantity = (int)$input['quantity'];
        if ($quantity < 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Quantity must be at least 1']);
            return;
        }

        // Get database connection
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        require_once dirname(__DIR__, 2) . '/models/Cart.php';
        
        try {
            $conn = Db::getConnection($config);
            $cartModel = new Cart($conn);
            
            // First verify the item belongs to the user
            $item = $cartModel->getItemById($id);
            if (!$item || $item['user_id'] != $user_id) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Cart item not found']);
                return;
            }
            
            // Update the item
            $updatedItem = $cartModel->updateItem($id, $quantity);
            
            $response = [
                'success' => true,
                'message' => 'Cart item updated',
                'data' => $updatedItem
            ];
        } catch (Exception $e) {
            http_response_code(500);
            $response = [
                'success' => false,
                'message' => 'Failed to update cart item: ' . $e->getMessage()
            ];
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function deleteCartItem($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }

        // Get user ID from JWT
        $user_id = null;
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            try {
                $user = \Helpers\UserAuthHelper::validateJWT($token);
                if ($user && isset($user['sub'])) {  // 'sub' contains the user ID in JWT
                    $user_id = $user['sub'];
                }
            } catch (\Exception $e) {
                error_log('JWT validation error in deleteCartItem: ' . $e->getMessage());
            }
        }
        
        if (!$user_id) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Authentication required']);
            return;
        }

        // Get database connection
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        require_once dirname(__DIR__, 2) . '/models/Cart.php';
        
        try {
            $conn = Db::getConnection($config);
            $cartModel = new Cart($conn);
            
            // First verify the item belongs to the user
            $item = $cartModel->getItemById($id);
            if (!$item || $item['user_id'] != $user_id) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Cart item not found']);
                return;
            }
            
            // Delete the item
            $deleted = $cartModel->deleteItem($id);
            
            if (!$deleted) {
                throw new Exception('Failed to delete cart item');
            }
            
            $response = [
                'success' => true,
                'message' => 'Cart item deleted successfully',
                'data' => ['id' => $id]
            ];
        } catch (Exception $e) {
            http_response_code(500);
            $response = [
                'success' => false,
                'message' => 'Failed to delete cart item: ' . $e->getMessage()
            ];
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function clearCart()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            return;
        }

        // Get user ID from JWT
        $user_id = null;
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            try {
                $user = \Helpers\UserAuthHelper::validateJWT($token);
                if ($user && isset($user['sub'])) {  // 'sub' contains the user ID in JWT
                    $user_id = $user['sub'];
                }
            } catch (\Exception $e) {
                error_log('JWT validation error in clearCart: ' . $e->getMessage());
            }
        }
        
        if (!$user_id) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Authentication required']);
            return;
        }
        
        $config = require dirname(__DIR__, 2) . '/config/config.development.php';
        require_once dirname(__DIR__, 2) . '/src/Db.php';
        require_once dirname(__DIR__, 2) . '/models/Cart.php';
        
        try {
            $conn = Db::getConnection($config);
            $cartModel = new Cart($conn);
            
            // Clear all items for this user
            $deletedCount = $cartModel->clear($user_id);
            
            $response = [
                'success' => true,
                'message' => 'Cart cleared successfully',
                'data' => ['deleted_count' => $deletedCount]
            ];
        } catch (Exception $e) {
            http_response_code(500);
            $response = [
                'success' => false,
                'message' => 'Failed to clear cart: ' . $e->getMessage()
            ];
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
    }
}
