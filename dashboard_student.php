    <?php
    include 'config.php';
    checkAuth();

    // Check if user is student
    $user = getUserData($_SESSION['user_id']);
    if ($user['user_type'] != 'student') {
        header("Location: dashboard_{$user['user_type']}.php");
        exit();
    }

    $profile = getProfileData($_SESSION['user_id']);

    // Handle profile update
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // ... existing profile update code ...
        
        // Handle referral application
        if (isset($_POST['job_id'])) {
            $job_id = $_POST['job_id'];
            $application_message = $_POST['application_message'];
            
            // Get job details to find alumni_id
            $stmt = $pdo->prepare("SELECT alumni_id FROM job_postings WHERE id = ?");
            $stmt->execute([$job_id]);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($job) {
                // Check if already applied
                $check_stmt = $pdo->prepare("SELECT id FROM referrals WHERE job_posting_id = ? AND student_id = ?");
                $check_stmt->execute([$job_id, $_SESSION['user_id']]);
                
                if ($check_stmt->rowCount() == 0) {
                    $stmt = $pdo->prepare("
                        INSERT INTO referrals (job_posting_id, student_id, alumni_id, application_message) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$job_id, $_SESSION['user_id'], $job['alumni_id'], $application_message]);
                    
                    header("Location: dashboard_student.php?tab=applications&applied=1");
                    exit();
                } else {
                    // Already applied
                    header("Location: dashboard_student.php?tab=opportunities&error=already_applied");
                    exit();
                }
            }
        }
    }

    // Get search parameters
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $skill_filter = isset($_GET['skills']) ? $_GET['skills'] : '';
    $company_filter = isset($_GET['company']) ? $_GET['company'] : '';

    // Initialize query and parameters
    $query = "
        SELECT j.*, u.username, p.first_name, p.last_name,
            EXISTS(SELECT 1 FROM referrals r WHERE r.job_posting_id = j.id AND r.student_id = ?) as has_applied
        FROM job_postings j 
        JOIN users u ON j.alumni_id = u.id 
        JOIN profiles p ON u.id = p.user_id 
        WHERE j.is_verified = TRUE
    ";
    $params = [$_SESSION['user_id']];

    // Add search filters
    if (!empty($search)) {
        $query .= " AND (j.job_title LIKE ? OR j.company_name LIKE ? OR j.job_description LIKE ?)";
        $search_term = "%$search%";
        array_push($params, $search_term, $search_term, $search_term);
    }

    if (!empty($skill_filter)) {
        $query .= " AND j.required_skills LIKE ?";
        array_push($params, "%$skill_filter%");
    }

    if (!empty($company_filter)) {
        $query .= " AND j.company_name LIKE ?";
        array_push($params, "%$company_filter%");
    }

    $query .= " ORDER BY j.posted_at DESC";

    // Debug: Check if we have any job postings at all
    $debug_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM job_postings WHERE is_verified = TRUE");
    $debug_stmt->execute();
    $job_count = $debug_stmt->fetch(PDO::FETCH_ASSOC);
    error_log("Total verified jobs in database: " . $job_count['count']);

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $job_postings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug: Check what jobs were found
    error_log("Found " . count($job_postings) . " job postings after filtering");
    if (count($job_postings) > 0) {
        error_log("Sample job: " . print_r($job_postings[0], true));
    }

    // Get user's applications
    $stmt = $pdo->prepare("
        SELECT r.*, j.company_name, j.job_title, p.first_name, p.last_name 
        FROM referrals r 
        JOIN job_postings j ON r.job_posting_id = j.id 
        JOIN profiles p ON r.alumni_id = p.user_id 
        WHERE r.student_id = ? 
        ORDER BY r.applied_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get unique companies for filter
    $stmt = $pdo->prepare("SELECT DISTINCT company_name FROM job_postings WHERE is_verified = TRUE ORDER BY company_name");
    $stmt->execute();
    $companies = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get all skills for filter
    $stmt = $pdo->prepare("SELECT required_skills FROM job_postings WHERE is_verified = TRUE");
    $stmt->execute();
    $all_skills = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $skills) {
        if (!empty($skills)) {
            $skill_list = explode(',', $skills);
            foreach ($skill_list as $skill) {
                $skill = trim($skill);
                if (!empty($skill) && !in_array($skill, $all_skills)) {
                    $all_skills[] = $skill;
                }
            }
        }
    }
    sort($all_skills);

    // Get alumni for network tab
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, p.first_name, p.last_name, p.headline,  p.skills
        FROM users u 
        JOIN profiles p ON u.id = p.user_id 
        WHERE u.user_type = 'alumni' 
        ORDER BY p.first_name, p.last_name
    ");
    $stmt->execute();
    $alumni = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'opportunities';
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Student Dashboard - ReferralBridge</title>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            :root {
                --primary: #4361ee;
                --primary-dark: #3a0ca3;
                --secondary: #7209b7;
                --accent: #4cc9f0;
                --success: #2ec4b6;
                --warning: #ff9f1c;
                --danger: #e71d36;
                --dark: #2b2d42;
                --light: #f8f9fa;
                --gray: #6c757d;
                --light-gray: #e9ecef;
                --sidebar-width: 280px;
                --sidebar-collapsed: 80px;
            }
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                font-family: 'Poppins', sans-serif;
            }
            
            body {
                background: linear-gradient(135deg, #f5f7fa 0%, #e6e9f0 100%);
                color: #333;
                min-height: 100vh;
                display: flex;
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
            
            /* Main Content */
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
            
            .welcome-banner {
                background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
                color: white;
                border-radius: 16px;
                padding: 25px;
                margin-bottom: 30px;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }
            
            .welcome-text h3 {
                font-size: 22px;
                margin-bottom: 8px;
                font-weight: 600;
            }
            
            .welcome-text p {
                opacity: 0.9;
            }
            
            .welcome-stats {
                display: flex;
                gap: 20px;
            }
            
            .stat-item {
                text-align: center;
                padding: 15px 20px;
                background: rgba(255, 255, 255, 0.15);
                border-radius: 12px;
                backdrop-filter: blur(10px);
            }
            
            .stat-number {
                font-size: 24px;
                font-weight: 700;
                margin-bottom: 5px;
            }
            
            .stat-label {
                font-size: 14px;
                opacity: 0.9;
            }
            
            .dashboard-tabs {
                display: flex;
                background: white;
                border-radius: 16px;
                padding: 8px;
                margin-bottom: 25px;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
                overflow-x: auto;
            }
            
            .tab-button {
                padding: 14px 24px;
                border: none;
                background: none;
                border-radius: 12px;
                cursor: pointer;
                font-weight: 500;
                color: var(--gray);
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                gap: 10px;
                white-space: nowrap;
            }
            
            .tab-button:hover {
                background: rgba(67, 97, 238, 0.1);
                color: var(--primary);
            }
            
            .tab-button.active {
                background: var(--primary);
                color: white;
                box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
            }
            
            .tab-content {
                display: none;
            }
            
            .tab-content.active {
                display: block;
                animation: fadeIn 0.5s ease;
            }
            
            .dashboard-card {
                background: white;
                border-radius: 16px;
                padding: 28px;
                margin-bottom: 25px;
                box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
                transition: transform 0.3s ease, box-shadow 0.3s ease;
            }
            
            .dashboard-card:hover {
                transform: translateY(-3px);
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
            }
            
            .dashboard-card h3 {
                color: var(--primary-dark);
                font-weight: 600;
                margin-bottom: 20px;
                font-size: 20px;
                display: flex;
                align-items: center;
                gap: 12px;
            }
            
            .dashboard-card h3 i {
                color: var(--primary);
                font-size: 24px;
            }
            
            .btn {
                padding: 12px 24px;
                border: none;
                border-radius: 10px;
                cursor: pointer;
                font-weight: 600;
                transition: all 0.3s ease;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                text-decoration: none;
                font-size: 15px;
            }
            
            .btn-primary {
                background: var(--primary);
                color: white;
                box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
            }
            
            .btn-primary:hover {
                background: var(--primary-dark);
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(67, 97, 238, 0.4);
            }
            
            .btn-outline {
                background: transparent;
                color: var(--primary);
                border: 2px solid var(--primary);
            }
            
            .btn-outline:hover {
                background: var(--primary);
                color: white;
            }
            
            .btn-success {
                background: var(--success);
                color: white;
            }
            
            .btn-sm {
                padding: 10px 18px;
                font-size: 14px;
            }
            
            .form-group {
                margin-bottom: 20px;
            }
            
            .form-group label {
                display: block;
                margin-bottom: 8px;
                font-weight: 500;
                color: var(--dark);
            }
            
            .form-group input, .form-group textarea, .form-group select {
                width: 100%;
                padding: 14px 16px;
                border: 2px solid var(--light-gray);
                border-radius: 10px;
                font-size: 16px;
                transition: all 0.3s ease;
            }
            
            .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
                border-color: var(--primary);
                outline: none;
                box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
            }
            
            .form-group textarea {
                min-height: 120px;
                resize: vertical;
            }
            
            .form-actions {
                display: flex;
                gap: 12px;
                justify-content: flex-end;
                margin-top: 25px;
            }
            
            .form-row {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
            }
            
            .empty-state {
                text-align: center;
                padding: 50px 20px;
                color: var(--gray);
            }
            
            .empty-state i {
                font-size: 64px;
                margin-bottom: 20px;
                color: #dee2e6;
                opacity: 0.7;
            }
            
            .empty-state p {
                font-size: 18px;
                margin-bottom: 15px;
            }
            
            .empty-state .btn {
                margin-top: 15px;
            }
            
            .search-filters {
                background: white;
                border-radius: 16px;
                padding: 25px;
                margin-bottom: 25px;
                box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            }
            
            .filter-row {
                display: flex;
                gap: 20px;
                flex-wrap: wrap;
                margin-bottom: 15px;
            }
            
            .filter-group {
                flex: 1;
                min-width: 250px;
            }
            
            .filter-group label {
                display: block;
                margin-bottom: 8px;
                font-weight: 500;
                color: var(--dark);
            }
            
            .filter-group input, .filter-group select {
                width: 100%;
                padding: 12px 16px;
                border: 2px solid var(--light-gray);
                border-radius: 10px;
                font-size: 15px;
            }
            
            .search-button {
                align-self: flex-end;
            }
            
            .job-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
                gap: 25px;
            }
            
            .job-card {
                background: white;
                border-radius: 16px;
                padding: 25px;
                box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
                transition: transform 0.3s ease, box-shadow 0.3s ease;
                border-left: 4px solid var(--primary);
            }
            
            .job-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            }
            
            .job-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 20px;
            }
            
            .job-title {
                font-size: 20px;
                font-weight: 600;
                color: var(--dark);
                margin-bottom: 8px;
            }
            
            .job-company {
                font-size: 16px;
                color: var(--primary);
                font-weight: 500;
                margin-bottom: 5px;
            }
            
            .job-poster {
                font-size: 14px;
                color: var(--gray);
            }
            
            .job-meta {
                display: flex;
                gap: 20px;
                margin: 15px 0;
                flex-wrap: wrap;
            }
            
            .job-meta span {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 14px;
                color: var(--gray);
            }
            
            .job-meta i {
                color: var(--primary);
                font-size: 16px;
            }
            
            .job-description {
                margin: 20px 0;
                line-height: 1.6;
                color: #444;
                font-size: 15px;
                display: -webkit-box;
                -webkit-line-clamp: 3;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }
            
            .job-skills {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin: 20px 0;
            }
            
            .skill-pill {
                background: #eef3f8;
                color: var(--primary);
                padding: 6px 14px;
                border-radius: 20px;
                font-size: 14px;
                font-weight: 500;
            }
            
            .job-actions {
                display: flex;
                gap: 12px;
                margin-top: 20px;
            }
            
            .application-status {
                padding: 8px 16px;
                border-radius: 20px;
                font-size: 14px;
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
            
            .status-submitted {
                background: #cce5ff;
                color: #004085;
            }
            
            .application-card {
                background: white;
                border-radius: 16px;
                padding: 25px;
                margin-bottom: 20px;
                box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
                border-left: 4px solid var(--primary);
                transition: transform 0.3s ease;
            }
            
            .application-card:hover {
                transform: translateY(-3px);
            }
            
            .application-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
            }
            
            .application-job {
                font-size: 18px;
                font-weight: 600;
                color: var(--dark);
            }
            
            .application-company {
                color: var(--primary);
                font-weight: 500;
                margin-top: 5px;
            }
            
            .application-date {
                color: var(--gray);
                font-size: 14px;
            }
            
            .application-message {
                margin: 15px 0;
                padding: 20px;
                background: #f8f9fa;
                border-radius: 12px;
                font-size: 15px;
                line-height: 1.6;
            }
            
            .application-actions {
                margin-top: 20px;
            }
            
            .modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.5);
                backdrop-filter: blur(5px);
            }
            
            .modal-content {
                background-color: white;
                margin: 10% auto;
                padding: 35px;
                border-radius: 20px;
                width: 90%;
                max-width: 600px;
                box-shadow: 0 10px 35px rgba(0,0,0,0.2);
                position: relative;
            }
            
            .close-modal {
                position: absolute;
                top: 20px;
                right: 25px;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
                color: var(--gray);
                transition: color 0.3s ease;
            }
            
            .close-modal:hover {
                color: var(--dark);
            }
            
            .success-message {
                background: #d4edda;
                color: #155724;
                padding: 18px;
                border-radius: 12px;
                margin-bottom: 25px;
                border-left: 4px solid #28a745;
                display: flex;
                align-items: center;
                gap: 12px;
            }
            
            .success-message i {
                font-size: 20px;
            }
            
            .alumni-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
                gap: 25px;
                margin-top: 25px;
            }
            
            .alumni-card {
                background: white;
                border-radius: 16px;
                padding: 25px;
                box-shadow: 0 5px 20px rgba(0,0,0,0.08);
                transition: transform 0.3s ease, box-shadow 0.3s ease;
            }
            
            .alumni-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            }
            
            .alumni-header {
                display: flex;
                align-items: center;
                margin-bottom: 20px;
            }
            
            .alumni-avatar {
                width: 70px;
                height: 70px;
                background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-right: 20px;
                font-size: 24px;
                color: white;
                font-weight: 600;
            }
            
            .alumni-info h4 {
                margin: 0;
                color: var(--dark);
                font-weight: 600;
            }
            
            .alumni-info p {
                margin: 5px 0 0 0;
                color: var(--gray);
                font-size: 14px;
            }
            
            .alumni-details {
                margin-bottom: 20px;
            }
            
            .alumni-position {
                color: var(--primary);
                font-weight: 500;
                margin-bottom: 8px;
            }
            
            .alumni-company {
                color: var(--dark);
                font-weight: 500;
            }
            
            .alumni-skills {
                margin-bottom: 20px;
            }
            
            .alumni-skills h5 {
                margin-bottom: 10px;
                font-size: 14px;
                color: var(--dark);
            }
            
            .skills-list {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }
            
            .skill-tag {
                background: #eef3f8;
                color: var(--primary);
                padding: 5px 12px;
                border-radius: 16px;
                font-size: 12px;
                font-weight: 500;
            }
            
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            @media (max-width: 1024px) {
                .sidebar {
                    width: var(--sidebar-collapsed);
                }
                
                .sidebar-header, .sidebar-profile, .sidebar-item span {
                    display: none;
                }
                
                .sidebar-item {
                    justify-content: center;
                    padding: 18px;
                }
                
                .sidebar-item i {
                    margin-right: 0;
                    font-size: 1.3rem;
                }
                
                .dashboard-container {
                    margin-left: var(--sidebar-collapsed);
                }
            }
            
            @media (max-width: 768px) {
                .dashboard-container {
                    padding: 20px;
                    margin-left: 0;
                }
                
                .sidebar {
                    transform: translateX(-100%);
                }
                
                .sidebar.mobile-open {
                    transform: translateX(0);
                }
                
                .mobile-menu-btn {
                    display: block;
                }
                
                .welcome-banner {
                    flex-direction: column;
                    text-align: center;
                    gap: 20px;
                }
                
                .welcome-stats {
                    justify-content: center;
                }
                
                .dashboard-tabs {
                    flex-wrap: wrap;
                    gap: 5px;
                }
                
                .tab-button {
                    padding: 12px 16px;
                    font-size: 14px;
                }
                
                .form-row {
                    grid-template-columns: 1fr;
                }
                
                .filter-row {
                    flex-direction: column;
                }
                
                .job-grid {
                    grid-template-columns: 1fr;
                }
                
                .job-meta {
                    gap: 15px;
                }
                
                .alumni-grid {
                    grid-template-columns: 1fr;
                }
                
                .modal-content {
                    margin: 5% auto;
                    padding: 25px;
                }
            }
        </style>
    </head>
    <body>
        <!-- Mobile Menu Button -->
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <img src="1758439766575.png" alt="logo" style="height: 0px; border-radius: 10px;">
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
                
                <a href="dashboard_student.php?tab=opportunities" class="sidebar-item <?php echo $current_tab == 'opportunities' ? 'active' : ''; ?>">
                    <i class="fas fa-briefcase"></i>
                    <span>Job Opportunities</span>
                </a>
                
                <a href="dashboard_student.php?tab=applications" class="sidebar-item <?php echo $current_tab == 'applications' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt"></i>
                    <span>My Applications</span>
                </a>
                
                <a href="dashboard_student.php?tab=alumni" class="sidebar-item <?php echo $current_tab == 'alumni' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Alumni Network</span>
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

        <!-- Main Content -->
        <div class="dashboard-container">
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <h2>Student Dashboard</h2>
                    <p>Welcome back, <?php echo htmlspecialchars($profile['first_name'] ?? $user['username']); ?>! Ready to find your next opportunity?</p>
                </div>
                
                <div class="welcome-banner">
                    <div class="welcome-text">
                        <h3>🚀 Launch Your Career</h3>
                        <p>Connect with alumni and discover amazing opportunities tailored for students</p>
                    </div>
                    <div class="welcome-stats">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo count($job_postings); ?></div>
                            <div class="stat-label">Jobs Available</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo count($applications); ?></div>
                            <div class="stat-label">Your Applications</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo count($alumni); ?></div>
                            <div class="stat-label">Alumni Network</div>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-tabs">
                    <button class="tab-button <?php echo $current_tab == 'profile' ? 'active' : ''; ?>" onclick="switchTab('profile')">
                        <i class="fas fa-user-circle"></i> Profile
                    </button>
                    <button class="tab-button <?php echo $current_tab == 'opportunities' ? 'active' : ''; ?>" onclick="switchTab('opportunities')">
                        <i class="fas fa-briefcase"></i> Job Opportunities
                    </button>
                    <button class="tab-button <?php echo $current_tab == 'applications' ? 'active' : ''; ?>" onclick="switchTab('applications')">
                        <i class="fas fa-file-alt"></i> My Applications
                    </button>
                    <button class="tab-button <?php echo $current_tab == 'alumni' ? 'active' : ''; ?>" onclick="switchTab('alumni')">
                        <i class="fas fa-users"></i> Alumni Network
                    </button>
                </div>
                
                <!-- Profile Tab -->
                <div id="profile-tab" class="tab-content <?php echo $current_tab == 'profile' ? 'active' : ''; ?>">
                    <div class="dashboard-card">
                        <h3><i class="fas fa-user-edit"></i> Your Profile</h3>
                        <form method="POST" action="">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="first_name">First Name</label>
                                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($profile['first_name'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="last_name">Last Name</label>
                                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($profile['last_name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="headline">Headline</label>
                                <input type="text" id="headline" name="headline" value="<?php echo htmlspecialchars($profile['headline'] ?? ''); ?>" placeholder="e.g. Computer Science Student">
                            </div>
                            
                            <div class="form-group">
                                <label for="skills">Skills (comma separated)</label>
                                <textarea id="skills" name="skills" placeholder="e.g. PHP, JavaScript, MySQL, React, Python"><?php echo htmlspecialchars($profile['skills'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Job Opportunities Tab -->
                <div id="opportunities-tab" class="tab-content <?php echo $current_tab == 'opportunities' ? 'active' : ''; ?>">
                    <div class="search-filters">
                        <form method="GET" action="">
                            <input type="hidden" name="tab" value="opportunities">
                            <div class="filter-row">
                                <div class="filter-group">
                                    <label for="search">🔍 Search Jobs</label>
                                    <input type="text" id="search" name="search" placeholder="Job title, company, or keywords" value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                
                                <div class="filter-group">
                                    <label for="skills">💻 Filter by Skill</label>
                                    <select id="skills" name="skills">
                                        <option value="">All Skills</option>
                                        <?php foreach ($all_skills as $skill): ?>
                                            <option value="<?php echo htmlspecialchars($skill); ?>" <?php echo $skill_filter == $skill ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($skill); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label for="company">🏢 Filter by Company</label>
                                    <select id="company" name="company">
                                        <option value="">All Companies</option>
                                        <?php foreach ($companies as $company): ?>
                                            <option value="<?php echo htmlspecialchars($company); ?>" <?php echo $company_filter == $company ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($company); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="filter-group search-button">
                                    <button type="submit" class="btn btn-primary" style="margin-top: 24px">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                    <a href="?tab=opportunities" class="btn btn-outline" style="margin-top: 24px">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <div class="job-grid">
                        <?php if (count($job_postings) > 0): ?>
                            <?php foreach ($job_postings as $job): ?>
                                <div class="job-card">
                                    <div class="job-header">
                                        <div>
                                            <h3 class="job-title"><?php echo htmlspecialchars($job['job_title']); ?></h3>
                                            <p class="job-company">🏢 <?php echo htmlspecialchars($job['company_name']); ?></p>
                                            <p class="job-poster">👤 Posted by <?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="job-meta">
                                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($job['location'] ?? 'Remote'); ?></span>
                                        <span><i class="fas fa-clock"></i> <?php echo ucfirst($job['job_type']); ?></span>
                                        <span><i class="fas fa-chart-line"></i> <?php echo ucfirst($job['experience_level']); ?> Level</span>
                                    </div>
                                    
                                    <div class="job-description">
                                        <?php echo nl2br(htmlspecialchars(substr($job['job_description'], 0, 200) . '...')); ?>
                                    </div>
                                    
                                    <?php if (!empty($job['required_skills'])): ?>
                                        <div class="job-skills">
                                            <?php 
                                            $skills = explode(',', $job['required_skills']);
                                            foreach (array_slice($skills, 0, 5) as $skill): 
                                                if (!empty(trim($skill))): ?>
                                                    <span class="skill-pill"><?php echo trim($skill); ?></span>
                                                <?php endif;
                                            endforeach; 
                                            if (count($skills) > 5): ?>
                                                <span class="skill-pill">+<?php echo count($skills) - 5; ?> more</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="job-actions">
                                        <?php if ($job['has_applied']): ?>
                                            <span class="application-status status-submitted">
                                                <i class="fas fa-check-circle"></i> Applied
                                            </span>
                                        <?php else: ?>
                                            <button class="btn btn-primary btn-sm" onclick="openApplyModal(<?php echo $job['id']; ?>, '<?php echo htmlspecialchars(addslashes($job['job_title'])); ?>', '<?php echo htmlspecialchars(addslashes($job['company_name'])); ?>')">
                                                <i class="fas fa-handshake"></i> Request Referral
                                            </button>
                                        <?php endif; ?>
                                        <a href="view_job.php?id=<?php echo $job['id']; ?>" class="btn btn-outline btn-sm">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state" style="grid-column: 1 / -1;">
                                <i class="fas fa-briefcase"></i>
                                <p>No job opportunities found</p>
                                <?php if (!empty($search) || !empty($skill_filter) || !empty($company_filter)): ?>
                                    <p>Try adjusting your search filters</p>
                                    <a href="?tab=opportunities" class="btn btn-primary">
                                        <i class="fas fa-times"></i> Clear Filters
                                    </a>
                                <?php else: ?>
                                    <p>Check back later for new opportunities</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Applications Tab -->
                <div id="applications-tab" class="tab-content <?php echo $current_tab == 'applications' ? 'active' : ''; ?>">
                    <?php if (isset($_GET['applied'])): ?>
                        <div class="success-message">
                            <i class="fas fa-check-circle"></i>
                            Your referral request has been submitted successfully!
                        </div>
                    <?php endif; ?>
                    
                    <div class="dashboard-card">
                        <h3><i class="fas fa-file-alt"></i> My Referral Applications</h3>
                        
                        <?php if (count($applications) > 0): ?>
                            <?php foreach ($applications as $application): ?>
                                <div class="application-card">
                                    <div class="application-header">
                                        <div>
                                            <h4 class="application-job"><?php echo htmlspecialchars($application['job_title']); ?></h4>
                                            <p class="application-company">🏢 <?php echo htmlspecialchars($application['company_name']); ?></p>
                                        </div>
                                        <span class="application-status status-<?php echo $application['status']; ?>">
                                            <?php echo ucfirst($application['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="application-meta">
                                        <span class="application-date">📅 Applied: <?php echo date('M j, Y g:i a', strtotime($application['applied_at'])); ?></span>
                                        <span>👤 Referred by: <?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></span>
                                    </div>
                                    
                                    <?php if (!empty($application['application_message'])): ?>
                                        <div class="application-message">
                                            <strong>Your message:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($application['application_message'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($application['status'] == 'approved'): ?>
                                        <div class="application-actions">
                                            <a href="chat.php?user=<?php echo $application['alumni_id']; ?>" class="btn btn-success">
                                                <i class="fas fa-comment"></i> Contact Referrer
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-file-alt"></i>
                                <p>No applications yet</p>
                                <p>Browse job opportunities and request referrals from alumni</p>
                                <a href="?tab=opportunities" class="btn btn-primary">
                                    <i class="fas fa-briefcase"></i> Browse Jobs
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Alumni Network Tab -->
                <div id="alumni-tab" class="tab-content <?php echo $current_tab == 'alumni' ? 'active' : ''; ?>">
                    <div class="dashboard-card">
                        <h3><i class="fas fa-users"></i> Alumni Network</h3>
                        <p>Connect with alumni who can help you in your career journey</p>
                        
                        <div class="search-filters">
                            <div class="filter-row">
                                <div class="filter-group">
                                    <input type="text" id="alumni-search" placeholder="🔍 Search alumni by name, company, or skills" onkeyup="filterAlumni()">
                                </div>
                            </div>
                        </div>
                        
                        <div class="alumni-grid">
                            <?php if (count($alumni) > 0): ?>
                                <?php foreach ($alumni as $alumnus): ?>
                                    <div class="alumni-card">
                                        <div class="alumni-header">
                                            <div class="alumni-avatar">
                                                <?php echo strtoupper(substr($alumnus['first_name'], 0, 1) . substr($alumnus['last_name'], 0, 1)); ?>
                                            </div>
                                            <div class="alumni-info">
                                                <h4><?php echo htmlspecialchars($alumnus['first_name'] . ' ' . $alumnus['last_name']); ?></h4>
                                                <p>@<?php echo htmlspecialchars($alumnus['username']); ?></p>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($alumnus['headline'])): ?>
                                            <div class="alumni-details">
                                                <p class="alumni-position"><?php echo htmlspecialchars($alumnus['headline']); ?></p>
                                                <?php if (!empty($alumnus['current_company']) || !empty($alumnus['current_position'])): ?>
                                                    <p class="alumni-company">
                                                        <?php if (!empty($alumnus['current_position'])): ?>
                                                            <?php echo htmlspecialchars($alumnus['current_position']); ?>
                                                        <?php endif; ?>
                                                        <?php if (!empty($alumnus['current_company'])): ?>
                                                            at <?php echo htmlspecialchars($alumnus['current_company']); ?>
                                                        <?php endif; ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($alumnus['skills'])): ?>
                                            <div class="alumni-skills">
                                                <h5>Skills:</h5>
                                                <div class="skills-list">
                                                    <?php 
                                                    $skills = explode(',', $alumnus['skills']);
                                                    foreach (array_slice($skills, 0, 5) as $skill): 
                                                        if (!empty(trim($skill))): ?>
                                                            <span class="skill-tag"><?php echo trim($skill); ?></span>
                                                        <?php endif;
                                                    endforeach; 
                                                    if (count($skills) > 5): ?>
                                                        <span class="skill-tag">+<?php echo count($skills) - 5; ?> more</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                    <div>
        <?php
        $connection_status = getConnectionStatus($_SESSION['user_id'], $alumnus['id']);
        if ($connection_status == 'not_connected'): ?>
            <form method="POST" action="send_request.php" style="display: inline;">
                <input type="hidden" name="receiver_id" value="<?php echo $alumnus['id']; ?>">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-user-plus"></i> Connect
                </button>
            </form>
        <?php elseif ($connection_status == 'request_sent'): ?>
            <span class="btn btn-secondary btn-sm disabled">
                <i class="fas fa-clock"></i> Request Sent
            </span>
        <?php elseif ($connection_status == 'connected'): ?>
            <a href="chat.php?user=<?php echo $alumnus['id']; ?>" class="btn btn-success btn-sm">
                <i class="fas fa-comment"></i> Message
            </a>
        <?php endif; ?>
    </div>
                                        
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state" style="grid-column: 1 / -1;">
                                    <i class="fas fa-users"></i>
                                    <p>No alumni found</p>
                                    <p>Check back later as more alumni join the network</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Apply Modal -->
        <div id="applyModal" class="modal">
            <div class="modal-content">
                <span class="close-modal" onclick="closeApplyModal()">&times;</span>
                <h3>Request Referral</h3>
                <p>For: <span id="modal-job-title"></span> at <span id="modal-company"></span></p>
                
                <form method="POST" action="">
                    <input type="hidden" id="modal-job-id" name="job_id">
                    
                    <div class="form-group">
                        <label for="application_message">Message to Alumni</label>
                        <textarea id="application_message" name="application_message" rows="5" placeholder="Introduce yourself and explain why you're interested in this position. Mention your relevant skills and why you'd be a good fit..." required></textarea>
                        <small>This message will be sent to the alumni who posted this job.</small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="closeApplyModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Send Request
                        </button>
                    </div>
                </form>
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
            
            function openApplyModal(jobId, jobTitle, companyName) {
                document.getElementById('modal-job-id').value = jobId;
                document.getElementById('modal-job-title').textContent = jobTitle;
                document.getElementById('modal-company').textContent = companyName;
                document.getElementById('applyModal').style.display = 'block';
            }
            
            function closeApplyModal() {
                document.getElementById('applyModal').style.display = 'none';
            }
            
            // Close modal when clicking outside
            window.onclick = function(event) {
                if (event.target == document.getElementById('applyModal')) {
                    closeApplyModal();
                }
            }
            
            function filterAlumni() {
                const input = document.getElementById('alumni-search');
                const filter = input.value.toLowerCase();
                const cards = document.querySelectorAll('.alumni-card');
                
                cards.forEach(card => {
                    const text = card.textContent.toLowerCase();
                    if (text.includes(filter)) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            }
            
            // Mobile menu functionality
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.getElementById('sidebar');
            
            function checkMobile() {
                if (window.innerWidth <= 768) {
                    mobileMenuBtn.style.display = 'block';
                    sidebar.classList.remove('mobile-open');
                } else {
                    mobileMenuBtn.style.display = 'none';
                    sidebar.classList.remove('mobile-open');
                }
            }
            
            mobileMenuBtn.addEventListener('click', () => {
                sidebar.classList.toggle('mobile-open');
            });
            
            // Check on load and resize
            window.addEventListener('load', checkMobile);
            window.addEventListener('resize', checkMobile);
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 768 && 
                    !sidebar.contains(e.target) && 
                    e.target !== mobileMenuBtn && 
                    !mobileMenuBtn.contains(e.target)) {
                    sidebar.classList.remove('mobile-open');
                }
            });
            
            // Add animation to cards when they come into view
            document.addEventListener('DOMContentLoaded', function() {
                const cards = document.querySelectorAll('.job-card, .alumni-card, .application-card');
                
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.style.opacity = '1';
                            entry.target.style.transform = 'translateY(0)';
                        }
                    });
                }, { threshold: 0.1 });
                
                cards.forEach(card => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    observer.observe(card);
                });
            });
        </script>
    </body>
    </html>