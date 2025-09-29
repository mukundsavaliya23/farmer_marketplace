<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response_json(['success' => false, 'message' => 'Method not allowed'], 405);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$crop_name = $data['crop_name'] ?? '';
$location = $data['location'] ?? '';

if (empty($crop_name) || empty($location)) {
    response_json(['success' => false, 'message' => 'Missing required parameters: crop_name and location'], 400);
    exit;
}

// For demo, fetch last 30 days price history from database
try {
    global $pdo;
    $stmt = $pdo->prepare("SELECT price, date_recorded FROM price_history WHERE crop_name = ? AND market_location = ? ORDER BY date_recorded DESC LIMIT 30");
    $stmt->execute([$crop_name, $location]);
    $historical_prices = $stmt->fetchAll();

    if (count($historical_prices) < 5) {
        // If insufficient data, provide fallback prediction
        $current_price = 25.0; // default price
        $change_pct = rand(-5, 8);
        $predicted_price = $current_price * (1 + $change_pct / 100);

        $response = [
            'success' => true,
            'prediction' => [
                'current_price' => $current_price,
                'predicted_price' => round($predicted_price, 2),
                'change' => $change_pct,
                'trend' => ($change_pct >= 0) ? 'up' : 'down',
                'confidence' => 60,
                'recommendation' => ($change_pct >= 0) ? 'Good time to sell.' : 'Consider selling soon.',
                'factors' => ['Limited data', 'Market volatility']
            ]
        ];

        response_json($response);
        exit;
    }

    // Simple linear regression for prediction demo
    $prices = array_column($historical_prices, 'price');
    $dates = array_column($historical_prices, 'date_recorded');

    $n = count($prices);
    $x = range(1, $n);

    $sum_x = array_sum($x);
    $sum_y = array_sum($prices);
    $sum_xy = 0;
    $sum_x2 = 0;

    for ($i = 0; $i < $n; $i++) {
        $sum_xy += $x[$i] * $prices[$i];
        $sum_x2 += $x[$i] * $x[$i];
    }

    $slope = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_x2 - $sum_x * $sum_x);
    $intercept = ($sum_y - $slope * $sum_x) / $n;

    $current_price = $prices[0];
    $predicted_price = $intercept + $slope * ($n + 30); // prediction for 30 days in future

    $change_pct = (($predicted_price - $current_price) / $current_price) * 100;

    $confidence = min(90, max(60, 70 + abs($slope) * 20));

    $recommendation = ($change_pct > 5) ?
        'Wait to sell - prices are expected to rise.' :
        (($change_pct > 0) ? 'Good time to sell.' : 'Consider selling soon.');

    $factors = ['Seasonal trends', 'Market demand', 'Supply fluctuations'];

    $response = [
        'success' => true,
        'prediction' => [
            'current_price' => round($current_price,2),
            'predicted_price' => round($predicted_price,2),
            'change' => round($change_pct,2),
            'trend' => ($change_pct >= 0) ? 'up' : 'down',
            'confidence' => round($confidence),
            'recommendation' => $recommendation,
            'factors' => $factors
        ]
    ];

    response_json($response);
    exit;
} catch (Exception $e) {
    response_json(['success' => false, 'message' => 'Failed to generate prediction'], 500);
    exit;
}
?>