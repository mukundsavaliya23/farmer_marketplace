<?php
// Simple test to verify API connection - FIXED VERSION
$api_key = 'AIzaSyBw8mZTeKBqKuaOLmem7CQpJ0ZQ0cVoAsU';
$api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';

$payload = [
    'contents' => [
        [
            'parts' => [
                ['text' => 'Hello! Please reply with just "API working correctly" to test the connection.']
            ]
        ]
    ],
    'generationConfig' => [
        'maxOutputTokens' => 100
    ]
];

$headers = [
    "Content-Type: application/json",
    "X-Goog-Api-Key: " . $api_key
];

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

echo "<h1>🤖 Simple API Test</h1>";
echo "<p><strong>Testing:</strong> " . $api_url . "</p>";
echo "<p><strong>API Key:</strong> " . substr($api_key, 0, 20) . "...</p>";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo "<p style='color: red;'><strong>❌ cURL Error:</strong> " . curl_error($ch) . "</p>";
} else {
    echo "<p><strong>HTTP Code:</strong> " . $httpCode . "</p>";
    
    $data = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        // DEBUG: Show full response structure
        echo "<h3>Full API Response:</h3>";
        echo "<pre style='background: #f8f9fa; padding: 1rem; border-radius: 5px; overflow-x: auto;'>";
        print_r($data);
        echo "</pre>";
        
        // SAFE: Extract text with proper defensive checking
        if (isset($data['candidates']) && is_array($data['candidates']) && !empty($data['candidates'])) {
            $candidate = $data['candidates'][0];
            
            if (isset($candidate) && is_array($candidate)) {
                if (isset($candidate['content']) && is_array($candidate['content'])) {
                    if (isset($candidate['content']['parts']) && is_array($candidate['content']['parts']) && !empty($candidate['content']['parts'])) {
                        if (isset($candidate['content']['parts'][0]['text'])) {
                            $text = trim($candidate['content']['parts']['text']);
                            
                            if (!empty($text)) {
                                echo "<div style='background: #d4edda; padding: 1rem; border-radius: 5px; margin: 1rem 0;'>";
                                echo "<strong>✅ SUCCESS!</strong><br>";
                                echo "<strong>Response:</strong> " . htmlspecialchars($text) . "<br>";
                                echo "<strong>Model:</strong> " . (isset($data['modelVersion']) ? $data['modelVersion'] : 'N/A') . "<br>";
                                echo "<strong>Finish Reason:</strong> " . (isset($candidate['finishReason']) ? $candidate['finishReason'] : 'N/A') . "<br>";
                                echo "<strong>Tokens Used:</strong> " . (isset($data['usageMetadata']['totalTokenCount']) ? $data['usageMetadata']['totalTokenCount'] : 'N/A');
                                echo "</div>";
                            } else {
                                echo "<p style='color: orange;'>⚠️ Response text is empty</p>";
                            }
                        } else {
                            echo "<p style='color: red;'>❌ No 'text' key in parts[0]</p>";
                        }
                    } else {
                        echo "<p style='color: red;'>❌ No 'parts' array in content or parts is empty</p>";
                    }
                } else {
                    echo "<p style='color: red;'>❌ No 'content' key in candidate</p>";
                }
            } else {
                echo "<p style='color: red;'>❌ candidate is not valid</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ No 'candidates' array or candidates is empty</p>";
        }
        
        // Additional debugging info
        echo "<h3>🔍 Debug Info:</h3>";
        echo "<ul>";
        echo "<li>Candidates exist: " . (isset($data['candidates']) ? 'YES' : 'NO') . "</li>";
        echo "<li>Candidates count: " . (isset($data['candidates']) && is_array($data['candidates']) ? count($data['candidates']) : '0') . "</li>";
        echo "<li>First candidate exists: " . (isset($data['candidates'][0]) ? 'YES' : 'NO') . "</li>";
        echo "<li>Content exists: " . (isset($data['candidates']) && isset($data['candidates']['content']) ? 'YES' : 'NO') . "</li>";
        echo "<li>Parts exist: " . (isset($data['candidates']['content']['parts']) ? 'YES' : 'NO') . "</li>";
        echo "<li>Parts count: " . (isset($data['candidates']['content']['parts']) && is_array($data['candidates']['content']['parts']) ? count($data['candidates']['content']['parts']) : '0') . "</li>";
        echo "<li>Text exists: " . (isset($data['candidates']['content']['parts']['text']) ? 'YES' : 'NO') . "</li>";
        echo "</ul>";
        
    } else {
        echo "<p style='color: red;'>❌ JSON decode error: " . json_last_error_msg() . "</p>";
        echo "<p>Raw response:</p><pre>" . htmlspecialchars($response) . "</pre>";
    }
}

curl_close($ch);
?>