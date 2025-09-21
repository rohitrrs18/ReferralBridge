<?php
$page_title = "Alumni Directory - ReferralPortal";
include 'header.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Search and filter handling
$search = isset($_GET['search']) ? $_GET['search'] : '';
$skill_filter = isset($_GET['skills']) ? $_GET['skills'] : '';

// Build query based on filters
$query = "SELECT p.*, u.username, u.email 
          FROM profiles p 
          JOIN users u ON p.user_id = u.id 
          WHERE p.is_verified = TRUE AND u.user_type = 'alumni'";

$params = [];

if (!empty($search)) {
    $query .= " AND (p.first_name LIKE ? OR p.last_name LIKE ? OR p.headline LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($skill_filter)) {
    $query .= " AND p.skills LIKE ?";
    $params[] = "%$skill_filter%";
}

$query .= " ORDER BY p.first_name, p.last_name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$alumni = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check connection status for each alumni
if ($user_type == 'student') {
    foreach ($alumni as &$alum) {
        $stmt = $pdo->prepare("SELECT status FROM connections WHERE student_id = ? AND alumni_id = ?");
        $stmt->execute([$user_id, $alum['user_id']]);
        $connection = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $alum['connection_status'] = $connection ? $connection['status'] : 'not_connected';
    }
    unset($alum); // Break the reference
}
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Alumni Directory</h4>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-12">
                        <form method="GET" action="">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="search" class="form-label">Search</label>
                                        <input type="text" class="form-control" id="search" name="search" 
                                               value="<?php echo htmlspecialchars($search); ?>" 
                                               placeholder="Name, username, or headline">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="skills" class="form-label">Skills</label>
                                        <input type="text" class="form-control" id="skills" name="skills" 
                                               value="<?php echo htmlspecialchars($skill_filter); ?>" 
                                               placeholder="Filter by skills">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="mb-3">
                                        <label class="form-label">&nbsp;</label>
                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-primary">Filter</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php if ($alumni): ?>
                    <div class="row">
                        <?php foreach ($alumni as $alum): ?>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-start">
                                        <div class="flex-grow-1">
                                            <h5><?php echo htmlspecialchars($alum['first_name'] . ' ' . $alum['last_name']); ?></h5>
                                            <p class="text-muted">@<?php echo htmlspecialchars($alum['username']); ?></p>
                                            
                                            <?php if (!empty($alum['headline'])): ?>
                                            <p><strong><?php echo htmlspecialchars($alum['headline']); ?></strong></p>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($alum['skills'])): ?>
                                            <div class="mb-3">
                                                <h6>Skills</h6>
                                                <div class="d-flex flex-wrap">
                                                    <?php 
                                                    $skills_array = explode(',', $alum['skills']);
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
                                        </div>
                                        <div class="ms-3">
                                            <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Verified</span>
                                        </div>
                                    </div>
                                    
                                    <?php if ($user_type == 'student'): ?>
                                    <div class="mt-3">
                                        <?php if ($alum['connection_status'] == 'not_connected'): ?>
                                        <form method="POST" action="handle_connection.php">
                                            <input type="hidden" name="alumni_id" value="<?php echo $alum['user_id']; ?>">
                                            <input type="hidden" name="action" value="connect">
                                            <button type="submit" class="btn btn-sm btn-primary">
                                                <i class="fas fa-user-plus me-1"></i>Connect
                                            </button>
                                        </form>
                                        <?php elseif ($alum['connection_status'] == 'pending'): ?>
                                        <button class="btn btn-sm btn-secondary" disabled>
                                            <i class="fas fa-clock me-1"></i>Request Pending
                                        </button>
                                        <?php elseif ($alum['connection_status'] == 'accepted'): ?>
                                        <div class="d-flex">
                                            <button class="btn btn-sm btn-success me-2" disabled>
                                                <i class="fas fa-check me-1"></i>Connected
                                            </button>
                                            <a href="chat.php?user_id=<?php echo $alum['user_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-comment me-1"></i>Message
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <h5>No alumni found</h5>
                        <p>Try adjusting your search filters or check back later as more alumni join the platform.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>