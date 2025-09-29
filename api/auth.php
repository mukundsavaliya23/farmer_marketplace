 <?php



session_start();

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // Clear all session variables
    $_SESSION = array();
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Clear remember me cookie if it exists
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    // Redirect to home page
    header('Location: ../index.php');
    exit;
}

// If not logout action, redirect to home
header('Location: ../index.php');
exit;



require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'signup':
        handleSignup();
        break;
    case 'logout':
        handleLogout();
        break;
    default:
        response_json(['success' => false, 'message' => 'Invalid action'], 400);
}

function handleLogin() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['email'], $data['password'], $data['user_type'])) {
        response_json(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND user_type = ? AND is_active = 1');
        $stmt->execute([$data['email'], $data['user_type']]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($data['password'], $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = $user['user_type'];
            
            $token = generate_jwt($user);
            unset($user['password']);
            
            response_json([
                'success' => true,
                'message' => 'Login successful',
                'token' => $token,
                'user' => $user
            ]);
        } else {
            response_json(['success' => false, 'message' => 'Invalid credentials'], 401);
        }
    } catch (Exception $e) {
        response_json(['success' => false, 'message' => 'Database error'], 500);
    }
}

function handleSignup() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required_fields = ['username', 'email', 'password', 'user_type', 'full_name', 'phone'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            response_json(['success' => false, 'message' => "Missing field: $field"]);
            return;
        }
    }
    
    // Validate email
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        response_json(['success' => false, 'message' => 'Invalid email format']);
        return;
    }
    
    // Validate password strength
    if (strlen($data['password']) < 6) {
        response_json(['success' => false, 'message' => 'Password must be at least 6 characters']);
        return;
    }
    
    try {
        // Check if user already exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
        $stmt->execute([$data['email'], $data['username']]);
        
        if ($stmt->rowCount() > 0) {
            response_json(['success' => false, 'message' => 'User already exists']);
            return;
        }
        
        // Hash password
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $pdo->prepare('
            INSERT INTO users (username, email, password, user_type, full_name, phone, address, location) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        
        $success = $stmt->execute([
            $data['username'],
            $data['email'],
            $hashed_password,
            $data['user_type'],
            $data['full_name'],
            $data['phone'],
            $data['address'] ?? '',
            $data['location'] ?? ''
        ]);
        
        if ($success) {
            response_json(['success' => true, 'message' => 'Account created successfully']);
        } else {
            response_json(['success' => false, 'message' => 'Failed to create account'], 500);
        }
    } catch (Exception $e) {
        response_json(['success' => false, 'message' => 'Database error'], 500);
    }
}

function handleLogout() {
    session_destroy();
    response_json(['success' => true, 'message' => 'Logged out successfully']);
}
?>