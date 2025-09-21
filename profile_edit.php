<?php
include 'config.php';
checkAuth();

$user = getUserData($_SESSION['user_id']);
$profile = getProfileData($_SESSION['user_id']);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $headline = $_POST['headline'];
    $about = $_POST['about'];
    $skills = $_POST['skills'];
    $education = $_POST['education'];
    $experience = $_POST['experience'];
    
    // Handle file upload
    $resume_path = $profile['resume_path'];
    if (!empty($_FILES['resume']['name'])) {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_name = time() . '_' . basename($_FILES['resume']['name']);
        $target_file = $target_dir . $file_name;
        
        if (move_uploaded_file($_FILES['resume']['tmp_name'], $target_file)) {
            $resume_path = $target_file;
        }
    }
    
    if ($profile) {
        // Update existing profile
        $stmt = $pdo->prepare("
            UPDATE profiles 
            SET first_name = ?, last_name = ?, headline = ?, about = ?, skills = ?, education = ?, experience = ?, resume_path = ?, updated_at = NOW() 
            WHERE user_id = ?
        ");
        $stmt->execute([$first_name, $last_name, $headline, $about, $skills, $education, $experience, $resume_path, $_SESSION['user_id']]);
    } else {
        // Create new profile
        $stmt = $pdo->prepare("
            INSERT INTO profiles (user_id, first_name, last_name, headline, about, skills, education, experience, resume_path) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$_SESSION['user_id'], $first_name, $last_name, $headline, $about, $skills, $education, $experience, $resume_path]);
    }
    
    // Request verification if alumni
    if ($user['user_type'] == 'alumni' && isset($_POST['request_verification'])) {
        $stmt = $pdo->prepare("UPDATE profiles SET is_verified = FALSE WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    }
    
    header("Location: profile_edit.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - AlumniConnect</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Sidebar Styles */
            .sidebar {
        width: var(--sidebar-width);
        background: white; /* Changed to white */
        color: black; /* Text color changed so it’s visible */
        height: 100vh;
        position: fixed;
        left: 0;
        top: 0;
        display: flex;
        flex-direction: column;
        transition: all 0.3s ease;
        z-index: 1000;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
    }

            
            .sidebar-header {
                padding: 25px 20px;
                border-bottom: 1px solid rgba(0, 0, 0, 0.1);
                text-align: center;
            }
            
            .sidebar-logo {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 12px;
                font-size: 1.5rem;
                font-weight: 700;
                color: black;
            }
            
            .sidebar-logo i {
                font-size: 2rem;
                color: black;
            }
            
            .sidebar-profile {
                padding: 25px 20px;
                text-align: center;
                border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            }
            
            .sidebar-avatar {
                width: 80px;
                height: 80px;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.1);
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 15px;
                border: 3px solid rgba(0, 0, 0, 0.2);

            }
            
            .sidebar-avatar i {
                font-size: 2rem;
                color: black;
            }
            
            .sidebar-userinfo h4 {
                font-size: 1.2rem;
                font-weight: 600;
                margin-bottom: 5px;
                color: black;
            }
            
            .sidebar-userinfo p {
                color: rgba(0, 0, 0, 0.7);
                font-size: 0.9rem;
            }
            
            .sidebar-menu {
                flex: 1;
                padding: 20px 0;
                overflow-y: auto;
            }
            
            .sidebar-item {
                display: flex;
                align-items: center;
                padding: 15px 25px;
                color: rgba(0, 0, 0, 0.9);
                text-decoration: none;
                transition: all 0.3s ease;
                border-left: 4px solid transparent;
            }
            
            .sidebar-item:hover {
                background: rgba(0, 0, 0, 0.05);
                color: black;
                border-left-color: var(--accent);
            }
            
            .sidebar-item.active {
                background: rgba(0, 0, 0, 0.1);
                color: white;
                border-left-color: black;
            }
            
            .sidebar-item i {
                width: 24px;
                margin-right: 15px;
                font-size: 1.1rem;
                color: black;
            }
            
            .sidebar-item span {
                flex: 1;
                color: black;
            }
            
            .logout-btn {
                margin-top: auto;
                background: rgba(0, 0, 0, 0.05);
                border-left: 4px solid transparent !important;
                color: black;
            }
            
            .logout-btn:hover {
                background: rgba(231, 29, 54, 0.2);
                color: black;   
            }
            
            /* Mobile Menu Button */
            .mobile-menu-btn {
                display: none;
                position: fixed;
                top: 20px;
                left: 20px;
                z-index: 1001;
                background: var(--primary);
                color: white;
                border: none;
                border-radius: 8px;
                padding: 10px;
                cursor: pointer;
            }
            /* Main Content */
            .dashboard-container {
                flex: 1;
                margin-left: var(--sidebar-width);
                padding: 30px;
                transition: all 0.3s ease;
            }
            
            .dashboard-content {
                max-width: 1200px;
                margin: 0 auto;
            }
            
            .dashboard-header {
                background: white;
                border-radius: 16px;
                padding: 25px;
                box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
                margin-bottom: 30px;
            }
            
            .dashboard-header h2 {
                font-size: 28px;
                color: #000000;;
                font-weight: 700;
                margin-bottom: 8px;
            }
            
            .dashboard-header p {
                color: var(--gray);
                font-size: 16px;
            }
            
    </style>
</head>
<body>
    <div class="dashboard-container">
     <!-- Mobile Menu Button -->
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <img src="1758439766575.png" alt="logo" style="height: 0px; border-radius: 10px;">
                    <span>ReferralBridge</span>
                </div>
            </div>
            
            <div class="sidebar-profile">
                <div class="sidebar-avatar">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="sidebar-userinfo">
                    <h4><?php echo htmlspecialchars($profile['first_name'] ?? $user['username']); ?></h4>
                    <p>@<?php echo htmlspecialchars($user['username']); ?></p>
                    <small style="color: rgba(255,255,255,0.7);">Student</small>
                </div>
            </div>
            
            <div class="sidebar-menu">
    
                
                <a href="dashboard_student.php?tab=applications" class="sidebar-item <?php echo $current_tab == 'applications' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt"></i>
                    <span>My Applications</span>
                </a>
                
                <a href="dashboard_student.php?tab=alumni" class="sidebar-item <?php echo $current_tab == 'alumni' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Alumni Network</span>
                </a>
                
                <a href="resume_analyzer.php" class="sidebar-item">
                    <i class="fas fa-file-alt"></i>
                    <span>Resume Analyzer</span>
                </a>
                
                <a href="chat.php" class="sidebar-item">
                    <i class="fas fa-comments"></i>
                    <span>Messages</span>
                </a>
                
                <a href="profile_edit.php" class="sidebar-item">
                    <i class="fas fa-user-edit"></i>
                    <span>Edit Profile</span>
                </a>
            </div>
            
            <a href="logout.php" class="sidebar-item logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>

        
        <div class="dashboard-content">
            <div class="dashboard-header">
                <h2>Edit Profile</h2>
                <p>Update your profile information</p>
            </div>
            
            <div class="dashboard-card">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" value="<?php echo $profile ? $profile['first_name'] : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" value="<?php echo $profile ? $profile['last_name'] : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="headline">Headline</label>
                        <input type="text" id="headline" name="headline" value="<?php echo $profile ? $profile['headline'] : ''; ?>" placeholder="e.g. Software Engineer at Google">
                    </div>
                    
                    <div class="form-group">
                        <label for="about">About</label>
                        <textarea id="about" name="about" rows="3" placeholder="Tell us about yourself"><?php echo $profile ? $profile['about'] : ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="skills">Skills</label>
                        <textarea id="skills" name="skills" rows="2" placeholder="Separate skills with commas"><?php echo $profile ? $profile['skills'] : ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="education">Education</label>
                        <textarea id="education" name="education" rows="2" placeholder="Your educational background"><?php echo $profile ? $profile['education'] : ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="experience">Experience</label>
                        <textarea id="experience" name="experience" rows="3" placeholder="Your professional experience"><?php echo $profile ? $profile['experience'] : ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="resume">Resume/CV</label>
                        <input type="file" id="resume" name="resume" accept=".pdf,.doc,.docx">
                        <?php if ($profile && $profile['resume_path']): ?>
                            <p>Current file: <a href="<?php echo $profile['resume_path']; ?>" target="_blank">View Resume</a></p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($user['user_type'] == 'alumni'): ?>
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="request_verification" value="1">
                                <span>Request verification</span>
                            </label>
                            <small>Check this box to request admin verification of your profile</small>
                        </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-primary">Save Profile</button>
                </form>
            </div>
        </div>
    </div>
    
    <script src="script.js"></script>
</body>
</html>