<?php
require_once __DIR__ . '/../config/config.php';

class GeminiClient {
    private $apiKey;
    private $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';
    
    public function __construct() {
        $this->apiKey = GEMINI_API_KEY;
    }
    
    public function generateResponse($message, $chatHistory = []) {
        $startTime = microtime(true);
        
        try {
            // Build conversation context
            $conversationContext = $this->buildContext($message, $chatHistory);
            
            // Use Gemini 2.0 Flash model
            $model = 'gemini-2.0-flash';
            $url = $this->baseUrl . '/' . $model . ':generateContent';
            
            // Build request payload
            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $conversationContext
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'topK' => 40,
                    'topP' => 0.95,
                    'maxOutputTokens' => 2048
                ],
                'safetySettings' => [
                    [
                        'category' => 'HARM_CATEGORY_HARASSMENT',
                        'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                    ],
                    [
                        'category' => 'HARM_CATEGORY_HATE_SPEECH',
                        'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                    ],
                    [
                        'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                        'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                    ],
                    [
                        'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                        'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                    ]
                ]
            ];
            
            // Make API request
            $response = $this->makeApiCall($url, $payload);
            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000);
            
            // Parse response with safe array access
            $generatedText = $this->parseResponse($response);
            
            return [
                'success' => true,
                'message' => $generatedText,
                'response_time' => $responseTime,
                'model' => $model,
                'usage' => isset($response['usageMetadata']) ? $response['usageMetadata'] : null
            ];
            
        } catch (Exception $e) {
            error_log("Gemini API Error: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $this->getErrorMessage($e->getMessage()),
                'error' => $e->getMessage(),
                'response_time' => 0
            ];
        }
    }
    
    private function buildContext($message, $chatHistory) {
        $context = "You are FarmBot Pro, an intelligent AI assistant for FarmConnect Pro agricultural marketplace. ";
        $context .= "You are knowledgeable, helpful, and professional. You specialize in farming, agriculture, ";
        $context .= "marketplace guidance, and general assistance. Always provide accurate, helpful, and well-formatted responses.\n\n";
        
        // Add recent chat history for context
        if (!empty($chatHistory)) {
            $context .= "Previous conversation:\n";
            $recentHistory = array_slice($chatHistory, -3);
            
            foreach ($recentHistory as $msg) {
                $role = $msg['message_type'] === 'user' ? 'User' : 'FarmBot Pro';
                $context .= "{$role}: {$msg['message']}\n";
            }
            $context .= "\n";
        }
        
        $context .= "User: {$message}\n\nFarmBot Pro:";
        
        return $context;
    }
    
    private function makeApiCall($url, $payload) {
        $ch = curl_init();
        
        $headers = [
            'Content-Type: application/json',
            'X-Goog-Api-Key: ' . $this->apiKey
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'FarmBotPro/1.0'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $curlError = curl_error($ch);
            curl_close($ch);
            throw new Exception("Connection Error: {$curlError}");
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("HTTP Error {$httpCode}: " . $this->getHttpErrorMessage($httpCode));
        }
        
        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response: " . json_last_error_msg());
        }
        
        return $decodedResponse;
    }
    
    private function parseResponse($response) {
        // Check for API errors first
        if (isset($response['error'])) {
            $errorMsg = isset($response['error']['message']) ? $response['error']['message'] : 'Unknown API error';
            throw new Exception("API Error: {$errorMsg}");
        }
        
        // SAFE: Check candidates exist and is valid array
        if (!isset($response['candidates']) || 
            !is_array($response['candidates']) || 
            empty($response['candidates'])) {
            throw new Exception("No response candidates generated");
        }
        
        $candidate = $response['candidates'][0];
        
        // SAFE: Check if candidate is valid array
        if (!isset($candidate) || !is_array($candidate)) {
            throw new Exception("Invalid candidate structure");
        }
        
        // Check finish reason
        if (isset($candidate['finishReason'])) {
            $finishReason = $candidate['finishReason'];
            if ($finishReason === 'SAFETY') {
                throw new Exception("Response blocked by safety filters");
            } elseif ($finishReason === 'RECITATION') {
                throw new Exception("Response blocked due to recitation");
            } elseif ($finishReason === 'OTHER') {
                throw new Exception("Response blocked for other reasons");
            }
            // STOP is normal and expected - continue processing
        }
        
        // SAFE: Check content structure step by step
        if (!isset($candidate['content']) || !is_array($candidate['content'])) {
            throw new Exception("No content in response candidate");
        }
        
        if (!isset($candidate['content']['parts']) || 
            !is_array($candidate['content']['parts']) || 
            empty($candidate['content']['parts'])) {
            throw new Exception("No content parts in response");
        }
        
        $part = $candidate['content']['parts'][0];
        if (!isset($part) || !is_array($part)) {
            throw new Exception("Invalid content part structure");
        }
        
        if (!isset($part['text'])) {
            throw new Exception("No text content in response part");
        }
        
        $text = $part['text'];
        
        // Check for empty text (even just whitespace)
        if (empty(trim($text))) {
            throw new Exception("Response text is empty");
        }
        
        return $this->formatResponse(trim($text));
    }
    
    private function formatResponse($text) {
        // Clean up the response
        $text = trim($text);
        
        // Remove bot name prefix if it exists
        $text = preg_replace('/^(FarmBot Pro|Assistant):\s*/i', '', $text);
        
        // Format agricultural terms properly
        $agriculturalReplacements = [
            '/\bph\b/i' => 'pH',
            '/\bnpk\b/i' => 'N-P-K',
            '/\burea\b/i' => 'Urea',
            '/\bdap\b/i' => 'DAP (Diammonium Phosphate)',
            '/\bppm\b/i' => 'PPM',
            '/\bec\b/i' => 'EC (Electrical Conductivity)'
        ];
        
        foreach ($agriculturalReplacements as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }
        
        // Format lists and bullet points
        $text = preg_replace('/^\* /m', '• ', $text);
        $text = preg_replace('/^\d+\. /m', '• ', $text);
        
        // Format emphasis
        $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
        
        return $text;
    }
    
    private function getHttpErrorMessage($httpCode) {
        $messages = [
            400 => 'Bad Request - Invalid request format',
            401 => 'Unauthorized - Invalid API key',
            403 => 'Forbidden - API access denied',
            404 => 'Not Found - Invalid model or endpoint',
            429 => 'Too Many Requests - Rate limit exceeded',
            500 => 'Internal Server Error - Gemini service error',
            502 => 'Bad Gateway - Service temporarily unavailable',
            503 => 'Service Unavailable - Service overloaded'
        ];
        
        return isset($messages[$httpCode]) ? $messages[$httpCode] : 'Unknown HTTP error';
    }
    
    private function getErrorMessage($error) {
        if (strpos($error, 'rate limit') !== false || strpos($error, '429') !== false) {
            return "I'm receiving many questions right now. Please wait a moment and try again.";
        } elseif (strpos($error, 'safety') !== false || strpos($error, 'blocked') !== false) {
            return "I can't provide a response to that question. Please ask about farming, agriculture, or marketplace topics.";
        } elseif (strpos($error, 'API key') !== false || strpos($error, '401') !== false) {
            return "I'm having trouble connecting to my knowledge base. Please contact support.";
        } elseif (strpos($error, 'service') !== false || strpos($error, '500') !== false) {
            return "My AI service is temporarily unavailable. Please try again in a few moments.";
        } else {
            return "I apologize, but I encountered a technical issue. Please try again.";
        }
    }
    
    public function testConnection() {
        try {
            $testUrl = $this->baseUrl . '/gemini-2.0-flash:generateContent';
            
            $testPayload = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => 'Hello! Please respond with exactly: "FarmBot Pro connection test successful"']
                        ]
                    ]
                ],
                'generationConfig' => [
                    'maxOutputTokens' => 100
                ]
            ];
            
            $response = $this->makeApiCall($testUrl, $testPayload);
            $text = $this->parseResponse($response);
            
            return [
                'success' => true,
                'message' => 'Connection successful',
                'test_response' => $text,
                'model_version' => isset($response['modelVersion']) ? $response['modelVersion'] : 'unknown',
                'usage' => isset($response['usageMetadata']) ? $response['usageMetadata'] : null
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'debug_error' => $e->getMessage()
            ];
        }
    }
}
?>