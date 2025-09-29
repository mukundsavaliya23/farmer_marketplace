<?php
require_once '../config.php';

// Handle login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_type'] = $user['user_type'];
                
                // Handle remember me
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $expires = time() + (30 * 24 * 60 * 60); // 30 days
                    
                    // Store token in database
                    $stmt = $pdo->prepare("INSERT INTO remember_tokens (user_id, token, expires) VALUES (?, ?, ?)");
                    $stmt->execute([$user['id'], hash('sha256', $token), date('Y-m-d H:i:s', $expires)]);
                    
                    // Set cookie
                    setcookie('remember_token', $token, $expires, '/', '', false, true);
                }
                
                // Redirect based on user type
                switch ($user['user_type']) {
                    case 'admin':
                        header('Location: ../panels/admin.php');
                        break;
                    case 'farmer':
                        header('Location: ../panels/farmer.php');
                        break;
                    case 'buyer':
                        header('Location: ../panels/buyer.php');
                        break;
                    default:
                        header('Location: ../index.php');
                }
                exit;
            } else {
                $error = 'Invalid email or password';
            }
        } catch (Exception $e) {
            $error = 'Login failed. Please try again.';
        }
    }
}

// Handle password reset request
if (isset($_POST['reset_password'])) {
    $email = $_POST['reset_email'] ?? '';
    
    if (!empty($email)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
                
                $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token = ?, expires = ?");
                $stmt->execute([$user['id'], $token, $expires, $token, $expires]);
                
                // In real app, send email here
                $reset_success = "Password reset link sent to your email address!";
            } else {
                $reset_error = "No account found with that email address.";
            }
        } catch (Exception $e) {
            $reset_error = "Failed to send reset link. Please try again.";
        }
    } else {
        $reset_error = "Please enter your email address.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FarmConnect Pro</title>
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
        overflow: hidden;
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
    .auth-container {
        position: relative;
        z-index: 10;
        width: 100%;
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 4rem;
        align-items: center;
        min-height: 100vh;
    }

    /* Left Side - Branding */
    .auth-branding {
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

    /* Right Side - Login Form */
    .auth-form-container {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        border-radius: 24px;
        padding: 3rem;
        box-shadow: var(--shadow-xl);
        border: 1px solid rgba(255, 255, 255, 0.2);
        position: relative;
        overflow: hidden;
    }

    .auth-form-container::before {
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
        margin-bottom: 2.5rem;
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

    .error-message,
    .success-message {
        padding: 1rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        border-left: 4px solid;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .error-message {
        background: rgba(239, 68, 68, 0.1);
        color: #DC2626;
        border-left-color: #DC2626;
    }

    .success-message {
        background: rgba(16, 185, 129, 0.1);
        color: #059669;
        border-left-color: #059669;
    }

    .form-group {
        margin-bottom: 1.5rem;
        position: relative;
    }

    .form-label {
        display: block;
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }

    .form-input {
        width: 100%;
        padding: 1rem 1rem 1rem 3rem;
        border: 2px solid var(--gray-light);
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: white;
    }

    .form-input:focus {
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

    .form-input:focus+.form-icon {
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

    .form-options {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        font-size: 0.9rem;
    }

    .remember-me {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--gray);
        cursor: pointer;
    }

    .remember-me input {
        accent-color: var(--primary);
    }

    .forgot-password {
        color: var(--primary);
        text-decoration: none;
        font-weight: 500;
        transition: color 0.3s ease;
        cursor: pointer;
    }

    .forgot-password:hover {
        color: var(--primary-dark);
    }

    .btn-login {
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

    .btn-login:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }

    .btn-login:active {
        transform: translateY(0);
    }

    .btn-login:disabled {
        background: var(--gray);
        cursor: not-allowed;
        transform: none;
    }

    .divider {
        display: flex;
        align-items: center;
        margin: 1.5rem 0;
        color: var(--gray);
        font-size: 0.9rem;
    }

    .divider::before,
    .divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--gray-light);
    }

    .divider span {
        padding: 0 1rem;
    }

    .social-login {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .btn-social {
        padding: 1rem;
        border: 2px solid var(--gray-light);
        background: white;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        font-weight: 600;
    }

    .btn-google {
        color: #DB4437;
        border-color: #DB4437;
    }

    .btn-facebook {
        color: #1877F2;
        border-color: #1877F2;
    }

    .btn-social:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
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

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 3000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(5px);
    }

    .modal-content {
        background: white;
        margin: 10% auto;
        padding: 2rem;
        border-radius: 20px;
        width: 90%;
        max-width: 500px;
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
        margin-bottom: 1.5rem;
    }

    .modal-title {
        font-size: 1.3rem;
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

    /* Responsive Design */
    @media (max-width: 968px) {
        .auth-container {
            grid-template-columns: 1fr;
            gap: 2rem;
        }

        .auth-branding {
            text-align: center;
            order: 2;
        }

        .brand-title {
            font-size: 2.5rem;
        }

        .auth-form-container {
            order: 1;
        }
    }

    @media (max-width: 600px) {
        .auth-container {
            padding: 1rem;
        }

        .auth-form-container {
            padding: 2rem;
        }

        .social-login {
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

    <div class="auth-container">
        <!-- Left Side - Branding -->
        <div class="auth-branding">
            <div class="brand-logo">
                <i class="fas fa-seedling"></i>
                <span>FarmConnect Pro</span>
            </div>
            <h1 class="brand-title">
                Welcome Back to the Future of Agriculture
            </h1>
            <p class="brand-subtitle">
                Access your personalized dashboard and continue revolutionizing Indian agriculture with cutting-edge
                technology.
            </p>
            <ul class="brand-features">
                <li><i class="fas fa-check-circle"></i> Direct farmer-to-buyer connections</li>
                <li><i class="fas fa-check-circle"></i> AI-powered price predictions</li>
                <li><i class="fas fa-check-circle"></i> Real-time market analytics</li>
                <li><i class="fas fa-check-circle"></i> Secure payment processing</li>
                <li><i class="fas fa-check-circle"></i> 24/7 farming support</li>
            </ul>
        </div>

        <!-- Right Side - Login Form -->
        <div class="auth-form-container">
            <div class="form-header">
                <h2 class="form-title">Welcome Back!</h2>
                <p class="form-subtitle">Sign in to your account to continue</p>
            </div>

            <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
            <?php endif; ?>

            <?php if (isset($reset_success)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($reset_success); ?></span>
            </div>
            <?php endif; ?>

            <?php if (isset($reset_error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?php echo htmlspecialchars($reset_error); ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" id="email" name="email" class="form-input" required
                        placeholder="Enter your email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    <i class="fas fa-envelope form-icon"></i>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-input" required
                        placeholder="Enter your password">
                    <i class="fas fa-lock form-icon"></i>
                    <i class="fas fa-eye password-toggle" onclick="togglePassword()"></i>
                </div>

                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" id="remember">
                        <span>Remember me</span>
                    </label>
                    <span class="forgot-password" onclick="openForgotPasswordModal()">Forgot Password?</span>
                </div>

                <button type="submit" class="btn-login" id="loginBtn">
                    <i class="fas fa-sign-in-alt"></i>
                    Sign In
                </button>
            </form>

            <div class="divider">
                <span>Or continue with</span>
            </div>

            <div class="social-login">
                <button class="btn-social btn-google" onclick="socialLogin('google')">
                    <i class="fab fa-google"></i>
                    Google
                </button>
                <button class="btn-social btn-facebook" onclick="socialLogin('facebook')">
                    <i class="fab fa-facebook-f"></i>
                    Facebook
                </button>
            </div>

            <div class="auth-switch">
                Don't have an account? <a href="signup.php">Create Account</a>
            </div>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgotPasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Reset Password</h2>
                <button class="modal-close" onclick="closeForgotPasswordModal()">&times;</button>
            </div>
            <form method="POST" id="resetForm">
                <input type="hidden" name="reset_password" value="1">
                <div class="form-group">
                    <label for="reset_email" class="form-label">Email Address</label>
                    <input type="email" id="reset_email" name="reset_email" class="form-input" required
                        placeholder="Enter your email address">
                    <i class="fas fa-envelope form-icon"></i>
                </div>
                <p style="color: var(--gray); font-size: 0.9rem; margin-bottom: 1.5rem;">
                    We'll send you a password reset link to your email address.
                </p>
                <button type="submit" class="btn-login">
                    <i class="fas fa-paper-plane"></i>
                    Send Reset Link
                </button>
            </form>
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
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.querySelector('.password-toggle');

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

    // Social login functionality
    function socialLogin(provider) {
        showNotification(
            `🔗 ${provider.charAt(0).toUpperCase() + provider.slice(1)} login integration is coming soon! This feature will be available in the next update.`,
            'info');
    }

    // Forgot password modal
    function openForgotPasswordModal() {
        document.getElementById('forgotPasswordModal').style.display = 'block';
    }

    function closeForgotPasswordModal() {
        document.getElementById('forgotPasswordModal').style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('forgotPasswordModal');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    }

    // Form submission with loading state
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const submitBtn = document.getElementById('loginBtn');
        const originalText = submitBtn.innerHTML;

        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing In...';
        submitBtn.disabled = true;

        // Re-enable button after form submission (in case of errors)
        setTimeout(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }, 3000);
    });

    // Form validation
    document.getElementById('email').addEventListener('input', function() {
        const email = this.value;
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (email && !emailRegex.test(email)) {
            this.style.borderColor = '#DC2626';
        } else {
            this.style.borderColor = '';
        }
    });

    // Remember me persistence
    document.addEventListener('DOMContentLoaded', function() {
        const rememberCheckbox = document.getElementById('remember');
        const emailInput = document.getElementById('email');

        // Load saved email if remember me was checked
        const savedEmail = localStorage.getItem('rememberedEmail');
        if (savedEmail) {
            emailInput.value = savedEmail;
            rememberCheckbox.checked = true;
        }

        // Save email when remember me is checked
        rememberCheckbox.addEventListener('change', function() {
            if (this.checked && emailInput.value) {
                localStorage.setItem('rememberedEmail', emailInput.value);
            } else {
                localStorage.removeItem('rememberedEmail');
            }
        });
    });

    // Notification system
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'error' ? '#DC2626' : type === 'success' ? '#059669' : '#3B82F6'};
                color: white;
                padding: 1rem 2rem;
                border-radius: 12px;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
                z-index: 10000;
                animation: slideIn 0.3s ease;
                max-width: 400px;
                font-weight: 500;
            `;

        notification.innerHTML = `
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas ${type === 'error' ? 'fa-exclamation-triangle' : type === 'success' ? 'fa-check-circle' : 'fa-info-circle'}"></i>
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
        }, 5000);
    }

    // Add animations
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