<?php include 'config.php'; ?>

<?php
// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Get user profile
$stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();

// Get stats based on user type
if ($user_type == 'student') {
    // Count applied jobs
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE student_id = ?");
    $stmt->execute([$user_id]);
    $applied_jobs = $stmt->fetchColumn();
    
    // Count connections
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM connections WHERE student_id = ? AND status = 'accepted'");
    $stmt->execute([$user_id]);
    $connections = $stmt->fetchColumn();
    
    // Count available jobs
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_postings WHERE approved = TRUE");
    $stmt->execute();
    $available_jobs = $stmt->fetchColumn();
} elseif ($user_type == 'alumni') {
    // Count job posts
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_postings WHERE alumni_id = ?");
    $stmt->execute([$user_id]);
    $job_posts = $stmt->fetchColumn();
    
    // Count connections
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM connections WHERE alumni_id = ? AND status = 'accepted'");
    $stmt->execute([$user_id]);
    $connections = $stmt->fetchColumn();
    
    // Count pending requests
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM connections WHERE alumni_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    $pending_requests = $stmt->fetchColumn();
} elseif ($user_type == 'admin') {
    // Count pending verifications
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM profiles WHERE verified = FALSE");
    $stmt->execute();
    $pending_verifications = $stmt->fetchColumn();
    
    // Count pending job approvals
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_postings WHERE approved = FALSE");
    $stmt->execute();
    $pending_approvals = $stmt->fetchColumn();
    
    // Count total users
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
    $stmt->execute();
    $total_users = $stmt->fetchColumn();
}
?>

<?php include 'header.php'; ?>

<h1 style="margin-bottom: 30px;">Welcome, <?php echo $_SESSION['username']; ?>!</h1>

<?php if (!$profile || empty($profile['first_name'])): ?>
    <div class="alert alert-info">
        <strong>Complete your profile!</strong> 
        <a href="profile.php" style="color: var(--info); text-decoration: underline;">Click here</a> 
        to set up your profile and get the most out of our platform.
    </div>
<?php endif; ?>

<div class="dashboard-stats">
    <?php if ($user_type == 'student'): ?>
        <div class="stat-card">
            <div class="stat-icon" style="background: var(--primary);">
                <i class="fas fa-briefcase"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $applied_jobs; ?></div>
                <div class="stat-label">Jobs Applied</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: var(--success);">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $connections; ?></div>
                <div class="stat-label">Connections</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: var(--info);">
                <i class="fas fa-search"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $available_jobs; ?></div>
                <div class="stat-label">Available Jobs</div>
            </div>
        </div>
    <?php elseif ($user_type == 'alumni'): ?>
        <div class="stat-card">
            <div class="stat-icon" style="background: var(--primary);">
                <i class="fas fa-briefcase"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $job_posts; ?></div>
                <div class="stat-label">Job Posts</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: var(--success);">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $connections; ?></div>
                <div class="stat-label">Connections</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: var(--warning);">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $pending_requests; ?></div>
                <div class="stat-label">Pending Requests</div>
            </div>
        </div>
    <?php elseif ($user_type == 'admin'): ?>
        <div class="stat-card">
            <div class="stat-icon" style="background: var(--warning);">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $pending_verifications; ?></div>
                <div class="stat-label">Pending Verifications</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: var(--info);">
                <i class="fas fa-tasks"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $pending_approvals; ?></div>
                <div class="stat-label">Pending Approvals</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: var(--success);">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $total_users; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="row">
    <div class="col-6">
        <div class="card">
            <div class="card-header">
                Quick Actions
            </div>
            <div class="card-body">
                <?php if ($user_type == 'student'): ?>
                    <a href="jobs.php" class="btn" style="display: block; text-align: center; margin-bottom: 10px;">
                        <i class="fas fa-search"></i> Browse Jobs
                    </a>
                    <a href="alumni.php" class="btn" style="display: block; text-align: center; margin-bottom: 10px;">
                        <i class="fas fa-user-graduate"></i> Find Alumni
                    </a>
                    <a href="profile.php" class="btn" style="display: block; text-align: center;">
                        <i class="fas fa-user-edit"></i> Edit Profile
                    </a>
                <?php elseif ($user_type == 'alumni'): ?>
                    <a href="post_job.php" class="btn" style="display: block; text-align: center; margin-bottom: 10px;">
                        <i class="fas fa-plus"></i> Post Opportunity
                    </a>
                    <a href="my_jobs.php" class="btn" style="display: block; text-align: center; margin-bottom: 10px;">
                        <i class="fas fa-briefcase"></i> View My Posts
                    </a>
                    <a href="profile.php" class="btn" style="display: block; text-align: center;">
                        <i class="fas fa-user-edit"></i> Edit Profile
                    </a>
                <?php elseif ($user_type == 'admin'): ?>
                    <a href="verify_profiles.php" class="btn" style="display: block; text-align: center; margin-bottom: 10px;">
                        <i class="fas fa-user-check"></i> Verify Profiles
                    </a>
                    <a href="manage_jobs.php" class="btn" style="display: block; text-align: center; margin-bottom: 10px;">
                        <i class="fas fa-tasks"></i> Manage Job Posts
                    </a>
                    <a href="manage_users.php" class="btn" style="display: block; text-align: center;">
                        <i class="fas fa-users-cog"></i> Manage Users
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-6">
        <div class="card">
            <div class="card-header">
                Recent Activity
            </div>
            <div class="card-body">
                <?php if ($user_type == 'student'): ?>
                    <p>Your recent job applications will appear here.</p>
                    <p>Connection requests and messages will also be shown here.</p>
                <?php elseif ($user_type == 'alumni'): ?>
                    <p>Your recent job posts will appear here.</p>
                    <p>Connection requests and referral activities will also be shown here.</p>
                <?php elseif ($user_type == 'admin'): ?>
                    <p>Recent profile verification requests will appear here.</p>
                    <p>Job post approvals and user activities will also be shown here.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>