<?php
// Test file to verify the complete chatbot functionality
require_once __DIR__ . '/api/GeminiClient.php';
require_once __DIR__ . '/config/config.php';

echo "<h1>🤖 FarmBot Pro Complete Test</h1>";

$gemini = new GeminiClient();

echo "<h2>1. Testing Connection</h2>";
$testResult = $gemini->testConnection();
echo "<div style='background: " . ($testResult['success'] ? '#d4edda' : '#f8d7da') . "; padding: 1rem; border-radius: 5px; margin: 1rem 0;'>";
echo "<strong>" . ($testResult['success'] ? '✅ SUCCESS' : '❌ FAILED') . "</strong><br>";
echo "Message: " . htmlspecialchars($testResult['message']) . "<br>";
if (isset($testResult['test_response'])) {
    echo "Test Response: " . htmlspecialchars($testResult['test_response']) . "<br>";
}
if (isset($testResult['model_version'])) {
    echo "Model: " . htmlspecialchars($testResult['model_version']) . "<br>";
}
echo "</div>";

echo "<h2>2. Testing Farming Question</h2>";
$farmingResponse = $gemini->generateResponse("What are the best crops to grow in winter season?", []);
echo "<div style='background: " . ($farmingResponse['success'] ? '#d4edda' : '#f8d7da') . "; padding: 1rem; border-radius: 5px; margin: 1rem 0;'>";
echo "<strong>" . ($farmingResponse['success'] ? '✅ SUCCESS' : '❌ FAILED') . "</strong><br>";
if ($farmingResponse['success']) {
    echo "Response: " . $farmingResponse['message'] . "<br>";
    echo "Response Time: " . $farmingResponse['response_time'] . "ms<br>";
    echo "Model: " . $farmingResponse['model'] . "<br>";
} else {
    echo "Error: " . htmlspecialchars($farmingResponse['message']) . "<br>";
    if (isset($farmingResponse['error'])) {
        echo "Technical Error: " . htmlspecialchars($farmingResponse['error']) . "<br>";
    }
}
echo "</div>";

echo "<h2>3. Testing Agricultural Question</h2>";
$agriResponse = $gemini->generateResponse("How do I improve soil pH naturally?", []);
echo "<div style='background: " . ($agriResponse['success'] ? '#d4edda' : '#f8d7da') . "; padding: 1rem; border-radius: 5px; margin: 1rem 0;'>";
echo "<strong>" . ($agriResponse['success'] ? '✅ SUCCESS' : '❌ FAILED') . "</strong><br>";
if ($agriResponse['success']) {
    echo "Response: " . $agriResponse['message'] . "<br>";
    echo "Response Time: " . $agriResponse['response_time'] . "ms<br>";
} else {
    echo "Error: " . htmlspecialchars($agriResponse['message']) . "<br>";
}
echo "</div>";

echo "<h2>4. Testing Conversation Context</h2>";
$contextHistory = [
    ['message_type' => 'user', 'message' => 'I am a new farmer'],
    ['message_type' => 'bot', 'message' => 'Welcome! I can help you with farming guidance.']
];
$contextResponse = $gemini->generateResponse("What crops should I start with?", $contextHistory);
echo "<div style='background: " . ($contextResponse['success'] ? '#d4edda' : '#f8d7da') . "; padding: 1rem; border-radius: 5px; margin: 1rem 0;'>";
echo "<strong>" . ($contextResponse['success'] ? '✅ SUCCESS' : '❌ FAILED') . "</strong><br>";
if ($contextResponse['success']) {
    echo "Response: " . $contextResponse['message'] . "<br>";
    echo "Response Time: " . $contextResponse['response_time'] . "ms<br>";
} else {
    echo "Error: " . htmlspecialchars($contextResponse['message']) . "<br>";
}
echo "</div>";

echo "<h2>5. API Configuration</h2>";
echo "<p><strong>API Key:</strong> " . substr(GEMINI_API_KEY, 0, 20) . "..." . substr(GEMINI_API_KEY, -10) . "</p>";
echo "<p><strong>Model:</strong> gemini-2.0-flash</p>";
echo "<p><strong>Max Message Length:</strong> " . MAX_MESSAGE_LENGTH . " characters</p>";

echo "<h2>6. Ready to Use!</h2>";
echo "<p>If all tests above show ✅ SUCCESS, your chatbot is ready!</p>";
echo "<p><a href='index.html' style='background: #10B981; color: white; padding: 0.75rem 1.5rem; text-decoration: none; border-radius: 8px;'>🚀 Open Chatbot Interface</a></p>";
?>