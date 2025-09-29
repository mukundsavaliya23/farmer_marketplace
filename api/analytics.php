<?php
require_once '../config.php';

// Set content type to JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache');

try {
    $data = [];
    
    // Monthly data for last 12 months
    $labels = [];
    $sales = [];
    $orders = [];
    $users = [];
    
    for ($i = 11; $i >= 0; $i--) {
        $labels[] = date('M Y', strtotime("-{$i} months"));
        $month = date('n', strtotime("-{$i} months"));
        $year = date('Y', strtotime("-{$i} months"));
        
        // Revenue for the month
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(total_amount), 0) as revenue 
            FROM orders 
            WHERE MONTH(order_date) = ? AND YEAR(order_date) = ? 
            AND status NOT IN ('cancelled', 'pending')
        ");
        $stmt->execute([$month, $year]);
        $result = $stmt->fetch();
        $sales[] = (float) $result['revenue'];
        
        // Orders count for the month
        $stmt2 = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM orders 
            WHERE MONTH(order_date) = ? AND YEAR(order_date) = ?
        ");
        $stmt2->execute([$month, $year]);
        $result2 = $stmt2->fetch();
        $orders[] = (int) $result2['count'];
        
        // New users for the month
        $stmt3 = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM users 
            WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?
        ");
        $stmt3->execute([$month, $year]);
        $result3 = $stmt3->fetch();
        $users[] = (int) $result3['count'];
    }
    
    $data['monthly'] = [
        'labels' => $labels,
        'sales' => $sales,
        'orders' => $orders,
        'users' => $users
    ];
    
    // Top performing farmers
    $stmt = $pdo->query("
        SELECT u.full_name, u.location,
               COUNT(o.id) as order_count,
               COALESCE(SUM(o.total_amount), 0) as total_revenue
        FROM users u
        LEFT JOIN orders o ON u.id = o.farmer_id 
            AND o.status NOT IN ('cancelled', 'pending')
        WHERE u.user_type = 'farmer'
        GROUP BY u.id
        ORDER BY total_revenue DESC
        LIMIT 10
    ");
    $data['topFarmers'] = $stmt->fetchAll();
    
    // Category performance
    $stmt = $pdo->query("
        SELECT category,
               COUNT(id) as product_count,
               COALESCE(SUM(price_per_unit * quantity), 0) as revenue
        FROM products
        GROUP BY category
        ORDER BY revenue DESC
    ");
    $data['categories'] = $stmt->fetchAll();
    
    // Order status distribution
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count
        FROM orders
        GROUP BY status
        ORDER BY count DESC
    ");
    $data['orderStatus'] = $stmt->fetchAll();
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
    
} catch (Exception $e) {
    // Log error and return JSON error response
    error_log("Analytics API Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch analytics data',
        'error' => $e->getMessage()
    ]);
}

// Ensure no extra output
exit();
?>