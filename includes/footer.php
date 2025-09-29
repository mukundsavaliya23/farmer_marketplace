 </main>

 <!-- Footer -->
 <footer class="footer">
     <div class="container">
         <div class="footer-grid">
             <div class="footer-section">
                 <div class="footer-logo">
                     <i class="fas fa-seedling"></i>
                     <h3>FarmConnect Pro</h3>
                 </div>
                 <p>Empowering farmers and connecting them with buyers through innovative technology and AI-powered
                     insights.</p>
                 <div class="social-links">
                     <a href="#"><i class="fab fa-facebook"></i></a>
                     <a href="#"><i class="fab fa-twitter"></i></a>
                     <a href="#"><i class="fab fa-instagram"></i></a>
                     <a href="#"><i class="fab fa-linkedin"></i></a>
                 </div>
             </div>
             <div class="footer-section">
                 <h4>Quick Links</h4>
                 <ul class="footer-links">
                     <li><a href="index.php">Home</a></li>
                     <li><a href="panels/farmer.php">Farmer Panel</a></li>
                     <li><a href="panels/buyer.php">Buyer Panel</a></li>
                     <li><a href="#about">About Us</a></li>
                 </ul>
             </div>
             <div class="footer-section">
                 <h4>Support</h4>
                 <ul class="footer-links">
                     <li><a href="#help">Help Center</a></li>
                     <li><a href="#contact">Contact Us</a></li>
                     <li><a href="#faq">FAQ</a></li>
                     <li><a href="#terms">Terms of Service</a></li>
                 </ul>
             </div>
             <div class="footer-section">
                 <h4>Contact Info</h4>
                 <div class="contact-info">
                     <p><i class="fas fa-phone"></i> +91 98765 43210</p>
                     <p><i class="fas fa-envelope"></i> support@farmconnect.com</p>
                     <p><i class="fas fa-map-marker-alt"></i> Mumbai, Maharashtra, India</p>
                 </div>
             </div>
         </div>
         <div class="footer-bottom">
             <p>&copy; 2025 FarmConnect Pro. All rights reserved.</p>
         </div>
     </div>
 </footer>

 <!-- Login Modal -->
 <div id="loginModal" class="modal">
     <div class="modal-content">
         <div class="modal-header">
             <h2>Welcome Back</h2>
             <span class="close" onclick="closeModal('loginModal')">&times;</span>
         </div>
         <form id="loginForm" class="auth-form">
             <div class="form-group">
                 <label>User Type</label>
                 <select name="user_type" required>
                     <option value="">Select User Type</option>
                     <option value="farmer">Farmer</option>
                     <option value="buyer">Buyer</option>
                     <option value="admin">Admin</option>
                 </select>
             </div>
             <div class="form-group">
                 <label>Email Address</label>
                 <input type="email" name="email" placeholder="Enter your email" required>
             </div>
             <div class="form-group">
                 <label>Password</label>
                 <div class="password-input">
                     <input type="password" name="password" placeholder="Enter your password" required>
                     <i class="fas fa-eye password-toggle" onclick="togglePassword(this)"></i>
                 </div>
             </div>
             <button type="submit" class="btn-primary auth-btn">
                 <i class="fas fa-sign-in-alt"></i> Sign In
             </button>
         </form>
         <div class="auth-divider">
             <span>Don't have an account?</span>
             <a href="#" onclick="switchToSignup()">Sign Up</a>
         </div>
     </div>
 </div>

 <!-- Signup Modal -->
 <div id="signupModal" class="modal">
     <div class="modal-content">
         <div class="modal-header">
             <h2>Join FarmConnect</h2>
             <span class="close" onclick="closeModal('signupModal')">&times;</span>
         </div>
         <form id="signupForm" class="auth-form">
             <div class="form-row">
                 <div class="form-group">
                     <label>User Type</label>
                     <select name="user_type" required>
                         <option value="">Select Type</option>
                         <option value="farmer">Farmer</option>
                         <option value="buyer">Buyer</option>
                     </select>
                 </div>
                 <div class="form-group">
                     <label>Full Name</label>
                     <input type="text" name="full_name" placeholder="Enter your full name" required>
                 </div>
             </div>
             <div class="form-row">
                 <div class="form-group">
                     <label>Username</label>
                     <input type="text" name="username" placeholder="Choose a username" required>
                 </div>
                 <div class="form-group">
                     <label>Email Address</label>
                     <input type="email" name="email" placeholder="Enter your email" required>
                 </div>
             </div>
             <div class="form-group">
                 <label>Phone Number</label>
                 <input type="tel" name="phone" placeholder="Enter phone number" required>
             </div>
             <div class="form-group">
                 <label>Address</label>
                 <textarea name="address" rows="3" placeholder="Enter your address" required></textarea>
             </div>
             <div class="form-group">
                 <label>Password</label>
                 <div class="password-input">
                     <input type="password" name="password" placeholder="Create password" required>
                     <i class="fas fa-eye password-toggle" onclick="togglePassword(this)"></i>
                 </div>
             </div>
             <button type="submit" class="btn-primary auth-btn">
                 <i class="fas fa-user-plus"></i> Create Account
             </button>
         </form>
         <div class="auth-divider">
             <span>Already have an account?</span>
             <a href="#" onclick="switchToLogin()">Sign In</a>
         </div>
     </div>
 </div>

 <!-- Enhanced Chatbot -->
 <div id="chatbot" class="enhanced-chatbot">
     <div class="chatbot-header" onclick="toggleChatbot()">
         <div class="chatbot-title">
             <i class="fas fa-robot"></i>
             <span>Farm Assistant AI</span>
             <span class="online-status">Online</span>
         </div>
         <i class="fas fa-chevron-up toggle-icon"></i>
     </div>
     <div class="chatbot-body">
         <div class="chat-messages" id="chat-messages"></div>
         <div class="quick-actions">
             <button class="quick-btn" onclick="sendQuickMessage('What crops should I plant this season?')">
                 <i class="fas fa-seedling"></i> Crop Advice
             </button>
             <button class="quick-btn" onclick="sendQuickMessage('Current market prices for vegetables')">
                 <i class="fas fa-chart-line"></i> Market Prices
             </button>
             <button class="quick-btn" onclick="sendQuickMessage('How to control pests naturally?')">
                 <i class="fas fa-bug"></i> Pest Control
             </button>
         </div>
         <div class="chat-input-container">
             <input type="text" id="chat-input" placeholder="Ask about farming, crops, prices...">
             <button onclick="sendMessage()">
                 <i class="fas fa-paper-plane"></i>
             </button>
         </div>
     </div>
 </div>

 <script src="js/main.js"></script>
 <script src="js/chatbot.js"></script>
 </body>

 </html>