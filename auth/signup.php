<?php
require_once '../config.php';

// Initialize variables
$errors = [];
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize form data
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $user_type = $_POST['user_type'] ?? '';
    $location = trim($_POST['location'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($username)) {
        $errors[] = 'Username is required';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    if (empty($full_name)) {
        $errors[] = 'Full name is required';
    }
    
    if (empty($phone)) {
        $errors[] = 'Phone number is required';
    } elseif (!preg_match('/^[6-9]\d{9}$/', $phone)) {
        $errors[] = 'Please enter a valid 10-digit Indian phone number';
    }
    
    if (empty($user_type) || !in_array($user_type, ['farmer', 'buyer'])) {
        $errors[] = 'Please select a valid user type';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    }
    
    // Check for existing users
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            $existing_user = $stmt->fetch();
            
            if ($existing_user) {
                $errors[] = 'Username or email already exists';
            }
        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
            error_log("Signup check error: " . $e->getMessage());
        }
    }
    
    // Insert user if no errors
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, full_name, phone, user_type, location, verification_status, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'verified', 1)
            ");
            
            $result = $stmt->execute([
                $username,
                $email,
                $hashed_password,
                $full_name,
                $phone,
                $user_type,
                $location
            ]);
            
            if ($result) {
                $success_message = 'Account created successfully! You can now login.';
                
                // Clear form data
                $username = $email = $full_name = $phone = $user_type = $location = '';
                
                // Log the successful registration
                error_log("User registered successfully: $email");
                
                // Optional: Auto-login the user
                // $_SESSION['user_id'] = $pdo->lastInsertId();
                // $_SESSION['user_type'] = $user_type;
                // header('Location: ../index.php');
                // exit;
                
            } else {
                $errors[] = 'Registration failed. Please try again.';
            }
            
        } catch (Exception $e) {
            $errors[] = 'Registration failed: ' . $e->getMessage();
            error_log("Signup insert error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - FarmConnect Pro</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">
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
        --gradient-bg: linear-gradient(135deg, #0F172A 0%, #1E293B 50%, #334155 100%);
        --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
        --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
        --shadow-xl: 0 20px 40px rgba(0, 0, 0, 0.1);
    }

    body {
        font-family: 'Poppins', sans-serif;
        background: var(--gradient-bg);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        overflow-x: hidden;
        padding: 2rem 0;
    }

    /* Enhanced Background Animation */
    .background-animation {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
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

    /* Floating Particles */
    .particles {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        overflow: hidden;
        z-index: 1;
    }

    .particle {
        position: absolute;
        width: 4px;
        height: 4px;
        background: var(--primary);
        border-radius: 50%;
        animation: float 15s linear infinite;
        opacity: 0.7;
    }

    @keyframes float {
        0% {
            transform: translateY(100vh) rotate(0deg);
            opacity: 0;
        }

        10% {
            opacity: 0.7;
        }

        90% {
            opacity: 0.7;
        }

        100% {
            transform: translateY(-100vh) rotate(360deg);
            opacity: 0;
        }
    }

    /* Main Container */
    .signup-container {
        position: relative;
        z-index: 10;
        width: 100%;
        max-width: 1400px;
        margin: 0 auto;
        padding: 2rem;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 4rem;
        align-items: center;
        min-height: 100vh;
    }

    /* Left Side - Branding */
    .signup-branding {
        color: white;
        text-align: left;
    }

    .brand-logo {
        display: flex;
        align-items: center;
        gap: 1rem;
        font-size: 2.5rem;
        font-weight: 900;
        margin-bottom: 2rem;
        animation: glow 3s ease-in-out infinite alternate;
    }

    @keyframes glow {
        0% {
            text-shadow: 0 0 20px rgba(16, 185, 129, 0.5);
        }

        100% {
            text-shadow: 0 0 30px rgba(16, 185, 129, 0.8);
        }
    }

    .brand-logo i {
        color: var(--secondary-light);
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

    .brand-title {
        font-size: 3.5rem;
        font-weight: 900;
        line-height: 1.1;
        margin-bottom: 1.5rem;
        background: linear-gradient(135deg, #FFFFFF 0%, #10B981 50%, #FBBF24 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .brand-subtitle {
        font-size: 1.2rem;
        opacity: 0.9;
        line-height: 1.6;
        margin-bottom: 2rem;
    }

    .brand-features {
        list-style: none;
    }

    .brand-features li {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
        font-size: 1rem;
        opacity: 0.9;
    }

    .brand-features i {
        color: var(--secondary-light);
        width: 24px;
        text-align: center;
    }

    /* Right Side - Signup Form */
    .signup-form-container {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        border-radius: 24px;
        padding: 3rem;
        box-shadow: var(--shadow-xl);
        border: 1px solid rgba(255, 255, 255, 0.2);
        position: relative;
        overflow: hidden;
        max-height: 90vh;
        overflow-y: auto;
    }

    .signup-form-container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: var(--gradient-1);
    }

    .form-header {
        text-align: center;
        margin-bottom: 2rem;
    }

    .form-title {
        font-size: 2rem;
        font-weight: 800;
        color: var(--dark);
        margin-bottom: 0.5rem;
    }

    .form-subtitle {
        color: var(--gray);
        font-size: 1rem;
    }

    .error-messages,
    .success-message {
        padding: 1rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        border-left: 4px solid;
    }

    .error-messages {
        background: rgba(239, 68, 68, 0.1);
        border-left-color: #DC2626;
    }

    .error-messages ul {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .error-messages li {
        color: #DC2626;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
    }

    .error-messages li:last-child {
        margin-bottom: 0;
    }

    .success-message {
        background: rgba(16, 185, 129, 0.1);
        color: #059669;
        border-left-color: #059669;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    .form-label {
        display: block;
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }

    .form-input,
    .form-select {
        width: 100%;
        padding: 1rem 1rem 1rem 3rem;
        border: 2px solid var(--gray-light);
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: white;
        font-family: 'Poppins', sans-serif;
    }

    .form-input:focus,
    .form-select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        transform: translateY(-1px);
    }

    .form-icon {
        position: absolute;
        left: 1rem;
        top: 2.3rem;
        color: var(--gray);
        font-size: 1rem;
        transition: color 0.3s ease;
    }

    .form-group:focus-within .form-icon {
        color: var(--primary);
    }

    .password-toggle {
        position: absolute;
        right: 1rem;
        top: 2.3rem;
        color: var(--gray);
        cursor: pointer;
        font-size: 1rem;
        transition: color 0.3s ease;
    }

    .password-toggle:hover {
        color: var(--primary);
    }

    .user-type-selection {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .user-type-option {
        position: relative;
    }

    .user-type-radio {
        position: absolute;
        opacity: 0;
        cursor: pointer;
    }

    .user-type-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 1.5rem;
        border: 2px solid var(--gray-light);
        border-radius: 16px;
        cursor: pointer;
        transition: all 0.3s ease;
        text-align: center;
    }

    .user-type-card:hover {
        border-color: var(--primary);
        background: rgba(16, 185, 129, 0.05);
    }

    .user-type-radio:checked+.user-type-card {
        border-color: var(--primary);
        background: rgba(16, 185, 129, 0.1);
    }

    .user-type-icon {
        font-size: 2rem;
        margin-bottom: 1rem;
        color: var(--primary);
    }

    .user-type-title {
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 0.5rem;
    }

    .user-type-desc {
        font-size: 0.85rem;
        color: var(--gray);
    }

    .btn-signup {
        width: 100%;
        padding: 1.25rem;
        background: var(--gradient-1);
        color: white;
        border: none;
        border-radius: 12px;
        font-size: 1.1rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .btn-signup:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }

    .btn-signup:disabled {
        background: var(--gray);
        cursor: not-allowed;
        transform: none;
    }

    .auth-switch {
        text-align: center;
        color: var(--gray);
        font-size: 0.95rem;
    }

    .auth-switch a {
        color: var(--primary);
        font-weight: 600;
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .auth-switch a:hover {
        color: var(--primary-dark);
    }

    /* Responsive Design */
    @media (max-width: 968px) {
        .signup-container {
            grid-template-columns: 1fr;
            gap: 2rem;
        }

        .signup-branding {
            text-align: center;
            order: 2;
        }

        .brand-title {
            font-size: 2.5rem;
        }

        .signup-form-container {
            order: 1;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }

        .user-type-selection {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 600px) {
        .signup-container {
            padding: 1rem;
        }

        .signup-form-container {
            padding: 2rem;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
</head>

<body>
    <!-- Background Animation -->
    <div class="background-animation"></div>

    <!-- Floating Particles -->
    <div class="particles" id="particles"></div>

    <div class="signup-container">
        <!-- Left Side - Branding -->
        <div class="signup-branding">
            <div class="brand-logo">
                <i class="fas fa-seedling"></i>
                <span>FarmConnect Pro</span>
            </div>
            <h1 class="brand-title">
                Join India's Premier Agricultural Revolution
            </h1>
            <p class="brand-subtitle">
                Create your account and become part of the largest farming community that's transforming agriculture
                through technology.
            </p>
            <ul class="brand-features">
                <li><i class="fas fa-check-circle"></i> Connect directly with farmers/buyers</li>
                <li><i class="fas fa-check-circle"></i> AI-powered price predictions</li>
                <li><i class="fas fa-check-circle"></i> Zero commission on first 10 orders</li>
                <li><i class="fas fa-check-circle"></i> 24/7 farming assistance</li>
                <li><i class="fas fa-check-circle"></i> Secure payment processing</li>
            </ul>
        </div>

        <!-- Right Side - Signup Form -->
        <div class="signup-form-container">
            <div class="form-header">
                <h2 class="form-title">Create Your Account</h2>
                <p class="form-subtitle">Join thousands of farmers and buyers already using our platform</p>
            </div>

            <?php if (!empty($errors)): ?>
            <div class="error-messages">
                <ul>
                    <?php foreach ($errors as $error): ?>
                    <li><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success_message); ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" id="signupForm" novalidate>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" id="username" name="username" class="form-input" required
                            placeholder="Choose a unique username"
                            value="<?php echo htmlspecialchars($username ?? ''); ?>">
                        <i class="fas fa-user form-icon"></i>
                    </div>

                    <div class="form-group">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" id="full_name" name="full_name" class="form-input" required
                            placeholder="Your full name" value="<?php echo htmlspecialchars($full_name ?? ''); ?>">
                        <i class="fas fa-id-card form-icon"></i>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" id="email" name="email" class="form-input" required
                        placeholder="your.email@example.com" value="<?php echo htmlspecialchars($email ?? ''); ?>">
                    <i class="fas fa-envelope form-icon"></i>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-input" required
                            placeholder="10-digit mobile number" value="<?php echo htmlspecialchars($phone ?? ''); ?>"
                            pattern="[6-9][0-9]{9}" maxlength="10">
                        <i class="fas fa-phone form-icon"></i>
                    </div>

                    <div class="form-group">
                        <label for="location" class="form-label">Location</label>
                        <input type="text" id="location" name="location" class="form-input"
                            placeholder="City, State (Optional)"
                            value="<?php echo htmlspecialchars($location ?? ''); ?>">
                        <i class="fas fa-map-marker-alt form-icon"></i>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label class="form-label">I am a:</label>
                    <div class="user-type-selection">
                        <div class="user-type-option">
                            <input type="radio" id="farmer" name="user_type" value="farmer" class="user-type-radio"
                                <?php echo (isset($user_type) && $user_type === 'farmer') ? 'checked' : ''; ?>>
                            <label for="farmer" class="user-type-card">
                                <i class="fas fa-tractor user-type-icon"></i>
                                <div class="user-type-title">Farmer</div>
                                <div class="user-type-desc">I want to sell my produce</div>
                            </label>
                        </div>
                        <div class="user-type-option">
                            <input type="radio" id="buyer" name="user_type" value="buyer" class="user-type-radio"
                                <?php echo (isset($user_type) && $user_type === 'buyer') ? 'checked' : ''; ?>>
                            <label for="buyer" class="user-type-card">
                                <i class="fas fa-shopping-cart user-type-icon"></i>
                                <div class="user-type-title">Buyer</div>
                                <div class="user-type-desc">I want to buy fresh produce</div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" id="password" name="password" class="form-input" required
                            placeholder="Minimum 6 characters">
                        <i class="fas fa-lock form-icon"></i>
                        <i class="fas fa-eye password-toggle" onclick="togglePassword('password')"></i>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" required
                            placeholder="Repeat your password">
                        <i class="fas fa-lock form-icon"></i>
                        <i class="fas fa-eye password-toggle" onclick="togglePassword('confirm_password')"></i>
                    </div>
                </div>

                <button type="submit" class="btn-signup" id="signupBtn">
                    <i class="fas fa-user-plus"></i>
                    Create Account
                </button>
            </form>

            <div class="auth-switch">
                Already have an account? <a href="login.php">Sign In</a>
            </div>
        </div>
    </div>

    <script>
    // Create floating particles
    function createParticles() {
        const particlesContainer = document.getElementById('particles');
        const particleCount = 50;

        for (let i = 0; i < particleCount; i++) {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.animationDelay = Math.random() * 15 + 's';
            particle.style.animationDuration = (Math.random() * 10 + 10) + 's';
            particlesContainer.appendChild(particle);
        }
    }

    // Initialize particles
    createParticles();

    // Password toggle functionality
    function togglePassword(fieldId) {
        const passwordInput = document.getElementById(fieldId);
        const toggleIcon = passwordInput.nextElementSibling.nextElementSibling;

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }

    // Form validation
    document.getElementById('signupForm').addEventListener('submit', function(e) {
        const submitBtn = document.getElementById('signupBtn');
        const originalText = submitBtn.innerHTML;

        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
        submitBtn.disabled = true;

        // Re-enable button if form validation fails
        setTimeout(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }, 5000);
    });

    // Real-time validation
    document.getElementById('phone').addEventListener('input', function() {
        const phone = this.value;
        const phoneRegex = /^[6-9]\d{9}$/;

        if (phone && !phoneRegex.test(phone)) {
            this.style.borderColor = '#DC2626';
        } else {
            this.style.borderColor = '';
        }
    });

    document.getElementById('email').addEventListener('input', function() {
        const email = this.value;
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (email && !emailRegex.test(email)) {
            this.style.borderColor = '#DC2626';
        } else {
            this.style.borderColor = '';
        }
    });

    document.getElementById('confirm_password').addEventListener('input', function() {
        const password = document.getElementById('password').value;
        const confirmPassword = this.value;

        if (confirmPassword && password !== confirmPassword) {
            this.style.borderColor = '#DC2626';
        } else {
            this.style.borderColor = '';
        }
    });
    </script>
</body>

</html>