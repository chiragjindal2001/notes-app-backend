<?php
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
// All routing logic moved here from index.php
$uri = strtok($_SERVER['REQUEST_URI'], '?');

if ($uri === '/' || $uri === '/hello') {
    echo 'E-Notes Backend API';
} elseif ($uri === '/api/notes' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once dirname(__DIR__) . '/Controllers/User/NotesController.php';
    NotesController::getNotes();
} elseif (preg_match('#^/api/notes/(\d+)$#', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once dirname(__DIR__) . '/Controllers/User/NotesController.php';
    NotesController::getNoteById((int)$matches[1]);
} elseif ($uri === '/api/subjects' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once dirname(__DIR__) . '/Controllers/User/NotesController.php';
    NotesController::getSubjects();
} elseif (($uri === '/api/cart/add' || $uri === '/api/cart/add/' || $uri === '/api/cart' || $uri === '/api/cart/') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once dirname(__DIR__) . '/Controllers/User/CartController.php';
    CartController::addToCart();
} elseif (($uri === '/api/cart' || $uri === '/api/cart/') && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once dirname(__DIR__) . '/Controllers/User/CartController.php';
    CartController::getCartItems();
} elseif ((preg_match('#^/api/cart/(\d+)$#', $uri, $matches) || preg_match('#^/api/cart/(\d+)/$#', $uri, $matches)) && $_SERVER['REQUEST_METHOD'] === 'PUT') {
    require_once dirname(__DIR__) . '/Controllers/User/CartController.php';
    CartController::updateCartItem((int)$matches[1]);
} elseif ((preg_match('#^/api/cart/(\d+)$#', $uri, $matches) || preg_match('#^/api/cart/(\d+)/$#', $uri, $matches)) && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
    require_once dirname(__DIR__) . '/Controllers/User/CartController.php';
    CartController::deleteCartItem((int)$matches[1]);
} elseif (($uri === '/api/cart' || $uri === '/api/cart/') && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
    require_once dirname(__DIR__) . '/Controllers/User/CartController.php';
    CartController::clearCart();
} elseif ($uri === '/api/checkout/create-order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once dirname(__DIR__) . '/models/Order.php';
    require_once dirname(__DIR__) . '/Controllers/User/CheckoutController.php';
    \Controllers\User\CheckoutController::createOrder();
} elseif ($uri === '/api/checkout/verify-payment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once dirname(__DIR__) . '/models/Order.php';
    require_once dirname(__DIR__) . '/Controllers/User/CheckoutController.php';
    \Controllers\User\CheckoutController::verifyPayment();
} elseif ($uri === '/api/payments/verify' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once dirname(__DIR__) . '/models/Order.php';
    require_once dirname(__DIR__) . '/Controllers/User/CheckoutController.php';
    \Controllers\User\CheckoutController::verifySignature();
} elseif ($uri === '/api/coupons/validate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once dirname(__DIR__) . '/Controllers/User/CouponController.php';
    CouponController::validateCoupon();
} elseif ($uri === '/api/auth/register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once dirname(__DIR__) . '/Controllers/User/AuthController.php';
    AuthController::register();
} elseif ($uri === '/api/auth/login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once dirname(__DIR__) . '/Controllers/User/AuthController.php';
    AuthController::login();
} elseif ($uri === '/api/auth/me' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once dirname(__DIR__) . '/Controllers/User/AuthController.php';
    AuthController::me();
} elseif ($uri === '/api/auth/google/callback' && ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'POST')) {
    // Include required files in the correct order
    require_once dirname(__DIR__) . '/Controllers/BaseController.php';
    require_once dirname(__DIR__) . '/models/User.php';
    require_once dirname(__DIR__) . '/src/Services/GoogleAuthService.php';
    require_once dirname(__DIR__) . '/Controllers/Auth/AuthController.php';
    
    $authController = new AuthController();
    $authController->googleCallback();
} elseif ($uri === '/api/auth/logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once dirname(__DIR__) . '/Controllers/User/AuthController.php';
    AuthController::logout();
} elseif ($uri === '/api/auth/refresh' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once dirname(__DIR__) . '/Controllers/User/AuthController.php';
    AuthController::refresh();
} elseif ($uri === '/api/webhooks/razorpay' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once dirname(__DIR__) . '/Controllers/WebhookController.php';
    WebhookController::handleRazorpayWebhook();
} elseif (preg_match('#^/api/download/pdf/(\d+)$#', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once dirname(__DIR__) . '/Controllers/User/DownloadController.php';
    DownloadController::downloadPdf((int)$matches[1]);
} elseif (preg_match('#^/api/downloads/([\w\-]+)/([\d]+)$#', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once dirname(__DIR__) . '/Controllers/User/DownloadController.php';
    DownloadController::getDownloadLink($matches[1], (int)$matches[2]);
} elseif ($uri === '/api/contact' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once dirname(__DIR__) . '/Controllers/User/ContactController.php';
    ContactController::submitContact();
} elseif ($uri === '/api/admin/orders' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once dirname(__DIR__) . '/Controllers/Admin/AdminOrdersController.php';
    AdminOrdersController::listOrders();
} elseif ($uri === '/api/auth/admin/login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once dirname(__DIR__) . '/Controllers/Admin/AdminAuthController.php';
    AdminAuthController::login();
} elseif ($uri === '/api/admin/notes' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once dirname(__DIR__) . '/Controllers/Admin/AdminNotesController.php';
    AdminNotesController::createNote();
} elseif ($uri === '/api/admin/notes' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once dirname(__DIR__) . '/Controllers/Admin/AdminNotesController.php';
    AdminNotesController::listNotes();
} elseif (preg_match('#^/api/admin/notes/(\d+)$#', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'PUT') {
    require_once dirname(__DIR__) . '/Controllers/Admin/AdminNotesController.php';
    AdminNotesController::updateNote((int)$matches[1]);
} elseif (preg_match('#^/api/admin/notes/(\d+)$#', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once dirname(__DIR__) . '/Controllers/Admin/AdminNotesController.php';
    AdminNotesController::updateNote((int)$matches[1]);
} elseif (preg_match('#^/api/admin/notes/(\d+)$#', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
    require_once dirname(__DIR__) . '/Controllers/Admin/AdminNotesController.php';
    AdminNotesController::deleteNote((int)$matches[1]);
} elseif (preg_match('#^/api/admin/notes/(\d+)/status$#', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'PATCH') {
    require_once dirname(__DIR__) . '/Controllers/Admin/AdminNotesController.php';
    AdminNotesController::updateNoteStatus((int)$matches[1]);
} elseif (preg_match('#^/api/admin/orders/([\w\-]+)$#', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once dirname(__DIR__) . '/Controllers/Admin/AdminOrdersController.php';
    AdminOrdersController::getOrderDetail($matches[1]);
} elseif (preg_match('#^/api/admin/orders/([\w\-]+)/status$#', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'PATCH') {
    require_once dirname(__DIR__) . '/Controllers/Admin/AdminOrdersController.php';
    AdminOrdersController::updateOrderStatus($matches[1]);
} elseif ($uri === '/api/admin/payments/refund' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once dirname(__DIR__) . '/Controllers/Admin/AdminOrdersController.php';
    AdminOrdersController::processRefund();
} elseif ($uri === '/api/admin/coupons' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once dirname(__DIR__) . '/Controllers/Admin/AdminCouponsController.php';
    AdminCouponsController::createCoupon();
} elseif ($uri === '/api/admin/coupons' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once dirname(__DIR__) . '/Controllers/Admin/AdminCouponsController.php';
    AdminCouponsController::listCoupons();
} elseif ($uri === '/api/admin/dashboard/stats' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once dirname(__DIR__) . '/Controllers/Admin/AdminDashboardController.php';
    AdminDashboardController::stats();
} else {
http_response_code(404);
echo '404 Not Found';
}
