<?php
include 'config.php';
checkAuth();

// Check if user is alumni
$user = getUserData($_SESSION['user_id']);
if ($user['user_type'] != 'alumni') {
    header("Location: dashboard_{$user['user_type']}.php");
    exit();
}

$profile = getProfileData($_SESSION['user_id']);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // ... existing profile update code ...
    
    // Handle job posting
    if (isset($_POST['company_name'])) {
        $company_name = $_POST['company_name'];
        $job_title = $_POST['job_title'];
        $job_description = $_POST['job_description'];
        $required_skills = $_POST['required_skills'];
        $location = $_POST['location'];
        $job_type = $_POST['job_type'];
        $experience_level = $_POST['experience_level'];
        
       // In the job posting section of the alumni dashboard
$stmt = $pdo->prepare("
    INSERT INTO job_postings (alumni_id, company_name, job_title, job_description, required_skills, location, job_type, experience_level, is_verified) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, FALSE)
");
$stmt->execute([$_SESSION['user_id'], $company_name, $job_title, $job_description, $required_skills, $location, $job_type, $experience_level]);
        
        header("Location: dashboard_alumni.php?tab=jobs");
        exit();
    }
}

// Get friend requests
$stmt = $pdo->prepare("
    SELECT fr.*, u.username, p.first_name, p.last_name 
    FROM friend_requests fr 
    JOIN users u ON fr.sender_id = u.id 
    JOIN profiles p ON u.id = p.user_id 
    WHERE fr.receiver_id = ? AND fr.status = 'pending'
");
$stmt->execute([$_SESSION['user_id']]);
$friend_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get job postings
$stmt = $pdo->prepare("
    SELECT j.*, COUNT(r.id) as referral_count 
    FROM job_postings j 
    LEFT JOIN referrals r ON j.id = r.job_posting_id 
    WHERE j.alumni_id = ? 
    GROUP BY j.id 
    ORDER BY j.posted_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$job_postings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending referrals
$stmt = $pdo->prepare("
    SELECT r.*, u.username, p.first_name, p.last_name, j.company_name, j.job_title 
    FROM referrals r 
    JOIN users u ON r.student_id = u.id 
    JOIN profiles p ON u.id = p.user_id 
    JOIN job_postings j ON r.job_posting_id = j.id 
    WHERE r.alumni_id = ? AND r.status = 'pending'
");
$stmt->execute([$_SESSION['user_id']]);
$pending_referrals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle actions
if (isset($_GET['accept_referral'])) {
    $referral_id = $_GET['accept_referral'];
    $stmt = $pdo->prepare("UPDATE referrals SET status = 'approved' WHERE id = ?");
    $stmt->execute([$referral_id]);
    header("Location: dashboard_alumni.php?tab=referrals");
    exit();
}

if (isset($_GET['reject_referral'])) {
    $referral_id = $_GET['reject_referral'];
    $stmt = $pdo->prepare("UPDATE referrals SET status = 'rejected' WHERE id = ?");
    $stmt->execute([$referral_id]);
    header("Location: dashboard_alumni.php?tab=referrals");
    exit();
}

if (isset($_GET['delete_job'])) {
    $job_id = $_GET['delete_job'];
    $stmt = $pdo->prepare("DELETE FROM job_postings WHERE id = ? AND alumni_id = ?");
    $stmt->execute([$job_id, $_SESSION['user_id']]);
    header("Location: dashboard_alumni.php?tab=jobs");
    exit();
}

$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Dashboard - AlumniConnect</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --success: #06d6a0;
            --danger: #ef476f;
            --warning: #ffd166;
            --info: #118ab2;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --smooth-transition: all 0.3s ease;
        }
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
        .dashboard-tabs {
            display: flex;
            border-bottom: 1px solid var(--light-gray);
            margin-bottom: 30px;
            background: white;
            border-radius: 12px;
            padding: 5px;
            box-shadow: var(--card-shadow);
        }
        
        .tab-button {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            color: var(--gray);
            border-radius: 8px;
            transition: var(--smooth-transition);
            position: relative;
        }
        
        .tab-button:hover {
            background-color: rgba(67, 97, 238, 0.05);
            color: var(--primary);
        }
        
        .tab-button.active {
            background-color: var(--primary);
            color: white;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.25);
        }
        
        .tab-content {
            display: none;
            animation: fadeIn 0.4s ease;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }
        
        .dashboard-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--card-shadow);
            transition: var(--smooth-transition);
        }
        
        .dashboard-card:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }
        
        .dashboard-card h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: var(--dark);
            font-weight: 600;
            font-size: 1.4rem;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .job-card, .referral-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
            border-left: 4px solid var(--primary);
            transition: var(--smooth-transition);
        }
        
        .job-card:hover, .referral-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
        }
        
        .job-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .job-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .job-company {
            font-size: 1rem;
            color: var(--primary);
            font-weight: 500;
        }
        
        .job-meta {
            display: flex;
            gap: 15px;
            margin: 12px 0;
            flex-wrap: wrap;
        }
        
        .job-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
            color: var(--gray);
            background: var(--light);
            padding: 5px 12px;
            border-radius: 20px;
        }
        
        .job-description {
            margin: 15px 0;
            line-height: 1.6;
            color: #444;
        }
        
        .job-skills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 15px 0;
        }
        
        .skill-pill {
            background: #eef3f8;
            color: var(--primary);
            padding: 6px 14px;
            border-radius: 16px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .job-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--light-gray);
        }
        
        .posted-date {
            font-size: 0.85rem;
            color: var(--gray);
        }
        
        .referral-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .stat {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px 15px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            line-height: 1;
            margin-bottom: 8px;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: var(--gray);
            font-weight: 500;
        }
        
        .referral-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px;
            background: var(--light);
            border-radius: 12px;
            margin-bottom: 12px;
            transition: var(--smooth-transition);
        }
        
        .referral-item:hover {
            background: #f1f3f5;
        }
        
        .referral-info h4 {
            margin-bottom: 5px;
            color: var(--dark);
            font-weight: 600;
        }
        
        .referral-info p {
            color: var(--gray);
            font-size: 0.9rem;
            margin-bottom: 3px;
        }
        
        .referral-actions {
            display: flex;
            gap: 10px;
        }
        
        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 3.5rem;
            color: #e9ecef;
            margin-bottom: 15px;
        }
        
        .empty-state p {
            font-size: 1.1rem;
            margin-bottom: 20px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            transition: var(--smooth-transition);
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
        }
        
        .btn-sm {
            padding: 7px 14px;
            font-size: 0.85rem;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-secondary {
            background: var(--light-gray);
            color: var(--dark);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .request-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: var(--light);
            border-radius: 10px;
            margin-bottom: 12px;
        }
        
        .request-info h4 {
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .request-info p {
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        .card-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--light-gray);
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-tabs {
                flex-wrap: wrap;
            }
            
            .tab-button {
                padding: 10px 15px;
                font-size: 0.9rem;
            }
            
            .job-header, .referral-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .referral-actions, .job-actions {
                width: 100%;
                justify-content: flex-start;
            }
            
            .job-meta {
                gap: 8px;
            }
            
            .job-meta span {
                font-size: 0.85rem;
            }
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
                    <img src="1758439766575.png" alt="logo" style="height: 50px; border-radius: 10px;">
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
                <a href="dashboard_student.php?tab=profile" class="sidebar-item <?php echo $current_tab == 'profile' ? 'active' : ''; ?>">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
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
                <h2>Alumni Dashboard</h2>
                <p>Welcome back, <?php echo $profile ? $profile['first_name'] : $user['username']; ?>!</p>
            </div>
            
            <div class="dashboard-tabs">
                <button class="tab-button <?php echo $current_tab == 'profile' ? 'active' : ''; ?>" onclick="switchTab('profile')">
                    <i class="fas fa-user"></i> Profile
                </button>
                <button class="tab-button <?php echo $current_tab == 'jobs' ? 'active' : ''; ?>" onclick="switchTab('jobs')">
                    <i class="fas fa-briefcase"></i> Job Postings
                </button>
                <button class="tab-button <?php echo $current_tab == 'referrals' ? 'active' : ''; ?>" onclick="switchTab('referrals')">
                    <i class="fas fa-handshake"></i> Referrals
                </button>
                <button class="tab-button <?php echo $current_tab == 'requests' ? 'active' : ''; ?>" onclick="switchTab('requests')">
                    <i class="fas fa-user-friends"></i> Connections
                </button>
            </div>
            
            <!-- Profile Tab -->
            <div id="profile-tab" class="tab-content <?php echo $current_tab == 'profile' ? 'active' : ''; ?>">
                <div class="dashboard-card">
                    <h3>Your Profile</h3>
                    <?php if ($profile && $profile['is_verified']): ?>
                        <div class="verification-badge status-approved status-badge">
                            <i class="fas fa-check-circle"></i>
                            <span>Verified Profile</span>
                        </div>
                    <?php elseif ($profile): ?>
                        <div class="verification-pending status-pending status-badge">
                            <i class="fas fa-clock"></i>
                            <span>Verification Pending</span>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" enctype="multipart/form-data">
                        <!-- Existing profile form fields -->
                    </form>
                </div>
            </div>
            
            <!-- Job Postings Tab -->
            <div id="jobs-tab" class="tab-content <?php echo $current_tab == 'jobs' ? 'active' : ''; ?>">
                <div class="dashboard-grid">
                    <div class="dashboard-card">
                        <h3>Post New Job Opportunity</h3>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="company_name">Company Name</label>
                                <input type="text" id="company_name" name="company_name" required class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label for="job_title">Job Title</label>
                                <input type="text" id="job_title" name="job_title" required class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label for="job_description">Job Description</label>
                                <textarea id="job_description" name="job_description" rows="4" required class="form-control"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="required_skills">Required Skills (comma separated)</label>
                                <textarea id="required_skills" name="required_skills" rows="2" placeholder="e.g. PHP, MySQL, JavaScript" class="form-control"></textarea>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="location">Location</label>
                                    <input type="text" id="location" name="location" class="form-control">
                                </div>
                                
                                <div class="form-group">
                                    <label for="job_type">Job Type</label>
                                    <select id="job_type" name="job_type" class="form-control">
                                        <option value="full-time">Full Time</option>
                                        <option value="part-time">Part Time</option>
                                        <option value="internship">Internship</option>
                                        <option value="contract">Contract</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="experience_level">Experience Level</label>
                                <select id="experience_level" name="experience_level" class="form-control">
                                    <option value="entry">Entry Level</option>
                                    <option value="mid">Mid Level</option>
                                    <option value="senior">Senior Level</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus-circle"></i> Post Job
                            </button>
                        </form>
                    </div>
                    
                    <div class="dashboard-card">
                        <h3>Your Job Postings</h3>
                        
                        <?php if (count($job_postings) > 0): ?>
                            <?php foreach ($job_postings as $job): ?>
                                <div class="job-card">
                                    <div class="job-header">
                                        <div>
                                            <h3 class="job-title"><?php echo htmlspecialchars($job['job_title']); ?></h3>
                                            <p class="job-company"><?php echo htmlspecialchars($job['company_name']); ?></p>
                                        </div>
                                        <span class="status-badge <?php echo $job['is_verified'] ? 'status-approved' : 'status-pending'; ?>">
                                            <?php echo $job['is_verified'] ? 'Verified' : 'Pending Approval'; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="job-meta">
                                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($job['location']); ?></span>
                                        <span><i class="fas fa-clock"></i> <?php echo ucfirst($job['job_type']); ?></span>
                                        <span><i class="fas fa-chart-line"></i> <?php echo ucfirst($job['experience_level']); ?> Level</span>
                                        <span><i class="fas fa-users"></i> <?php echo $job['referral_count']; ?> Referrals</span>
                                    </div>
                                    
                                    <div class="job-description">
                                        <?php echo nl2br(htmlspecialchars($job['job_description'])); ?>
                                    </div>
                                    
                                    <?php if (!empty($job['required_skills'])): ?>
                                        <div class="job-skills">
                                            <?php 
                                            $skills = explode(',', $job['required_skills']);
                                            foreach ($skills as $skill): 
                                                if (!empty(trim($skill))): ?>
                                                    <span class="skill-pill"><?php echo trim($skill); ?></span>
                                                <?php endif;
                                            endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="job-actions">
                                        <a href="?delete_job=<?php echo $job['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this job posting?')">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </a>
                                        <span class="posted-date">Posted: <?php echo date('M j, Y', strtotime($job['posted_at'])); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-briefcase"></i>
                                <p>No job postings yet</p>
                                <p class="text-muted">Post your first job opportunity to help students find great positions</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Referrals Tab -->
            <div id="referrals-tab" class="tab-content <?php echo $current_tab == 'referrals' ? 'active' : ''; ?>">
                <div class="dashboard-card">
                    <h3>Referral Requests</h3>
                    
                    <?php if (count($pending_referrals) > 0): ?>
                        <?php foreach ($pending_referrals as $referral): ?>
                            <div class="referral-item">
                                <div class="referral-info">
                                    <h4><?php echo htmlspecialchars($referral['first_name'] . ' ' . $referral['last_name']); ?></h4>
                                    <p>Applied for <?php echo htmlspecialchars($referral['job_title']); ?> at <?php echo htmlspecialchars($referral['company_name']); ?></p>
                                    <p><small><?php echo date('M j, Y g:i a', strtotime($referral['applied_at'])); ?></small></p>
                                </div>
                                
                                <div class="referral-actions">
                                    <a href="?accept_referral=<?php echo $referral['id']; ?>" class="btn btn-success btn-sm">
                                        <i class="fas fa-check"></i> Approve
                                    </a>
                                    <a href="?reject_referral=<?php echo $referral['id']; ?>" class="btn btn-danger btn-sm">
                                        <i class="fas fa-times"></i> Reject
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-handshake"></i>
                            <p>No pending referral requests</p>
                            <p class="text-muted">When students request referrals for your job postings, they'll appear here</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Connection Requests Tab -->
            <div id="requests-tab" class="tab-content <?php echo $current_tab == 'requests' ? 'active' : ''; ?>">
                <div class="dashboard-card">
                    <h3>Friend Requests</h3>
                    
                    <?php if (count($friend_requests) > 0): ?>
                        <div class="requests-list">
                            <?php foreach ($friend_requests as $request): ?>
                                <div class="request-item">
                                    <div class="request-info">
                                        <h4><?php echo $request['first_name'] . ' ' . $request['last_name']; ?></h4>
                                        <p>@<?php echo $request['username']; ?> (Student)</p>
                                    </div>
                                    <div class="request-actions">
                                        <a href="accept_request.php?request_id=<?php echo $request['id']; ?>" class="btn btn-success btn-sm">
                                            <i class="fas fa-check"></i> Accept
                                        </a>
                                        <a href="reject_request.php?request_id=<?php echo $request['id']; ?>" class="btn btn-danger btn-sm">
                                            <i class="fas fa-times"></i> Reject
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-user-friends"></i>
                            <p>No pending friend requests</p>
                            <p class="text-muted">Connection requests from students will appear here</p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="card-actions">
                        <a href="friends.php" class="btn btn-secondary">
                            <i class="fas fa-users"></i> Manage Connections
                        </a>
                        <a href="chat.php" class="btn btn-primary">
                            <i class="fas fa-comments"></i> Messages
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Update active tab button
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            event.currentTarget.classList.add('active');
            
            // Update URL without reloading page
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.replaceState({}, '', url);
        }
    </script>
</body>
</html>