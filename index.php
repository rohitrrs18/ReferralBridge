<?php include 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni-Student Network</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
               <img src="1758439766575.png" alt="logo" style="height:70px; border-radius: 10px;">
                <span>ReferralBridge</span>
            </div>
            <div class="nav-menu">
                <a href="#home" class="nav-link">Home</a>
                <a href="#about" class="nav-link">About</a>
                <a href="#features" class="nav-link">Features</a>
                <?php if (isLoggedIn()): ?>
                    <a href="logout.php" class="nav-link">Logout</a>
                    <?php 
                    $user = getUserData($_SESSION['user_id']);
                    if ($user['user_type'] == 'admin'): ?>
                        <a href="dashboard_admin.php" class="nav-link">Dashboard</a>
                    <?php elseif ($user['user_type'] == 'alumni'): ?>
                        <a href="dashboard_alumni.php" class="nav-link">Dashboard</a>
                    <?php else: ?>
                        <a href="dashboard_student.php" class="nav-link">Dashboard</a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="login.php" class="nav-link">Login</a>
                    <a href="register.php" class="nav-link">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <section id="home" class="hero">
        <div class="hero-content">
            <h1 class="hero-title">Connect. Network. Succeed.</h1>
            <p class="hero-description">Bridge the gap between students and alumni for mentorship, career guidance, and professional networking.</p>
            <div class="hero-buttons">
                <?php if (!isLoggedIn()): ?>
                    <a href="register.php" class="btn btn-primary">Get Started</a>
                    <a href="login.php" class="btn btn-secondary">Login</a>
                <?php else: ?>
                    <?php 
                    $user = getUserData($_SESSION['user_id']);
                    if ($user['user_type'] == 'admin'): ?>
                        <a href="dashboard_admin.php" class="btn btn-primary">Admin Dashboard</a>
                    <?php elseif ($user['user_type'] == 'alumni'): ?>
                        <a href="dashboard_alumni.php" class="btn btn-primary">Alumni Dashboard</a>
                    <?php else: ?>
                        <a href="dashboard_student.php" class="btn btn-primary">Student Dashboard</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="hero-image">
            <img src="1758439766575.png" alt="Networking Illustration">
        </div>
    </section>

    <section id="about" class="about">
        <h2>About ReferralBridge</h2>
        <p>A platform designed to foster connections between current students and alumni for mentorship, career guidance, and professional networking opportunities.</p>
    </section>

    <section id="features" class="features">
        <h2>Key Features</h2>
        <div class="features-grid">
            <div class="feature-card">
                <i class="fas fa-user-graduate"></i>
                <h3>Student Profiles</h3>
                <p>Create your profile, showcase your skills, and connect with alumni.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-briefcase"></i>
                <h3>Alumni Network</h3>
                <p>Verified alumni profiles with professional experience and resume uploads.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-comments"></i>
                <h3>Direct Messaging</h3>
                <p>Chat with your connections for mentorship and guidance.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-file-alt"></i>
                <h3>Resume Analysis</h3>
                <p>Get insights on your resume and improve your job application.</p>
            </div>
        </div>
    </section>

    <footer class="footer">
        <p>&copy; 2023 ReferralBridge AI. All rights reserved.</p>
    </footer>

    <script src="script.js"></script>
</body>
</html>