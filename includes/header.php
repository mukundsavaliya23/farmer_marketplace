 <?php
if (!isset($page_title)) $page_title = 'FarmConnect Pro';
$user = current_user();
?>
 <!DOCTYPE html>
 <html lang="en">

 <head>
     <meta charset="UTF-8">
     <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <title><?php echo sanitize($page_title); ?> - FarmConnect Pro</title>
     <link rel="stylesheet" href="css/style.css">
     <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
     <link rel="preconnect" href="https://fonts.googleapis.com">
     <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
     <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
         rel="stylesheet">
 </head>

 <body>
     <!-- Navigation -->
     <nav class="navbar" id="navbar">
         <div class="nav-container">
             <div class="nav-logo">
                 <i class="fas fa-seedling"></i>
                 <h2>FarmConnect Pro</h2>
             </div>
             <div class="nav-menu" id="nav-menu">
                 <a href="index.php" class="nav-link">Home</a>
                 <a href="panels/farmer.php" class="nav-link">Farmer Panel</a>
                 <a href="panels/buyer.php" class="nav-link">Buyer Panel</a>
                 <?php if ($user && $user['user_type'] === 'admin'): ?>
                 <a href="panels/admin.php" class="nav-link">Admin Panel</a>
                 <?php endif; ?>
                 <div class="auth-buttons">
                     <?php if ($user): ?>
                     <div class="user-info">
                         <span>Welcome, <?php echo sanitize($user['full_name']); ?></span>
                         <a href="api/auth.php?action=logout" class="btn-logout">
                             <i class="fas fa-sign-out-alt"></i> Logout
                         </a>
                     </div>
                     <?php else: ?>
                     <button class="btn-login" onclick="showLoginModal()">
                         <i class="fas fa-sign-in-alt"></i> Login
                     </button>
                     <button class="btn-signup" onclick="showSignupModal()">
                         <i class="fas fa-user-plus"></i> Sign Up
                     </button>
                     <?php endif; ?>
                 </div>
             </div>
             <div class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                 <span></span>
                 <span></span>
                 <span></span>
             </div>
         </div>
     </nav>

     <main>