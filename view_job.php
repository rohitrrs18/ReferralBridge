<?php
include 'config.php';
checkAuth();

$job_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $pdo->prepare("
    SELECT j.*, u.username, p.first_name, p.last_name, p.headline, p.about,
           (SELECT COUNT(*) FROM referrals r WHERE r.job_posting_id = j.id AND r.student_id = ?) as has_applied
    FROM job_postings j 
    JOIN users u ON j.alumni_id = u.id 
    JOIN profiles p ON u.id = p.user_id 
    WHERE j.id = ? AND j.is_verified = TRUE
");
$stmt->execute([$_SESSION['user_id'], $job_id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    header("Location: dashboard_student.php");
    exit();
}

// Handle referral application
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['application_message'])) {
    $application_message = $_POST['application_message'];
    
    $stmt = $pdo->prepare("
        INSERT INTO referrals (job_posting_id, student_id, alumni_id, application_message) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$job_id, $_SESSION['user_id'], $job['alumni_id'], $application_message]);
    
    header("Location: view_job.php?id=$job_id&applied=1");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($job['job_title']); ?> - AlumniConnect</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .job-detail-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .job-detail-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .job-detail-header {
            margin-bottom: 25px;
        }
        
        .job-detail-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .job-detail-company {
            font-size: 1.3rem;
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .job-detail-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin: 20px 0;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
            color: #666;
        }
        
        .job-detail-section {
            margin: 25px 0;
        }
        
        .section-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .job-description {
            line-height: 1.7;
            font-size: 1.05rem;
            color: #444;
        }
        
        .skills-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 15px 0;
        }
        
        .alumni-info {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            margin: 25px 0;
        }
        
        .alumni-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        
        .alumni-details h4 {
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .alumni-details p {
            color: #666;
            margin: 0;
        }
        
        .apply-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-top: 30px;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="dashboard-content">
            <div class="dashboard-header">
                <h2>Job Opportunity</h2>
                <p>Viewing job details</p>
            </div>
            
            <div class="job-detail-container">
                <div class="job-detail-card">
                    <?php if (isset($_GET['applied'])): ?>
                        <div class="success-message">
                            <i class="fas fa-check-circle"></i>
                            Your referral request has been submitted successfully!
                        </div>
                    <?php endif; ?>
                    
                    <div class="job-detail-header">
                        <h1 class="job-detail-title"><?php echo htmlspecialchars($job['job_title']); ?></h1>
                        <p class="job-detail-company"><?php echo htmlspecialchars($job['company_name']); ?></p>
                        
                        <div class="job-detail-meta">
                            <span class="meta-item"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($job['location']); ?></span>
                            <span class="meta-item"><i class="fas fa-clock"></i> <?php echo ucfirst($job['job_type']); ?></span>
                            <span class="meta-item"><i class="fas fa-chart-line"></i> <?php echo ucfirst($job['experience_level']); ?> Level</span>
                            <span class="meta-item"><i class="fas fa-calendar"></i> Posted <?php echo date('M j, Y', strtotime($job['posted_at'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="alumni-info">
                        <div class="alumni-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="alumni-details">
                            <h4><?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?></h4>
                            <p><?php echo htmlspecialchars($job['headline']); ?></p>
                            <p>Alumni Referrer</p>
                        </div>
                    </div>
                    
                    <div class="job-detail-section">
                        <h3 class="section-title">Job Description</h3>
                        <div class="job-description">
                            <?php echo nl2br(htmlspecialchars($job['job_description'])); ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($job['required_skills'])): ?>
                        <div class="job-detail-section">
                            <h3 class="section-title">Required Skills</h3>
                            <div class="skills-container">
                                <?php 
                                $skills = explode(',', $job['required_skills']);
                                foreach ($skills as $skill): 
                                    if (!empty(trim($skill))): ?>
                                        <span class="skill-pill"><?php echo trim($skill); ?></span>
                                    <?php endif;
                                endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($job['has_applied'] == 0): ?>
                        <div class="apply-section">
                            <h3 class="section-title">Request Referral</h3>
                            <p>This job was posted by an alumni. You can request a referral to increase your chances of getting hired.</p>
                            
                            <form method="POST" action="">
                                <div class="form-group">
                                    <label for="application_message">Message to Alumni</label>
                                    <textarea id="application_message" name="application_message" rows="4" placeholder="Introduce yourself and explain why you're interested in this position..." required></textarea>
                                    <small>This message will be sent to the alumni who posted this job.</small>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Send Referral Request</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="apply-section">
                            <div class="application-status status-submitted">
                                <i class="fas fa-check-circle"></i>
                                You've already applied for this position
                            </div>
                            <p>Check your <a href="dashboard_student.php?tab=applications">applications</a> to see the status of your referral request.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="script.js"></script>
</body>
</html>