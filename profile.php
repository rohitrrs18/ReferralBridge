<?php include 'config.php'; ?>

<?php
// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $headline = trim($_POST['headline']);
    $about = trim($_POST['about']);
    $skills = trim($_POST['skills']);
    $experience = trim($_POST['experience']);
    $education = trim($_POST['education']);
    
    // Check if profile exists
    $stmt = $pdo->prepare("SELECT id FROM profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $profile_exists = $stmt->fetch();
    
    if ($profile_exists) {
        // Update profile
        $stmt = $pdo->prepare("UPDATE profiles SET first_name = ?, last_name = ?, headline = ?, about = ?, skills = ?, experience = ?, education = ? WHERE user_id = ?");
        $stmt->execute([$first_name, $last_name, $headline, $about, $skills, $experience, $education, $user_id]);
    } else {
        // Create profile
        $stmt = $pdo->prepare("INSERT INTO profiles (user_id, first_name, last_name, headline, about, skills, experience, education) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $first_name, $last_name, $headline, $about, $skills, $experience, $education]);
    }
    
    // Handle resume upload
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] == UPLOAD_ERR_OK) {
        $resume_name = $_FILES['resume']['name'];
        $resume_tmp = $_FILES['resume']['tmp_name'];
        $resume_ext = strtolower(pathinfo($resume_name, PATHINFO_EXTENSION));
        
        $allowed_ext = ['pdf', 'doc', 'docx'];
        
        if (in_array($resume_ext, $allowed_ext)) {
            $new_resume_name = "resume_{$user_id}_" . time() . ".{$resume_ext}";
            $upload_path = "uploads/resumes/{$new_resume_name}";
            
            // Create directory if it doesn't exist
            if (!file_exists('uploads/resumes')) {
                mkdir('uploads/resumes', 0777, true);
            }
            
            if (move_uploaded_file($resume_tmp, $upload_path)) {
                $stmt = $pdo->prepare("UPDATE profiles SET resume_path = ? WHERE user_id = ?");
                $stmt->execute([$upload_path, $user_id]);
                
                // Analyze resume for skills extraction
                if ($resume_ext == 'pdf') {
                    // For PDF, we would use a library like Smalot/pdfparser
                    // This is a simplified version
                    $resume_text = "PDF content extraction would be implemented here";
                } else {
                    // For DOC/DOCX, we would use a library like PHPWord
                    // This is a simplified version
                    $resume_text = "DOC content extraction would be implemented here";
                }
                
                // Extract skills from resume text
                $extracted_skills = extractKeywords($resume_text);
                
                // Merge with existing skills
                $existing_skills = $skills ? explode(',', $skills) : [];
                $all_skills = array_unique(array_merge($existing_skills, $extracted_skills));
                
                // Update skills in profile
                $stmt = $pdo->prepare("UPDATE profiles SET skills = ? WHERE user_id = ?");
                $stmt->execute([implode(',', $all_skills), $user_id]);
            }
        }
    }
    
    // If alumni, set verified to false until admin re-verifies
    if ($user_type == 'alumni') {
        $stmt = $pdo->prepare("UPDATE profiles SET verified = FALSE WHERE user_id = ?");
        $stmt->execute([$user_id]);
    }
    
    redirect("profile.php?message=" . urlencode("Profile updated successfully!") . "&type=success");
}

// Get user profile
$stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();

// If no profile exists, create empty values
if (!$profile) {
    $profile = [
        'first_name' => '',
        'last_name' => '',
        'headline' => '',
        'about' => '',
        'skills' => '',
        'experience' => '',
        'education' => '',
        'resume_path' => '',
        'verified' => 0
    ];
}
?>

<?php 
// Custom header for this page
$customHeader = '
<style>
:root {
    --primary: #000;
    --secondary: #333;
    --accent: #4e73df;
    --light: #f8f9fa;
    --dark: #343a40;
    --success: #28a745;
    --warning: #ffc107;
    --danger: #dc3545;
    --gray-100: #f8f9fa;
    --gray-200: #e9ecef;
    --gray-300: #dee2e6;
    --gray-800: #343a40;
}

body {
    background-color: white;
    color: #000;
    font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100%;
    width: 250px;
    background-color: white;
    border-right: 1px solid #e0e0e0;
    padding: 20px 0;
    z-index: 1000;
    box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
}

.sidebar-brand {
    padding: 15px 20px;
    margin-bottom: 20px;
    border-bottom: 1px solid #eee;
    text-align: center;
}

.sidebar-brand h2 {
    color: #000;
    font-weight: 700;
    font-size: 1.5rem;
    margin: 0;
}

.sidebar-nav {
    list-style: none;
    padding: 0;
    margin: 0;
}

.sidebar-nav li {
    margin-bottom: 5px;
}

.sidebar-nav a {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    color: #000;
    text-decoration: none;
    transition: all 0.2s;
    border-left: 3px solid transparent;
}

.sidebar-nav a:hover, .sidebar-nav a.active {
    background-color: #f5f5f5;
    border-left-color: #000;
}

.sidebar-nav i {
    margin-right: 10px;
    width: 20px;
    text-align: center;
}

.main-content {
    margin-left: 250px;
    padding: 30px;
    background-color: white;
    min-height: 100vh;
}

.page-header {
    margin-bottom: 30px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.page-header h1 {
    color: #000;
    font-weight: 600;
    margin: 0;
}

.card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
}

.card-header {
    background: white;
    border-bottom: 1px solid #e0e0e0;
    padding: 15px 20px;
    font-weight: 600;
    color: #000;
}

.card-body {
    padding: 20px;
}

.form-label {
    font-weight: 500;
    color: #000;
    margin-bottom: 8px;
    display: block;
}

.form-control {
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 10px 15px;
    background: white;
    color: #000;
}

.form-control:focus {
    border-color: #000;
    box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.1);
}

.btn {
    background: #000;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.2s;
}

.btn:hover {
    background: #333;
    color: white;
}

.profile-header {
    text-align: center;
    padding: 20px;
    background: white;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
}

.profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    margin-bottom: 15px;
    border: 3px solid #f0f0f0;
}

.profile-name {
    font-size: 1.5rem;
    font-weight: 600;
    color: #000;
    margin-bottom: 5px;
}

.profile-headline {
    color: #666;
    margin-bottom: 15px;
    font-size: 1rem;
}

.profile-stats {
    display: flex;
    justify-content: space-around;
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.profile-stat {
    text-align: center;
}

.profile-stat-value {
    font-size: 1.2rem;
    font-weight: 600;
    color: #000;
}

.profile-stat-label {
    font-size: 0.8rem;
    color: #666;
}

.skill-tag {
    background: #f0f0f0;
    color: #000;
    padding: 5px 12px;
    border-radius: 50px;
    font-size: 0.85rem;
    display: inline-block;
    margin: 0 5px 5px 0;
}

.alert {
    padding: 12px 15px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.navbar {
    display: none;
}
</style>
';

// Include the modified header
include 'header.php'; 
?>

<div class="sidebar">
    <div class="sidebar-brand">
        <h2>Alumni Network</h2>
    </div>
    
    <ul class="sidebar-nav">
        <li><a href="dashboard.php" class=""><i class="fas fa-home"></i> Dashboard</a></li>
        <li><a href="profile.php" class="active"><i class="fas fa-user"></i> Profile</a></li>
        <li><a href="jobs.php"><i class="fas fa-briefcase"></i> Jobs</a></li>
        <li><a href="network.php"><i class="fas fa-users"></i> Network</a></li>
        <li><a href="messages.php"><i class="fas fa-envelope"></i> Messages</a></li>
        <li><a href="events.php"><i class="fas fa-calendar"></i> Events</a></li>
        <?php if ($user_type == 'alumni'): ?>
        <li><a href="verification.php"><i class="fas fa-check-circle"></i> Verification</a></li>
        <?php endif; ?>
        <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>

<div class="main-content">
    <div class="page-header">
        <h1>Your Profile</h1>
    </div>

    <?php if (isset($_GET['message'])): ?>
    <div class="alert alert-<?php echo isset($_GET['type']) ? $_GET['type'] : 'success'; ?>">
        <?php echo htmlspecialchars($_GET['message']); ?>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-8">
            <div class="card">
                <div class="card-header">
                    Edit Profile Information
                </div>
                <div class="card-body">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label" for="first_name">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                        value="<?php echo htmlspecialchars($profile['first_name']); ?>" required>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label" for="last_name">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                        value="<?php echo htmlspecialchars($profile['last_name']); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="headline">Headline</label>
                            <input type="text" class="form-control" id="headline" name="headline" 
                                value="<?php echo htmlspecialchars($profile['headline']); ?>" 
                                placeholder="e.g. Software Engineer at Google">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="about">About</label>
                            <textarea class="form-control" id="about" name="about" rows="4" 
                                placeholder="Tell us about yourself..."><?php echo htmlspecialchars($profile['about']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="skills">Skills (comma separated)</label>
                            <textarea class="form-control" id="skills" name="skills" rows="3" 
                                placeholder="e.g. Java, Python, SQL, React..."><?php echo htmlspecialchars($profile['skills']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="experience">Experience</label>
                            <textarea class="form-control" id="experience" name="experience" rows="4" 
                                placeholder="Describe your work experience..."><?php echo htmlspecialchars($profile['experience']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="education">Education</label>
                            <textarea class="form-control" id="education" name="education" rows="3" 
                                placeholder="List your educational background..."><?php echo htmlspecialchars($profile['education']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="resume">Upload Resume</label>
                            <input type="file" class="form-control" id="resume" name="resume" accept=".pdf,.doc,.docx">
                            <?php if ($profile['resume_path']): ?>
                                <div style="margin-top: 10px;">
                                    <a href="<?php echo $profile['resume_path']; ?>" target="_blank">
                                        <i class="fas fa-file-pdf"></i> View Current Resume
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <button type="submit" class="btn">Save Profile</button>
                        
                        <?php if ($user_type == 'alumni' && !$profile['verified']): ?>
                            <a href="request_verification.php" class="btn" style="background: var(--success);">
                                <i class="fas fa-check-circle"></i> Request Verification
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-4">
            <div class="profile-header">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($profile['first_name'] . ' ' . $profile['last_name']); ?>&size=150&background=000000&color=fff" 
                     alt="Profile" class="profile-avatar">
                
                <h2 class="profile-name"><?php echo htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']); ?></h2>
                
                <?php if ($profile['headline']): ?>
                    <div class="profile-headline"><?php echo htmlspecialchars($profile['headline']); ?></div>
                <?php endif; ?>
                
                <?php if ($user_type == 'alumni'): ?>
                    <div style="margin-bottom: 15px;">
                        <?php if ($profile['verified']): ?>
                            <span style="background: var(--success); color: white; padding: 5px 10px; border-radius: 50px; font-size: 14px;">
                                <i class="fas fa-check-circle"></i> Verified Alumni
                            </span>
                        <?php else: ?>
                            <span style="background: var(--warning); color: #000; padding: 5px 10px; border-radius: 50px; font-size: 14px;">
                                <i class="fas fa-clock"></i> Verification Pending
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="profile-stats">
                    <div class="profile-stat">
                        <div class="profile-stat-value">0</div>
                        <div class="profile-stat-label">Connections</div>
                    </div>
                    
                    <div class="profile-stat">
                        <div class="profile-stat-value">0</div>
                        <div class="profile-stat-label">Views</div>
                    </div>
                    
                    <div class="profile-stat">
                        <div class="profile-stat-value">0</div>
                        <div class="profile-stat-label">Posts</div>
                    </div>
                </div>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <div class="card-header">
                    Skills
                </div>
                <div class="card-body">
                    <?php if ($profile['skills']): ?>
                        <div>
                            <?php 
                            $skills_array = explode(',', $profile['skills']);
                            foreach ($skills_array as $skill): 
                                $skill = trim($skill);
                                if (!empty($skill)):
                            ?>
                                <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                    <?php else: ?>
                        <p>No skills added yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>