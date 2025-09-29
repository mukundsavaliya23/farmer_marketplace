<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/GeminiClient.php';
try {
    // Initialize services
    $db = new Database();
    $gemini = new GeminiClient();
    
    // Handle test endpoint
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['test'])) {
        $testResult = $gemini->testConnection();
        echo json_encode([
            'test_result' => $testResult,
            'available_models' => $gemini->getAvailableModels(),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests are allowed');
    }
    
    // Get and validate input
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }
    
    if (empty($input['message']) || empty($input['session_id'])) {
        throw new Exception('Message and session_id are required');
    }
    
    $message = trim($input['message']);
    $sessionId = $input['session_id'];
    
    // Validate message
    if (strlen($message) > MAX_MESSAGE_LENGTH) {
        throw new Exception("Message too long. Maximum " . MAX_MESSAGE_LENGTH . " characters.");
    }
    
    if (strlen($message) < 1) {
        throw new Exception('Message cannot be empty');
    }
    
    // Rate limiting
    $messageCount = $db->getMessageCount($sessionId);
    if ($messageCount >= MAX_MESSAGES_PER_SESSION) {
        throw new Exception(RATE_LIMIT_MESSAGE);
    }
    
    // Create/update session
    $userIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $db->createSession($sessionId, $userIp, $userAgent);
    
    // Save user message
    $db->saveMessage($sessionId, 'user', $message);
    
    // Get chat history
    $chatHistory = $db->getChatHistory($sessionId, 5);
    
    // Generate AI response
    $aiResponse = $gemini->generateResponse($message, $chatHistory);
    
    if ($aiResponse['success']) {
        // Save bot response
        $db->saveMessage($sessionId, 'bot', $aiResponse['message'], $aiResponse['response_time']);
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => $aiResponse['message'],
            'response_time' => $aiResponse['response_time'],
            'model' => $aiResponse['model'],
            'session_id' => $sessionId,
            'message_count' => $messageCount + 2,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        throw new Exception($aiResponse['message']);
    }
    
} catch (Exception $e) {
    // Detailed error logging
    $errorData = [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'input' => $rawInput ?? 'No input',
        'session_id' => $input['session_id'] ?? 'unknown',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    error_log("Chat API Error: " . json_encode($errorData));
    
    // Return user-friendly error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'debug' => (isset($_GET['debug']) ? $errorData : null)
    ]);
}
?>