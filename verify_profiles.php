<?php
$page_title = "Verify Profiles - ReferralPortal";
include 'header.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || getUserType() != 'admin') {
    redirect('login.php');
}

// Handle verification actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $profile_id = $_POST['profile_id'];
    
    if ($_POST['action'] == 'verify') {
        $stmt = $pdo->prepare("UPDATE profiles SET is_verified = TRUE WHERE id = ?");
        $stmt->execute([$profile_id]);
        $_SESSION['success'] = "Profile verified successfully.";
    } elseif ($_POST['action'] == 'reject') {
        $stmt = $pdo->prepare("UPDATE profiles SET is_verified = FALSE WHERE id = ?");
        $stmt->execute([$profile_id]);
        $_SESSION['success'] = "Profile verification rejected.";
    } elseif ($_POST['action'] == 'delete') {
        $stmt = $pdo->prepare("DELETE FROM profiles WHERE id = ?");
        $stmt->execute([$profile_id]);
        $_SESSION['success'] = "Profile deleted successfully.";
    }
    
    redirect('verify_profiles.php');
}

// Fetch unverified alumni profiles
$stmt = $pdo->prepare("SELECT p.*, u.username, u.email 
                      FROM profiles p 
                      JOIN users u ON p.user_id = u.id 
                      WHERE p.is_verified = FALSE AND u.user_type = 'alumni'");
$stmt->execute();
$profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Verify Alumni Profiles</h4>
                <span class="badge bg-warning"><?php echo count($profiles); ?> pending</span>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                
                <?php if ($profiles): ?>
                    <?php foreach ($profiles as $profile): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <h5><?php echo htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']); ?></h5>
                                    <p class="text-muted">@<?php echo htmlspecialchars($profile['username']); ?> • <?php echo htmlspecialchars($profile['email']); ?></p>
                                    
                                    <?php if (!empty($profile['headline'])): ?>
                                    <p><strong><?php echo htmlspecialchars($profile['headline']); ?></strong></p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($profile['about'])): ?>
                                    <p><?php echo htmlspecialchars(substr($profile['about'], 0, 200)); ?>...</p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($profile['skills'])): ?>
                                    <div class="mb-3">
                                        <h6>Skills</h6>
                                        <div class="d-flex flex-wrap">
                                            <?php 
                                            $skills_array = explode(',', $profile['skills']);
                                            foreach ($skills_array as $skill): 
                                                $skill = trim($skill);
                                                if (!empty($skill)):
                                            ?>
                                            <span class="badge bg-light text-dark me-1 mb-1"><?php echo htmlspecialchars($skill); ?></span>
                                            <?php 
                                                endif;
                                            endforeach; 
                                            ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($profile['resume_path'])): ?>
                                    <div>
                                        <a href="<?php echo $profile['resume_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-download me-1"></i>View Resume
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="d-grid gap-2">
                                        <form method="POST" action="">
                                            <input type="hidden" name="profile_id" value="<?php echo $profile['id']; ?>">
                                            <input type="hidden" name="action" value="verify">
                                            <button type="submit" class="btn btn-success w-100 mb-2">
                                                <i class="fas fa-check-circle me-1"></i>Verify Profile
                                            </button>
                                        </form>
                                        
                                        <form method="POST" action="">
                                            <input type="hidden" name="profile_id" value="<?php echo $profile['id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn btn-warning w-100 mb-2">
                                                <i class="fas fa-times-circle me-1"></i>Reject Verification
                                            </button>
                                        </form>
                                        
                                        <form method="POST" action="">
                                            <input type="hidden" name="profile_id" value="<?php echo $profile['id']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="btn btn-danger w-100" onclick="return confirm('Are you sure you want to delete this profile?');">
                                                <i class="fas fa-trash me-1"></i>Delete Profile
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        <h5>No profiles pending verification</h5>
                        <p>All alumni profiles have been verified.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>