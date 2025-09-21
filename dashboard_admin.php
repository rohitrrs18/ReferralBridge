<?php
include 'config.php';
checkAuth();
// Get unverified job postings
$stmt = $pdo->prepare("
    SELECT j.*, u.username, p.first_name, p.last_name 
    FROM job_postings j 
    JOIN users u ON j.alumni_id = u.id 
    JOIN profiles p ON u.id = p.user_id 
    WHERE j.is_verified = FALSE
    ORDER BY j.posted_at DESC
");
$stmt->execute();
$pending_jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Check if user is admin
$user = getUserData($_SESSION['user_id']);
if ($user['user_type'] != 'admin') {
    header("Location: dashboard_{$user['user_type']}.php");
    exit();
}

// Get all profiles waiting for verification
$stmt = $pdo->prepare("
    SELECT p.*, u.username 
    FROM profiles p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.is_verified = FALSE AND u.user_type = 'alumni'
");
$stmt->execute();
$profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verify profile
if (isset($_GET['verify'])) {
    $profile_id = $_GET['verify'];
    $stmt = $pdo->prepare("UPDATE profiles SET is_verified = TRUE, verified_by = ?, verified_at = NOW() WHERE id = ?");
    $stmt->execute([$_SESSION['user_id'], $profile_id]);
    header("Location: dashboard_admin.php");
    exit();
}

// Delete profile
if (isset($_GET['delete'])) {
    $user_id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    header("Location: dashboard_admin.php");
    exit();
}

// Handle job verification
if (isset($_GET['verify_job'])) {
    $job_id = $_GET['verify_job'];
    $stmt = $pdo->prepare("UPDATE job_postings SET is_verified = TRUE, verified_by = ?, verified_at = NOW() WHERE id = ?");
    $stmt->execute([$_SESSION['user_id'], $job_id]);
    header("Location: dashboard_admin.php?tab=jobs");
    exit();
}

if (isset($_GET['delete_job'])) {
    $job_id = $_GET['delete_job'];
    $stmt = $pdo->prepare("DELETE FROM job_postings WHERE id = ?");
    $stmt->execute([$job_id]);
    header("Location: dashboard_admin.php?tab=jobs");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - AlumniConnect</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --danger: #e63946;
            --border-radius: 10px;
            --box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            --sidebar-width: 250px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f7fa;
            color: var(--dark);
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--dark);
            color: white;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            padding: 20px 0;
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        
        .sidebar-header h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }
        
        .sidebar-header h3 i {
            color: var(--primary);
        }
        
        .sidebar-menu {
            flex: 1;
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        
        .menu-item:hover, .menu-item.active {
            background: rgba(255,255,255,0.05);
            color: white;
            border-left-color: var(--primary);
        }
        
        .menu-item i {
            margin-right: 15px;
            width: 20px;
            text-align: center;
        }
        
        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .logout-btn {
            display: block;
            width: 100%;
            padding: 12px;
            background: rgba(255,255,255,0.1);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.2);
        }
        
        /* Main Content Styles */
        .dashboard-container {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 30px;
        }
        
        .dashboard-header {
            margin-bottom: 30px;
        }
        
        .dashboard-header h2 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark);
        }
        
        .dashboard-header p {
            color: var(--gray);
        }
        
        .dashboard-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
        }
        
        .dashboard-section h3 {
            font-size: 20px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
            color: var(--dark);
        }
        
        /* Profile Grid */
        .profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .profile-card {
            border: 1px solid #eee;
            border-radius: var(--border-radius);
            padding: 20px;
            transition: all 0.3s;
        }
        
        .profile-card:hover {
            box-shadow: var(--box-shadow);
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .profile-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 20px;
        }
        
        .profile-info h4 {
            margin-bottom: 5px;
        }
        
        .profile-info p {
            color: var(--gray);
            font-size: 14px;
        }
        
        .profile-details {
            margin-bottom: 15px;
        }
        
        .profile-details p {
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .profile-actions {
            display: flex;
            gap: 10px;
        }
        
        /* Buttons */
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: var(--border-radius);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            opacity: 0.9;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            opacity: 0.9;
        }
        
        .btn-secondary {
            background: var(--gray);
            color: white;
        }
        
        .btn-secondary:hover {
            opacity: 0.9;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 50px;
            margin-bottom: 15px;
            color: #ddd;
        }
        
        /* Job List */
        .job-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .job-card {
            border: 1px solid #eee;
            border-radius: var(--border-radius);
            padding: 20px;
            transition: all 0.3s;
        }
        
        .job-card:hover {
            box-shadow: var(--box-shadow);
        }
        
        .job-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .job-header h4 {
            font-size: 18px;
            margin-bottom: 5px;
        }
        
        .company {
            color: var(--primary);
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .job-details {
            margin-bottom: 15px;
        }
        
        .job-details p {
            margin-bottom: 10px;
        }
        
        .job-meta {
            display: flex;
            gap: 15px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .job-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
            color: var(--gray);
        }
        
        .job-actions {
            display: flex;
            gap: 10px;
        }
        
        /* Tabs */
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }
        
        .tab {
            padding: 10px 20px;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .tab.active {
            background: var(--primary);
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                overflow: hidden;
            }
            
            .sidebar-header h3 span, .menu-item span, .logout-btn span {
                display: none;
            }
            
            .dashboard-container {
                margin-left: 70px;
                padding: 15px;
            }
            
            .profile-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-actions, .job-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3> <img src="1758439766575.png" alt="logo" style="height: 50px; border-radius: 10px;"> <span>ReferralBridge</span></h3>
        </div>
        
        <div class="sidebar-menu">
            <a href="dashboard_admin.php" class="menu-item active">
                <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
            </a>
            <a href="dashboard_admin.php?tab=profiles" class="menu-item">
                <i class="fas fa-users"></i> <span>Profile Verification</span>
            </a>
            <a href="dashboard_admin.php?tab=jobs" class="menu-item">
                <i class="fas fa-briefcase"></i> <span>Job Postings</span>
            </a>
        </div>
        
        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h2>Admin Dashboard</h2>
            <p>Manage alumni profiles and platform settings</p>
        </div>
        
        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" data-tab="profiles">Profile Verification</div>
            <div class="tab" data-tab="jobs">Job Postings</div>
        </div>
        
        <!-- Profiles Tab -->
        <div class="tab-content active" id="profiles-tab">
            <div class="dashboard-section">
                <h3>Alumni Profiles Pending Verification</h3>
                
                <?php if (count($profiles) > 0): ?>
                    <div class="profile-grid">
                        <?php foreach ($profiles as $profile): ?>
                            <div class="profile-card">
                                <div class="profile-header">
                                    <div class="profile-avatar">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="profile-info">
                                        <h4><?php echo $profile['first_name'] . ' ' . $profile['last_name']; ?></h4>
                                        <p>@<?php echo $profile['username']; ?></p>
                                    </div>
                                </div>
                                
                                <div class="profile-details">
                                    <?php if (!empty($profile['headline'])): ?>
                                        <p><strong>Headline:</strong> <?php echo $profile['headline']; ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($profile['education'])): ?>
                                        <p><strong>Education:</strong> <?php echo $profile['education']; ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="profile-actions">
                                    <a href="?verify=<?php echo $profile['id']; ?>" class="btn btn-success">Verify</a>
                                    <a href="?delete=<?php echo $profile['user_id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this profile?')">Delete</a>
                                    <a href="view_profile.php?id=<?php echo $profile['user_id']; ?>" class="btn btn-secondary">View Details</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>No profiles pending verification</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Jobs Tab -->
        <div class="tab-content" id="jobs-tab">
            <div class="dashboard-section">
                <h3>Job Postings Pending Verification</h3>
                
                <?php if (count($pending_jobs) > 0): ?>
                    <div class="job-list">
                        <?php foreach ($pending_jobs as $job): ?>
                            <div class="job-card">
                                <div class="job-header">
                                    <div>
                                        <h4><?php echo htmlspecialchars($job['job_title']); ?></h4>
                                        <p class="company"><?php echo htmlspecialchars($job['company_name']); ?></p>
                                        <p>Posted by: <?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?></p>
                                    </div>
                                    <span class="status-badge status-pending">Pending</span>
                                </div>
                                
                                <div class="job-details">
                                    <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($job['job_description'])); ?></p>
                                    
                                    <?php if (!empty($job['required_skills'])): ?>
                                        <p><strong>Required Skills:</strong> <?php echo htmlspecialchars($job['required_skills']); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="job-meta">
                                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($job['location']); ?></span>
                                        <span><i class="fas fa-clock"></i> <?php echo ucfirst($job['job_type']); ?></span>
                                        <span><i class="fas fa-chart-line"></i> <?php echo ucfirst($job['experience_level']); ?></span>
                                    </div>
                                </div>
                                
                                <div class="job-actions">
                                    <a href="?verify_job=<?php echo $job['id']; ?>" class="btn btn-success">Verify</a>
                                    <a href="?delete_job=<?php echo $job['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this job posting?')">Delete</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>No job postings pending verification</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Tab functionality
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                // Remove active class from all tabs
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                // Add active class to clicked tab
                tab.classList.add('active');
                
                // Hide all tab contents
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                
                // Show the corresponding tab content
                const tabId = tab.getAttribute('data-tab');
                document.getElementById(`${tabId}-tab`).classList.add('active');
                
                // Update URL without reloading page
                const url = new URL(window.location);
                url.searchParams.set('tab', tabId);
                window.history.replaceState({}, '', url);
            });
        });
        
        // Check URL for tab parameter on page load
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            
            if (tabParam) {
                // Remove active class from all tabs
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                // Hide all tab contents
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                
                // Activate the specified tab
                const tabToActivate = document.querySelector(`.tab[data-tab="${tabParam}"]`);
                if (tabToActivate) {
                    tabToActivate.classList.add('active');
                    document.getElementById(`${tabParam}-tab`).classList.add('active');
                }
            }
        });
    </script>
</body>
</html>