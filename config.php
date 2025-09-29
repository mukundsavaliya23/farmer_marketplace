<?php
// config.php - Complete configuration file for FarmConnect Pro

// Set secure session parameters BEFORE starting session
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting for development (set to 0 for production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone setting
date_default_timezone_set('Asia/Kolkata');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'farmer_marketplace');
define('DB_USER', 'root'); // Change for production
define('DB_PASS', ''); // Change for production
define('DB_CHARSET', 'utf8mb4');

// Site Configuration
define('SITE_NAME', 'FarmConnect Pro');
define('SITE_URL', 'http://localhost/farmer_marketplace'); // Updated path
define('SITE_EMAIL', 'admin@farmconnect.com');
define('SITE_DESCRIPTION', 'Premium Agricultural Marketplace connecting farmers directly with buyers');

// File Upload Configuration
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);

// Security Configuration
define('BCRYPT_COST', 12);
define('SESSION_LIFETIME', 3600); // 1 hour
define('REMEMBER_TOKEN_LIFETIME', 30 * 24 * 3600); // 30 days
define('PASSWORD_RESET_LIFETIME', 3600); // 1 hour

// Pagination Configuration
define('PRODUCTS_PER_PAGE', 12);
define('ORDERS_PER_PAGE', 10);
define('USERS_PER_PAGE', 15);

// Email Configuration (for production)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_ENCRYPTION', 'tls');

// API Keys (for production)
define('GOOGLE_CLIENT_ID', 'your-google-client-id');
define('GOOGLE_CLIENT_SECRET', 'your-google-client-secret');
define('FACEBOOK_APP_ID', 'your-facebook-app-id');
define('FACEBOOK_APP_SECRET', 'your-facebook-app-secret');

// Payment Gateway Configuration (for production)
define('RAZORPAY_KEY_ID', 'your-razorpay-key-id');
define('RAZORPAY_KEY_SECRET', 'your-razorpay-key-secret');

// Create uploads directory if it doesn't exist
if (!is_dir(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
    mkdir(UPLOAD_PATH . 'products', 0755, true);
    mkdir(UPLOAD_PATH . 'profiles', 0755, true);
}

// Database Connection with Error Handling
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false, // Changed to false for XAMPP compatibility
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // Set SQL mode for better compatibility
    $pdo->exec("SET sql_mode = ''");
    
} catch (PDOException $e) {
    // Log error in production, display in development
    error_log("Database Connection Error: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

// Helper Functions

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user information
 */
function current_user() {
    global $pdo;
    
    if (!is_logged_in()) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error fetching current user: " . $e->getMessage());
        return null;
    }
}

/**
 * Check user permissions
 */
function has_permission($required_type) {
    if (!is_logged_in()) {
        return false;
    }
    
    $user = current_user();
    return $user && $user['user_type'] === $required_type;
}

/**
 * Redirect function
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Sanitize input
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Validate email
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (Indian format)
 */
function is_valid_phone($phone) {
    return preg_match('/^[6-9]\d{9}$/', $phone);
}

/**
 * Generate secure random token
 */
function generate_token($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Hash password
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT, ['cost' => BCRYPT_COST]);
}

/**
 * Verify password
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Upload file with validation
 */
function upload_file($file, $upload_dir = 'products', $allowed_types = null) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error');
    }
    
    $allowed_types = $allowed_types ?: ALLOWED_IMAGE_TYPES;
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_types)) {
        throw new Exception('Invalid file type. Allowed: ' . implode(', ', $allowed_types));
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception('File size too large. Maximum allowed: ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB');
    }
    
    $upload_path = UPLOAD_PATH . $upload_dir . '/';
    if (!is_dir($upload_path)) {
        mkdir($upload_path, 0755, true);
    }
    
    $filename = uniqid() . '_' . time() . '.' . $file_extension;
    $target_path = $upload_path . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return 'uploads/' . $upload_dir . '/' . $filename;
    } else {
        throw new Exception('Failed to upload file');
    }
}

/**
 * Format currency
 */
function format_currency($amount, $currency = 'INR') {
    return '₹' . number_format($amount, 2);
}

/**
 * Format date
 */
function format_date($date, $format = 'd M Y') {
    return date($format, strtotime($date));
}

/**
 * Get time ago
 */
function time_ago($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' min ago';
    if ($time < 86400) return floor($time/3600) . ' hr ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    return floor($time/31536000) . ' years ago';
}

/**
 * Send notification
 */
function send_notification($user_id, $title, $message, $type = 'system', $related_id = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, related_id) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$user_id, $title, $message, $type, $related_id]);
    } catch (Exception $e) {
        error_log("Error sending notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get system setting
 */
function get_setting($key, $default = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        error_log("Error getting setting: " . $e->getMessage());
        return $default;
    }
}

/**
 * Set system setting
 */
function set_setting($key, $value, $description = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?, description = COALESCE(?, description)");
        return $stmt->execute([$key, $value, $description, $value, $description]);
    } catch (Exception $e) {
        error_log("Error setting setting: " . $e->getMessage());
        return false;
    }
}

/**
 * Log activity
 */
function log_activity($user_id, $action, $details = null) {
    // Create activity_logs table if it doesn't exist
    global $pdo;
    
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS activity_logs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT,
                action VARCHAR(255),
                details TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([
            $user_id,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Error logging activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Check remember me token
 */
function check_remember_token() {
    global $pdo;
    
    if (!isset($_COOKIE['remember_token'])) {
        return false;
    }
    
    try {
        $token_hash = hash('sha256', $_COOKIE['remember_token']);
        $stmt = $pdo->prepare("SELECT user_id FROM remember_tokens WHERE token = ? AND expires > NOW()");
        $stmt->execute([$token_hash]);
        $result = $stmt->fetch();
        
        if ($result) {
            $_SESSION['user_id'] = $result['user_id'];
            $user = current_user();
            if ($user) {
                $_SESSION['user_type'] = $user['user_type'];
                return true;
            }
        }
    } catch (Exception $e) {
        error_log("Error checking remember token: " . $e->getMessage());
    }
    
    return false;
}

/**
 * Clean expired tokens
 */
function clean_expired_tokens() {
    global $pdo;
    
    try {
        $pdo->exec("DELETE FROM remember_tokens WHERE expires < NOW()");
        $pdo->exec("DELETE FROM password_resets WHERE expires < NOW() OR used = 1");
    } catch (Exception $e) {
        error_log("Error cleaning expired tokens: " . $e->getMessage());
    }
}

/**
 * CSRF Protection
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Rate limiting
 */
function check_rate_limit($action, $limit = 10, $window = 3600) {
    $key = $action . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $cache_file = sys_get_temp_dir() . '/rate_limit_' . md5($key);
    
    $current_time = time();
    $attempts = [];
    
    if (file_exists($cache_file)) {
        $attempts = json_decode(file_get_contents($cache_file), true) ?: [];
    }
    
    // Remove old attempts
    $attempts = array_filter($attempts, function($timestamp) use ($current_time, $window) {
        return ($current_time - $timestamp) < $window;
    });
    
    if (count($attempts) >= $limit) {
        return false;
    }
    
    $attempts[] = $current_time;
    file_put_contents($cache_file, json_encode($attempts));
    
    return true;
}

// Auto-login with remember token if user is not logged in
if (!is_logged_in() && isset($_COOKIE['remember_token'])) {
    check_remember_token();
}

// Clean expired tokens periodically (1% chance on each request)
if (rand(1, 100) === 1) {
    clean_expired_tokens();
}

// Regenerate session ID periodically for security
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

?>