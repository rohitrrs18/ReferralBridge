<?php
include 'config.php';
checkAuth();

$user = getUserData($_SESSION['user_id']);

// Get the profile to view
$profile_user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$profile_user = getUserData($profile_user_id);
$profile_data = getProfileData($profile_user_id);

// Check if users are connected
$is_connected = false;
if ($profile_user_id != $_SESSION['user_id']) {
    $stmt = $pdo->prepare("
        SELECT status 
        FROM friend_requests 
        WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) 
        AND status = 'accepted'
    ");
    $stmt->execute([$_SESSION['user_id'], $profile_user_id, $profile_user_id, $_SESSION['user_id']]);
    $is_connected = $stmt->rowCount() > 0;
}

// Check if there's a pending request
$pending_request = false;
if ($profile_user_id != $_SESSION['user_id']) {
    $stmt = $pdo->prepare("
        SELECT status 
        FROM friend_requests 
        WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'
    ");
    $stmt->execute([$_SESSION['user_id'], $profile_user_id]);
    $pending_request = $stmt->rowCount() > 0;
}

// Handle connection requests
if (isset($_GET['connect'])) {
    $alumni_id = $_GET['connect'];
    $stmt = $pdo->prepare("INSERT INTO friend_requests (sender_id, receiver_id) VALUES (?, ?)");
    $stmt->execute([$_SESSION['user_id'], $alumni_id]);
    header("Location: view_profile.php?id=" . $profile_user_id);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $profile_data['first_name'] . ' ' . $profile_data['last_name']; ?> - AlumniConnect</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Enhanced Profile View Styles */
        .profile-view {
            padding: 20px 0;
        }
        
        .profile-card-large {
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08), 0 4px 12px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .profile-cover {
            height: 200px;
            background: linear-gradient(135deg, #4e6fff 0%, #3a5df0 100%);
            position: relative;
        }
        
        .profile-header-large {
            padding: 0 30px 30px;
            position: relative;
            margin-top: -75px;
        }
        
        .profile-avatar-large {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4e6fff 0%, #3a5df0 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 3.5rem;
            border: 4px solid var(--white);
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08), 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .profile-info-large {
            margin-top: 20px;
        }
        
        .profile-info-large h1 {
            margin-bottom: 5px;
            font-size: 2.2rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .profile-headline {
            font-size: 1.3rem;
            margin-bottom: 10px;
            color: var(--gray);
            font-weight: 400;
        }
        
        .profile-location, .profile-connections {
            font-size: 0.95rem;
            color: var(--gray);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .profile-actions-large {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
        
        .profile-content {
            padding: 0 30px 30px;
        }
        
        .profile-section {
            margin-bottom: 30px;
            padding-bottom: 25px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        }
        
        .profile-section:last-child {
            margin-bottom: 0;
            border-bottom: none;
            padding-bottom: 0;
        }
        
        .profile-section h3 {
            margin-bottom: 15px;
            color: var(--dark);
            font-size: 1.4rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .profile-section h3 i {
            color: var(--primary);
            font-size: 1.2rem;
        }
        
        .profile-section p {
            line-height: 1.6;
            color: #444;
            font-size: 1rem;
        }
        
        .skills-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .skill-tag {
            background-color: #eef3f8;
            color: var(--primary);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .skill-tag:hover {
            background-color: #dfe8f3;
            transform: translateY(-2px);
        }
        
        .resume-download {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background-color: var(--primary);
            color: white;
            border-radius: 24px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .resume-download:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(78, 111, 255, 0.3);
        }
        
        .profile-stats {
            display: flex;
            gap: 30px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(0, 0, 0, 0.08);
        }
        
        .stat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary);
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .profile-header-large {
                flex-direction: column;
                text-align: center;
                padding: 0 20px 20px;
            }
            
            .profile-actions-large {
                justify-content: center;
            }
            
            .profile-content {
                padding: 0 20px 20px;
            }
        }
        
        @media (max-width: 768px) {
            .profile-cover {
                height: 150px;
            }
            
            .profile-avatar-large {
                width: 120px;
                height: 120px;
                font-size: 2.8rem;
            }
            
            .profile-info-large h1 {
                font-size: 1.8rem;
            }
            
            .profile-headline {
                font-size: 1.1rem;
            }
            
            .profile-actions-large {
                flex-direction: column;
                width: 100%;
            }
            
            .profile-actions-large .btn {
                width: 100%;
                text-align: center;
            }
            
            .profile-stats {
                flex-wrap: wrap;
                gap: 15px;
                justify-content: center;
            }
        }
        
        /* Animation for profile elements */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .profile-header-large, .profile-content {
            animation: fadeInUp 0.5s ease forwards;
        }
        
        .profile-content {
            animation-delay: 0.2s;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="dashboard-content">
            <div class="dashboard-header">
                <h2>Profile</h2>
                <p>Viewing profile of <?php echo $profile_data['first_name'] . ' ' . $profile_data['last_name']; ?></p>
            </div>
            
            <div class="profile-view">
                <div class="profile-card-large">
                    <div class="profile-cover"></div>
                    
                    <div class="profile-header-large">
                        <div class="profile-avatar-large">
                            <i class="fas fa-user"></i>
                        </div>
                        
                        <div class="profile-info-large">
                            <h1><?php echo $profile_data['first_name'] . ' ' . $profile_data['last_name']; ?></h1>
                            <p class="profile-headline"><?php echo $profile_data['headline']; ?></p>
                            
                            <div class="profile-location">
                                <i class="fas fa-map-marker-alt"></i>
                                <span>New York, USA</span>
                            </div>
                            
                            <div class="profile-connections">
                                <i class="fas fa-users"></i>
                                <span>500+ connections</span>
                            </div>
                            
                            <?php if ($profile_data['is_verified']): ?>
                                <div class="verification-badge">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Verified Profile</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($profile_user_id != $_SESSION['user_id']): ?>
                            <div class="profile-actions-large">
                                <?php if ($is_connected): ?>
                                    <a href="chat.php?user=<?php echo $profile_user_id; ?>" class="btn btn-success">
                                        <i class="fas fa-comment"></i> Message
                                    </a>
                                    <a href="#" class="btn btn-outline">
                                        <i class="fas fa-envelope"></i> Email
                                    </a>
                                <?php elseif ($pending_request): ?>
                                    <button class="btn btn-secondary" disabled>
                                        <i class="fas fa-clock"></i> Request Sent
                                    </button>
                                <?php elseif ($user['user_type'] == 'student' && $profile_user['user_type'] == 'alumni' && $profile_data['is_verified']): ?>
                                    <a href="?connect=<?php echo $profile_user_id; ?>" class="btn btn-primary">
                                        <i class="fas fa-user-plus"></i> Connect
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="profile-actions-large">
                                <a href="profile_edit.php" class="btn btn-outline">
                                    <i class="fas fa-edit"></i> Edit Profile
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="profile-stats">
                        <div class="stat-item">
                            <span class="stat-value">12</span>
                            <span class="stat-label">Connections</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">45</span>
                            <span class="stat-label">Profile Views</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">8</span>
                            <span class="stat-label">Posts</span>
                        </div>
                    </div>
                    
                    <div class="profile-content">
                        <div class="profile-section">
                            <h3><i class="fas fa-user"></i> About</h3>
                            <p><?php echo !empty($profile_data['about']) ? nl2br(htmlspecialchars($profile_data['about'])) : 'No information provided.'; ?></p>
                        </div>
                        
                        <div class="profile-section">
                            <h3><i class="fas fa-graduation-cap"></i> Education</h3>
                            <p><?php echo !empty($profile_data['education']) ? nl2br(htmlspecialchars($profile_data['education'])) : 'No education information provided.'; ?></p>
                        </div>
                        
                        <div class="profile-section">
                            <h3><i class="fas fa-briefcase"></i> Experience</h3>
                            <p><?php echo !empty($profile_data['experience']) ? nl2br(htmlspecialchars($profile_data['experience'])) : 'No experience information provided.'; ?></p>
                        </div>
                        
                        <div class="profile-section">
                            <h3><i class="fas fa-star"></i> Skills</h3>
                            <div class="skills-container">
                                <?php if (!empty($profile_data['skills'])): 
                                    $skills = explode(',', $profile_data['skills']);
                                    foreach ($skills as $skill): 
                                        if (!empty(trim($skill))): ?>
                                            <span class="skill-tag"><?php echo trim($skill); ?></span>
                                        <?php endif;
                                    endforeach;
                                else: ?>
                                    <p>No skills listed.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($profile_data['resume_path'])): ?>
                            <div class="profile-section">
                                <h3><i class="fas fa-file-alt"></i> Resume</h3>
                                <a href="<?php echo $profile_data['resume_path']; ?>" target="_blank" class="resume-download">
                                    <i class="fas fa-download"></i> Download Resume
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="script.js"></script>
</body>
</html>