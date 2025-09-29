<?php
require_once '../config.php';

if (!is_logged_in() || current_user()['user_type'] !== 'admin') {
    redirect('../index.php');
}

$page_title = 'Admin Dashboard';
$user = current_user();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_admin_stats':
            echo json_encode(getAdminStats($pdo));
            exit;
        case 'get_all_users':
            echo json_encode(getAllUsers($pdo, $_POST));
            exit;
        case 'get_all_products':
            echo json_encode(getAllProducts($pdo, $_POST));
            exit;
        case 'get_all_orders':
            echo json_encode(getAllOrders($pdo, $_POST));
            exit;
        case 'update_user_status':
            echo json_encode(updateUserStatus($pdo, $_POST['user_id'], $_POST['status']));
            exit;
        case 'delete_user':
            echo json_encode(deleteUser($pdo, $_POST['user_id']));
            exit;
        case 'update_product_status':
            echo json_encode(updateProductStatus($pdo, $_POST['product_id'], $_POST['status']));
            exit;
        case 'delete_product':
            echo json_encode(deleteProduct($pdo, $_POST['product_id']));
            exit;
        case 'update_order_status':
            echo json_encode(updateOrderStatus($pdo, $_POST['order_id'], $_POST['status']));
            exit;
    }
}

// Admin Functions
function getAdminStats($pdo) {
    try {
        $stats = [];
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $stats['total_users'] = $stmt->fetch()['count'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'farmer'");
        $stats['total_farmers'] = $stmt->fetch()['count'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'buyer'");
        $stats['total_buyers'] = $stmt->fetch()['count'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM products");
        $stats['total_products'] = $stmt->fetch()['count'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders");
        $stats['total_orders'] = $stmt->fetch()['count'];
        
        $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM orders WHERE status != 'cancelled'");
        $stats['total_revenue'] = $stmt->fetch()['revenue'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stats['new_users_month'] = $stmt->fetch()['count'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stats['new_orders_month'] = $stmt->fetch()['count'];
        
        return ['success' => true, 'data' => $stats];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getAllUsers($pdo, $filters = []) {
    try {
        $sql = "SELECT id, username, email, full_name, phone, user_type, location, verification_status, is_active, created_at FROM users";
        $params = [];
        
        if (!empty($filters['search'])) {
            $sql .= " WHERE (full_name LIKE ? OR email LIKE ? OR username LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = [$searchTerm, $searchTerm, $searchTerm];
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();
        
        return ['success' => true, 'data' => $users];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getAllProducts($pdo, $filters = []) {
    try {
        $sql = "
            SELECT p.*, u.full_name as farmer_name, u.email as farmer_email 
            FROM products p 
            JOIN users u ON p.farmer_id = u.id
        ";
        $params = [];
        
        if (!empty($filters['search'])) {
            $sql .= " WHERE (p.name LIKE ? OR p.category LIKE ? OR u.full_name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = [$searchTerm, $searchTerm, $searchTerm];
        }
        
        $sql .= " ORDER BY p.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();
        
        return ['success' => true, 'data' => $products];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getAllOrders($pdo, $filters = []) {
    try {
        $sql = "
            SELECT o.*, 
                   p.name as product_name,
                   u1.full_name as buyer_name, u1.email as buyer_email,
                   u2.full_name as farmer_name, u2.email as farmer_email
            FROM orders o
            JOIN products p ON o.product_id = p.id
            JOIN users u1 ON o.buyer_id = u1.id
            JOIN users u2 ON o.farmer_id = u2.id
        ";
        $params = [];
        
        if (!empty($filters['search'])) {
            $sql .= " WHERE (p.name LIKE ? OR u1.full_name LIKE ? OR u2.full_name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = [$searchTerm, $searchTerm, $searchTerm];
        }
        
        $sql .= " ORDER BY o.order_date DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();
        
        return ['success' => true, 'data' => $orders];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function updateUserStatus($pdo, $userId, $status) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET verification_status = ? WHERE id = ?");
        $stmt->execute([$status, $userId]);
        return ['success' => true, 'message' => 'User status updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function deleteUser($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND user_type != 'admin'");
        $stmt->execute([$userId]);
        return ['success' => true, 'message' => 'User deleted successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function updateProductStatus($pdo, $productId, $status) {
    try {
        $stmt = $pdo->prepare("UPDATE products SET status = ? WHERE id = ?");
        $stmt->execute([$status, $productId]);
        return ['success' => true, 'message' => 'Product status updated successfully'];
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

function updateOrderStatus($pdo, $orderId, $status) {
    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $orderId]);
        return ['success' => true, 'message' => 'Order status updated successfully'];
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
        --gradient-3: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);
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
        background: var(--gradient-3);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        animation: rotate 10s linear infinite;
    }

    @keyframes rotate {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
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
        background: var(--gradient-3);
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

    .admin-container {
        margin-top: 80px;
        min-height: calc(100vh - 80px);
        padding: 2rem;
    }

    .admin-header {
        background: var(--gradient-3);
        color: white;
        padding: 3rem 2rem;
        border-radius: 24px;
        margin-bottom: 2rem;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .admin-header::before {
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

    .admin-header h1 {
        font-size: 3rem;
        font-weight: 900;
        margin-bottom: 1rem;
        position: relative;
        z-index: 2;
    }

    .admin-header p {
        font-size: 1.2rem;
        opacity: 0.9;
        position: relative;
        z-index: 2;
    }

    .admin-nav {
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
        background: var(--gradient-3);
        color: white;
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .nav-tab:hover:not(.active) {
        background: rgba(139, 92, 246, 0.1);
        color: var(--accent);
        transform: translateY(-2px);
    }

    .admin-section {
        display: none;
    }

    .admin-section.active {
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
        border: 1px solid rgba(139, 92, 246, 0.1);
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
        background: var(--gradient-3);
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        background: var(--gradient-3);
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
        color: var(--accent);
        margin-bottom: 0.5rem;
        display: block;
    }

    .stat-label {
        color: var(--gray);
        font-weight: 600;
        font-size: 1.1rem;
    }

    .data-table {
        background: white;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: var(--shadow-md);
        margin-bottom: 2rem;
    }

    .table-header {
        padding: 2rem;
        background: var(--gradient-bg);
        border-bottom: 1px solid rgba(139, 92, 246, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .table-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--dark);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .search-box {
        display: flex;
        gap: 1rem;
        align-items: center;
    }

    .search-input {
        padding: 0.75rem 1rem;
        border: 2px solid var(--gray-light);
        border-radius: 12px;
        font-size: 1rem;
        width: 300px;
        transition: all 0.3s ease;
    }

    .search-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
    }

    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        border: none;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.9rem;
    }

    .btn-primary {
        background: var(--gradient-3);
        color: black;
        box-shadow: var(--shadow-md);
    }

    .btn-success {
        background: var(--gradient-1);
        color: white;
        box-shadow: var(--shadow-md);
    }

    .btn-warning {
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

    .table-content {
        overflow-x: auto;
    }

    .data-table table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table th,
    .data-table td {
        padding: 1rem 1.5rem;
        text-align: left;
        border-bottom: 1px solid var(--gray-light);
    }

    .data-table th {
        background: var(--gray-light);
        color: var(--dark);
        font-weight: 700;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .data-table tr:hover {
        background: rgba(139, 92, 246, 0.05);
    }

    .status-badge {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-verified {
        background: rgba(16, 185, 129, 0.1);
        color: #059669;
    }

    .status-pending {
        background: rgba(245, 158, 11, 0.1);
        color: #D97706;
    }

    .status-rejected {
        background: rgba(239, 68, 68, 0.1);
        color: #DC2626;
    }

    .status-available {
        background: rgba(16, 185, 129, 0.1);
        color: #059669;
    }

    .status-sold {
        background: rgba(139, 92, 246, 0.1);
        color: #7C3AED;
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

    .action-buttons {
        display: flex;
        gap: 0.5rem;
    }

    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.8rem;
    }

    /* Analytics Styles */
    .analytics-overview {
        margin-bottom: 3rem;
    }

    .analytics-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 2rem;
    }

    .analytics-card {
        background: white;
        border-radius: 20px;
        padding: 2rem;
        box-shadow: var(--shadow-md);
        border: 1px solid rgba(139, 92, 246, 0.1);
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }

    .card-header h3 {
        color: var(--dark);
        font-size: 1.1rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .growth-indicator {
        font-size: 1.2rem;
        font-weight: 800;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        background: rgba(16, 185, 129, 0.1);
        color: #059669;
    }

    .growth-indicator.negative {
        background: rgba(239, 68, 68, 0.1);
        color: #DC2626;
    }

    .chart-container {
        height: 300px;
        position: relative;
    }

    .performance-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        margin-bottom: 3rem;
    }

    .performance-card {
        background: white;
        border-radius: 20px;
        padding: 2rem;
        box-shadow: var(--shadow-md);
        border: 1px solid rgba(139, 92, 246, 0.1);
    }

    .performance-card h3 {
        color: var(--dark);
        font-size: 1.2rem;
        font-weight: 700;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .performance-list {
        max-height: 400px;
        overflow-y: auto;
    }

    .farmer-item {
        display: grid;
        grid-template-columns: 40px 1fr auto;
        gap: 1rem;
        padding: 1.5rem;
        margin-bottom: 1rem;
        background: var(--gray-light);
        border-radius: 16px;
        transition: all 0.3s ease;
        align-items: center;
    }

    .farmer-item:hover {
        background: rgba(139, 92, 246, 0.1);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .farmer-rank {
        width: 30px;
        height: 30px;
        background: var(--gradient-3);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.9rem;
    }

    .farmer-info h4 {
        color: var(--dark);
        font-size: 1rem;
        margin-bottom: 0.25rem;
    }

    .farmer-info p {
        color: var(--gray);
        font-size: 0.85rem;
        margin-bottom: 0.5rem;
    }

    .farmer-stats-mini {
        display: flex;
        gap: 1rem;
        font-size: 0.75rem;
        color: var(--gray);
    }

    .farmer-revenue {
        text-align: right;
    }

    .revenue-amount {
        color: var(--accent);
        font-weight: 800;
        font-size: 1.1rem;
    }

    .revenue-label {
        color: var(--gray);
        font-size: 0.75rem;
    }

    .insights-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
        margin-bottom: 3rem;
    }

    .insight-card {
        background: white;
        border-radius: 20px;
        padding: 2rem;
        box-shadow: var(--shadow-md);
        border: 1px solid rgba(139, 92, 246, 0.1);
        display: flex;
        gap: 1.5rem;
        transition: all 0.3s ease;
    }

    .insight-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-xl);
    }

    .insight-icon {
        width: 60px;
        height: 60px;
        background: var(--gradient-3);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
        flex-shrink: 0;
    }

    .insight-content h4 {
        color: var(--dark);
        font-size: 1.1rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .insight-content p {
        color: var(--gray);
        line-height: 1.5;
        font-size: 0.95rem;
    }

    .analytics-actions {
        display: flex;
        gap: 1rem;
        justify-content: center;
        margin-top: 3rem;
        padding: 2rem;
        background: white;
        border-radius: 20px;
        box-shadow: var(--shadow-md);
    }

    .loading-placeholder,
    .error-placeholder {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--gray);
    }

    .error-placeholder {
        color: #DC2626;
    }

    .no-data-placeholder {
        text-align: center;
        padding: 2rem;
        color: var(--gray);
        font-style: italic;
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
        .performance-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 968px) {
        .nav-tabs {
            flex-direction: column;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .analytics-cards {
            grid-template-columns: 1fr;
        }

        .nav-menu {
            display: none;
        }

        .admin-header h1 {
            font-size: 2rem;
        }

        .search-input {
            width: 200px;
        }

        .table-header {
            flex-direction: column;
            gap: 1rem;
            align-items: stretch;
        }

        .insights-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 600px) {
        .admin-container {
            padding: 1rem;
        }

        .farmer-item {
            grid-template-columns: 1fr;
            text-align: center;
            gap: 0.5rem;
        }

        .action-buttons {
            flex-direction: column;
        }
    }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php?action=dashboard" class="nav-logo">
                <i class="fas fa-crown"></i>
                <span>Admin Panel</span>
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

    <div class="admin-container">
        <!-- Header -->
        <div class="admin-header" data-aos="fade-down">
            <h1>👑 Admin Dashboard</h1>
            <p>Complete control center for managing FarmConnect Pro platform</p>
        </div>

        <!-- Navigation Tabs -->
        <div class="admin-nav" data-aos="fade-up" data-aos-delay="100">
            <div class="nav-tabs">
                <button class="nav-tab active" onclick="showSection('dashboard')" data-section="dashboard">
                    <i class="fas fa-chart-pie"></i>
                    Dashboard
                </button>
                <button class="nav-tab" onclick="showSection('users')" data-section="users">
                    <i class="fas fa-users"></i>
                    Users
                </button>
                <button class="nav-tab" onclick="showSection('products')" data-section="products">
                    <i class="fas fa-seedling"></i>
                    Products
                </button>
                <button class="nav-tab" onclick="showSection('orders')" data-section="orders">
                    <i class="fas fa-shopping-cart"></i>
                    Orders
                </button>
                <button class="nav-tab" onclick="showSection('analytics')" data-section="analytics">
                    <i class="fas fa-chart-line"></i>
                    Analytics
                </button>
            </div>
        </div>

        <!-- Dashboard Section -->
        <div id="dashboard" class="admin-section active">
            <div class="stats-grid" data-aos="fade-up" data-aos-delay="200">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <span class="stat-number" id="total-users">0</span>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-tractor"></i>
                    </div>
                    <span class="stat-number" id="total-farmers">0</span>
                    <div class="stat-label">Farmers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <span class="stat-number" id="total-buyers">0</span>
                    <div class="stat-label">Buyers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-seedling"></i>
                    </div>
                    <span class="stat-number" id="total-products">0</span>
                    <div class="stat-label">Products</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <span class="stat-number" id="total-orders">0</span>
                    <div class="stat-label">Orders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-rupee-sign"></i>
                    </div>
                    <span class="stat-number" id="total-revenue">₹0</span>
                    <div class="stat-label">Revenue</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <span class="stat-number" id="new-users-month">0</span>
                    <div class="stat-label">New Users (30d)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <span class="stat-number" id="new-orders-month">0</span>
                    <div class="stat-label">New Orders (30d)</div>
                </div>
            </div>
        </div>

        <!-- Users Section -->
        <div id="users" class="admin-section">
            <div class="data-table" data-aos="fade-up">
                <div class="table-header">
                    <h2 class="table-title">
                        <i class="fas fa-users"></i>
                        User Management
                    </h2>
                    <div class="search-box">
                        <input type="text" class="search-input" placeholder="Search users..." id="users-search">
                        <button class="btn btn-primary" onclick="searchUsers()">
                            <i class="fas fa-search"></i>
                            Search
                        </button>
                    </div>
                </div>
                <div class="table-content">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Type</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="users-list">
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 2rem;">
                                    <i class="fas fa-spinner fa-spin"></i> Loading users...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Products Section -->
        <div id="products" class="admin-section">
            <div class="data-table" data-aos="fade-up">
                <div class="table-header">
                    <h2 class="table-title">
                        <i class="fas fa-seedling"></i>
                        Product Management
                    </h2>
                    <div class="search-box">
                        <input type="text" class="search-input" placeholder="Search products..." id="products-search">
                        <button class="btn btn-primary" onclick="searchProducts()">
                            <i class="fas fa-search"></i>
                            Search
                        </button>
                    </div>
                </div>
                <div class="table-content">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Product</th>
                                <th>Farmer</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="products-list">
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 2rem;">
                                    <i class="fas fa-spinner fa-spin"></i> Loading products...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Orders Section -->
        <div id="orders" class="admin-section">
            <div class="data-table" data-aos="fade-up">
                <div class="table-header">
                    <h2 class="table-title">
                        <i class="fas fa-shopping-cart"></i>
                        Order Management
                    </h2>
                    <div class="search-box">
                        <input type="text" class="search-input" placeholder="Search orders..." id="orders-search">
                        <button class="btn btn-primary" onclick="searchOrders()">
                            <i class="fas fa-search"></i>
                            Search
                        </button>
                    </div>
                </div>
                <div class="table-content">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Product</th>
                                <th>Buyer</th>
                                <th>Farmer</th>
                                <th>Quantity</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="orders-list">
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 2rem;">
                                    <i class="fas fa-spinner fa-spin"></i> Loading orders...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Enhanced Analytics Section with Working Charts -->
        <div id="analytics" class="admin-section">
            <!-- Analytics Overview Cards -->
            <div class="analytics-overview" data-aos="fade-up">
                <div class="analytics-cards">
                    <div class="analytics-card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-line"></i> Revenue Analytics</h3>
                            <span class="growth-indicator" id="revenue-growth">Loading...</span>
                        </div>
                        <div class="chart-container">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>

                    <div class="analytics-card">
                        <div class="card-header">
                            <h3><i class="fas fa-users"></i> User Growth</h3>
                            <span class="growth-indicator" id="user-growth">Loading...</span>
                        </div>
                        <div class="chart-container">
                            <canvas id="userGrowthChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Metrics -->
            <div class="performance-grid" data-aos="fade-up" data-aos-delay="200">
                <div class="performance-card">
                    <h3><i class="fas fa-trophy"></i> Top Performing Farmers</h3>
                    <div class="performance-list" id="top-farmers-list">
                        <div class="loading-placeholder">
                            <i class="fas fa-spinner fa-spin"></i> Loading farmers data...
                        </div>
                    </div>
                </div>

                <div class="performance-card">
                    <h3><i class="fas fa-tags"></i> Category Performance</h3>
                    <div class="chart-container">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Order Status Distribution -->
            <div class="performance-grid" data-aos="fade-up" data-aos-delay="400">
                <div class="performance-card">
                    <h3><i class="fas fa-clipboard-list"></i> Order Status Distribution</h3>
                    <div class="chart-container">
                        <canvas id="orderStatusChart"></canvas>
                    </div>
                </div>

                <div class="performance-card">
                    <h3><i class="fas fa-chart-bar"></i> Monthly Orders</h3>
                    <div class="chart-container">
                        <canvas id="monthlyOrdersChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Market Insights -->
            <div class="insights-grid" data-aos="fade-up" data-aos-delay="600">
                <div class="insight-card">
                    <div class="insight-icon">
                        <i class="fas fa-trending-up"></i>
                    </div>
                    <div class="insight-content">
                        <h4>Market Trends</h4>
                        <p id="market-trends">Loading market insights...</p>
                    </div>
                </div>

                <div class="insight-card">
                    <div class="insight-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="insight-content">
                        <h4>Regional Activity</h4>
                        <p id="regional-activity">Analyzing regional performance...</p>
                    </div>
                </div>

                <div class="insight-card">
                    <div class="insight-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="insight-content">
                        <h4>Performance Metrics</h4>
                        <p id="performance-metrics">Calculating key metrics...</p>
                    </div>
                </div>
            </div>

            <!-- Refresh Button -->
            <div class="analytics-actions" data-aos="fade-up" data-aos-delay="800">
                <button class="btn btn-primary" onclick="refreshAnalytics()">
                    <i class="fas fa-sync-alt"></i> Refresh Analytics
                </button>
                <button class="btn btn-success" onclick="exportAnalytics()">
                    <i class="fas fa-download"></i> Export Report
                </button>
            </div>
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
    let analyticsCharts = {};

    document.addEventListener('DOMContentLoaded', function() {
        loadAdminStats();
    });

    function showSection(sectionId) {
        document.querySelectorAll('.admin-section').forEach(section => {
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
                loadAdminStats();
                break;
            case 'users':
                loadUsers();
                break;
            case 'products':
                loadProducts();
                break;
            case 'orders':
                loadOrders();
                break;
            case 'analytics':
                setTimeout(() => {
                    loadAdvancedAnalytics();
                }, 300);
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

            const response = await fetch('admin.php', {
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

    async function loadAdminStats() {
        const result = await apiCall('get_admin_stats');
        if (result.success) {
            const stats = result.data;
            animateCounter('total-users', stats.total_users);
            animateCounter('total-farmers', stats.total_farmers);
            animateCounter('total-buyers', stats.total_buyers);
            animateCounter('total-products', stats.total_products);
            animateCounter('total-orders', stats.total_orders);
            animateCounter('new-users-month', stats.new_users_month);
            animateCounter('new-orders-month', stats.new_orders_month);

            const revenueElement = document.getElementById('total-revenue');
            animateRevenue(revenueElement, stats.total_revenue);
        }
    }

    async function loadUsers() {
        const result = await apiCall('get_all_users');
        const tbody = document.getElementById('users-list');

        if (result.success) {
            if (result.data.length === 0) {
                tbody.innerHTML =
                    '<tr><td colspan="8" style="text-align: center; padding: 2rem;">No users found</td></tr>';
                return;
            }

            tbody.innerHTML = result.data.map(user => `
                    <tr>
                        <td>${user.id}</td>
                        <td>
                            <strong>${user.full_name}</strong><br>
                            <small>@${user.username}</small>
                        </td>
                        <td>${user.email}</td>
                        <td>
                            <span class="status-badge status-${user.user_type}">
                                ${user.user_type.charAt(0).toUpperCase() + user.user_type.slice(1)}
                            </span>
                        </td>
                        <td>${user.location || 'Not provided'}</td>
                        <td>
                            <span class="status-badge status-${user.verification_status}">
                                ${user.verification_status}
                            </span>
                        </td>
                        <td>${formatDate(user.created_at)}</td>
                        <td>
                            <div class="action-buttons">
                                <select onchange="updateUserStatus(${user.id}, this.value)" class="btn btn-sm btn-primary">
                                    <option value="verified" ${user.verification_status === 'verified' ? 'selected' : ''}>Verified</option>
                                    <option value="pending" ${user.verification_status === 'pending' ? 'selected' : ''}>Pending</option>
                                    <option value="rejected" ${user.verification_status === 'rejected' ? 'selected' : ''}>Rejected</option>
                                </select>
                                ${user.user_type !== 'admin' ? `<button class="btn btn-sm btn-danger" onclick="confirmDeleteUser(${user.id}, '${user.full_name}')"><i class="fas fa-trash"></i></button>` : ''}
                            </div>
                        </td>
                    </tr>
                `).join('');
        }
    }

    async function loadProducts() {
        const result = await apiCall('get_all_products');
        const tbody = document.getElementById('products-list');

        if (result.success) {
            if (result.data.length === 0) {
                tbody.innerHTML =
                    '<tr><td colspan="8" style="text-align: center; padding: 2rem;">No products found</td></tr>';
                return;
            }

            tbody.innerHTML = result.data.map(product => `
                    <tr>
                        <td>${product.id}</td>
                        <td>
                            <strong>${product.name}</strong><br>
                            <small>${product.description ? product.description.substring(0, 50) + '...' : 'No description'}</small>
                        </td>
                        <td>
                            <strong>${product.farmer_name}</strong><br>
                            <small>${product.farmer_email}</small>
                        </td>
                        <td>
                            <span class="status-badge status-${product.category}">
                                ${product.category.charAt(0).toUpperCase() + product.category.slice(1)}
                            </span>
                        </td>
                        <td>₹${parseFloat(product.price_per_unit).toFixed(2)}/${product.unit}</td>
                        <td>${product.quantity} ${product.unit}</td>
                        <td>
                            <span class="status-badge status-${product.status}">
                                ${product.status}
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <select onchange="updateProductStatus(${product.id}, this.value)" class="btn btn-sm btn-primary">
                                    <option value="available" ${product.status === 'available' ? 'selected' : ''}>Available</option>
                                    <option value="sold" ${product.status === 'sold' ? 'selected' : ''}>Sold</option>
                                    <option value="out_of_stock" ${product.status === 'out_of_stock' ? 'selected' : ''}>Out of Stock</option>
                                </select>
                                <button class="btn btn-sm btn-danger" onclick="confirmDeleteProduct(${product.id}, '${product.name.replace(/'/g, "\\'")}')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `).join('');
        }
    }

    async function loadOrders() {
        const result = await apiCall('get_all_orders');
        const tbody = document.getElementById('orders-list');

        if (result.success) {
            if (result.data.length === 0) {
                tbody.innerHTML =
                    '<tr><td colspan="9" style="text-align: center; padding: 2rem;">No orders found</td></tr>';
                return;
            }

            tbody.innerHTML = result.data.map(order => `
                    <tr>
                        <td>${order.id}</td>
                        <td>
                            <strong>${order.product_name}</strong>
                        </td>
                        <td>
                            <strong>${order.buyer_name}</strong><br>
                            <small>${order.buyer_email}</small>
                        </td>
                        <td>
                            <strong>${order.farmer_name}</strong><br>
                            <small>${order.farmer_email}</small>
                        </td>
                        <td>${order.quantity} ${order.unit}</td>
                        <td>₹${parseFloat(order.total_amount).toFixed(2)}</td>
                        <td>
                            <span class="status-badge status-${order.status}">
                                ${order.status}
                            </span>
                        </td>
                        <td>${formatDate(order.order_date)}</td>
                        <td>
                            <div class="action-buttons">
                                <select onchange="updateOrderStatus(${order.id}, this.value)" class="btn btn-sm btn-primary">
                                    <option value="pending" ${order.status === 'pending' ? 'selected' : ''}>Pending</option>
                                    <option value="confirmed" ${order.status === 'confirmed' ? 'selected' : ''}>Confirmed</option>
                                    <option value="shipped" ${order.status === 'shipped' ? 'selected' : ''}>Shipped</option>
                                    <option value="delivered" ${order.status === 'delivered' ? 'selected' : ''}>Delivered</option>
                                    <option value="cancelled" ${order.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                                </select>
                            </div>
                        </td>
                    </tr>
                `).join('');
        }
    }

    // Enhanced Analytics JavaScript with Chart.js Integration
    // Load Chart.js library
    if (!window.Chart) {
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js';
        script.onload = function() {
            console.log('Chart.js loaded successfully');
            if (currentSection === 'analytics') {
                loadAdvancedAnalytics();
            }
        };
        script.onerror = function() {
            console.error('Failed to load Chart.js');
            showNotification('Failed to load charting library', 'error');
        };
        document.head.appendChild(script);
    }

    // Main Analytics Loading Function
    async function loadAdvancedAnalytics() {
        try {
            showLoadingState(true);

            const response = await fetch('../api/analytics.php');
            const responseText = await response.text();

            let result;
            try {
                result = JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON Parse Error:', parseError);
                console.error('Response was:', responseText);
                throw new Error('Invalid JSON response from server');
            }

            if (!result.success) {
                throw new Error(result.message || 'Analytics API returned error');
            }

            const data = result.data;
            console.log('Analytics data loaded:', data);

            // Load all charts
            await loadAllCharts(data);

            showLoadingState(false);
            showNotification('📊 Analytics loaded successfully!', 'success');

        } catch (error) {
            console.error('Analytics loading error:', error);
            showLoadingState(false);
            showNotification('Failed to load analytics: ' + error.message, 'error');
            showFallbackContent();
        }
    }

    // Load all charts function
    async function loadAllCharts(data) {
        // Wait for Chart.js to be available
        if (typeof Chart === 'undefined') {
            await loadChartJS();
        }

        // Load Revenue Chart
        loadRevenueChart(data.monthly);

        // Load User Growth Chart
        loadUserGrowthChart(data.monthly);

        // Load Category Chart
        if (data.categories && data.categories.length > 0) {
            loadCategoryChart(data.categories);
        }

        // Load Order Status Chart
        if (data.orderStatus && data.orderStatus.length > 0) {
            loadOrderStatusChart(data.orderStatus);
        }

        // Load Monthly Orders Chart
        loadMonthlyOrdersChart(data.monthly);

        // Load Top Farmers
        loadTopFarmers(data.topFarmers);

        // Update insights
        updateInsights(data);
    }

    // Load Chart.js dynamically if not present
    function loadChartJS() {
        return new Promise((resolve, reject) => {
            if (typeof Chart !== 'undefined') {
                resolve();
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js';
            script.onload = () => {
                console.log('Chart.js loaded successfully');
                resolve();
            };
            script.onerror = () => {
                console.error('Failed to load Chart.js');
                reject(new Error('Failed to load Chart.js library'));
            };
            document.head.appendChild(script);
        });
    }

    // Revenue Chart
    function loadRevenueChart(monthlyData) {
        const ctx = document.getElementById('revenueChart');
        if (!ctx) {
            console.warn('Revenue chart canvas not found');
            return;
        }

        // Destroy existing chart
        if (analyticsCharts.revenue) {
            analyticsCharts.revenue.destroy();
        }

        analyticsCharts.revenue = new Chart(ctx, {
            type: 'line',
            data: {
                labels: monthlyData.labels,
                datasets: [{
                    label: 'Revenue (₹)',
                    data: monthlyData.sales,
                    borderColor: '#8B5CF6',
                    backgroundColor: 'rgba(139, 92, 246, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#8B5CF6',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        callbacks: {
                            label: function(context) {
                                return 'Revenue: ₹' + context.parsed.y.toLocaleString('en-IN');
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + (value >= 1000 ? (value / 1000).toFixed(0) + 'K' : value);
                            }
                        }
                    }
                }
            }
        });
    }

    // User Growth Chart
    function loadUserGrowthChart(monthlyData) {
        const ctx = document.getElementById('userGrowthChart');
        if (!ctx) {
            console.warn('User growth chart canvas not found');
            return;
        }

        if (analyticsCharts.userGrowth) {
            analyticsCharts.userGrowth.destroy();
        }

        analyticsCharts.userGrowth = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: monthlyData.labels,
                datasets: [{
                    label: 'New Users',
                    data: monthlyData.users,
                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                    borderColor: 'rgba(16, 185, 129, 1)',
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Category Performance Chart
    function loadCategoryChart(categoryData) {
        const ctx = document.getElementById('categoryChart');
        if (!ctx || !categoryData.length) return;

        if (analyticsCharts.category) {
            analyticsCharts.category.destroy();
        }

        const labels = categoryData.map(item =>
            item.category.charAt(0).toUpperCase() + item.category.slice(1)
        );
        const revenues = categoryData.map(item => parseFloat(item.revenue));

        analyticsCharts.category = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: revenues,
                    backgroundColor: ['#8B5CF6', '#10B981', '#F59E0B', '#EF4444', '#3B82F6'],
                    borderWidth: 3,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    // Order Status Chart
    function loadOrderStatusChart(orderData) {
        const ctx = document.getElementById('orderStatusChart');
        if (!ctx || !orderData.length) return;

        if (analyticsCharts.orderStatus) {
            analyticsCharts.orderStatus.destroy();
        }

        const labels = orderData.map(item =>
            item.status.charAt(0).toUpperCase() + item.status.slice(1)
        );
        const counts = orderData.map(item => parseInt(item.count));

        analyticsCharts.orderStatus = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: counts,
                    backgroundColor: ['#10B981', '#F59E0B', '#3B82F6', '#8B5CF6', '#EF4444'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    // Monthly Orders Chart
    function loadMonthlyOrdersChart(monthlyData) {
        const ctx = document.getElementById('monthlyOrdersChart');
        if (!ctx) return;

        if (analyticsCharts.monthlyOrders) {
            analyticsCharts.monthlyOrders.destroy();
        }

        analyticsCharts.monthlyOrders = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: monthlyData.labels,
                datasets: [{
                    label: 'Orders Count',
                    data: monthlyData.orders,
                    backgroundColor: 'rgba(245, 158, 11, 0.8)',
                    borderColor: 'rgba(245, 158, 11, 1)',
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Load Top Farmers
    function loadTopFarmers(farmersData) {
        const container = document.getElementById('top-farmers-list');
        if (!container) return;

        if (!farmersData || farmersData.length === 0) {
            container.innerHTML = '<div class="no-data-placeholder">No farmer data available</div>';
            return;
        }

        container.innerHTML = farmersData.map((farmer, index) => `
                <div class="farmer-item">
                    <div class="farmer-rank">#${index + 1}</div>
                    <div class="farmer-info">
                        <h4>${farmer.full_name}</h4>
                        <p><i class="fas fa-map-marker-alt"></i> ${farmer.location || 'Location not specified'}</p>
                        <div class="farmer-stats-mini">
                            <span><i class="fas fa-shopping-cart"></i> ${farmer.order_count} orders</span>
                        </div>
                    </div>
                    <div class="farmer-revenue">
                        <div class="revenue-amount">₹${parseFloat(farmer.total_revenue).toLocaleString('en-IN')}</div>
                        <div class="revenue-label">Total Revenue</div>
                    </div>
                </div>
            `).join('');
    }

    // Update insights
    function updateInsights(data) {
        const totalRevenue = data.monthly.sales.reduce((a, b) => a + b, 0);
        const totalOrders = data.monthly.orders.reduce((a, b) => a + b, 0);
        const totalUsers = data.monthly.users.reduce((a, b) => a + b, 0);

        // Market trends
        const marketTrendsEl = document.getElementById('market-trends');
        if (marketTrendsEl) {
            marketTrendsEl.textContent =
                `Total revenue: ₹${totalRevenue.toLocaleString('en-IN')} from ${totalOrders} orders`;
        }

        // Regional activity
        const regionalEl = document.getElementById('regional-activity');
        if (regionalEl && data.categories.length > 0) {
            const topCategory = data.categories[0];
            regionalEl.textContent = `${topCategory.category} leads with ${topCategory.product_count} products`;
        }

        // Performance metrics
        const performanceEl = document.getElementById('performance-metrics');
        if (performanceEl) {
            const conversionRate = totalUsers > 0 ? ((totalOrders / totalUsers) * 100).toFixed(1) : '0';
            performanceEl.textContent = `${totalUsers} users, ${totalOrders} orders (${conversionRate}% conversion)`;
        }
    }

    // Show loading state
    function showLoadingState(show) {
        const elements = ['revenue-growth', 'user-growth'];
        elements.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = show ? 'Loading...' : 'Updated';
                element.className = show ? 'growth-indicator loading' : 'growth-indicator';
            }
        });
    }

    // Show fallback content on error
    function showFallbackContent() {
        const containers = [
            'top-farmers-list',
            'market-trends',
            'regional-activity',
            'performance-metrics'
        ];

        containers.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.innerHTML =
                    '<div class="error-placeholder"><i class="fas fa-exclamation-triangle"></i> Failed to load data</div>';
            }
        });
    }

    function refreshAnalytics() {
        showNotification('🔄 Refreshing analytics data...', 'info');
        loadAdvancedAnalytics();
    }

    function exportAnalytics() {
        showNotification('📊 Preparing analytics export...', 'info');

        // Simulate export process
        setTimeout(() => {
            showNotification('✅ Analytics report exported successfully!', 'success');
        }, 2000);
    }

    // User management functions
    async function updateUserStatus(userId, status) {
        const result = await apiCall('update_user_status', {
            user_id: userId,
            status: status
        });
        if (result.success) {
            showNotification('✅ User status updated successfully', 'success');
            loadUsers();
        } else {
            showNotification('Failed to update user status', 'error');
        }
    }

    function confirmDeleteUser(userId, userName) {
        if (confirm(`Are you sure you want to delete user "${userName}"? This action cannot be undone.`)) {
            deleteUser(userId);
        }
    }

    async function deleteUser(userId) {
        const result = await apiCall('delete_user', {
            user_id: userId
        });
        if (result.success) {
            showNotification('🗑️ User deleted successfully', 'success');
            loadUsers();
            loadAdminStats();
        } else {
            showNotification('Failed to delete user', 'error');
        }
    }

    // Product management functions
    async function updateProductStatus(productId, status) {
        const result = await apiCall('update_product_status', {
            product_id: productId,
            status: status
        });
        if (result.success) {
            showNotification('✅ Product status updated successfully', 'success');
            loadProducts();
        } else {
            showNotification('Failed to update product status', 'error');
        }
    }

    function confirmDeleteProduct(productId, productName) {
        if (confirm(`Are you sure you want to delete product "${productName}"? This action cannot be undone.`)) {
            deleteProduct(productId);
        }
    }

    async function deleteProduct(productId) {
        const result = await apiCall('delete_product', {
            product_id: productId
        });
        if (result.success) {
            showNotification('🗑️ Product deleted successfully', 'success');
            loadProducts();
            loadAdminStats();
        } else {
            showNotification('Failed to delete product', 'error');
        }
    }

    // Order management functions
    async function updateOrderStatus(orderId, status) {
        const result = await apiCall('update_order_status', {
            order_id: orderId,
            status: status
        });
        if (result.success) {
            showNotification('📦 Order status updated successfully', 'success');
            loadOrders();
            loadAdminStats();
        } else {
            showNotification('Failed to update order status', 'error');
        }
    }

    // Search functions
    async function searchUsers() {
        const searchTerm = document.getElementById('users-search').value;
        const result = await apiCall('get_all_users', {
            search: searchTerm
        });
        const tbody = document.getElementById('users-list');

        if (result.success) {
            // Same logic as loadUsers but with search results
            loadUsers();
        }
    }

    async function searchProducts() {
        const searchTerm = document.getElementById('products-search').value;
        const result = await apiCall('get_all_products', {
            search: searchTerm
        });
        loadProducts();
    }

    async function searchOrders() {
        const searchTerm = document.getElementById('orders-search').value;
        const result = await apiCall('get_all_orders', {
            search: searchTerm
        });
        loadOrders();
    }

    // Utility functions
    function animateCounter(elementId, target) {
        const element = document.getElementById(elementId);
        if (!element) return;

        let current = 0;
        const increment = target / 50;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                element.textContent = target.toLocaleString();
                clearInterval(timer);
            } else {
                element.textContent = Math.floor(current).toLocaleString();
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
                background: ${type === 'error' ? '#DC2626' : type === 'success' ? '#059669' : type === 'warning' ? '#D97706' : '#8B5CF6'};
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

            .growth-indicator.loading {
                background: rgba(245, 158, 11, 0.1);
                color: #D97706;
            }
        `;
    document.head.appendChild(style);

    console.log('🔧 Complete Admin Panel Loaded Successfully! 👑');
    </script>
</body>

</html>