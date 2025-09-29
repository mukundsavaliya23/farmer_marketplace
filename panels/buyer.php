<?php
require_once '../config.php';

if (!is_logged_in() || current_user()['user_type'] !== 'buyer') {
    redirect('../index.php');
}

$page_title = 'Buyer Dashboard';
$user = current_user();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_buyer_stats':
            echo json_encode(getBuyerStats($pdo, $user['id']));
            exit;
        case 'get_all_products':
            echo json_encode(getAllProducts($pdo, $_POST));
            exit;
        case 'place_order':
            echo json_encode(placeOrder($pdo, $user['id'], $_POST));
            exit;
        case 'get_buyer_orders':
            echo json_encode(getBuyerOrders($pdo, $user['id']));
            exit;
        case 'cancel_order':
            echo json_encode(cancelOrder($pdo, $_POST['order_id']));
            exit;
        case 'add_to_favorites':
            echo json_encode(addToFavorites($pdo, $user['id'], $_POST['product_id']));
            exit;
        case 'remove_from_favorites':
            echo json_encode(removeFromFavorites($pdo, $user['id'], $_POST['product_id']));
            exit;
        case 'get_favorites':
            echo json_encode(getFavorites($pdo, $user['id']));
            exit;
        case 'update_profile':
            echo json_encode(updateBuyerProfile($pdo, $user['id'], $_POST));
            exit;
    }
}

// Add these new functions to your existing functions
function addToFavorites($pdo, $buyerId, $productId) {
    try {
        // First check if already in favorites
        $stmt = $pdo->prepare("SELECT id FROM favorites WHERE buyer_id = ? AND product_id = ?");
        $stmt->execute([$buyerId, $productId]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Already in favorites'];
        }
        
        $stmt = $pdo->prepare("INSERT INTO favorites (buyer_id, product_id) VALUES (?, ?)");
        $stmt->execute([$buyerId, $productId]);
        return ['success' => true, 'message' => 'Added to favorites'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function removeFromFavorites($pdo, $buyerId, $productId) {
    try {
        $stmt = $pdo->prepare("DELETE FROM favorites WHERE buyer_id = ? AND product_id = ?");
        $stmt->execute([$buyerId, $productId]);
        return ['success' => true, 'message' => 'Removed from favorites'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getFavorites($pdo, $buyerId) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, u.full_name as farmer_name, u.location as farmer_location
            FROM favorites f
            JOIN products p ON f.product_id = p.id
            JOIN users u ON p.farmer_id = u.id
            WHERE f.buyer_id = ? AND p.status = 'available'
            ORDER BY f.created_at DESC
        ");
        $stmt->execute([$buyerId]);
        $favorites = $stmt->fetchAll();
        return ['success' => true, 'data' => $favorites];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function updateBuyerProfile($pdo, $buyerId, $data) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, location = ? WHERE id = ?");
        $stmt->execute([$data['full_name'], $data['phone'], $data['location'], $buyerId]);
        return ['success' => true, 'message' => 'Profile updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Include all existing functions from previous version...
function getBuyerStats($pdo, $buyerId) {
    try {
        $stats = [];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE buyer_id = ?");
        $stmt->execute([$buyerId]);
        $stats['total_orders'] = $stmt->fetch()['count'];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE buyer_id = ? AND status = 'pending'");
        $stmt->execute([$buyerId]);
        $stats['pending_orders'] = $stmt->fetch()['count'];
        
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as spent FROM orders WHERE buyer_id = ? AND status != 'cancelled'");
        $stmt->execute([$buyerId]);
        $stats['total_spent'] = $stmt->fetch()['spent'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE status = 'available'");
        $stats['available_products'] = $stmt->fetch()['count'];
        
        return ['success' => true, 'data' => $stats];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getAllProducts($pdo, $filters = []) {
    try {
        $sql = "
            SELECT p.*, u.full_name as farmer_name, u.phone as farmer_phone, u.location as farmer_location
            FROM products p 
            JOIN users u ON p.farmer_id = u.id 
            WHERE p.status = 'available'
        ";
        
        $params = [];
        
        if (!empty($filters['search'])) {
            $sql .= " AND (p.name LIKE ? OR p.description LIKE ? OR p.category LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['category']) && $filters['category'] !== 'all') {
            $sql .= " AND p.category = ?";
            $params[] = $filters['category'];
        }
        
        if (!empty($filters['location'])) {
            $sql .= " AND u.location LIKE ?";
            $params[] = '%' . $filters['location'] . '%';
        }
        
        $sql .= " ORDER BY p.created_at DESC, p.views_count DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();
        
        return ['success' => true, 'data' => $products];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function placeOrder($pdo, $buyerId, $data) {
    try {
        // Get product details
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 'available'");
        $stmt->execute([$data['product_id']]);
        $product = $stmt->fetch();
        
        if (!$product) {
            return ['success' => false, 'message' => 'Product not available'];
        }
        
        if ($data['quantity'] > $product['quantity']) {
            return ['success' => false, 'message' => 'Requested quantity exceeds available stock'];
        }
        
        $totalAmount = $data['quantity'] * $product['price_per_unit'];
        
        // Place order
        $stmt = $pdo->prepare("INSERT INTO orders (buyer_id, farmer_id, product_id, quantity, unit, total_amount, delivery_address, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $buyerId,
            $product['farmer_id'],
            $data['product_id'],
            $data['quantity'],
            $product['unit'],
            $totalAmount,
            $data['delivery_address'],
            $data['notes'] ?? ''
        ]);
        
        // Update product quantity
        $newQuantity = $product['quantity'] - $data['quantity'];
        $newStatus = $newQuantity <= 0 ? 'sold' : 'available';
        
        $stmt = $pdo->prepare("UPDATE products SET quantity = ?, status = ? WHERE id = ?");
        $stmt->execute([$newQuantity, $newStatus, $data['product_id']]);
        
        return ['success' => true, 'message' => 'Order placed successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getBuyerOrders($pdo, $buyerId) {
    try {
        $stmt = $pdo->prepare("
            SELECT o.*, p.name as product_name, p.unit, 
                   u.full_name as farmer_name, u.phone as farmer_phone, u.location as farmer_location
            FROM orders o
            JOIN products p ON o.product_id = p.id
            JOIN users u ON o.farmer_id = u.id
            WHERE o.buyer_id = ?
            ORDER BY o.order_date DESC
        ");
        $stmt->execute([$buyerId]);
        $orders = $stmt->fetchAll();
        return ['success' => true, 'data' => $orders];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function cancelOrder($pdo, $orderId) {
    try {
        // Get order details
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND status IN ('pending', 'confirmed')");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        if (!$order) {
            return ['success' => false, 'message' => 'Order cannot be cancelled'];
        }
        
        // Update order status
        $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$orderId]);
        
        // Restore product quantity
        $stmt = $pdo->prepare("UPDATE products SET quantity = quantity + ?, status = 'available' WHERE id = ?");
        $stmt->execute([$order['quantity'], $order['product_id']]);
        
        return ['success' => true, 'message' => 'Order cancelled successfully'];
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
        --primary: #3B82F6;
        --primary-light: #60A5FA;
        --primary-dark: #1D4ED8;
        --secondary: #F59E0B;
        --secondary-light: #FBBF24;
        --accent: #10B981;
        --dark: #111827;
        --dark-light: #1F2937;
        --gray: #6B7280;
        --gray-light: #F3F4F6;
        --white: #FFFFFF;
        --gradient-1: linear-gradient(135deg, #3B82F6 0%, #1D4ED8 100%);
        --gradient-2: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
        --gradient-bg: linear-gradient(135deg, #F0F9FF 0%, #E0F2FE 100%);
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

    /* All previous styles from buyer panel... */
    .navbar {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 80px;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        border-bottom: 1px solid rgba(59, 130, 246, 0.1);
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
        animation: cartBounce 2s ease-in-out infinite;
    }

    @keyframes cartBounce {

        0%,
        100% {
            transform: translateY(0px);
        }

        50% {
            transform: translateY(-5px);
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
        background: rgba(59, 130, 246, 0.1);
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

    .buyer-container {
        margin-top: 80px;
        min-height: calc(100vh - 80px);
        padding: 2rem;
    }

    .buyer-header {
        background: var(--gradient-1);
        color: white;
        padding: 3rem 2rem;
        border-radius: 24px;
        margin-bottom: 2rem;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .buyer-header::before {
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

    .buyer-header h1 {
        font-size: 3rem;
        font-weight: 900;
        margin-bottom: 1rem;
        position: relative;
        z-index: 2;
    }

    .buyer-header p {
        font-size: 1.2rem;
        opacity: 0.9;
        position: relative;
        z-index: 2;
    }

    .buyer-nav {
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
    }

    .nav-tab.active {
        background: var(--gradient-1);
        color: white;
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .nav-tab:hover:not(.active) {
        background: rgba(59, 130, 246, 0.1);
        color: var(--primary);
        transform: translateY(-2px);
    }

    .buyer-section {
        display: none;
    }

    .buyer-section.active {
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
        border: 1px solid rgba(59, 130, 246, 0.1);
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

    .search-filters {
        background: white;
        border-radius: 20px;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-md);
    }

    .search-grid {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr auto;
        gap: 1rem;
        align-items: end;
    }

    .search-input,
    .filter-select {
        padding: 1rem;
        border: 2px solid var(--gray-light);
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
        font-family: 'Poppins', sans-serif;
    }

    .search-input:focus,
    .filter-select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .btn-search {
        padding: 1rem 2rem;
        background: var(--gradient-1);
        color: white;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-search:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 2rem;
    }

    .product-card {
        background: white;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: var(--shadow-md);
        transition: all 0.3s ease;
        border: 1px solid rgba(59, 130, 246, 0.1);
        position: relative;
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
    }

    .product-badges {
        position: absolute;
        top: 1rem;
        right: 1rem;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .product-badge {
        background: white;
        color: var(--primary);
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 700;
        box-shadow: var(--shadow-sm);
    }

    .product-badge.organic {
        background: var(--accent);
        color: white;
    }

    .product-info {
        padding: 2rem;
    }

    .product-name {
        font-size: 1.25rem;
        font-weight: 700;
        margin-bottom: 1rem;
        color: var(--dark);
    }

    .product-farmer {
        display: flex;
        align-items: center;
        gap: 1rem;
        color: var(--gray);
        margin-bottom: 1rem;
        font-size: 0.9rem;
    }

    .product-price {
        font-size: 2rem;
        font-weight: 800;
        color: var(--primary);
        margin-bottom: 1rem;
    }

    .product-actions {
        display: flex;
        gap: 0.75rem;
    }

    .btn-order {
        flex: 1;
        padding: 0.75rem 1.5rem;
        background: var(--gradient-1);
        color: white;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .btn-favorite {
        width: 50px;
        height: 50px;
        background: var(--gray-light);
        color: var(--gray);
        border: none;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .btn-order:hover,
    .btn-favorite:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .btn-favorite.active {
        background: #FEE2E2;
        color: #DC2626;
    }

    /* Enhanced Modal Styles with Better Scrolling */
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
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: var(--shadow-xl);
        animation: modalSlideIn 0.3s ease;
        position: relative;
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
    .form-textarea {
        width: 100%;
        padding: 1rem;
        border: 2px solid var(--gray-light);
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
        font-family: 'Poppins', sans-serif;
    }

    .form-input:focus,
    .form-textarea:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
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

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }

    .status-badge {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
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

    /* Responsive */
    @media (max-width: 768px) {
        .nav-tabs {
            flex-direction: column;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .search-grid {
            grid-template-columns: 1fr;
        }

        .products-grid {
            grid-template-columns: 1fr;
        }

        .nav-menu {
            display: none;
        }

        .buyer-header h1 {
            font-size: 2rem;
        }

        .modal-content {
            width: 95%;
            padding: 1.5rem;
            margin: 1rem auto;
        }
    }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="nav-logo">
                <i class="fas fa-shopping-cart"></i>
                <span>Buyer Panel</span>
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

    <div class="buyer-container">
        <!-- Header -->
        <div class="buyer-header" data-aos="fade-down">
            <h1>🛒 Buyer Dashboard</h1>
            <p>Discover fresh produce directly from farmers across India with guaranteed quality and fair prices</p>
        </div>

        <!-- Navigation Tabs -->
        <div class="buyer-nav" data-aos="fade-up" data-aos-delay="100">
            <div class="nav-tabs">
                <button class="nav-tab active" onclick="showSection('dashboard')" data-section="dashboard">
                    <i class="fas fa-chart-pie"></i>
                    Dashboard
                </button>
                <button class="nav-tab" onclick="showSection('marketplace')" data-section="marketplace">
                    <i class="fas fa-store"></i>
                    Marketplace
                </button>
                <button class="nav-tab" onclick="showSection('orders')" data-section="orders">
                    <i class="fas fa-receipt"></i>
                    My Orders
                </button>
                <button class="nav-tab" onclick="showSection('favorites')" data-section="favorites">
                    <i class="fas fa-heart"></i>
                    Favorites
                </button>
                <button class="nav-tab" onclick="showSection('profile')" data-section="profile">
                    <i class="fas fa-user-edit"></i>
                    Profile
                </button>
            </div>
        </div>

        <!-- Dashboard Section -->
        <div id="dashboard" class="buyer-section active">
            <div class="stats-grid" data-aos="fade-up" data-aos-delay="200">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <span class="stat-number" id="total-orders">0</span>
                    <div class="stat-label">Total Orders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <span class="stat-number" id="pending-orders">0</span>
                    <div class="stat-label">Pending Orders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-rupee-sign"></i>
                    </div>
                    <span class="stat-number" id="total-spent">₹0</span>
                    <div class="stat-label">Total Spent</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-leaf"></i>
                    </div>
                    <span class="stat-number" id="available-products">0</span>
                    <div class="stat-label">Available Products</div>
                </div>
            </div>
        </div>

        <!-- Marketplace Section -->
        <div id="marketplace" class="buyer-section">
            <div class="search-filters" data-aos="fade-up">
                <div class="search-grid">
                    <input type="text" id="search-input" class="search-input"
                        placeholder="Search for products, farmers, or categories...">
                    <select id="category-filter" class="filter-select">
                        <option value="">All Categories</option>
                        <option value="vegetables">Vegetables</option>
                        <option value="fruits">Fruits</option>
                        <option value="grains">Grains</option>
                        <option value="pulses">Pulses</option>
                        <option value="spices">Spices</option>
                    </select>
                    <input type="text" id="location-filter" class="search-input" placeholder="Location">
                    <button class="btn-search" onclick="searchProducts()">
                        <i class="fas fa-search"></i>
                        Search
                    </button>
                </div>
            </div>
            <div id="products-grid" class="products-grid" data-aos="fade-up" data-aos-delay="200">
                <!-- Products will be loaded here -->
            </div>
        </div>

        <!-- Orders Section -->
        <div id="orders" class="buyer-section">
            <div id="orders-list" data-aos="fade-up">
                <!-- Orders will be loaded here -->
            </div>
        </div>

        <!-- Favorites Section -->
        <div id="favorites" class="buyer-section">
            <div id="favorites-list" data-aos="fade-up">
                <!-- Favorites will be loaded here -->
            </div>
        </div>

        <!-- Profile Section -->
        <div id="profile" class="buyer-section">
            <div
                style="background: white; border-radius: 20px; padding: 2.5rem; box-shadow: var(--shadow-md); margin-bottom: 2rem;">
                <h2
                    style="font-size: 2rem; font-weight: 800; margin-bottom: 2rem; color: var(--dark); display: flex; align-items: center; gap: 1rem;">
                    <i class="fas fa-user-edit" style="color: var(--primary);"></i>
                    Edit Profile
                </h2>
                <form id="profile-form"
                    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                    <div class="form-group">
                        <label for="profile-name" class="form-label">Full Name</label>
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
                        <label for="profile-phone" class="form-label">Phone Number</label>
                        <input type="tel" id="profile-phone" name="phone" class="form-input"
                            value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" pattern="[0-9]{10}" required>
                    </div>
                    <div class="form-group">
                        <label for="profile-location" class="form-label">Location</label>
                        <input type="text" id="profile-location" name="location" class="form-input"
                            value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>" placeholder="City, State">
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

    <!-- Order Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Place Order</h2>
                <button class="modal-close" onclick="closeModal('orderModal')">&times;</button>
            </div>
            <form id="orderForm">
                <input type="hidden" id="order-product-id" name="product_id">
                <div id="order-product-info" style="margin-bottom: 2rem;">
                    <!-- Product info will be populated here -->
                </div>
                <div class="form-group">
                    <label for="order-quantity" class="form-label">Quantity *</label>
                    <input type="number" id="order-quantity" name="quantity" class="form-input" required min="1"
                        onchange="calculateTotal()" placeholder="Enter quantity">
                </div>
                <div class="form-group">
                    <label for="delivery-address" class="form-label">Delivery Address *</label>
                    <textarea id="delivery-address" name="delivery_address" class="form-textarea" required
                        placeholder="Enter your complete delivery address with landmarks..."></textarea>
                </div>
                <div class="form-group">
                    <label for="order-notes" class="form-label">Special Instructions (Optional)</label>
                    <textarea id="order-notes" name="notes" class="form-textarea"
                        placeholder="Any special requirements, quality preferences, or delivery instructions..."></textarea>
                </div>
                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding: 1.5rem; background: var(--gradient-bg); border-radius: 12px; border: 1px solid rgba(59, 130, 246, 0.1);">
                    <strong style="font-size: 1.1rem;">Total Amount:</strong>
                    <span id="total-amount"
                        style="font-size: 1.8rem; font-weight: 800; color: var(--primary);">₹0</span>
                </div>
                <button type="submit"
                    style="width: 100%; padding: 1.25rem; background: var(--gradient-1); color: white; border: none; border-radius: 12px; font-weight: 600; cursor: pointer; font-size: 1.1rem; transition: all 0.3s ease;">
                    <i class="fas fa-shopping-cart"></i>
                    Place Order
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
    let currentProducts = [];
    let userFavorites = new Set();

    document.addEventListener('DOMContentLoaded', function() {
        loadBuyerStats();
        loadUserFavorites();
    });

    function showSection(sectionId) {
        document.querySelectorAll('.buyer-section').forEach(section => {
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
                loadBuyerStats();
                break;
            case 'marketplace':
                loadProducts();
                break;
            case 'orders':
                loadOrders();
                break;
            case 'favorites':
                loadFavorites();
                break;
        }
    }

    async function apiCall(action, data = {}) {
        try {
            const formData = new FormData();
            formData.append('action', action);
            Object.keys(data).forEach(key => {
                formData.append(key, data[key]);
            });

            const response = await fetch('buyer.php', {
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

    async function loadBuyerStats() {
        const result = await apiCall('get_buyer_stats');
        if (result.success) {
            const stats = result.data;
            animateCounter('total-orders', stats.total_orders);
            animateCounter('pending-orders', stats.pending_orders);
            animateCounter('available-products', stats.available_products);

            const spentElement = document.getElementById('total-spent');
            animateRevenue(spentElement, stats.total_spent);
        }
    }

    async function loadUserFavorites() {
        const result = await apiCall('get_favorites');
        if (result.success) {
            userFavorites.clear();
            result.data.forEach(product => {
                userFavorites.add(product.id);
            });
        }
    }

    async function loadProducts() {
        const filters = {
            search: document.getElementById('search-input').value,
            category: document.getElementById('category-filter').value,
            location: document.getElementById('location-filter').value
        };

        const result = await apiCall('get_all_products', filters);
        const container = document.getElementById('products-grid');

        if (result.success) {
            if (result.data.length === 0) {
                container.innerHTML = `
                        <div style="grid-column: 1 / -1; text-align: center; padding: 4rem; background: white; border-radius: 20px; box-shadow: var(--shadow-md);">
                            <i class="fas fa-seedling" style="font-size: 4rem; color: var(--primary); margin-bottom: 2rem;"></i>
                            <h3 style="color: var(--dark); margin-bottom: 1rem;">No Products Found</h3>
                            <p style="color: var(--gray);">Try adjusting your search filters or check back later.</p>
                        </div>
                    `;
                return;
            }

            container.innerHTML = result.data.map(product => {
                const isFavorite = userFavorites.has(product.id);
                return `
                        <div class="product-card">
                            <div class="product-image">
                                <i class="fas fa-seedling"></i>
                                <div class="product-badges">
                                    ${product.organic == 1 ? '<span class="product-badge organic">🌿 Organic</span>' : ''}
                                    ${product.quality_grade === 'A' ? '<span class="product-badge premium">⭐ Premium</span>' : ''}
                                </div>
                            </div>
                            <div class="product-info">
                                <h3 class="product-name">${product.name}</h3>
                                <div class="product-farmer">
                                    <i class="fas fa-user"></i>
                                    <span>${product.farmer_name}</span>
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span>${product.farmer_location || 'India'}</span>
                                </div>
                                <div class="product-price">₹${product.price_per_unit}/${product.unit}</div>
                                <p style="color: var(--gray); font-size: 0.9rem; margin-bottom: 1.5rem; line-height: 1.5;">
                                    ${product.description ? product.description.substring(0, 100) + '...' : 'Fresh produce available'}
                                </p>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; color: var(--gray); font-size: 0.9rem;">
                                    <span><i class="fas fa-box"></i> ${product.quantity} ${product.unit} available</span>
                                    <span style="color: var(--accent); font-weight: 600;">Grade ${product.quality_grade || 'B'}</span>
                                </div>
                                <div class="product-actions">
                                    <button class="btn-order" onclick="openOrderModal(${product.id}, '${product.name.replace(/'/g, "\\'")}', ${product.price_per_unit}, '${product.unit}', ${product.quantity}, '${product.farmer_name.replace(/'/g, "\\'")}')">
                                        <i class="fas fa-shopping-cart"></i>
                                        Order Now
                                    </button>
                                    <button class="btn-favorite ${isFavorite ? 'active' : ''}" onclick="toggleFavorite(${product.id}, this)" data-product-id="${product.id}">
                                        <i class="fas fa-heart"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
            }).join('');

            currentProducts = result.data;
        }
    }

    async function loadOrders() {
        const result = await apiCall('get_buyer_orders');
        const container = document.getElementById('orders-list');

        if (result.success) {
            if (result.data.length === 0) {
                container.innerHTML = `
                        <div style="text-align: center; padding: 4rem; background: white; border-radius: 20px; box-shadow: var(--shadow-md);">
                            <i class="fas fa-shopping-bag" style="font-size: 4rem; color: var(--primary); margin-bottom: 2rem;"></i>
                            <h3 style="color: var(--dark); margin-bottom: 1rem;">No Orders Yet</h3>
                            <p style="color: var(--gray); margin-bottom: 2rem;">Start shopping in our marketplace to see your orders here.</p>
                            <button onclick="showSection('marketplace')" style="padding: 1rem 2rem; background: var(--gradient-1); color: white; border: none; border-radius: 12px; font-weight: 600; cursor: pointer;">
                                <i class="fas fa-store"></i> Browse Marketplace
                            </button>
                        </div>
                    `;
                return;
            }

            container.innerHTML = result.data.map(order => `
                    <div style="background: white; border-radius: 20px; padding: 2rem; margin-bottom: 2rem; box-shadow: var(--shadow-md); border: 1px solid rgba(59, 130, 246, 0.1);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                            <h3 style="color: var(--dark);">Order #${order.id}</h3>
                            <span class="status-badge status-${order.status}">${order.status}</span>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
                            <div>
                                <p><strong>Product:</strong> ${order.product_name}</p>
                                <p><strong>Quantity:</strong> ${order.quantity} ${order.unit}</p>
                                <p><strong>Amount:</strong> <span style="color: var(--primary); font-weight: 800;">₹${order.total_amount}</span></p>
                            </div>
                            <div>
                                <p><strong>Farmer:</strong> ${order.farmer_name}</p>
                                <p><strong>Location:</strong> ${order.farmer_location || 'India'}</p>
                                <p><strong>Order Date:</strong> ${formatDate(order.order_date)}</p>
                            </div>
                        </div>
                        ${order.delivery_address ? `<div style="background: var(--gray-light); padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem;"><strong>Delivery Address:</strong><br>${order.delivery_address}</div>` : ''}
                        ${order.notes ? `<div style="background: var(--gray-light); padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem;"><strong>Special Instructions:</strong><br>${order.notes}</div>` : ''}
                        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            ${order.status === 'pending' || order.status === 'confirmed' ? `
                                <button onclick="cancelOrder(${order.id})" style="padding: 0.75rem 1.5rem; background: linear-gradient(135deg, #DC2626, #B91C1C); color: white; border: none; border-radius: 12px; font-weight: 600; cursor: pointer; transition: all 0.3s ease;">
                                    <i class="fas fa-times"></i> Cancel Order
                                </button>
                            ` : ''}
                            <button onclick="contactFarmer('${order.farmer_name}', '${order.farmer_phone || ''}')" style="padding: 0.75rem 1.5rem; background: var(--gradient-2); color: white; border: none; border-radius: 12px; font-weight: 600; cursor: pointer; transition: all 0.3s ease;">
                                <i class="fas fa-phone"></i> Contact Farmer
                            </button>
                        </div>
                    </div>
                `).join('');
        }
    }

    async function loadFavorites() {
        const result = await apiCall('get_favorites');
        const container = document.getElementById('favorites-list');

        if (result.success) {
            if (result.data.length === 0) {
                container.innerHTML = `
                        <div style="text-align: center; padding: 4rem; background: white; border-radius: 20px; box-shadow: var(--shadow-md);">
                            <i class="fas fa-heart" style="font-size: 4rem; color: #DC2626; margin-bottom: 2rem;"></i>
                            <h3 style="color: var(--dark); margin-bottom: 1rem;">No Favorites Yet</h3>
                            <p style="color: var(--gray); margin-bottom: 2rem;">Add products to favorites while browsing to see them here.</p>
                            <button onclick="showSection('marketplace')" style="padding: 1rem 2rem; background: var(--gradient-1); color: white; border: none; border-radius: 12px; font-weight: 600; cursor: pointer;">
                                <i class="fas fa-store"></i> Browse Marketplace
                            </button>
                        </div>
                    `;
                return;
            }

            container.innerHTML = `
                    <div class="products-grid">
                        ${result.data.map(product => `
                            <div class="product-card">
                                <div class="product-image">
                                    <i class="fas fa-seedling"></i>
                                    <div class="product-badges">
                                        ${product.organic == 1 ? '<span class="product-badge organic">🌿 Organic</span>' : ''}
                                        <span class="product-badge" style="background: #FEE2E2; color: #DC2626;">❤️ Favorite</span>
                                    </div>
                                </div>
                                <div class="product-info">
                                    <h3 class="product-name">${product.name}</h3>
                                    <div class="product-farmer">
                                        <i class="fas fa-user"></i>
                                        <span>${product.farmer_name}</span>
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span>${product.farmer_location || 'India'}</span>
                                    </div>
                                    <div class="product-price">₹${product.price_per_unit}/${product.unit}</div>
                                    <div class="product-actions">
                                        <button class="btn-order" onclick="openOrderModal(${product.id}, '${product.name.replace(/'/g, "\\'")}', ${product.price_per_unit}, '${product.unit}', ${product.quantity}, '${product.farmer_name.replace(/'/g, "\\'")}')">
                                            <i class="fas fa-shopping-cart"></i>
                                            Order Now
                                        </button>
                                        <button class="btn-favorite active" onclick="toggleFavorite(${product.id}, this)">
                                            <i class="fas fa-heart"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                `;
        }
    }

    async function toggleFavorite(productId, button) {
        const isFavorite = userFavorites.has(productId);
        const action = isFavorite ? 'remove_from_favorites' : 'add_to_favorites';

        const result = await apiCall(action, {
            product_id: productId
        });

        if (result.success) {
            if (isFavorite) {
                userFavorites.delete(productId);
                button.classList.remove('active');
                showNotification('💔 Removed from favorites', 'info');
            } else {
                userFavorites.add(productId);
                button.classList.add('active');
                showNotification('❤️ Added to favorites!', 'success');
            }

            // If we're on favorites page, reload it
            if (currentSection === 'favorites') {
                loadFavorites();
            }
        } else {
            showNotification('Failed to update favorites: ' + result.message, 'error');
        }
    }

    function openOrderModal(productId, productName, pricePerUnit, unit, availableQuantity, farmerName) {
        document.getElementById('order-product-id').value = productId;
        document.getElementById('order-product-info').innerHTML = `
                <div style="background: var(--gradient-bg); padding: 2rem; border-radius: 16px; border: 1px solid rgba(59, 130, 246, 0.1);">
                    <h3 style="margin: 0 0 1rem 0; color: var(--dark); font-size: 1.3rem;">${productName}</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; color: var(--gray);">
                        <p><strong>Farmer:</strong><br>${farmerName}</p>
                        <p><strong>Price:</strong><br>₹${pricePerUnit} per ${unit}</p>
                        <p><strong>Available:</strong><br>${availableQuantity} ${unit}</p>
                    </div>
                </div>
            `;

        document.getElementById('order-product-info').setAttribute('data-price', pricePerUnit);
        document.getElementById('order-quantity').setAttribute('max', availableQuantity);
        document.getElementById('order-quantity').value = 1;
        document.getElementById('total-amount').textContent = `₹${pricePerUnit}`;

        document.getElementById('delivery-address').value = '';
        document.getElementById('order-notes').value = '';

        document.getElementById('orderModal').style.display = 'block';

        // Scroll to top of modal
        document.querySelector('.modal-content').scrollTop = 0;
    }

    function calculateTotal() {
        const quantity = parseInt(document.getElementById('order-quantity').value) || 0;
        const pricePerUnit = parseFloat(document.getElementById('order-product-info').getAttribute('data-price')) || 0;
        const maxQuantity = parseInt(document.getElementById('order-quantity').getAttribute('max')) || 0;

        if (quantity > maxQuantity) {
            document.getElementById('order-quantity').value = maxQuantity;
            showNotification(`Maximum available quantity is ${maxQuantity}`, 'warning');
            return;
        }

        const total = quantity * pricePerUnit;
        document.getElementById('total-amount').textContent = `₹${total.toFixed(2)}`;
    }

    // Profile form submission
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

    // Order form submission
    document.getElementById('orderForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const productId = document.getElementById('order-product-id').value;
        const quantity = parseInt(document.getElementById('order-quantity').value);
        const deliveryAddress = document.getElementById('delivery-address').value.trim();
        const notes = document.getElementById('order-notes').value.trim();

        if (!deliveryAddress) {
            showNotification('Please enter delivery address', 'error');
            return;
        }

        if (quantity <= 0) {
            showNotification('Please enter valid quantity', 'error');
            return;
        }

        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Placing Order...';
        submitBtn.disabled = true;

        const result = await apiCall('place_order', {
            product_id: productId,
            quantity: quantity,
            delivery_address: deliveryAddress,
            notes: notes
        });

        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;

        if (result.success) {
            showNotification('🎉 Order placed successfully! Farmer will contact you soon.', 'success');
            closeModal('orderModal');
            loadOrders();
            loadBuyerStats();
            loadProducts();
        } else {
            showNotification('Failed to place order: ' + result.message, 'error');
        }
    });

    async function cancelOrder(orderId) {
        if (!confirm('Are you sure you want to cancel this order? This action cannot be undone.')) {
            return;
        }

        const result = await apiCall('cancel_order', {
            order_id: orderId
        });

        if (result.success) {
            showNotification('Order cancelled successfully', 'success');
            loadOrders();
            loadBuyerStats();
            loadProducts();
        } else {
            showNotification('Failed to cancel order: ' + result.message, 'error');
        }
    }

    function contactFarmer(farmerName, phone) {
        const phoneDisplay = phone || '+91 98765 43210';
        const message = `📞 Contact ${farmerName}:

Phone: ${phoneDisplay}
Email: ${farmerName.toLowerCase().replace(/\s+/g, '.')}@farmconnect.com

You can call the farmer directly to:
• Discuss product quality
• Coordinate delivery
• Ask for bulk discounts
• Share special requirements`;

        alert(message);
    }

    function searchProducts() {
        loadProducts();
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    // Auto-search functionality
    let searchTimeout;
    document.getElementById('search-input').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            if (currentSection === 'marketplace') {
                searchProducts();
            }
        }, 500);
    });

    document.getElementById('category-filter').addEventListener('change', function() {
        if (currentSection === 'marketplace') {
            searchProducts();
        }
    });

    document.getElementById('location-filter').addEventListener('change', function() {
        if (currentSection === 'marketplace') {
            searchProducts();
        }
    });

    document.getElementById('order-quantity').addEventListener('input', calculateTotal);

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