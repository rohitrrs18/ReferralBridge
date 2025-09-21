<?php
$page_title = "Job Opportunities - ReferralPortal";
include 'header.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Fetch user profile for match score calculation
$stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

// Search and filter handling
$search = isset($_GET['search']) ? $_GET['search'] : '';
$skill_filter = isset($_GET['skills']) ? $_GET['skills'] : '';
$company_filter = isset($_GET['company']) ? $_GET['company'] : '';

// Build query based on filters
$query = "SELECT j.*, u.username 
          FROM job_postings j 
          JOIN users u ON j.alumni_id = u.id 
          WHERE j.is_approved = TRUE";

$params = [];

if (!empty($search)) {
    $query .= " AND (j.position LIKE ? OR j.company_name LIKE ? OR j.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($skill_filter)) {
    $query .= " AND j.required_skills LIKE ?";
    $params[] = "%$skill_filter%";
}

if (!empty($company_filter)) {
    $query .= " AND j.company_name = ?";
    $params[] = $company_filter;
}

$query .= " ORDER BY j.posted_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique companies for filter
$companies_stmt = $pdo->query("SELECT DISTINCT company_name FROM job_postings WHERE is_approved = TRUE ORDER BY company_name");
$companies = $companies_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Job Opportunities</h4>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-12">
                        <form method="GET" action="">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="search" class="form-label">Search</label>
                                        <input type="text" class="form-control" id="search" name="search" 
                                               value="<?php echo htmlspecialchars($search); ?>" 
                                               placeholder="Position, company, or keywords">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="skills" class="form-label">Skills</label>
                                        <input type="text" class="form-control" id="skills" name="skills" 
                                               value="<?php echo htmlspecialchars($skill_filter); ?>" 
                                               placeholder="Filter by skills">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="company" class="form-label">Company</label>
                                        <select class="form-select" id="company" name="company">
                                            <option value="">All Companies</option>
                                            <?php foreach ($companies as $company): ?>
                                            <option value="<?php echo htmlspecialchars($company); ?>" 
                                                <?php echo ($company_filter == $company) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($company); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
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
                
                <?php if ($jobs): ?>
                    <div class="row">
                        <?php foreach ($jobs as $job): 
                            // Calculate match score if user is a student with skills
                            $match_score = 0;
                            if ($user_type == 'student' && $profile && !empty($profile['skills']) && !empty($job['required_skills'])) {
                                $match_score = calculateMatchScore($job['required_skills'], $profile['skills']);
                            }
                        ?>
                        <div class="col-md-6 mb-4">
                            <div class="card job-card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <h5 class="card-title"><?php echo htmlspecialchars($job['position']); ?></h5>
                                        <?php if ($match_score > 0): ?>
                                        <span class="match-score"><?php echo $match_score; ?>% Match</span>
                                        <?php endif; ?>
                                    </div>
                                    <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($job['company_name']); ?></h6>
                                    <p class="card-text"><?php echo substr(htmlspecialchars($job['description']), 0, 200); ?>...</p>
                                    
                                    <?php if (!empty($job['required_skills'])): ?>
                                    <div class="mb-3">
                                        <h6>Required Skills:</h6>
                                        <div class="d-flex flex-wrap">
                                            <?php 
                                            $skills_array = explode(',', $job['required_skills']);
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
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">Posted by: <?php echo htmlspecialchars($job['username']); ?></small>
                                        <div>
                                            <a href="job_details.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline-primary me-2">View Details</a>
                                            <?php if ($user_type == 'student'): ?>
                                            <a href="apply_job.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-primary">Apply Now</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <h5>No job opportunities found</h5>
                        <p>Try adjusting your search filters or check back later for new opportunities.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>