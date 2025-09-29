<?php
// Chatbot Configuration
define('GEMINI_API_KEY', 'AIzaSyBw8mZTeKBqKuaOLmem7CQpJ0ZQ0cVoAsU');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'chatbot_system');
define('DB_USER', 'root');
define('DB_PASS', '');

// Chatbot Settings
define('MAX_MESSAGE_LENGTH', 1000);
define('MAX_MESSAGES_PER_SESSION', 100);
define('SESSION_TIMEOUT', 3600);

// Bot Settings
define('BOT_NAME', 'FarmBot Pro');
define('ERROR_MESSAGE', 'I apologize, but I encountered an issue. Please try again.');
define('RATE_LIMIT_MESSAGE', 'Please slow down! Wait a moment before sending another message.');
?>