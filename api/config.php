<?php
// Start session for authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Configuration - Railway/Vercel compatible
$envDbHost = getenv('MYSQLHOST') ?: getenv('MYSQL_HOST') ?: getenv('RAILWAY_MYSQL_HOST');
$envDbUser = getenv('MYSQLUSER') ?: getenv('MYSQL_USER') ?: getenv('RAILWAY_MYSQL_USER');
$envDbPass = getenv('MYSQLPASSWORD') ?: getenv('MYSQL_PASSWORD') ?: getenv('RAILWAY_MYSQL_PASSWORD');
$envDbName = getenv('MYSQLDATABASE') ?: getenv('MYSQL_DATABASE') ?: getenv('RAILWAY_MYSQL_DATABASE');
$envDbPort = getenv('MYSQLPORT') ?: getenv('MYSQL_PORT') ?: getenv('RAILWAY_MYSQL_PORT');

// Parse connection URLs if Railway or Vercel provides them as a single string.
$databaseUrl = getenv('MYSQL_PUBLIC_URL') ?: getenv('MYSQL_URL') ?: getenv('DATABASE_URL') ?: getenv('MYSQL_DATABASE_URL');
if ($databaseUrl) {
    $parsedUrl = parse_url($databaseUrl);
    if ($parsedUrl !== false) {
        if (!empty($parsedUrl['host'])) {
            $envDbHost = $parsedUrl['host'];
        }
        if (!empty($parsedUrl['user'])) {
            $envDbUser = $parsedUrl['user'];
        }
        if (isset($parsedUrl['pass'])) {
            $envDbPass = $parsedUrl['pass'];
        }
        if (!empty($parsedUrl['path'])) {
            $envDbName = ltrim($parsedUrl['path'], '/');
        }
        if (!empty($parsedUrl['port'])) {
            $envDbPort = $parsedUrl['port'];
        }
    }
}

define('DB_HOST', $envDbHost ?: 'localhost');
define('DB_USER', $envDbUser ?: 'root');
define('DB_PASS', $envDbPass ?: '');
define('DB_NAME', $envDbName ?: 'crm_checklist');
define('DB_PORT', $envDbPort ?: 3306);

// Create connection
function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    if ($conn->connect_error) {
        die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
    }
    
    $conn->set_charset('utf8mb4');
    return $conn;
}

// CORS headers for API
function setCorsHeaders() {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }
}

// Check if user is authenticated
function requireAuth() {
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated', 'redirect' => 'login.html']);
        exit;
    }
}

// Helper to send JSON response
function sendResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// Helper to get JSON input
function getJsonInput() {
    return json_decode(file_get_contents('php://input'), true);
}
?>
