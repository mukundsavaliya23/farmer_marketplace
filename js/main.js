// Main JavaScript for animations and functionality
document.addEventListener('DOMContentLoaded', function() {
    initializeAnimations();
    initializeAuthForms();
    initializePricePredictionDemo();
    initializeScrollEffects();
});

// Initialize animations
function initializeAnimations() {
    // Animate elements on scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
            }
        });
    }, observerOptions);

    // Observe all animatable elements
    document.querySelectorAll('.feature-card, .hero-content, .section-header').forEach(el => {
        observer.observe(el);
    });

    // Number counter animation for stats
    animateCounters();
}

// Animate counters
function animateCounters() {
    const counters = document.querySelectorAll('.stat-item strong');
    
    counters.forEach(counter => {
        const target = parseInt(counter.textContent.replace(/[^\d]/g, ''));
        const duration = 2000;
        const step = target / (duration / 16);
        let current = 0;
        
        const timer = setInterval(() => {
            current += step;
            if (current >= target) {
                counter.textContent = formatStatNumber(target);
                clearInterval(timer);
            } else {
                counter.textContent = formatStatNumber(Math.floor(current));
            }
        }, 16);
    });
}

function formatStatNumber(num) {
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M+';
    } else if (num >= 1000) {
        return (num / 1000).toFixed(0) + 'K+';
    }
    return num.toString();
}

// Initialize authentication forms
function initializeAuthForms() {
    const loginForm = document.getElementById('loginForm');
    const signupForm = document.getElementById('signupForm');
    
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }
    
    if (signupForm) {
        signupForm.addEventListener('submit', handleSignup);
    }
}

// Handle login
async function handleLogin(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const loginData = Object.fromEntries(formData);
    
    try {
        showLoading(e.target.querySelector('button'));
        
        const response = await fetch('api/auth.php?action=login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(loginData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            localStorage.setItem('authToken', result.token);
            localStorage.setItem('currentUser', JSON.stringify(result.user));
            
            showNotification('Login successful! Redirecting...', 'success');
            
            setTimeout(() => {
                redirectToPanel(loginData.user_type);
            }, 1000);
        } else {
            showNotification(result.message || 'Login failed', 'error');
        }
    } catch (error) {
        console.error('Login error:', error);
        showNotification('Login failed. Please try again.', 'error');
    } finally {
        hideLoading(e.target.querySelector('button'));
    }
}

// Handle signup
async function handleSignup(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const signupData = Object.fromEntries(formData);
    
    try {
        showLoading(e.target.querySelector('button'));
        
        const response = await fetch('api/auth.php?action=signup', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(signupData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Account created successfully! Please login.', 'success');
            closeModal('signupModal');
            showLoginModal();
        } else {
            showNotification(result.message || 'Signup failed', 'error');
        }
    } catch (error) {
        console.error('Signup error:', error);
        showNotification('Signup failed. Please try again.', 'error');
    } finally {
        hideLoading(e.target.querySelector('button'));
    }
}

// Initialize price prediction demo
function initializePricePredictionDemo() {
    const demoForm = document.getElementById('demoPredictionForm');
    if (demoForm) {
        demoForm.addEventListener('submit', handleDemoPrediction);
    }
}

// Handle demo price prediction
async function handleDemoPrediction(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const predictionData = Object.fromEntries(formData);
    
    const resultDiv = document.getElementById('demoPredictionResult');
    
    try {
        showPredictionLoading(resultDiv);
        
        const response = await fetch('api/price_prediction.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(predictionData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            displayPredictionResult(resultDiv, result.prediction);
        } else {
            throw new Error(result.message || 'Prediction failed');
        }
    } catch (error) {
        console.error('Prediction error:', error);
        showPredictionError(resultDiv);
    }
}

function showPredictionLoading(container) {
    container.innerHTML = `
        <div class="prediction-loading">
            <div class="loading"></div>
            <h3>Analyzing Market Data</h3>
            <p>Processing historical prices and market trends...</p>
        </div>
    `;
}

function displayPredictionResult(container, prediction) {
    const changeClass = prediction.change >= 0 ? 'positive' : 'negative';
    const trendIcon = prediction.trend === 'up' ? 'fa-arrow-up' : 'fa-arrow-down';
    
    container.innerHTML = `
        <div class="prediction-result">
            <div class="prediction-header">
                <h3><i class="fas fa-brain"></i> AI Prediction</h3>
                <div class="confidence-badge">${prediction.confidence}% Confidence</div>
            </div>
            
            <div class="prediction-metrics">
                <div class="metric">
                    <span class="label">Current Price</span>
                    <span class="value">₹${prediction.current_price}/kg</span>
                </div>
                <div class="metric">
                    <span class="label">Predicted Price</span>
                    <span class="value ${changeClass}">
                        ₹${prediction.predicted_price}/kg
                        <i class="fas ${trendIcon}"></i>
                    </span>
                </div>
                <div class="metric">
                    <span class="label">Expected Change</span>
                    <span class="value ${changeClass}">
                        ${prediction.change >= 0 ? '+' : ''}${prediction.change}%
                    </span>
                </div>
            </div>
            
            <div class="recommendation">
                <h4><i class="fas fa-lightbulb"></i> Recommendation</h4>
                <p>${prediction.recommendation}</p>
            </div>
        </div>
    `;
    
    // Animate the result
    container.classList.add('animate-in');
}

function showPredictionError(container) {
    container.innerHTML = `
        <div class="prediction-error">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Prediction Unavailable</h3>
            <p>Unable to generate price prediction. Please try again later.</p>
        </div>
    `;
}

// Initialize scroll effects
function initializeScrollEffects() {
    window.addEventListener('scroll', function() {
        const navbar = document.getElementById('navbar');
        if (window.scrollY > 100) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
    
    // Smooth scrolling for navigation links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
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
}

// Modal functions
function showLoginModal() {
    document.getElementById('loginModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function showSignupModal() {
    document.getElementById('signupModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    document.body.style.overflow = 'auto';
}

function switchToSignup() {
    closeModal('loginModal');
    showSignupModal();
}

function switchToLogin() {
    closeModal('signupModal');
    showLoginModal();
}

// Panel redirection
function redirectToPanel(userType) {
    const urls = {
        farmer: 'panels/farmer.php',
        buyer: 'panels/buyer.php',
        admin: 'panels/admin.php'
    };
    
    if (urls[userType]) {
        window.location.href = urls[userType];
    }
}

// Password visibility toggle
function togglePassword(icon) {
    const input = icon.previousElementSibling;
    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
    input.setAttribute('type', type);
    icon.classList.toggle('fa-eye');
    icon.classList.toggle('fa-eye-slash');
}

// Mobile menu toggle
function toggleMobileMenu() {
    const navMenu = document.getElementById('nav-menu');
    navMenu.classList.toggle('active');
}

// Utility functions
function showLoading(button) {
    const originalText = button.innerHTML;
    button.innerHTML = '<div class="loading"></div> Processing...';
    button.disabled = true;
    button.dataset.originalText = originalText;
}

function hideLoading(button) {
    button.innerHTML = button.dataset.originalText;
    button.disabled = false;
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${getNotificationIcon(type)}"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Style the notification
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${getNotificationColor(type)};
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 10px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        z-index: 3000;
        animation: slideInRight 0.5s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        max-width: 400px;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.animation = 'slideOutRight 0.5s ease';
            setTimeout(() => notification.remove(), 500);
        }
    }, 5000);
}

function getNotificationIcon(type) {
    const icons = {
        success: 'check-circle',
        error: 'exclamation-circle',
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };
    return icons[type] || 'info-circle';
}

function getNotificationColor(type) {
    const colors = {
        success: '#4CAF50',
        error: '#F44336',
        warning: '#FF9800',
        info: '#2196F3'
    };
    return colors[type] || '#2196F3';
}

// Close modals on outside click
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    .prediction-loading {
        text-align: center;
        padding: 2rem;
        color: var(--text-secondary);
    }
    
    .prediction-result {
        padding: 2rem;
        animation: fadeIn 0.5s ease;
    }
    
    .prediction-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    
    .confidence-badge {
        background: var(--success-color);
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 15px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    
    .prediction-metrics {
        display: grid;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .metric {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 0;
        border-bottom: 1px solid var(--border-color);
    }
    
    .metric .label {
        color: var(--text-secondary);
        font-weight: 500;
    }
    
    .metric .value {
        font-weight: 700;
        font-size: 1.1rem;
    }
    
    .metric .value.positive {
        color: var(--success-color);
    }
    
    .metric .value.negative {
        color: var(--error-color);
    }
    
    .recommendation {
        background: var(--background-color);
        padding: 1.5rem;
        border-radius: 10px;
    }
    
    .recommendation h4 {
        color: var(--primary-color);
        margin-bottom: 0.5rem;
    }
    
    .prediction-error {
        text-align: center;
        padding: 2rem;
        color: var(--error-color);
    }
    
    .prediction-error i {
        font-size: 2rem;
        margin-bottom: 1rem;
    }
`;
document.head.appendChild(style);
