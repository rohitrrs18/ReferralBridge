<?php
$user = getUserData($_SESSION['user_id']);
$profile = getProfileData($_SESSION['user_id']);
?>
<div class="sidebar">
    <div class="sidebar-profile">
        <div class="sidebar-avatar">
            <i class="fas fa-user"></i>
        </div>
        <div class="sidebar-userinfo">
            <h4><?php echo $profile ? $profile['first_name'] . ' ' . $profile['last_name'] : $user['username']; ?></h4>
            <p>@<?php echo $user['username']; ?></p>
        </div>
    </div>
    
    <div class="sidebar-menu">
        <a href="dashboard_<?php echo $user['user_type']; ?>.php" class="sidebar-item active">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="profile_edit.php" class="sidebar-item">
            <i class="fas fa-user-edit"></i>
            <span>Edit Profile</span>
        </a>
        
        <?php if ($user['user_type'] == 'student' || $user['user_type'] == 'alumni'): ?>
            <a href="resume_analyzer.php" class="sidebar-item">
                <i class="fas fa-file-alt"></i>
                <span>Resume Analyzer</span>
            </a>
        <?php endif; ?>
        
        <a href="friends.php" class="sidebar-item">
            <i class="fas fa-user-friends"></i>
            <span>Connections</span>
        </a>
        
        <a href="chat.php" class="sidebar-item">
            <i class="fas fa-comments"></i>
            <span>Messages</span>
        </a>
        
        <a href="logout.php" class="sidebar-item logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>