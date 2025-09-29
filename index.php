<?php
require_once 'config.php';

// Check if user is logged in and get user info
$user = null;
$is_logged_in = false;

if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            $is_logged_in = true;
            $_SESSION['user_type'] = $user['user_type'];
        } else {
            session_destroy();
        }
    } catch (Exception $e) {
        error_log("Error fetching user: " . $e->getMessage());
    }
}

// Handle dashboard redirect for logged-in users
if ($is_logged_in && isset($_GET['action']) && $_GET['action'] === 'dashboard') {
    switch ($user['user_type']) {
        case 'admin':
            header('Location: panels/admin.php');
            exit;
        case 'farmer':
            header('Location: panels/farmer.php');
            exit;
        case 'buyer':
            header('Location: panels/buyer.php');
            exit;
        default:
            session_destroy();
            header('Location: index.php');
            exit;
    }
}

// Get live statistics from database
function getLiveStats($pdo) {
    $stats = [];
    
    try {
        // Active farmers
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'farmer' AND verification_status = 'verified' AND is_active = 1");
        $stats['farmers'] = $stmt->fetch()['count'] ?: 0;
        
        // Happy buyers
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'buyer' AND verification_status = 'verified' AND is_active = 1");
        $stats['buyers'] = $stmt->fetch()['count'] ?: 0;
        
        // Products listed
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE status = 'available'");
        $stats['products'] = $stmt->fetch()['count'] ?: 0;
        
        // Revenue generated from completed orders
        $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM orders WHERE status IN ('delivered', 'shipped', 'confirmed')");
        $stats['revenue'] = $stmt->fetch()['revenue'] ?: 0;
        
    } catch (Exception $e) {
        error_log("Error fetching stats: " . $e->getMessage());
        // Fallback values
        $stats = ['farmers' => 125, 'buyers' => 245, 'products' => 89, 'revenue' => 125000];
    }
    
    return $stats;
}

$live_stats = getLiveStats($pdo);

// Featured products
$featured_products = [
    [
        'id' => 1, 'name' => 'Premium Organic Tomatoes', 'price_per_unit' => 45.00, 'unit' => 'kg',
        'description' => 'Hand-picked organic tomatoes with zero pesticides, rich in nutrients and perfect for cooking',
        'organic' => 1, 'farmer_name' => 'Rajesh Kumar', 'farmer_location' => 'Pune, Maharashtra',
        'rating' => 4.9, 'image' => 'tomato.jpg'
    ],
    [
        'id' => 2, 'name' => 'Golden Basmati Rice', 'price_per_unit' => 120.00, 'unit' => 'kg',
        'description' => 'Aged premium basmati rice with authentic aroma and taste, directly from Punjab farms',
        'organic' => 0, 'farmer_name' => 'Priya Sharma', 'farmer_location' => 'Amritsar, Punjab',
        'rating' => 4.8, 'image' => 'rice.jpg'
    ],
    [
        'id' => 3, 'name' => 'Fresh Red Onions', 'price_per_unit' => 35.00, 'unit' => 'kg',
        'description' => 'Premium quality red onions from Nashik, perfect for cooking and long storage',
        'organic' => 0, 'farmer_name' => 'Amit Patel', 'farmer_location' => 'Nashik, Maharashtra',
        'rating' => 4.7, 'image' => 'onion.jpg'
    ]
];

$testimonials = [
    [
        'name' => 'Ramesh Singh', 'role' => 'Farmer from Punjab', 'image' => 'farmer1.jpg', 'rating' => 5,
        'text' => 'FarmConnect Pro transformed my farming business! I directly reach buyers and get 40% better prices than traditional markets. The AI price prediction is amazing!'
    ],
    [
        'name' => 'Priya Sharma', 'role' => 'Restaurant Owner, Mumbai', 'image' => 'buyer1.jpg', 'rating' => 5,
        'text' => 'Fresh vegetables delivered directly from farms to my restaurant. Quality is outstanding and prices are very competitive. Highly recommended!'
    ],
    [
        'name' => 'Suresh Kumar', 'role' => 'Organic Farmer, Kerala', 'image' => 'farmer2.jpg', 'rating' => 5,
        'text' => 'The platform helped me connect with organic food stores across India. My income increased by 60% since joining FarmConnect Pro.'
    ]
];

$page_title = 'FarmConnect Pro - Premium Agricultural Marketplace';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <meta name="description"
        content="India's premier agricultural marketplace connecting farmers directly with buyers. Get fair prices, eliminate middlemen, and revolutionize farming with AI technology.">
    <meta name="keywords" content="farming, agriculture, marketplace, farmers, buyers, organic produce, India">

    <!-- External CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Enhanced FarmBot Pro Styles -->



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

    /* Enhanced Navigation */
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
        animation: grow 2s ease-in-out infinite alternate;
    }

    @keyframes grow {
        0% {
            transform: scale(1);
        }

        100% {
            transform: scale(1.1);
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
        padding: -2rem 1rem;
        border-radius: 12px;
        transition: all 0.3s ease;
    }

    .nav-link:hover {
        color: var(--primary);
        background: rgba(16, 185, 129, 0.1);
    }

    .nav-actions {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        border: none;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.9rem;
    }

    .btn-primary {
        background: var(--gradient-1);
        color: white;
        box-shadow: var(--shadow-md);
    }

    .btn-secondary {
        background: transparent;
        color: var(--primary);
        border: 2px solid var(--primary);
    }

    .btn-admin {
        background: var(--gradient-3);
        color: white;
        box-shadow: var(--shadow-md);
    }

    .btn-large {
        padding: 1rem 2.5rem;
        font-size: 1.1rem;
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 1rem;
        color: var(--gray);
        font-weight: 600;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        background: var(--gradient-1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
    }

    .dropdown {
        position: relative;
    }

    .dropdown-content {
        display: none;
        position: absolute;
        right: 0;
        top: 100%;
        background: white;
        min-width: 220px;
        box-shadow: var(--shadow-xl);
        border-radius: 12px;
        padding: 0.5rem 0;
        border: 1px solid rgba(16, 185, 129, 0.1);
        z-index: 1001;
    }

    .dropdown:hover .dropdown-content {
        display: block;
    }

    .dropdown-item {
        display: block;
        padding: 0.75rem 1rem;
        color: var(--gray);
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .dropdown-item:hover {
        background: rgba(16, 185, 129, 0.1);
        color: var(--primary);
    }

    .mobile-menu-toggle {
        display: none;
        background: none;
        border: none;
        color: var(--primary);
        font-size: 1.5rem;
        cursor: pointer;
    }

    /* Hero Section */
    .hero {
        min-height: 100vh;
        display: flex;
        align-items: center;
        padding: 6rem 2rem 2rem;
        position: relative;
        overflow: hidden;
    }

    .hero::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><defs><radialGradient id="a" cx="50%" cy="50%"><stop offset="0%" stop-color="%2310B981" stop-opacity="0.1"/><stop offset="100%" stop-color="%2310B981" stop-opacity="0"/></radialGradient></defs><rect width="100%" height="100%" fill="url(%23a)"/></svg>') center/cover;
        animation: backgroundShift 20s ease-in-out infinite;
    }

    @keyframes backgroundShift {

        0%,
        100% {
            transform: scale(1) rotate(0deg);
            opacity: 0.5;
        }

        50% {
            transform: scale(1.1) rotate(2deg);
            opacity: 0.8;
        }
    }

    .hero-container {
        max-width: 1400px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 4rem;
        align-items: center;
        position: relative;
        z-index: 10;
    }

    .hero-content {
        animation: slideInLeft 1s ease-out;
    }

    @keyframes slideInLeft {
        0% {
            opacity: 0;
            transform: translateX(-50px);
        }

        100% {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .hero-title {
        font-size: 3.2rem;
        font-weight: 900;
        line-height: 1.1;
        margin-bottom: 1.5rem;
        background: linear-gradient(135deg, var(--dark) 0%, var(--primary) 50%, var(--secondary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .hero-subtitle {
        font-size: 1.2rem;
        color: var(--gray);
        margin-bottom: 2.5rem;
        line-height: 1.8;
    }

    .hero-actions {
        display: flex;
        gap: 1.5rem;
        margin-bottom: 3rem;
        flex-wrap: wrap;
    }

    /* Enhanced Live Stats Section */
    .hero-stats {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
        margin-top: 3rem;
    }

    .hero-stat {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
        padding: 2rem 1.5rem;
        border-radius: 20px;
        text-align: center;
        box-shadow: var(--shadow-lg);
        border: 1px solid rgba(16, 185, 129, 0.1);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .hero-stat:hover {
        transform: translateY(-8px);
        box-shadow: var(--shadow-xl);
    }

    .hero-stat::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-1);
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: 900;
        color: var(--primary);
        display: block;
        margin-bottom: 0.5rem;
        position: relative;
        z-index: 10;
    }

    .stat-label {
        color: var(--gray);
        font-weight: 600;
        font-size: 0.95rem;
        position: relative;
        z-index: 10;
    }

    .hero-visual {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: slideInRight 1s ease-out;
    }

    @keyframes slideInRight {
        0% {
            opacity: 0;
            transform: translateX(50px);
        }

        100% {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .hero-image {
        width: 100%;
        max-width: 500px;
        height: 500px;
        background: var(--gradient-1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 8rem;
        color: white;
        position: relative;
        overflow: hidden;
        animation: float 6s ease-in-out infinite;
        box-shadow: var(--shadow-xl);
    }

    @keyframes float {

        0%,
        100% {
            transform: translateY(0px);
        }

        50% {
            transform: translateY(-20px);
        }
    }

    .hero-image::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><defs><radialGradient id="a" cx="50%" cy="50%"><stop offset="0%" stop-color="%23FFFFFF" stop-opacity="0.2"/><stop offset="100%" stop-color="%23FFFFFF" stop-opacity="0"/></radialGradient></defs><rect width="100%" height="100%" fill="url(%23a)"/></svg>') center/cover;
    }

    /* Features Section */
    .features {
        padding: 6rem 2rem;
        background: white;
    }

    .features-container {
        max-width: 1400px;
        margin: 0 auto;
    }

    .section-header {
        text-align: center;
        margin-bottom: 4rem;
    }

    .section-title {
        font-size: 3rem;
        font-weight: 900;
        color: var(--dark);
        margin-bottom: 1rem;
    }

    .section-subtitle {
        font-size: 1.1rem;
        color: var(--gray);
        max-width: 600px;
        margin: 0 auto;
        line-height: 1.7;
    }

    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 2.5rem;
        margin-top: 4rem;
    }

    .feature-card {
        background: var(--gradient-bg);
        padding: 2.5rem;
        border-radius: 24px;
        text-align: center;
        border: 1px solid rgba(16, 185, 129, 0.1);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .feature-card:hover {
        transform: translateY(-10px);
        box-shadow: var(--shadow-xl);
    }

    .feature-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-1);
    }

    .feature-icon {
        width: 80px;
        height: 80px;
        background: var(--gradient-1);
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        color: white;
        margin: 0 auto 2rem;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {

        0%,
        100% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.05);
        }
    }

    .feature-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 1rem;
    }

    .feature-description {
        color: var(--gray);
        line-height: 1.6;
    }

    /* Products Showcase */
    .products-showcase {
        padding: 6rem 2rem;
        background: var(--gradient-bg);
    }

    .products-container {
        max-width: 1400px;
        margin: 0 auto;
    }

    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 2rem;
        margin-top: 3rem;
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

    .product-farmer {
        color: var(--gray);
        font-size: 0.9rem;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .product-price {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--primary);
        margin-bottom: 1rem;
    }

    .product-rating {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }

    .stars {
        display: flex;
        gap: 0.25rem;
    }

    .star {
        color: var(--secondary);
    }

    /* How It Works */
    .how-it-works {
        padding: 6rem 2rem;
        background: white;
    }

    .steps-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 3rem;
        margin-top: 4rem;
    }

    .step-card {
        text-align: center;
        position: relative;
    }

    .step-number {
        width: 60px;
        height: 60px;
        background: var(--gradient-1);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        font-weight: 900;
        margin: 0 auto 1.5rem;
        box-shadow: var(--shadow-md);
    }

    .step-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 1rem;
    }

    .step-description {
        color: var(--gray);
        line-height: 1.6;
    }

    /* Testimonials */
    .testimonials {
        padding: 6rem 2rem;
        background: var(--gradient-bg);
    }

    .testimonials-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 2rem;
        margin-top: 4rem;
    }

    .testimonial-card {
        background: white;
        padding: 2rem;
        border-radius: 20px;
        box-shadow: var(--shadow-md);
        border: 1px solid rgba(16, 185, 129, 0.1);
        position: relative;
    }

    .testimonial-card::before {
        content: '"';
        position: absolute;
        top: -10px;
        left: 20px;
        font-size: 4rem;
        color: var(--primary);
        opacity: 0.3;
    }

    .testimonial-text {
        font-style: italic;
        line-height: 1.6;
        margin-bottom: 1.5rem;
        color: var(--gray);
    }

    .testimonial-author {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .author-avatar {
        width: 50px;
        height: 50px;
        background: var(--gradient-1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 1.2rem;
    }

    .author-info h4 {
        color: var(--dark);
        margin-bottom: 0.25rem;
    }

    .author-info p {
        color: var(--gray);
        font-size: 0.9rem;
    }

    /* CTA Section */
    .cta-section {
        padding: 6rem 2rem;
        background: var(--gradient-1);
        color: white;
        text-align: center;
    }

    .cta-container {
        max-width: 800px;
        margin: 0 auto;
    }

    .cta-title {
        font-size: 3rem;
        font-weight: 900;
        margin-bottom: 1.5rem;
    }

    .cta-subtitle {
        font-size: 1.2rem;
        opacity: 0.9;
        margin-bottom: 2.5rem;
        line-height: 1.6;
    }

    .cta-actions {
        display: flex;
        justify-content: center;
        gap: 1.5rem;
        flex-wrap: wrap;
    }

    .btn-white {
        background: white;
        color: var(--primary);
        border: 2px solid white;
    }

    .btn-outline {
        background: transparent;
        color: white;
        border: 2px solid white;
    }

    /* Footer */
    .footer {
        background: var(--dark);
        color: white;
        padding: 4rem 2rem 2rem;
    }

    .footer-container {
        max-width: 1400px;
        margin: 0 auto;
    }

    .footer-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 3rem;
        margin-bottom: 3rem;
    }

    .footer-section h3 {
        font-size: 1.25rem;
        font-weight: 700;
        margin-bottom: 1.5rem;
        color: var(--primary);
    }

    .footer-links {
        list-style: none;
    }

    .footer-links li {
        margin-bottom: 0.75rem;
    }

    .footer-links a {
        color: var(--gray-light);
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .footer-links a:hover {
        color: var(--primary);
    }

    .footer-bottom {
        border-top: 1px solid var(--dark-light);
        padding-top: 2rem;
        text-align: center;
        color: var(--gray);
    }

    .social-links {
        display: flex;
        justify-content: center;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .social-link {
        width: 40px;
        height: 40px;
        background: var(--dark-light);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--gray-light);
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .social-link:hover {
        background: var(--primary);
        color: white;
        transform: translateY(-2px);
    }

    /* AI Chatbot Styles */
    .ai-chatbot {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        z-index: 1000;
        font-family: 'Poppins', sans-serif;
    }

    .chatbot-toggle {
        width: 70px;
        height: 70px;
        background: linear-gradient(135deg, #10B981 0%, #059669 100%);
        border: none;
        border-radius: 50%;
        color: white;
        font-size: 1.8rem;
        cursor: pointer;
        box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
        transition: all 0.3s ease;
        animation: pulse 2s infinite;
        position: relative;
    }

    @keyframes pulse {
        0% {
            transform: scale(1);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
        }

        50% {
            transform: scale(1.05);
            box-shadow: 0 12px 35px rgba(16, 185, 129, 0.5);
        }

        100% {
            transform: scale(1);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
        }
    }

    .chat-notification {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #EF4444;
        color: white;
        border-radius: 50%;
        width: 22px;
        height: 22px;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        animation: bounce 1s infinite;
    }

    @keyframes bounce {

        0%,
        20%,
        50%,
        80%,
        100% {
            transform: translateY(0);
        }

        40% {
            transform: translateY(-10px);
        }

        60% {
            transform: translateY(-5px);
        }
    }

    .chatbot-toggle:hover {
        transform: scale(1.1);
        box-shadow: 0 12px 35px rgba(16, 185, 129, 0.6);
    }

    .chatbot-window {
        position: absolute;
        bottom: 85px;
        right: 0;
        width: 420px;
        height: 600px;
        background: white;
        border-radius: 24px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
        border: 1px solid rgba(16, 185, 129, 0.1);
        display: none;
        flex-direction: column;
        overflow: hidden;
        animation: slideUp 0.4s ease;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(30px) scale(0.95);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .chatbot-header {
        background: linear-gradient(135deg, #10B981 0%, #059669 100%);
        color: white;
        padding: 1.25rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .chatbot-title {
        font-weight: 600;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .api-status {
        display: flex;
        gap: 0.5rem;
        font-size: 0.9rem;
    }

    .api-status span {
        padding: 0.2rem 0.4rem;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 8px;
        cursor: help;
    }

    .chatbot-close {
        background: none;
        border: none;
        color: white;
        cursor: pointer;
        font-size: 1.4rem;
        width: 35px;
        height: 35px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }

    .chatbot-close:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: rotate(90deg);
    }

    .chatbot-messages {
        flex: 1;
        padding: 1.25rem;
        overflow-y: auto;
        background: linear-gradient(145deg, #f8fafc 0%, #f1f5f9 100%);
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .chat-message {
        display: flex;
        gap: 0.75rem;
        margin-bottom: 1rem;
        animation: messageAppear 0.3s ease;
    }

    @keyframes messageAppear {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .chat-message.user {
        flex-direction: row-reverse;
    }

    .message-content {
        max-width: 85%;
        padding: 1rem 1.25rem;
        border-radius: 18px;
        font-size: 0.95rem;
        line-height: 1.5;
        word-wrap: break-word;
        white-space: pre-line;
        position: relative;
    }

    .chat-message.user .message-content {
        background: linear-gradient(135deg, #10B981 0%, #059669 100%);
        color: white;
        border-bottom-right-radius: 6px;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    .chat-message.bot .message-content {
        background: white;
        color: #1f2937;
        border: 1px solid #e5e7eb;
        border-bottom-left-radius: 6px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .chat-message.fallback .message-content {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border-color: #f59e0b;
        color: #92400e;
    }

    .chatbot-typing {
        padding: 1.25rem;
        background: linear-gradient(145deg, #f8fafc 0%, #f1f5f9 100%);
        border-top: 1px solid #e5e7eb;
    }

    .typing-indicator {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        color: #6b7280;
        font-size: 0.9rem;
    }

    .typing-indicator span:not(.typing-text) {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #10B981;
        animation: typing 1.4s infinite ease-in-out;
    }

    .typing-indicator span:nth-child(1) {
        animation-delay: -0.32s;
    }

    .typing-indicator span:nth-child(2) {
        animation-delay: -0.16s;
    }

    @keyframes typing {

        0%,
        80%,
        100% {
            transform: scale(0.8);
            opacity: 0.5;
        }

        40% {
            transform: scale(1.2);
            opacity: 1;
        }
    }

    .chatbot-input {
        padding: 1.25rem;
        background: white;
        border-top: 1px solid #e5e7eb;
        display: flex;
        gap: 0.75rem;
    }

    .chatbot-input input {
        flex: 1;
        padding: 0.875rem 1.25rem;
        border: 2px solid #e5e7eb;
        border-radius: 25px;
        outline: none;
        font-family: 'Poppins', sans-serif;
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }

    .chatbot-input input:focus {
        border-color: #10B981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }

    .chatbot-send {
        background: linear-gradient(135deg, #10B981 0%, #059669 100%);
        color: white;
        border: none;
        padding: 0.875rem 1.25rem;
        border-radius: 25px;
        cursor: pointer;
        transition: all 0.3s ease;
        min-width: 55px;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    .chatbot-send:hover {
        transform: scale(1.05);
        box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
    }

    .chatbot-send:disabled {
        background: #9ca3af;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .chatbot-footer {
        padding: 0.75rem 1.25rem;
        background: #f8fafc;
        border-top: 1px solid #e5e7eb;
    }

    .powered-by {
        text-align: center;
        font-size: 0.8rem;
        color: #6b7280;
    }

    #current-api {
        color: #10B981;
        font-weight: 600;
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
        .hero-container {
            max-width: 1000px;
        }

        .hero-title {
            font-size: 2.8rem;
        }

        .section-title {
            font-size: 2.5rem;
        }
    }

    @media (max-width: 968px) {
        .nav-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            flex-direction: column;
            padding: 2rem;
            box-shadow: var(--shadow-lg);
        }

        .nav-menu.active {
            display: flex;
        }

        .mobile-menu-toggle {
            display: block;
        }

        .hero-container {
            grid-template-columns: 1fr;
            gap: 3rem;
            text-align: center;
        }

        .hero-title {
            font-size: 2.5rem;
        }

        .hero-actions {
            flex-direction: column;
            align-items: center;
        }

        .hero-image {
            max-width: 350px;
            height: 350px;
            font-size: 5rem;
        }

        .hero-stats {
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .section-title {
            font-size: 2.2rem;
        }

        .cta-title {
            font-size: 2.5rem;
        }

        .cta-actions {
            flex-direction: column;
            align-items: center;
        }

        .chatbot-window {
            width: 360px;
            height: 550px;
            bottom: 85px;
            right: -15px;
        }

        .ai-chatbot {
            bottom: 1.5rem;
            right: 1.5rem;
        }
    }

    @media (max-width: 600px) {
        .hero-title {
            font-size: 2rem;
        }

        .hero-stats {
            grid-template-columns: 1fr;
        }

        .products-grid,
        .features-grid,
        .testimonials-grid {
            grid-template-columns: 1fr;
        }

        .steps-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
</head>

<body>
    <!-- Enhanced Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <?php if ($is_logged_in): ?>
            <a href="index.php?action=dashboard" class="nav-logo">
                <i class="fas fa-seedling"></i>
                <span>FarmConnect Pro</span>
            </a>
            <?php else: ?>
            <a href="#hero" class="nav-logo">
                <i class="fas fa-seedling"></i>
                <span>FarmConnect Pro</span>
            </a>
            <?php endif; ?>

            <div class="nav-menu" id="navMenu">
                <a href="#features" class="nav-link">Features</a>
                <a href="#products" class="nav-link">Products</a>
                <a href="#how-it-works" class="nav-link">How It Works</a>
                <a href="#testimonials" class="nav-link">Success Stories</a>
                <a href="chatbot/index.html" class="nav-link"><i class="fas fa-robot"></i>AI Assistant</a>
                <a href="#contact" class="nav-link">Contact</a>
            </div>

            <div class="nav-actions">
                <?php if ($is_logged_in): ?>
                <?php if ($user['user_type'] === 'admin'): ?>
                <!-- <a href="panels/admin.php" class="btn btn-admin">
                    <i class="fas fa-crown"></i>
                    Admin Panel
                </a> -->
                <?php endif; ?>

                <div class="dropdown">
                    <div class="user-info" style="cursor: pointer;">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                        </div>
                        <span>Welcome, <?php echo htmlspecialchars(explode(' ', $user['full_name'])[0]); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="dropdown-content">
                        <?php if ($user['user_type'] === 'farmer'): ?>
                        <a href="panels/farmer.php" class="dropdown-item">
                            <i class="fas fa-tractor"></i> My Farm Dashboard
                        </a>
                        <?php elseif ($user['user_type'] === 'buyer'): ?>
                        <a href="panels/buyer.php" class="dropdown-item">
                            <i class="fas fa-shopping-cart"></i> My Buyer Dashboard
                        </a>
                        <?php elseif ($user['user_type'] === 'admin'): ?>
                        <a href="panels/admin.php" class="dropdown-item">
                            <i class="fas fa-crown"></i> Admin Control Panel
                        </a>

                        <?php endif; ?>
                        <!-- <a href="#profile" class="dropdown-item">
                            <i class="fas fa-user"></i> Edit Profile
                        </a> -->
                        <a href="api/auth.php?action=logout" class="dropdown-item"
                            onclick="return confirm('Are you sure you want to logout?')">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
                <?php else: ?>
                <a href="auth/login.php" class="btn btn-secondary">
                    <i class="fas fa-sign-in-alt"></i>
                    Login
                </a>
                <a href="auth/signup.php" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i>
                    Sign Up
                </a>
                <?php endif; ?>

                <button class="mobile-menu-toggle" id="mobileToggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="hero" class="hero">
        <div class="hero-container">
            <div class="hero-content" data-aos="fade-right">
                <h1 class="hero-title">
                    <?php if ($is_logged_in): ?>
                    Welcome Back, <?php echo htmlspecialchars(explode(' ', $user['full_name'])[0]); ?>!
                    <?php else: ?>
                    Welcome to India's Premier Agricultural Revolution
                    <?php endif; ?>
                </h1>

                <?php if ($is_logged_in): ?>
                <p class="hero-subtitle">
                    Ready to continue your agricultural journey? Access your personalized dashboard and manage your
                    <?php echo $user['user_type'] === 'farmer' ? 'farm operations' : 'purchases'; ?> efficiently with
                    our advanced tools.
                </p>
                <div class="hero-actions">
                    <?php if ($user['user_type'] === 'farmer'): ?>
                    <a href="panels/farmer.php" class="btn btn-primary btn-large">
                        <i class="fas fa-tractor"></i>
                        Go to Farm Dashboard
                    </a>
                    <?php elseif ($user['user_type'] === 'buyer'): ?>
                    <a href="panels/buyer.php" class="btn btn-primary btn-large">
                        <i class="fas fa-shopping-cart"></i>
                        Go to Buyer Dashboard
                    </a>
                    <?php elseif ($user['user_type'] === 'admin'): ?>
                    <a href="panels/admin.php" class="btn btn-primary btn-large">
                        <i class="fas fa-crown"></i>
                        Go to Admin Panel
                    </a>
                    <?php endif; ?>
                    <a href="#features" class="btn btn-secondary btn-large">
                        <i class="fas fa-info-circle"></i>
                        Explore Features
                    </a>
                </div>
                <?php else: ?>
                <p class="hero-subtitle">
                    Connecting farmers directly with buyers through cutting-edge technology, eliminating middlemen and
                    ensuring fair prices for everyone in the agricultural ecosystem.
                </p>
                <div class="hero-actions">
                    <a href="auth/signup.php" class="btn btn-primary btn-large">
                        <i class="fas fa-rocket"></i>
                        Start Your Journey
                    </a>
                    <a href="#how-it-works" class="btn btn-secondary btn-large">
                        <i class="fas fa-play-circle"></i>
                        Learn More
                    </a>
                </div>
                <?php endif; ?>

                <!-- Enhanced Live Statistics from Database -->
                <div class="hero-stats">
                    <div class="hero-stat" data-aos="fade-up" data-aos-delay="100">
                        <span class="stat-number" data-count="<?php echo $live_stats['farmers']; ?>">0</span>
                        <span class="stat-label">Active Farmers</span>
                    </div>
                    <div class="hero-stat" data-aos="fade-up" data-aos-delay="200">
                        <span class="stat-number" data-count="<?php echo $live_stats['buyers']; ?>">0</span>
                        <span class="stat-label">Happy Buyers</span>
                    </div>
                    <div class="hero-stat" data-aos="fade-up" data-aos-delay="300">
                        <span class="stat-number" data-count="<?php echo $live_stats['products']; ?>">0</span>
                        <span class="stat-label">Products Listed</span>
                    </div>
                    <div class="hero-stat" data-aos="fade-up" data-aos-delay="400">
                        <span class="stat-number" data-revenue="<?php echo $live_stats['revenue']; ?>">₹0</span>
                        <span class="stat-label">Revenue Generated</span>
                    </div>
                </div>
            </div>

            <div class="hero-visual" data-aos="fade-left">
                <div class="hero-image">
                    <i class="fas fa-seedling"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features">
        <div class="features-container">
            <div class="section-header" data-aos="fade-up">
                <h2 class="section-title">Revolutionary Features</h2>
                <p class="section-subtitle">
                    Experience the future of agriculture with our cutting-edge technology platform designed specifically
                    for Indian farming communities.
                </p>
            </div>

            <div class="features-grid">
                <div class="feature-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-icon">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <h3 class="feature-title">Direct Trading</h3>
                    <p class="feature-description">
                        Connect directly with farmers and buyers, eliminating middlemen to ensure maximum profits and
                        fair prices for all parties involved.
                    </p>
                </div>

                <div class="feature-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-icon">
                        <i class="fas fa-brain"></i>
                    </div>
                    <h3 class="feature-title">FarmBot Pro</h3>
                    <p class="feature-description">
                    Empowering farmers with precision agriculture through intelligent automation.
                    </p>
                </div>

                <div class="feature-card" data-aos="fade-up" data-aos-delay="400">
                    <div class="feature-icon">
                        <i class="fas fa-leaf"></i>
                    </div>
                    <h3 class="feature-title">Quality Assurance</h3>
                    <p class="feature-description">
                        Our quality verification system ensures that all products meet premium standards, building trust
                        between farmers and buyers.
                    </p>
                </div>

            </div>
        </div>
    </section>

    <!-- Products Showcase -->
    <section id="products" class="products-showcase">
        <div class="products-container">
            <div class="section-header" data-aos="fade-up">
                <h2 class="section-title">Featured Products</h2>
                <p class="section-subtitle">
                    Discover premium quality produce directly from verified farmers across India, with guaranteed
                    freshness and competitive prices.
                </p>
            </div>

            <div class="products-grid">
                <?php foreach ($featured_products as $index => $product): ?>
                <div class="product-card" data-aos="fade-up" data-aos-delay="<?php echo ($index + 1) * 100; ?>">
                    <div class="product-image">
                        <i class="fas fa-seedling"></i>
                        <?php if ($product['organic']): ?>
                        <div class="product-badge">🌿 Organic</div>
                        <?php else: ?>
                        <div class="product-badge">🌟 Premium</div>
                        <?php endif; ?>
                    </div>
                    <div class="product-info">
                        <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p class="product-farmer">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($product['farmer_name']); ?> •
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo htmlspecialchars($product['farmer_location']); ?>
                        </p>
                        <div class="product-price">
                            ₹<?php echo number_format($product['price_per_unit'], 2); ?>/<?php echo $product['unit']; ?>
                        </div>
                        <div class="product-rating">
                            <div class="stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star star"></i>
                                <?php endfor; ?>
                            </div>
                            <span><?php echo $product['rating']; ?> (127 reviews)</span>
                        </div>
                        <p class="feature-description">
                            <?php echo htmlspecialchars($product['description']); ?>
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="text-align: center; margin-top: 3rem;" data-aos="fade-up">
                <?php if ($is_logged_in): ?>
                <?php if ($user['user_type'] === 'buyer'): ?>
                <a href="panels/buyer.php" class="btn btn-primary btn-large">
                    <i class="fas fa-shopping-cart"></i>
                    Browse All Products
                </a>
                <?php else: ?>
                <a href="auth/signup.php" class="btn btn-primary btn-large">
                    <i class="fas fa-user-plus"></i>
                    Join as Buyer
                </a>
                <?php endif; ?>
                <?php else: ?>
                <a href="auth/signup.php" class="btn btn-primary btn-large">
                    <i class="fas fa-shopping-cart"></i>
                    Start Shopping
                </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" class="how-it-works">
        <div class="features-container">
            <div class="section-header" data-aos="fade-up">
                <h2 class="section-title">How FarmConnect Pro Works</h2>
                <p class="section-subtitle">
                    Our simple three-step process makes agricultural trading accessible to everyone, from seasoned
                    farmers to first-time buyers.
                </p>
            </div>

            <div class="steps-grid">
                <div class="step-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="step-number">1</div>
                    <h3 class="step-title">Sign Up & Verify</h3>
                    <p class="step-description">
                        Create your account as a farmer or buyer. Complete our quick verification process to ensure
                        platform security and build trust.
                    </p>
                </div>

                <div class="step-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="step-number">2</div>
                    <h3 class="step-title">List or Browse Products</h3>
                    <p class="step-description">
                        Farmers can list their produce with photos and details. Buyers can browse and filter products
                        based on location, price, and quality.
                    </p>
                </div>

                <div class="step-card" data-aos="fade-up" data-aos-delay="300">
                    <div class="step-number">3</div>
                    <h3 class="step-title">Trade & Get Paid</h3>
                    <p class="step-description">
                        Connect directly, negotiate prices, arrange delivery, and complete secure payments. Our platform
                        handles everything seamlessly.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section id="testimonials" class="testimonials">
        <div class="features-container">
            <div class="section-header" data-aos="fade-up">
                <h2 class="section-title">Success Stories</h2>
                <p class="section-subtitle">
                    Hear from our community members who have transformed their agricultural journey with FarmConnect
                    Pro.
                </p>
            </div>

            <div class="testimonials-grid">
                <?php foreach ($testimonials as $index => $testimonial): ?>
                <div class="testimonial-card" data-aos="fade-up" data-aos-delay="<?php echo ($index + 1) * 100; ?>">
                    <p class="testimonial-text">
                        <?php echo htmlspecialchars($testimonial['text']); ?>
                    </p>
                    <div class="testimonial-author">
                        <div class="author-avatar">
                            <?php echo strtoupper(substr($testimonial['name'], 0, 1)); ?>
                        </div>
                        <div class="author-info">
                            <h4><?php echo htmlspecialchars($testimonial['name']); ?></h4>
                            <p><?php echo htmlspecialchars($testimonial['role']); ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="cta-container" data-aos="fade-up">
            <h2 class="cta-title">Ready to Transform Your Agricultural Journey?</h2>
            <p class="cta-subtitle">
                Join thousands of farmers and buyers who are already experiencing the benefits of direct agricultural
                trading with cutting-edge technology.
            </p>
            <div class="cta-actions">
                <?php if ($is_logged_in): ?>
                <?php if ($user['user_type'] === 'farmer'): ?>
                <a href="panels/farmer.php" class="btn btn-white btn-large">
                    <i class="fas fa-tractor"></i>
                    Go to Dashboard
                </a>
                <?php elseif ($user['user_type'] === 'buyer'): ?>
                <a href="panels/buyer.php" class="btn btn-white btn-large">
                    <i class="fas fa-shopping-cart"></i>
                    Start Shopping
                </a>
                <?php else: ?>
                <a href="panels/admin.php" class="btn btn-white btn-large">
                    <i class="fas fa-crown"></i>
                    Admin Panel
                </a>
                <?php endif; ?>
                <?php else: ?>
                <a href="auth/signup.php" class="btn btn-white btn-large">
                    <i class="fas fa-user-plus"></i>
                    Join Now - It's Free
                </a>
                <a href="#features" class="btn btn-outline btn-large">
                    <i class="fas fa-info-circle"></i>
                    Learn More
                </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer id="contact" class="footer">
        <div class="footer-container">
            <div class="footer-grid">
                <div class="footer-section">
                    <div class="nav-logo" style="margin-bottom: 1rem; color: var(--primary);">
                        <i class="fas fa-seedling"></i>
                        <span>FarmConnect Pro</span>
                    </div>
                    <p style="color: var(--gray-light); line-height: 1.6;">
                        Revolutionizing Indian agriculture by connecting farmers directly with buyers through
                        cutting-edge technology and AI-powered solutions.
                    </p>
                </div>

                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="#features">Features</a></li>
                        <li><a href="#products">Products</a></li>
                        <li><a href="#how-it-works">How It Works</a></li>
                        <li><a href="#testimonials">Success Stories</a></li>
                        <?php if (!$is_logged_in): ?>
                        <li><a href="auth/signup.php">Join Now</a></li>
                        <li><a href="auth/login.php">Login</a></li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Support</h3>
                    <ul class="footer-links">
                        <li><a href="#help">Help Center</a></li>
                        <li><a href="#contact">Contact Us</a></li>
                        <li><a href="#faq">FAQ</a></li>
                        <li><a href="#terms">Terms of Service</a></li>
                        <li><a href="#privacy">Privacy Policy</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Contact Info</h3>
                    <ul class="footer-links">
                        <li><i class="fas fa-envelope"></i> support@farmconnect.com</li>
                        <li><i class="fas fa-phone"></i> +91 98765 43210</li>
                        <li><i class="fas fa-map-marker-alt"></i> Mumbai, Maharashtra, India</li>
                        <li><i class="fas fa-clock"></i> 24/7 Customer Support</li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <div class="social-links">
                    <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-youtube"></i></a>
                </div>
                <p>&copy; 2025 FarmConnect Pro. All rights reserved. Made with ❤️ for Indian Farmers.</p>
            </div>
        </div>
    </footer>

    <!-- AI Chatbot HTML will be inserted here by JavaScript -->

    <!-- Scripts -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
    // Initialize AOS
    AOS.init({
        duration: 800,
        easing: 'ease-out-cubic',
        once: true
    });

    // Mobile menu functionality
    document.getElementById('mobileToggle').addEventListener('click', function() {
        const navMenu = document.getElementById('navMenu');
        navMenu.classList.toggle('active');
    });

    // Enhanced smooth scrolling
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Navbar scroll effect
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.navbar');
        if (window.scrollY > 50) {
            navbar.style.background = 'rgba(255, 255, 255, 0.98)';
            navbar.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.1)';
        } else {
            navbar.style.background = 'rgba(255, 255, 255, 0.95)';
            navbar.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
        }
    });

    // Enhanced counter animations
    function animateCounters() {
        const counters = document.querySelectorAll('[data-count]');
        const revenueCounters = document.querySelectorAll('[data-revenue]');

        counters.forEach(counter => {
            const target = parseInt(counter.getAttribute('data-count'));
            const increment = target / 100;
            let current = 0;

            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    counter.textContent = target.toLocaleString();
                    clearInterval(timer);
                } else {
                    counter.textContent = Math.floor(current).toLocaleString();
                }
            }, 30);
        });

        revenueCounters.forEach(counter => {
            const target = parseInt(counter.getAttribute('data-revenue'));
            const increment = target / 150;
            let current = 0;

            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    if (target >= 100000) {
                        counter.textContent = '₹' + (target / 100000).toFixed(1) + 'L+';
                    } else if (target >= 1000) {
                        counter.textContent = '₹' + (target / 1000).toFixed(1) + 'K+';
                    } else {
                        counter.textContent = '₹' + target.toLocaleString();
                    }
                    clearInterval(timer);
                } else {
                    if (current >= 100000) {
                        counter.textContent = '₹' + (current / 100000).toFixed(1) + 'L+';
                    } else if (current >= 1000) {
                        counter.textContent = '₹' + (current / 1000).toFixed(1) + 'K+';
                    } else {
                        counter.textContent = '₹' + Math.floor(current).toLocaleString();
                    }
                }
            }, 25);
        });
    }

    // Start counter animation when hero section is in view
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCounters();
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.5
    });

    observer.observe(document.querySelector('.hero-stats'));



    <?php if ($is_logged_in): ?>
    console.log('Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>! 👋');
    <?php endif; ?>
    </script>
    <!-- Enhanced FarmBot Pro Widget -->


</body>

</html>