<?php
$page_title = "Post Job - ReferralPortal";
include 'header.php';

// Check if user is logged in and is alumni
if (!isLoggedIn() || getUserType() != 'alumni') {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// Handle job posting form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $company_name = $_POST['company_name'];
    $position = $_POST['position'];
    $description = $_POST['description'];
    $required_skills = $_POST['required_skills'];
    
    // Validate inputs
    $errors = [];
    
    if (empty($company_name)) {
        $errors[] = "Company name is required.";
    }
    
    if (empty($position)) {
        $errors[] = "Position is required.";
    }
    
    if (empty($description)) {
        $errors[] = "Description is required.";
    }
    
    // If no errors, insert job posting
    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO job_postings (alumni_id, company_name, position, description, required_skills) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $company_name, $position, $description, $required_skills]);
        
        $_SESSION['success'] = "Job posted successfully. It will be visible after admin approval.";
        redirect('my_jobs.php');
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Post a Job Opportunity</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="company_name" class="form-label">Company Name</label>
                            <input type="text" class="form-control" id="company_name" name="company_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="position" class="form-label">Position</label>
                            <input type="text" class="form-control" id="position" name="position" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Job Description</label>
                        <textarea class="form-control" id="description" name="description" rows="5" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="required_skills" class="form-label">Required Skills</label>
                        <textarea class="form-control" id="required_skills" name="required_skills" rows="3" 
                                  placeholder="Enter required skills separated by commas"></textarea>
                        <div class="form-text">List the skills required for this position</div>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle me-2"></i>Important Note</h6>
                        <p class="mb-0">Your job posting will be reviewed by administrators before it becomes visible to students. This process usually takes 24-48 hours.</p>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Post Job</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>