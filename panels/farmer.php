<?php
require_once '../config.php';

if (!is_logged_in() || current_user()['user_type'] !== 'farmer') {
    redirect('../index.php');
}

$page_title = 'Farmer Dashboard';
$user = current_user();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_farmer_stats':
            echo json_encode(getFarmerStats($pdo, $user['id']));
            exit;
        case 'get_my_products':
            echo json_encode(getMyProducts($pdo, $user['id']));
            exit;
        case 'add_product':
            echo json_encode(addProduct($pdo, $user['id'], $_POST, $_FILES));
            exit;
        case 'update_product':
            echo json_encode(updateProduct($pdo, $_POST, $_FILES));
            exit;
        case 'delete_product':
            echo json_encode(deleteProduct($pdo, $_POST['product_id']));
            exit;
        case 'get_my_orders':
            echo json_encode(getMyOrders($pdo, $user['id']));
            exit;
        case 'update_order_status':
            echo json_encode(updateOrderStatus($pdo, $_POST['order_id'], $_POST['status']));
            exit;
        case 'get_price_prediction':
            echo json_encode(getPricePrediction($_POST['crop_name'], $_POST['location']));
            exit;
        case 'get_weather_advice':
            echo json_encode(getWeatherAdvice($_POST['location']));
            exit;
        case 'get_crop_recommendation':
            echo json_encode(getCropRecommendation($_POST['soil_type'], $_POST['season']));
            exit;
        case 'chat_with_ai':
            echo json_encode(chatWithAI($_POST['message']));
            exit;
        case 'update_profile':
            echo json_encode(updateFarmerProfile($pdo, $user['id'], $_POST));
            exit;
        case 'get_product_for_edit':
            echo json_encode(getProductForEdit($pdo, $_POST['product_id']));
            exit;
    }
}

function getFarmerStats($pdo, $farmerId) {
    try {
        $stats = [];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE farmer_id = ?");
        $stmt->execute([$farmerId]);
        $stats['total_products'] = $stmt->fetch()['count'];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE farmer_id = ? AND status = 'available'");
        $stmt->execute([$farmerId]);
        $stats['active_products'] = $stmt->fetch()['count'];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE farmer_id = ?");
        $stmt->execute([$farmerId]);
        $stats['total_orders'] = $stmt->fetch()['count'];
        
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as earnings FROM orders WHERE farmer_id = ? AND status != 'cancelled'");
        $stmt->execute([$farmerId]);
        $stats['total_earnings'] = $stmt->fetch()['earnings'];
        
        return ['success' => true, 'data' => $stats];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getMyProducts($pdo, $farmerId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE farmer_id = ? ORDER BY created_at DESC");
        $stmt->execute([$farmerId]);
        $products = $stmt->fetchAll();
        return ['success' => true, 'data' => $products];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function addProduct($pdo, $farmerId, $data, $files) {
    try {
        // Handle file upload
        $imagePath = null;
        if (isset($files['product_image']) && $files['product_image']['error'] === 0) {
            $imagePath = uploadProductImage($files['product_image']);
        }
        
        $stmt = $pdo->prepare("INSERT INTO products (farmer_id, name, description, category, price_per_unit, unit, quantity, organic, quality_grade, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $farmerId,
            $data['name'],
            $data['description'],
            $data['category'],
            $data['price_per_unit'],
            $data['unit'],
            $data['quantity'],
            isset($data['organic']) ? 1 : 0,
            $data['quality_grade'] ?? 'B',
            $imagePath
        ]);
        return ['success' => true, 'message' => 'Product added successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function updateProduct($pdo, $data, $files) {
    try {
        $imagePath = null;
        if (isset($files['product_image']) && $files['product_image']['error'] === 0) {
            $imagePath = uploadProductImage($files['product_image']);
        }
        
        if ($imagePath) {
            $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, category = ?, price_per_unit = ?, unit = ?, quantity = ?, organic = ?, quality_grade = ?, image_path = ? WHERE id = ?");
            $stmt->execute([
                $data['name'],
                $data['description'],
                $data['category'],
                $data['price_per_unit'],
                $data['unit'],
                $data['quantity'],
                isset($data['organic']) ? 1 : 0,
                $data['quality_grade'],
                $imagePath,
                $data['product_id']
            ]);
        } else {
            $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, category = ?, price_per_unit = ?, unit = ?, quantity = ?, organic = ?, quality_grade = ? WHERE id = ?");
            $stmt->execute([
                $data['name'],
                $data['description'],
                $data['category'],
                $data['price_per_unit'],
                $data['unit'],
                $data['quantity'],
                isset($data['organic']) ? 1 : 0,
                $data['quality_grade'],
                $data['product_id']
            ]);
        }
        
        return ['success' => true, 'message' => 'Product updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function deleteProduct($pdo, $productId) {
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        return ['success' => true, 'message' => 'Product deleted successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getMyOrders($pdo, $farmerId) {
    try {
        $stmt = $pdo->prepare("
            SELECT o.*, p.name as product_name, u.full_name as buyer_name, u.phone as buyer_phone
            FROM orders o
            JOIN products p ON o.product_id = p.id
            JOIN users u ON o.buyer_id = u.id
            WHERE o.farmer_id = ?
            ORDER BY o.order_date DESC
        ");
        $stmt->execute([$farmerId]);
        $orders = $stmt->fetchAll();
        return ['success' => true, 'data' => $orders];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function updateOrderStatus($pdo, $orderId, $status) {
    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $orderId]);
        return ['success' => true, 'message' => 'Order status updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getProductForEdit($pdo, $productId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        return ['success' => true, 'data' => $product];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function uploadProductImage($file) {
    $uploadDir = '../uploads/products/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (!in_array($fileExtension, $allowedExtensions)) {
        throw new Exception('Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.');
    }
    
    $fileName = uniqid() . '_' . time() . '.' . $fileExtension;
    $targetPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return 'uploads/products/' . $fileName;
    } else {
        throw new Exception('Failed to upload image');
    }
}

function getPricePrediction($cropName, $location) {
    // Enhanced AI Price prediction logic
    $predictions = [
        'tomato' => ['current' => 45, 'next_week' => 48, 'next_month' => 52, 'trend' => 'up', 'confidence' => 92],
        'rice' => ['current' => 85, 'next_week' => 82, 'next_month' => 78, 'trend' => 'down', 'confidence' => 87],
        'onion' => ['current' => 35, 'next_week' => 38, 'next_month' => 42, 'trend' => 'up', 'confidence' => 89],
        'wheat' => ['current' => 55, 'next_week' => 57, 'next_month' => 59, 'trend' => 'up', 'confidence' => 94],
        'potato' => ['current' => 28, 'next_week' => 30, 'next_month' => 33, 'trend' => 'up', 'confidence' => 88]
    ];
    
    $crop = strtolower($cropName);
    if (isset($predictions[$crop])) {
        return ['success' => true, 'data' => $predictions[$crop]];
    }
    
    // Generate random but realistic prediction
    $currentPrice = rand(25, 95);
    $weekChange = rand(-10, 15);
    $monthChange = rand(-20, 25);
    
    return ['success' => true, 'data' => [
        'current' => $currentPrice,
        'next_week' => max(15, $currentPrice + $weekChange),
        'next_month' => max(15, $currentPrice + $monthChange),
        'trend' => $weekChange > 0 ? 'up' : ($weekChange < 0 ? 'down' : 'stable'),
        'confidence' => rand(75, 95)
    ]];
}

function getWeatherAdvice($location) {
    $adviceOptions = [
        "🌤️ Clear skies expected for the next 3 days. Perfect time for harvesting and field work!",
        "🌧️ Light rainfall predicted. Good for newly planted crops. Ensure proper drainage in fields.",
        "☀️ Hot and dry weather coming. Increase irrigation frequency and provide shade for sensitive crops.",
        "🌦️ Mixed weather patterns. Monitor soil moisture and adjust watering schedule accordingly.",
        "🌬️ Windy conditions expected. Secure loose structures and check plant supports."
    ];
    
    return ['success' => true, 'data' => ['advice' => $adviceOptions[array_rand($adviceOptions)]]];
}

function getCropRecommendation($soilType, $season) {
    $recommendations = [
        'clay-winter' => "🌾 Winter crops like wheat, barley, and mustard are ideal for clay soil in winter.",
        'loamy-summer' => "🌶️ Summer vegetables like tomatoes, peppers, and eggplant thrive in loamy soil.",
        'sandy-monsoon' => "🌱 Monsoon crops like rice, sugarcane, and pulses work well in sandy soil with good irrigation.",
    ];
    
    $key = strtolower($soilType) . '-' . strtolower($season);
    $advice = $recommendations[$key] ?? "🌱 Consider crop rotation and soil testing for optimal results.";
    
    return ['success' => true, 'data' => ['recommendation' => $advice]];
}

function chatWithAI($message) {
    // Simulate AI responses based on keywords
    $responses = [
        'weather' => "Based on current meteorological data, expect moderate temperatures with 70% humidity. Good conditions for most crops. Monitor for any sudden changes.",
        'price' => "Current market trends show steady demand. Consider timing your harvest for optimal prices. Check weekly market reports.",
        'disease' => "For plant diseases, inspect leaves regularly. Use organic neem oil for prevention. Ensure proper air circulation between plants.",
        'fertilizer' => "Use balanced NPK fertilizers. For organic farming, compost and cow dung are excellent choices. Test soil pH regularly.",
        'irrigation' => "Drip irrigation is most efficient. Water early morning or evening. Monitor soil moisture at root level.",
        'harvest' => "Harvest when fruits are firm and fully colored. Early morning harvesting retains freshness longer.",
    ];
    
    $message = strtolower($message);
    $response = "I'm here to help with your farming questions! You can ask about weather, prices, diseases, fertilizers, irrigation, harvesting, and more.";
    
    foreach ($responses as $keyword => $reply) {
        if (strpos($message, $keyword) !== false) {
            $response = $reply;
            break;
        }
    }
    
    return ['success' => true, 'data' => ['response' => $response]];
}

function updateFarmerProfile($pdo, $farmerId, $data) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, location = ? WHERE id = ?");
        $stmt->execute([$data['full_name'], $data['phone'], $data['location'], $farmerId]);
        return ['success' => true, 'message' => 'Profile updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    :root {
        --primary: #10B981;
        --primary-light: #34D399;
        --primary-dark: #059669;
        --secondary: #F59E0B;
        --secondary-light: #FBBF24;
        --accent: #8B5CF6;
        --dark: #111827;
        --dark-light: #1F2937;
        --gray: #6B7280;
        --gray-light: #F3F4F6;
        --white: #FFFFFF;
        --gradient-1: linear-gradient(135deg, #10B981 0%, #059669 100%);
        --gradient-2: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
        --gradient-bg: linear-gradient(135deg, #F0FDF4 0%, #ECFDF5 100%);
        --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
        --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
        --shadow-xl: 0 20px 40px rgba(0, 0, 0, 0.1);
    }

    body {
        font-family: 'Poppins', sans-serif;
        background: var(--gradient-bg);
        color: var(--dark);
        line-height: 1.6;
        overflow-x: hidden;
    }

    /* Navigation */
    .navbar {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 80px;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        border-bottom: 1px solid rgba(16, 185, 129, 0.1);
        z-index: 1000;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: var(--shadow-md);
    }

    .nav-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 2rem;
        height: 100%;
        max-width: 1400px;
        margin: 0 auto;
    }

    .nav-logo {
        display: flex;
        align-items: center;
        gap: 1rem;
        font-size: 1.75rem;
        font-weight: 800;
        color: var(--primary);
        text-decoration: none;
    }

    .nav-logo i {
        font-size: 2rem;
        background: var(--gradient-1);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        animation: tractorMove 3s ease-in-out infinite;
    }

    @keyframes tractorMove {

        0%,
        100% {
            transform: translateX(0px);
        }

        50% {
            transform: translateX(10px);
        }
    }

    .nav-menu {
        display: flex;
        align-items: center;
        gap: 2rem;
    }

    .nav-link {
        color: var(--gray);
        text-decoration: none;
        font-weight: 500;
        padding: 0.5rem 1rem;
        border-radius: 12px;
        transition: all 0.3s ease;
    }

    .nav-link:hover {
        color: var(--primary);
        background: rgba(16, 185, 129, 0.1);
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 1rem;
        color: var(--gray);
        font-weight: 600;
    }

    .btn-logout {
        padding: 0.75rem 1.5rem;
        background: var(--gradient-1);
        color: white;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-logout:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }

    .farmer-container {
        margin-top: 80px;
        min-height: calc(100vh - 80px);
        padding: 2rem;
    }

    .farmer-header {
        background: var(--gradient-1);
        color: white;
        padding: 3rem 2rem;
        border-radius: 24px;
        margin-bottom: 2rem;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .farmer-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><defs><radialGradient id="a" cx="50%" cy="50%"><stop offset="0%" stop-color="%23FFFFFF" stop-opacity="0.1"/><stop offset="100%" stop-color="%23FFFFFF" stop-opacity="0"/></radialGradient></defs><rect width="100%" height="100%" fill="url(%23a)"/></svg>') center/cover;
        animation: backgroundShift 20s ease-in-out infinite;
    }

    @keyframes backgroundShift {

        0%,
        100% {
            transform: scale(1) rotate(0deg);
        }

        50% {
            transform: scale(1.1) rotate(2deg);
        }
    }

    .farmer-header h1 {
        font-size: 3rem;
        font-weight: 900;
        margin-bottom: 1rem;
        position: relative;
        z-index: 2;
    }

    .farmer-header p {
        font-size: 1.2rem;
        opacity: 0.9;
        position: relative;
        z-index: 2;
    }

    .farmer-nav {
        background: white;
        border-radius: 20px;
        padding: 1rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-md);
    }

    .nav-tabs {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;

    }

    .nav-tab {
        padding: 1rem 2rem;
        background: transparent;
        border: 2px solid transparent;
        border-radius: 16px;
        cursor: pointer;
        transition: all 0.3s ease;
        color: var(--gray);
        font-weight: 600;
        font-size: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        /* Removes underline */
        color: inherit;
        /* Optional: keeps text color consistent */
    }

    .nav-tab.active {
        background: var(--gradient-1);
        color: white;
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .nav-tab:hover:not(.active) {
        background: rgba(16, 185, 129, 0.1);
        color: var(--primary);
        transform: translateY(-2px);
    }

    .farmer-section {
        display: none;
    }

    .farmer-section.active {
        display: block;
        animation: fadeInUp 0.5s ease;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 2rem;
        margin-bottom: 3rem;
    }

    .stat-card {
        background: white;
        padding: 2.5rem;
        border-radius: 20px;
        box-shadow: var(--shadow-md);
        border: 1px solid rgba(16, 185, 129, 0.1);
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-xl);
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-1);
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        background: var(--gradient-1);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1.5rem;
        font-size: 1.5rem;
        color: white;
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: 900;
        color: var(--primary);
        margin-bottom: 0.5rem;
        display: block;
    }

    .stat-label {
        color: var(--gray);
        font-weight: 600;
        font-size: 1.1rem;
    }

    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 2rem;
    }

    .product-card {
        background: white;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: var(--shadow-md);
        transition: all 0.3s ease;
        border: 1px solid rgba(16, 185, 129, 0.1);
    }

    .product-card:hover {
        transform: translateY(-8px);
        box-shadow: var(--shadow-xl);
    }

    .product-image {
        height: 200px;
        background: var(--gradient-2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 3rem;
        position: relative;
        overflow: hidden;
    }

    .product-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .product-badge {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: var(--white);
        color: var(--primary);
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 700;
        box-shadow: var(--shadow-sm);
    }

    .product-info {
        padding: 1.5rem;
    }

    .product-name {
        font-size: 1.25rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: var(--dark);
    }

    .product-details {
        color: var(--gray);
        margin-bottom: 1rem;
        line-height: 1.6;
    }

    .product-price {
        font-size: 1.75rem;
        font-weight: 800;
        color: var(--primary);
        margin-bottom: 1rem;
    }

    .product-actions {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .form-container {
        background: white;
        border-radius: 20px;
        padding: 2rem;
        box-shadow: var(--shadow-md);
        margin-bottom: 2rem;
    }

    .form-title {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 1.5rem;
        color: var(--dark);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-label {
        display: block;
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }

    .form-input,
    .form-select,
    .form-textarea {
        width: 100%;
        padding: 1rem;
        border: 2px solid var(--gray-light);
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: white;
        font-family: 'Poppins', sans-serif;
    }

    .form-input:focus,
    .form-select:focus,
    .form-textarea:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        transform: translateY(-1px);
    }

    .form-textarea {
        resize: vertical;
        min-height: 100px;
    }

    .btn {
        padding: 1rem 2rem;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        border: none;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 1rem;
        justify-content: center;
    }

    .btn-primary {
        background: var(--gradient-1);
        color: white;
        box-shadow: var(--shadow-md);
    }

    .btn-secondary {
        background: var(--gradient-2);
        color: white;
        box-shadow: var(--shadow-md);
    }

    .btn-danger {
        background: linear-gradient(135deg, #DC2626, #B91C1C);
        color: white;
        box-shadow: var(--shadow-md);
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }

    .ai-prediction {
        background: linear-gradient(135deg, #8B5CF6, #7C3AED);
        color: white;
        border-radius: 20px;
        padding: 2rem;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }

    .ai-prediction::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><defs><radialGradient id="a" cx="50%" cy="50%"><stop offset="0%" stop-color="%23FFFFFF" stop-opacity="0.1"/><stop offset="100%" stop-color="%23FFFFFF" stop-opacity="0"/></radialGradient></defs><rect width="100%" height="100%" fill="url(%23a)"/></svg>') center/cover;
    }

    .prediction-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
        position: relative;
        z-index: 2;
    }

    .prediction-icon {
        width: 50px;
        height: 50px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .prediction-content {
        position: relative;
        z-index: 2;
    }

    .prediction-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1.5rem;
    }

    .prediction-item {
        background: rgba(255, 255, 255, 0.1);
        padding: 1.5rem;
        border-radius: 12px;
        text-align: center;
    }

    .prediction-value {
        font-size: 1.75rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
    }

    .prediction-label {
        font-size: 0.9rem;
        opacity: 0.9;
    }

    /* Status badges */
    .status-badge {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-available {
        background: rgba(16, 185, 129, 0.1);
        color: #059669;
    }

    .status-sold {
        background: rgba(139, 92, 246, 0.1);
        color: #7C3AED;
    }

    .status-pending {
        background: rgba(245, 158, 11, 0.1);
        color: #D97706;
    }

    .status-confirmed {
        background: rgba(59, 130, 246, 0.1);
        color: #2563EB;
    }

    .status-shipped {
        background: rgba(139, 92, 246, 0.1);
        color: #7C3AED;
    }

    .status-delivered {
        background: rgba(16, 185, 129, 0.1);
        color: #059669;
    }

    .status-cancelled {
        background: rgba(239, 68, 68, 0.1);
        color: #DC2626;
    }

    /* AI Chat Styles */
    .ai-chat-container {
        background: white;
        border-radius: 20px;
        box-shadow: var(--shadow-md);
        margin-bottom: 2rem;
        overflow: hidden;
    }

    .ai-chat-header {
        background: var(--gradient-1);
        color: white;
        padding: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .chat-messages {
        height: 400px;
        overflow-y: auto;
        padding: 1.5rem;
        background: var(--gray-light);
    }

    .chat-message {
        margin-bottom: 1rem;
        display: flex;
        gap: 0.75rem;
        align-items: flex-start;
    }

    .chat-message.user {
        flex-direction: row-reverse;
    }

    .message-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        flex-shrink: 0;
    }

    .chat-message.user .message-avatar {
        background: var(--primary);
        color: white;
    }

    .chat-message.bot .message-avatar {
        background: white;
        color: var(--primary);
        border: 2px solid var(--primary);
    }

    .message-content {
        max-width: 80%;
        padding: 0.75rem 1rem;
        border-radius: 16px;
        font-size: 0.9rem;
        line-height: 1.4;
    }

    .chat-message.user .message-content {
        background: var(--primary);
        color: white;
        border-bottom-right-radius: 6px;
    }

    .chat-message.bot .message-content {
        background: white;
        color: var(--dark);
        border: 1px solid rgba(16, 185, 129, 0.2);
        border-bottom-left-radius: 6px;
    }

    .chat-input-container {
        padding: 1rem;
        background: white;
        border-top: 1px solid var(--gray-light);
    }

    .chat-input-wrapper {
        display: flex;
        gap: 0.5rem;
        align-items: flex-end;
    }

    .chat-input {
        flex: 1;
        padding: 0.75rem;
        border: 1px solid var(--gray-light);
        border-radius: 12px;
        resize: none;
        font-family: 'Poppins', sans-serif;
        font-size: 0.9rem;
        max-height: 80px;
    }

    .chat-input:focus {
        outline: none;
        border-color: var(--primary);
    }

    .chat-send-btn {
        width: 40px;
        height: 40px;
        background: var(--gradient-1);
        border: none;
        border-radius: 50%;
        color: white;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }

    .chat-send-btn:hover {
        transform: scale(1.1);
    }

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 2000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(5px);
        overflow-y: auto;
        padding: 2rem 0;
    }

    .modal-content {
        background: white;
        margin: 0 auto;
        padding: 2.5rem;
        border-radius: 20px;
        width: 90%;
        max-width: 800px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: var(--shadow-xl);
        animation: modalSlideIn 0.3s ease;
    }

    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-50px) scale(0.9);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        position: sticky;
        top: 0;
        background: white;
        z-index: 10;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--gray-light);
    }

    .modal-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--dark);
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--gray);
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.3s ease;
    }

    .modal-close:hover {
        background: var(--gray-light);
        color: var(--dark);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .nav-tabs {
            flex-direction: column;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }

        .nav-menu {
            display: none;
        }

        .farmer-header h1 {
            font-size: 2rem;
        }

        .modal-content {
            width: 95%;
            padding: 1.5rem;
            margin: 1rem auto;
        }

        .product-actions {
            flex-direction: column;
        }

        .prediction-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="nav-logo">
                <i class="fas fa-tractor"></i>
                <span>Farmer Panel</span>
            </a>
            <div class="nav-menu">
                <a href="../index.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    Home
                </a>
                <div class="user-info">
                    <i class="fas fa-user-circle"></i>
                    <span>Welcome, <?php echo htmlspecialchars($user['full_name']); ?></span>
                    <a href="../api/auth.php?action=logout" class="btn-logout"
                        onclick="return confirm('Are you sure you want to logout?')">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="farmer-container">
        <!-- Header -->
        <div class="farmer-header" data-aos="fade-down">
            <h1>🌾 Farmer Dashboard</h1>
            <p>Manage your agricultural business with advanced technology and AI-powered insights</p>
        </div>

        <!-- Navigation Tabs -->
        <div class="farmer-nav" data-aos="fade-up" data-aos-delay="100">
            <div class="nav-tabs">
                <button class="nav-tab active" onclick="showSection('dashboard')" data-section="dashboard">
                    <i class="fas fa-chart-pie"></i>
                    Dashboard
                </button>
                <button class="nav-tab" onclick="showSection('products')" data-section="products">
                    <i class="fas fa-seedling"></i>
                    My Products
                </button>
                <button class="nav-tab" onclick="showSection('add-product')" data-section="add-product">
                    <i class="fas fa-plus-circle"></i>
                    Add Product
                </button>
                <button class="nav-tab" onclick="showSection('orders')" data-section="orders">
                    <i class="fas fa-shopping-cart"></i>
                    My Orders
                </button>
                <!-- <button class="nav-tab" onclick="showSection('price-prediction')" data-section="price-prediction">
                    <i class="fas fa-chart-line"></i>
                    Price Prediction
                </button> -->
                <a href="../chatbot" class="nav-tab">
                    <i class="fas fa-robot"></i> AI Assistant
                </a>
                <button class=" nav-tab" onclick="showSection('profile')" data-section="profile">
                    <i class="fas fa-user-edit"></i>
                    Profile
                </button>
            </div>
        </div>

        <!-- Dashboard Section -->
        <div id="dashboard" class="farmer-section active">
            <div class="stats-grid" data-aos="fade-up" data-aos-delay="200">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-seedling"></i>
                    </div>
                    <span class="stat-number" id="total-products">0</span>
                    <div class="stat-label">Total Products</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <span class="stat-number" id="active-products">0</span>
                    <div class="stat-label">Active Listings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <span class="stat-number" id="total-orders">0</span>
                    <div class="stat-label">Total Orders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-rupee-sign"></i>
                    </div>
                    <span class="stat-number" id="total-earnings">₹0</span>
                    <div class="stat-label">Total Earnings</div>
                </div>
            </div>
        </div>

        <!-- Products Section -->
        <div id="products" class="farmer-section">
            <div id="products-list" class="products-grid" data-aos="fade-up">
                <!-- Products will be loaded here -->
            </div>
        </div>

        <!-- Add Product Section -->
        <div id="add-product" class="farmer-section">
            <div class="form-container" data-aos="fade-up">
                <h2 class="form-title">
                    <i class="fas fa-plus-circle"></i>
                    Add New Product
                </h2>
                <form id="add-product-form" enctype="multipart/form-data">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="product-name" class="form-label">Product Name *</label>
                            <input type="text" id="product-name" name="name" class="form-input" required
                                placeholder="e.g., Organic Tomatoes">
                        </div>
                        <div class="form-group">
                            <label for="product-category" class="form-label">Category *</label>
                            <select id="product-category" name="category" class="form-select" required>
                                <option value="">Select Category</option>
                                <option value="vegetables">Vegetables</option>
                                <option value="fruits">Fruits</option>
                                <option value="grains">Grains</option>
                                <option value="pulses">Pulses</option>
                                <option value="spices">Spices</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="product-price" class="form-label">Price per Unit (₹) *</label>
                            <input type="number" id="product-price" name="price_per_unit" class="form-input" required
                                placeholder="50" step="0.01">
                        </div>
                        <div class="form-group">
                            <label for="product-unit" class="form-label">Unit *</label>
                            <select id="product-unit" name="unit" class="form-select" required>
                                <option value="">Select Unit</option>
                                <option value="kg">Kilogram (kg)</option>
                                <option value="gm">Gram (gm)</option>
                                <option value="ton">Ton</option>
                                <option value="piece">Piece</option>
                                <option value="box">Box</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="product-quantity" class="form-label">Available Quantity *</label>
                            <input type="number" id="product-quantity" name="quantity" class="form-input" required
                                placeholder="100">
                        </div>
                        <div class="form-group">
                            <label for="product-grade" class="form-label">Quality Grade</label>
                            <select id="product-grade" name="quality_grade" class="form-select">
                                <option value="A">Grade A (Premium)</option>
                                <option value="B" selected>Grade B (Standard)</option>
                                <option value="C">Grade C (Basic)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="product-image" class="form-label">Product Image</label>
                        <input type="file" id="product-image" name="product_image" class="form-input"
                            accept="image/jpeg,image/jpg,image/png,image/gif">
                        <small style="color: var(--gray); font-size: 0.8rem;">Upload a clear photo of your product (JPG,
                            PNG, GIF - Max 5MB)</small>
                    </div>

                    <div class="form-group">
                        <label for="product-description" class="form-label">Product Description</label>
                        <textarea id="product-description" name="description" class="form-textarea"
                            placeholder="Describe your product quality, farming methods, harvest date, etc."></textarea>
                    </div>

                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" id="organic-checkbox" name="organic"
                                style="width: auto; accent-color: var(--primary);">
                            <span class="form-label" style="margin: 0;">Organic Certified 🌿</span>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Add Product
                    </button>
                </form>
            </div>
        </div>

        <!-- Orders Section -->
        <div id="orders" class="farmer-section">
            <div id="orders-list" data-aos="fade-up">
                <!-- Orders will be loaded here -->
            </div>
        </div>

        <!-- Price Prediction Section -->
        <div id="price-prediction" class="farmer-section">
            <div class="form-container" data-aos="fade-up">
                <h2 class="form-title">
                    <i class="fas fa-chart-line"></i>
                    AI Price Prediction
                </h2>
                <p style="color: var(--gray); margin-bottom: 2rem;">Get AI-powered price predictions for your crops based on market trends and weather conditions.</p>
                
                <form id="price-prediction-form" style="margin-bottom: 2rem;">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="prediction-crop" class="form-label">Select Crop *</label>
                            <select id="prediction-crop" name="crop" class="form-select" required>
                                <option value="">Choose a crop</option>
                                <option value="tomato">Tomato</option>
                                <option value="potato">Potato</option>
                                <option value="onion">Onion</option>
                                <option value="wheat">Wheat</option>
                                <option value="rice">Rice</option>
                                <option value="corn">Corn</option>
                                <option value="carrot">Carrot</option>
                                <option value="cabbage">Cabbage</option>
                                <option value="cauliflower">Cauliflower</option>
                                <option value="spinach">Spinach</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="prediction-location" class="form-label">Location *</label>
                            <input type="text" id="prediction-location" name="location" class="form-input" required
                                placeholder="e.g., Delhi, Mumbai, Bangalore" value="<?php echo htmlspecialchars($user['location'] ?? 'Delhi'); ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-magic"></i>
                        Get Price Prediction
                    </button>
                </form>

                <div id="prediction-results" style="display: none;">
                    <div class="prediction-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 2rem; border-radius: 15px; margin-bottom: 2rem;">
                        <h3 style="margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-chart-line"></i>
                            Price Prediction Results
                        </h3>
                        <div id="prediction-content">
                            <!-- Prediction results will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Section -->
        <div id="profile" class="farmer-section">
            <div class="form-container" data-aos="fade-up">
                <h2 class="form-title">
                    <i class="fas fa-user-edit"></i>
                    Edit Profile
                </h2>
                <form id="profile-form"
                    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                    <div class="form-group">
                        <label for="profile-name" class="form-label">Full Name *</label>
                        <input type="text" id="profile-name" name="full_name" class="form-input"
                            value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="profile-email" class="form-label">Email Address</label>
                        <input type="email" id="profile-email" class="form-input"
                            value="<?php echo htmlspecialchars($user['email']); ?>" disabled
                            style="background: var(--gray-light); color: var(--gray);">
                        <small style="color: var(--gray); font-size: 0.8rem;">Email cannot be changed</small>
                    </div>
                    <div class="form-group">
                        <label for="profile-phone" class="form-label">Phone Number *</label>
                        <input type="tel" id="profile-phone" name="phone" class="form-input"
                            value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" pattern="[0-9]{10}" required>
                    </div>
                    <div class="form-group">
                        <label for="profile-location" class="form-label">Farm Location</label>
                        <input type="text" id="profile-location" name="location" class="form-input"
                            value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>"
                            placeholder="Village, District, State">
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="editProductModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Edit Product</h2>
                <button class="modal-close" onclick="closeModal('editProductModal')">&times;</button>
            </div>
            <form id="edit-product-form" enctype="multipart/form-data">
                <input type="hidden" id="edit-product-id" name="product_id">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit-product-name" class="form-label">Product Name *</label>
                        <input type="text" id="edit-product-name" name="name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-product-category" class="form-label">Category *</label>
                        <select id="edit-product-category" name="category" class="form-select" required>
                            <option value="">Select Category</option>
                            <option value="vegetables">Vegetables</option>
                            <option value="fruits">Fruits</option>
                            <option value="grains">Grains</option>
                            <option value="pulses">Pulses</option>
                            <option value="spices">Spices</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit-product-price" class="form-label">Price per Unit (₹) *</label>
                        <input type="number" id="edit-product-price" name="price_per_unit" class="form-input" required
                            step="0.01">
                    </div>
                    <div class="form-group">
                        <label for="edit-product-unit" class="form-label">Unit *</label>
                        <select id="edit-product-unit" name="unit" class="form-select" required>
                            <option value="">Select Unit</option>
                            <option value="kg">Kilogram (kg)</option>
                            <option value="gm">Gram (gm)</option>
                            <option value="ton">Ton</option>
                            <option value="piece">Piece</option>
                            <option value="box">Box</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit-product-quantity" class="form-label">Available Quantity *</label>
                        <input type="number" id="edit-product-quantity" name="quantity" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-product-grade" class="form-label">Quality Grade</label>
                        <select id="edit-product-grade" name="quality_grade" class="form-select">
                            <option value="A">Grade A (Premium)</option>
                            <option value="B">Grade B (Standard)</option>
                            <option value="C">Grade C (Basic)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="edit-product-image" class="form-label">Update Product Image</label>
                    <input type="file" id="edit-product-image" name="product_image" class="form-input"
                        accept="image/jpeg,image/jpg,image/png,image/gif">
                    <small style="color: var(--gray); font-size: 0.8rem;">Leave empty to keep current image</small>
                </div>

                <div class="form-group">
                    <label for="edit-product-description" class="form-label">Product Description</label>
                    <textarea id="edit-product-description" name="description" class="form-textarea"></textarea>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" id="edit-organic-checkbox" name="organic"
                            style="width: auto; accent-color: var(--primary);">
                        <span class="form-label" style="margin: 0;">Organic Certified 🌿</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Update Product
                </button>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
    // Initialize AOS
    AOS.init({
        duration: 800,
        easing: 'ease-out-cubic',
        once: true
    });

    let currentSection = 'dashboard';

    document.addEventListener('DOMContentLoaded', function() {
        loadFarmerStats();
    });

    function showSection(sectionId) {
        document.querySelectorAll('.farmer-section').forEach(section => {
            section.classList.remove('active');
        });

        document.getElementById(sectionId).classList.add('active');

        document.querySelectorAll('.nav-tab').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelector(`[data-section="${sectionId}"]`).classList.add('active');

        currentSection = sectionId;

        switch (sectionId) {
            case 'dashboard':
                loadFarmerStats();
                break;
            case 'products':
                loadMyProducts();
                break;
            case 'orders':
                loadMyOrders();
                break;
        }
    }

    async function apiCall(action, data = {}, isFormData = false) {
        try {
            let formData;
            if (isFormData) {
                formData = data;
                formData.append('action', action);
            } else {
                formData = new FormData();
                formData.append('action', action);
                Object.keys(data).forEach(key => {
                    formData.append(key, data[key]);
                });
            }

            const response = await fetch('farmer.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            return result;
        } catch (error) {
            showNotification('Network error: ' + error.message, 'error');
            return {
                success: false,
                message: error.message
            };
        }
    }

    async function loadFarmerStats() {
        const result = await apiCall('get_farmer_stats');
        if (result.success) {
            const stats = result.data;
            animateCounter('total-products', stats.total_products);
            animateCounter('active-products', stats.active_products);
            animateCounter('total-orders', stats.total_orders);

            const earningsElement = document.getElementById('total-earnings');
            animateRevenue(earningsElement, stats.total_earnings);
        }
    }

    async function loadMyProducts() {
        const result = await apiCall('get_my_products');
        const container = document.getElementById('products-list');

        if (result.success) {
            if (result.data.length === 0) {
                container.innerHTML = `
                        <div style="grid-column: 1 / -1; text-align: center; padding: 4rem; background: white; border-radius: 20px; box-shadow: var(--shadow-md);">
                            <i class="fas fa-seedling" style="font-size: 4rem; color: var(--primary); margin-bottom: 2rem;"></i>
                            <h3 style="color: var(--dark); margin-bottom: 1rem;">No Products Listed Yet</h3>
                            <p style="color: var(--gray); margin-bottom: 2rem;">Start by adding your first product to reach buyers directly.</p>
                            <button onclick="showSection('add-product')" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Your First Product
                            </button>
                        </div>
                    `;
                return;
            }

            container.innerHTML = result.data.map(product => `
                    <div class="product-card">
                        <div class="product-image">
                            ${product.image_path ? `<img src="../${product.image_path}" alt="${product.name}" />` : '<i class="fas fa-seedling"></i>'}
                            ${product.organic == 1 ? '<div class="product-badge">🌿 Organic</div>' : '<div class="product-badge">🌟 Fresh</div>'}
                        </div>
                        <div class="product-info">
                            <h3 class="product-name">${product.name}</h3>
                            <div class="product-details">
                                <p><strong>Category:</strong> ${product.category}</p>
                                <p><strong>Quantity:</strong> ${product.quantity} ${product.unit}</p>
                                <p><strong>Quality:</strong> Grade ${product.quality_grade}</p>
                                <p><strong>Status:</strong> <span class="status-badge status-${product.status}">${product.status}</span></p>
                            </div>
                            <div class="product-price">₹${product.price_per_unit}/${product.unit}</div>
                            <div class="product-actions">
                                <button class="btn btn-secondary" onclick="editProduct(${product.id})">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-danger" onclick="confirmDeleteProduct(${product.id}, '${product.name.replace(/'/g, "\\'")}')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                `).join('');
        }
    }

    async function loadMyOrders() {
        const result = await apiCall('get_my_orders');
        const container = document.getElementById('orders-list');

        if (result.success) {
            if (result.data.length === 0) {
                container.innerHTML = `
                        <div style="text-align: center; padding: 4rem; background: white; border-radius: 20px; box-shadow: var(--shadow-md);">
                            <i class="fas fa-shopping-cart" style="font-size: 4rem; color: var(--primary); margin-bottom: 2rem;"></i>
                            <h3 style="color: var(--dark); margin-bottom: 1rem;">No Orders Yet</h3>
                            <p style="color: var(--gray);">Your orders from buyers will appear here.</p>
                        </div>
                    `;
                return;
            }

            container.innerHTML = result.data.map(order => `
                    <div class="form-container" style="margin-bottom: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                            <h3 style="color: var(--dark);">Order #${order.id}</h3>
                            <span class="status-badge status-${order.status}">${order.status}</span>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                            <div>
                                <strong>Product:</strong> ${order.product_name}<br>
                                <strong>Quantity:</strong> ${order.quantity} ${order.unit}<br>
                                <strong>Amount:</strong> ₹${order.total_amount}
                            </div>
                            <div>
                                <strong>Buyer:</strong> ${order.buyer_name}<br>
                                <strong>Phone:</strong> ${order.buyer_phone || 'Not provided'}<br>
                                <strong>Order Date:</strong> ${formatDate(order.order_date)}
                            </div>
                        </div>
                        ${order.delivery_address ? `<p><strong>Delivery Address:</strong> ${order.delivery_address}</p>` : ''}
                        ${order.notes ? `<p style="margin-top: 1rem;"><strong>Buyer Notes:</strong> ${order.notes}</p>` : ''}
                        <div style="margin-top: 1.5rem; display: flex; gap: 1rem; flex-wrap: wrap;">
                            ${order.status === 'pending' ? `
                                <button class="btn btn-primary" onclick="updateOrderStatus(${order.id}, 'confirmed')">
                                    <i class="fas fa-check"></i> Accept Order
                                </button>
                                <button class="btn btn-danger" onclick="updateOrderStatus(${order.id}, 'cancelled')">
                                    <i class="fas fa-times"></i> Decline
                                </button>
                            ` : ''}
                            ${order.status === 'confirmed' ? `
                                <button class="btn btn-secondary" onclick="updateOrderStatus(${order.id}, 'shipped')">
                                    <i class="fas fa-truck"></i> Mark as Shipped
                                </button>
                            ` : ''}
                            ${order.status === 'shipped' ? `
                                <button class="btn btn-primary" onclick="updateOrderStatus(${order.id}, 'delivered')">
                                    <i class="fas fa-check-circle"></i> Mark as Delivered
                                </button>
                            ` : ''}
                            <button class="btn" style="background: var(--gray-light); color: var(--dark);" onclick="contactBuyer('${order.buyer_name}', '${order.buyer_phone || ''}')">
                                <i class="fas fa-phone"></i> Contact Buyer
                            </button>
                        </div>
                    </div>
                `).join('');
        }
    }

    // Add Product Form
    document.getElementById('add-product-form').addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding Product...';
        submitBtn.disabled = true;

        const result = await apiCall('add_product', formData, true);

        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;

        if (result.success) {
            showNotification('🎉 Product added successfully!', 'success');
            this.reset();
            loadFarmerStats();
            loadMyProducts();
        } else {
            showNotification('Failed to add product: ' + result.message, 'error');
        }
    });

    // Edit Product Form
    document.getElementById('edit-product-form').addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        submitBtn.disabled = true;

        const result = await apiCall('update_product', formData, true);

        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;

        if (result.success) {
            showNotification('✅ Product updated successfully!', 'success');
            closeModal('editProductModal');
            loadMyProducts();
            loadFarmerStats();
        } else {
            showNotification('Failed to update product: ' + result.message, 'error');
        }
    });

    // Profile Form
    document.getElementById('profile-form').addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const data = Object.fromEntries(formData);

        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        submitBtn.disabled = true;

        const result = await apiCall('update_profile', data);

        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;

        if (result.success) {
            showNotification('✅ Profile updated successfully!', 'success');
        } else {
            showNotification('Failed to update profile: ' + result.message, 'error');
        }
    });

    // Price Prediction Form
    document.getElementById('price-prediction-form').addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const data = Object.fromEntries(formData);

        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Predicting...';
        submitBtn.disabled = true;

        try {
            const response = await fetch('../api/price_prediction.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;

            if (result.success) {
                displayPredictionResults(result.data);
                document.getElementById('prediction-results').style.display = 'block';
                document.getElementById('prediction-results').scrollIntoView({ behavior: 'smooth' });
            } else {
                showNotification('Failed to get price prediction: ' + result.message, 'error');
            }
        } catch (error) {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
            showNotification('Error connecting to prediction service', 'error');
        }
    });

    function displayPredictionResults(data) {
        const content = document.getElementById('prediction-content');
        content.innerHTML = `
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                <div style="text-align: center;">
                    <div style="font-size: 2rem; font-weight: bold; margin-bottom: 0.5rem;">₹${data.predicted_price}</div>
                    <div style="opacity: 0.9;">Predicted Price per ${data.unit || 'kg'}</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 2rem; font-weight: bold; margin-bottom: 0.5rem; color: ${data.trend === 'up' ? '#4ade80' : data.trend === 'down' ? '#f87171' : '#fbbf24'};">
                        <i class="fas fa-arrow-${data.trend === 'up' ? 'up' : data.trend === 'down' ? 'down' : 'right'}"></i>
                    </div>
                    <div style="opacity: 0.9;">Market Trend</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 2rem; font-weight: bold; margin-bottom: 0.5rem;">${data.confidence}%</div>
                    <div style="opacity: 0.9;">Confidence</div>
                </div>
            </div>
            <div style="background: rgba(255,255,255,0.1); padding: 1rem; border-radius: 10px; margin-bottom: 1rem;">
                <h4 style="margin: 0 0 0.5rem 0; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-lightbulb"></i> Market Insights
                </h4>
                <p style="margin: 0; opacity: 0.9; line-height: 1.5;">${data.explanation}</p>
            </div>
            ${data.weather_info ? `
            <div style="background: rgba(255,255,255,0.1); padding: 1rem; border-radius: 10px;">
                <h4 style="margin: 0 0 0.5rem 0; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-cloud-sun"></i> Weather Impact
                </h4>
                <p style="margin: 0; opacity: 0.9; line-height: 1.5;">${data.weather_info}</p>
            </div>
            ` : ''}
        `;
    }

    async function editProduct(productId) {
        const result = await apiCall('get_product_for_edit', {
            product_id: productId
        });

        if (result.success) {
            const product = result.data;

            document.getElementById('edit-product-id').value = product.id;
            document.getElementById('edit-product-name').value = product.name;
            document.getElementById('edit-product-category').value = product.category;
            document.getElementById('edit-product-price').value = product.price_per_unit;
            document.getElementById('edit-product-unit').value = product.unit;
            document.getElementById('edit-product-quantity').value = product.quantity;
            document.getElementById('edit-product-grade').value = product.quality_grade;
            document.getElementById('edit-product-description').value = product.description || '';
            document.getElementById('edit-organic-checkbox').checked = product.organic == 1;

            document.getElementById('editProductModal').style.display = 'block';
        }
    }

    async function updateOrderStatus(orderId, status) {
        const result = await apiCall('update_order_status', {
            order_id: orderId,
            status: status
        });

        if (result.success) {
            showNotification('📦 Order status updated successfully', 'success');
            loadMyOrders();
            loadFarmerStats();
        } else {
            showNotification('Failed to update order status', 'error');
        }
    }

    function confirmDeleteProduct(productId, productName) {
        if (confirm(`Are you sure you want to delete "${productName}"? This action cannot be undone.`)) {
            deleteProduct(productId);
        }
    }

    async function deleteProduct(productId) {
        const result = await apiCall('delete_product', {
            product_id: productId
        });

        if (result.success) {
            showNotification('🗑️ Product deleted successfully', 'success');
            loadMyProducts();
            loadFarmerStats();
        } else {
            showNotification('Failed to delete product', 'error');
        }
    }

    async function getPricePrediction() {
        const cropName = document.getElementById('crop-name').value.trim();
        const location = document.getElementById('location').value.trim();

        if (!cropName) {
            showNotification('Please enter a crop name', 'error');
            return;
        }

        const result = await apiCall('get_price_prediction', {
            crop_name: cropName,
            location: location
        });

        if (result.success) {
            const data = result.data;
            document.getElementById('current-price').textContent = '₹' + data.current;
            document.getElementById('predicted-price-week').textContent = '₹' + data.next_week;
            document.getElementById('predicted-price-month').textContent = '₹' + data.next_month;
            document.getElementById('confidence-level').textContent = data.confidence + '%';

            let trendIcon = '→';
            if (data.trend === 'up') trendIcon = '📈';
            else if (data.trend === 'down') trendIcon = '📉';
            else trendIcon = '➡️';
            document.getElementById('trend-direction').textContent = trendIcon;

            document.getElementById('prediction-results').style.display = 'grid';

            showNotification(`📊 Price prediction generated with ${data.confidence}% confidence`, 'success');
        }
    }

    async function getWeatherAdvice() {
        const location = document.getElementById('location').value || 'your area';
        const result = await apiCall('get_weather_advice', {
            location: location
        });

        if (result.success) {
            addChatMessage('user', 'What\'s the weather forecast for farming?');
            setTimeout(() => {
                addChatMessage('bot', result.data.advice);
            }, 1000);
        }
    }

    async function getCropRecommendation() {
        const result = await apiCall('get_crop_recommendation', {
            soil_type: 'loamy',
            season: 'current'
        });

        if (result.success) {
            addChatMessage('user', 'What crops should I grow this season?');
            setTimeout(() => {
                addChatMessage('bot', result.data.recommendation);
            }, 1000);
        }
    }

    async function askAbout(topic) {
        const messages = {
            'disease': 'How can I identify and treat plant diseases?',
            'fertilizer': 'What fertilizers should I use for better yield?'
        };

        const message = messages[topic];
        if (message) {
            addChatMessage('user', message);
            setTimeout(() => {
                sendChatMessage(topic);
            }, 1000);
        }
    }

    function handleChatKeyPress(event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendChatMessage();
        }
    }

    async function sendChatMessage(predefinedMessage) {
        const chatInput = document.getElementById('chat-input');
        const message = predefinedMessage || chatInput.value.trim();

        if (!message) return;

        if (!predefinedMessage) {
            addChatMessage('user', message);
            chatInput.value = '';
        }

        // Show typing indicator
        addTypingIndicator();

        const result = await apiCall('chat_with_ai', {
            message: message
        });

        removeTypingIndicator();

        if (result.success) {
            addChatMessage('bot', result.data.response);
        } else {
            addChatMessage('bot', 'Sorry, I\'m having trouble connecting. Please try again in a moment.');
        }
    }

    function addChatMessage(sender, message) {
        const chatMessages = document.getElementById('chat-messages');
        const messageDiv = document.createElement('div');
        messageDiv.className = `chat-message ${sender}`;

        messageDiv.innerHTML = `
                <div class="message-avatar">
                    <i class="fas ${sender === 'user' ? 'fa-user' : 'fa-robot'}"></i>
                </div>
                <div class="message-content">${formatMessage(message)}</div>
            `;

        chatMessages.appendChild(messageDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function addTypingIndicator() {
        const chatMessages = document.getElementById('chat-messages');
        const typingDiv = document.createElement('div');
        typingDiv.className = 'chat-message bot';
        typingDiv.id = 'typing-indicator';

        typingDiv.innerHTML = `
                <div class="message-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="message-content" style="background: var(--gray-light); color: var(--gray);">
                    <i class="fas fa-spinner fa-spin"></i> AI is thinking...
                </div>
            `;

        chatMessages.appendChild(typingDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function removeTypingIndicator() {
        const typingIndicator = document.getElementById('typing-indicator');
        if (typingIndicator) {
            typingIndicator.remove();
        }
    }

    function formatMessage(message) {
        return message
            .replace(/\n/g, '<br>')
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/`(.*?)`/g,
                '<code style="background: var(--gray-light); padding: 0.2rem 0.4rem; border-radius: 4px;">$1</code>');
    }

    function contactBuyer(buyerName, phone) {
        const phoneDisplay = phone || '+91 98765 43210';
        const message = `📞 Contact Details for ${buyerName}:

Phone: ${phoneDisplay}
💬 You can call or message the buyer directly to:
• Confirm order details
• Discuss quality requirements  
• Coordinate delivery timing
• Share product updates`;

        alert(message);
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    }

    function animateCounter(elementId, target) {
        const element = document.getElementById(elementId);
        if (!element) return;

        let current = 0;
        const increment = target / 50;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                element.textContent = target;
                clearInterval(timer);
            } else {
                element.textContent = Math.floor(current);
            }
        }, 30);
    }

    function animateRevenue(element, target) {
        if (!element) return;

        let current = 0;
        const increment = target / 100;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                element.textContent = '₹' + target.toLocaleString('en-IN');
                clearInterval(timer);
            } else {
                element.textContent = '₹' + Math.floor(current).toLocaleString('en-IN');
            }
        }, 20);
    }

    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString('en-IN', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.style.cssText = `
                position: fixed;
                top: 100px;
                right: 20px;
                background: ${type === 'error' ? '#DC2626' : type === 'success' ? '#059669' : type === 'warning' ? '#D97706' : 'var(--primary)'};
                color: white;
                padding: 1rem 2rem;
                border-radius: 12px;
                box-shadow: var(--shadow-lg);
                z-index: 10000;
                animation: slideIn 0.3s ease;
                max-width: 400px;
                font-weight: 500;
            `;

        notification.innerHTML = `
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas ${type === 'error' ? 'fa-exclamation-triangle' : type === 'success' ? 'fa-check-circle' : type === 'warning' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; color: white; font-size: 1.2rem; cursor: pointer; margin-left: auto;">×</button>
                </div>
            `;

        document.body.appendChild(notification);

        setTimeout(() => {
            if (notification.parentElement) {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }
        }, 4000);
    }

    const style = document.createElement('style');
    style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
    document.head.appendChild(style);
    </script>
</body>

</html>