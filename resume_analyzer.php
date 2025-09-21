<?php
include 'config.php';
checkAuth();

// Initialize user data safely
$user_id = $_SESSION['user_id'] ?? null;
$user = [];
$profile = [];

if ($user_id) {
    $user = getUserData($user_id) ?? [];
    $profile = getProfileData($user_id) ?? [];
}

$analysis_results = [];
$error = '';
$success = '';

// Function to analyze resume text
function analyzeResumeText($text) {
    $results = [
        'score' => 0,
        'strengths' => [],
        'suggestions' => [],
        'keywords' => [],
        'sections' => [
            'contact' => false,
            'education' => false,
            'experience' => false,
            'skills' => false,
            'projects' => false,
            'summary' => false
        ],
        'keyword_matches' => [],
        'extracted_text' => $text // For debugging
    ];
    
    // Convert text to lowercase for easier matching
    $lower_text = strtolower($text);
    
    // Check for essential resume sections
    $results['sections']['contact'] = preg_match('/(phone|email|address|contact)/', $lower_text);
    $results['sections']['education'] = preg_match('/(education|university|college|school|degree|b\.?a|b\.?s|m\.?a|m\.?s|ph\.?d)/', $lower_text);
    $results['sections']['experience'] = preg_match('/(experience|work|employment|job|internship)/', $lower_text);
    $results['sections']['skills'] = preg_match('/(skills|proficient|knowledge|expertise)/', $lower_text);
    $results['sections']['projects'] = preg_match('/(projects|portfolio|publications)/', $lower_text);
    $results['sections']['summary'] = preg_match('/(summary|objective|profile|about)/', $lower_text);
    
    // Define important keywords for different categories
    $keyword_categories = [
        'technical' => ['javascript', 'python', 'java', 'html', 'css', 'react', 'node', 'sql', 'database', 'api'],
        'soft_skills' => ['communication', 'teamwork', 'leadership', 'problem solving', 'adaptability', 'time management'],
        'action_verbs' => ['managed', 'developed', 'created', 'implemented', 'led', 'improved', 'increased', 'reduced'],
        'quantifiable' => ['%', '$', 'years', 'increased', 'decreased', 'saved', 'achieved', 'managed']
    ];
    
    // Check for keywords
    $found_keywords = [];
    foreach ($keyword_categories as $category => $keywords) {
        foreach ($keywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                $found_keywords[] = $keyword;
                $results['keyword_matches'][$category][] = $keyword;
            }
        }
    }
    
    $results['keywords'] = array_unique($found_keywords);
    
    // Calculate score based on various factors
    $score = 0;
    
    // Section completeness (40%)
    $section_score = 0;
    $essential_sections = ['contact', 'education', 'experience', 'skills'];
    foreach ($essential_sections as $section) {
        if ($results['sections'][$section]) {
            $section_score += 10;
        }
    }
    $score += $section_score;
    
    // Keyword richness (30%)
    $keyword_score = min(30, count($results['keywords']) * 2);
    $score += $keyword_score;
    
    // Quantifiable achievements (15%)
    $quantifiable_score = 0;
    if (count($results['keyword_matches']['quantifiable'] ?? []) > 0) {
        $quantifiable_score = 15;
    }
    $score += $quantifiable_score;
    
    // Action verbs (15%)
    $action_verbs_score = min(15, count($results['keyword_matches']['action_verbs'] ?? []) * 3);
    $score += $action_verbs_score;
    
    $results['score'] = min(100, $score);
    
    // Generate strengths
    if ($section_score >= 30) {
        $results['strengths'][] = "Good section structure";
    }
    
    if (count($results['keywords']) > 10) {
        $results['strengths'][] = "Rich in relevant keywords";
    }
    
    if ($quantifiable_score > 0) {
        $results['strengths'][] = "Includes quantifiable achievements";
    }
    
    if ($action_verbs_score > 10) {
        $results['strengths'][] = "Uses strong action verbs";
    }
    
    if (empty($results['strengths'])) {
        $results['strengths'][] = "Document uploaded successfully";
    }
    
    // Generate suggestions
    if (!$results['sections']['contact']) {
        $results['suggestions'][] = "Add contact information (phone, email, address)";
    }
    
    if (!$results['sections']['education']) {
        $results['suggestions'][] = "Include an education section";
    }
    
    if (!$results['sections']['experience']) {
        $results['suggestions'][] = "Add work experience section";
    }
    
    if (!$results['sections']['skills']) {
        $results['suggestions'][] = "Create a dedicated skills section";
    }
    
    if (count($results['keyword_matches']['quantifiable'] ?? []) < 2) {
        $results['suggestions'][] = "Add more quantifiable achievements (numbers, percentages, $)";
    }
    
    if (count($results['keyword_matches']['action_verbs'] ?? []) < 3) {
        $results['suggestions'][] = "Use more action verbs to describe your experience";
    }
    
    if (empty($results['suggestions'])) {
        $results['suggestions'][] = "Your resume looks strong! Consider tailoring it to specific job descriptions";
    }
    
    return $results;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_FILES['resume']['name'])) {
    $file_name = $_FILES['resume']['name'];
    $file_size = $_FILES['resume']['size'];
    $file_tmp = $_FILES['resume']['tmp_name'];
    $file_type = $_FILES['resume']['type'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Check file type
    $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    if (!in_array($file_type, $allowed_types) && !in_array($file_ext, ['pdf', 'doc', 'docx'])) {
        $error = "Only PDF, DOC, and DOCX files are allowed.";
    } elseif ($file_size > 5000000) { // 5MB max
        $error = "File size must be less than 5MB.";
    } else {
        // Store file for analysis
        $target_dir = "uploads/resumes/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $file_path = $target_dir . time() . '_' . basename($file_name);
        move_uploaded_file($file_tmp, $file_path);
        
        // Extract text from the document
        $text = '';
        
        // For PDF files
        if ($file_ext === 'pdf') {
            // Check if PDF parser is available
            if (class_exists('Smalot\PdfParser\Parser')) {
                try {
                    $parser = new Smalot\PdfParser\Parser();
                    $pdf = $parser->parseFile($file_path);
                    $text = $pdf->getText();
                } catch (Exception $e) {
                    $error = "Error parsing PDF: " . $e->getMessage();
                }
            } else {
                // Fallback for when PDF parser is not available
                $text = "PDF parsing library not installed. Please install smalot/pdfparser via Composer for full functionality.";
            }
        } 
        // For Word documents
        elseif (in_array($file_ext, ['doc', 'docx'])) {
            // Simple text extraction for DOCX files
            if ($file_ext === 'docx') {
                $zip = new ZipArchive();
                if ($zip->open($file_path) === TRUE) {
                    if (($index = $zip->locateName('word/document.xml')) !== FALSE) {
                        $data = $zip->getFromIndex($index);
                        $text = strip_tags($data);
                    }
                    $zip->close();
                }
            } else {
                // For .doc files, we can't easily extract text without additional libraries
                $text = "DOC file detected. For better analysis, please convert to PDF or DOCX format.";
            }
        }
        
       if (empty($text)) {
    $error = "Could not extract text from the document. Please ensure it's a valid resume file.";
} else {
    // Analyze the resume text
    $analysis_results = analyzeResumeText($text);
    $analysis_results['file_name'] = $file_name;
    $analysis_results['file_size'] = round($file_size / 1024, 2) . ' KB';
    $analysis_results['file_type'] = $file_type;
    $analysis_results['file_path'] = $file_path;

    $success = "Resume uploaded and analyzed successfully!";
}
    }}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resume Analyzer - AlumniConnect</title>
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
            background: white;
            color: black;
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
            background: rgba(67, 97, 238, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            border: 3px solid rgba(0, 0, 0, 0.2);
        }
        
        .sidebar-avatar i {
            font-size: 2rem;
            color: #000000;
        }
        
        .sidebar-userinfo h4 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
            color: black;
        }
        
        .sidebar-userinfo p {
            color: var(--gray);
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
            background: rgba(67, 97, 238, 0.05);
            color: black;
            border-left-color: var(--primary);
        }
        
        .sidebar-item.active {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
            border-left-color: var(--primary);
        }
        
        .sidebar-item i {
            width: 24px;
            margin-right: 15px;
            font-size: 1.1rem;
            color: #000000;
        }
        
        .sidebar-item span {
            flex: 1;
            color: black;
        }
        
        .logout-btn {
            margin-top: auto;
            background: rgba(231, 29, 54, 0.05);
            border-left: 4px solid transparent !important;
            color: var(--danger);
        }
        
        .logout-btn:hover {
            background: rgba(231, 29, 54, 0.1);
            color: var(--danger);   
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
            color: var(--dark);
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .dashboard-header p {
            color: var(--gray);
            font-size: 16px;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }
        
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
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
        
        /* Upload Area */
        .upload-area {
            border: 2px dashed var(--primary);
            border-radius: 12px;
            padding: 40px 30px;
            text-align: center;
            margin: 20px 0;
            background: rgba(67, 97, 238, 0.03);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .upload-area:hover {
            background: rgba(67, 97, 238, 0.08);
            border-color: var(--primary-dark);
        }
        
        .upload-area i {
            font-size: 48px;
            color: var(--primary);
            margin-bottom: 15px;
        }
        
        .upload-area h4 {
            font-size: 18px;
            margin-bottom: 10px;
            color: var(--dark);
        }
        
        .upload-area p {
            color: var(--gray);
            margin: 5px 0;
        }
        
        .file-input {
            display: none;
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
        
        .btn-secondary {
            background: var(--gray);
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-full {
            width: 100%;
        }
        
        .btn-sm {
            padding: 10px 18px;
            font-size: 14px;
        }
        
        /* Form Elements */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }
        
        /* Analysis Results */
        .analysis-score {
            text-align: center;
            margin: 20px 0;
        }
        
        .score-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: 600;
            background: conic-gradient(var(--primary) 0deg var(--rotation), var(--light-gray) var(--rotation) 360deg);
        }
        
        .keywords-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 15px 0;
        }
        
        .skill-tag {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 14px;
        }
        
        .suggestions-list {
            list-style: none;
            padding: 0;
        }
        
        .suggestions-list li {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
        }
        
        .suggestions-list li i {
            color: var(--warning);
            margin-right: 10px;
        }
        
        .analysis-section {
            margin: 20px 0;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin: 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-error {
            background: #ffe6e6;
            color: #dc3545;
            border-left: 4px solid #dc3545;
        }
        
        .alert-success {
            background: #e6f4ea;
            color: #0f5132;
            border-left: 4px solid #0f5132;
        }
        
        .loading {
            text-align: center;
            padding: 40px 20px;
            display: none;
        }
        
        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .file-info {
            background: var(--light-gray);
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        
        .analysis-details ul {
            list-style: none;
            padding: 0;
        }
        
        .analysis-details ul li {
            padding: 8px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .analysis-details ul li i {
            color: var(--success);
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
        
        /* Responsive */
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
                <img src="1758439766575.png" alt="logo" style="height: 50px; border-radius: 10px;">
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
            
        
            
            
            <a href="resume_analyzer.php" class="sidebar-item active">
                <i class="fas fa-file-alt"></i>
                <span>Resume Analyzer</span>
            </a>
            
            <a href="chat.php" class="sidebar-item">
                <i class="fas fa-comments"></i>
                <span>Messages</span>
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
                <h2>Resume Analyzer</h2>
                <p>Upload your resume to get personalized feedback and improvement suggestions</p>
            </div>
            
            <!-- Display error/success messages from PHP -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <div class="dashboard-grid">
                <!-- Upload Card -->
                <div class="dashboard-card">
                    <h3><i class="fas fa-file-upload"></i> Upload Your Resume</h3>
                    
                    <div id="errorMessage" class="alert alert-error" style="display: none;">
                        <i class="fas fa-exclamation-circle"></i> <span id="errorText"></span>
                    </div>
                    
                    <div id="successMessage" class="alert alert-success" style="display: none;">
                        <i class="fas fa-check-circle"></i> <span id="successText"></span>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" id="resumeForm">
                        <div class="upload-area" id="dropZone">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <h4>Drag & Drop your resume here</h4>
                            <p>or</p>
                            <input type="file" id="resume" name="resume" class="file-input" accept=".pdf,.doc,.docx" required>
                            <button type="button" class="btn btn-primary" onclick="document.getElementById('resume').click()">
                                <i class="fas fa-folder-open"></i> Browse Files
                            </button>
                            <p class="mt-2">Supported formats: PDF, DOC, DOCX (Max 5MB)</p>
                        </div>
                        
                        <div id="fileName" class="file-info" style="display: none;">
                            <p><strong>Selected file:</strong> <span id="selectedFileName"></span></p>
                            <p><strong>File size:</strong> <span id="fileSize"></span></p>
                        </div>
                        
                        <button type="submit" id="uploadBtn" class="btn btn-primary btn-full" disabled>
                            <i class="fas fa-upload"></i> Upload and Analyze Resume
                        </button>
                    </form>
                </div>
                
                <!-- Analysis Results Card -->
                <div class="dashboard-card">
                    <h3><i class="fas fa-chart-bar"></i> Analysis Results</h3>
                    
                    <?php if (!empty($analysis_results)): ?>
                        <div class="analysis-score">
                            <div class="score-circle" style="--rotation: <?php echo $analysis_results['score'] * 3.6; ?>deg">
                                <span><?php echo $analysis_results['score']; ?>%</span>
                            </div>
                            <p>Overall Resume Score</p>
                        </div>
                        
                        <div class="analysis-details">
                            <h4>Key Strengths</h4>
                            <ul>
                                <?php foreach ($analysis_results['strengths'] as $strength): ?>
                                    <li><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($strength); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            
                            <h4>Areas for Improvement</h4>
                            <ul class="suggestions-list">
                                <?php foreach ($analysis_results['suggestions'] as $suggestion): ?>
                                    <li><i class="fas fa-lightbulb"></i> <?php echo htmlspecialchars($suggestion); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            
                            <h4>Detected Keywords</h4>
                            <div class="keywords-list">
                                <?php foreach ($analysis_results['keywords'] as $keyword): ?>
                                    <span class="skill-tag"><?php echo htmlspecialchars($keyword); ?></span>
                                <?php endforeach; ?>
                            </div>
                            
                            <h4>Section Analysis</h4>
                            <div class="analysis-section">
                                <p>Contact Information: <?php echo $analysis_results['sections']['contact'] ? '✓ Present' : '✗ Missing'; ?></p>
                                <p>Education: <?php echo $analysis_results['sections']['education'] ? '✓ Present' : '✗ Missing'; ?></p>
                                <p>Experience: <?php echo $analysis_results['sections']['experience'] ? '✓ Present' : '✗ Missing'; ?></p>
                                <p>Skills: <?php echo $analysis_results['sections']['skills'] ? '✓ Present' : '✗ Missing'; ?></p>
                                <p>Projects: <?php echo $analysis_results['sections']['projects'] ? '✓ Present' : '✗ Missing'; ?></p>
                                <p>Professional Summary: <?php echo $analysis_results['sections']['summary'] ? '✓ Present' : '✗ Missing'; ?></p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <p>Upload and analyze your resume to see detailed results</p>
                            <p>Get personalized feedback to improve your resume</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
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
        
        // File upload functionality
        const resumeInput = document.getElementById('resume');
        const dropZone = document.getElementById('dropZone');
        const fileName = document.getElementById('fileName');
        const selectedFileName = document.getElementById('selectedFileName');
        const fileSize = document.getElementById('fileSize');
        const uploadBtn = document.getElementById('uploadBtn');
        const errorMessage = document.getElementById('errorMessage');
        const errorText = document.getElementById('errorText');
        const successMessage = document.getElementById('successMessage');
        const successText = document.getElementById('successText');
        
        // Handle file selection
        resumeInput.addEventListener('change', function(e) {
            handleFileSelection(e.target.files[0]);
        });
        
        // Drag and drop functionality
        dropZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            dropZone.style.background = 'rgba(67, 97, 238, 0.1)';
            dropZone.style.borderColor = '#3a0ca3';
        });
        
        dropZone.addEventListener('dragleave', function() {
            dropZone.style.background = 'rgba(67, 97, 238, 0.03)';
            dropZone.style.borderColor = '#4361ee';
        });
        
        dropZone.addEventListener('drop', function(e) {
            e.preventDefault();
            dropZone.style.background = 'rgba(67, 97, 238, 0.03)';
            dropZone.style.borderColor = '#4361ee';
            
            if (e.dataTransfer.files.length) {
                handleFileSelection(e.dataTransfer.files[0]);
                resumeInput.files = e.dataTransfer.files;
            }
        });
        
        // Handle file selection
        function handleFileSelection(file) {
            errorMessage.style.display = 'none';
            successMessage.style.display = 'none';
            
            if (!file) return;
            
            // Check file type
            const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            const fileExt = file.name.split('.').pop().toLowerCase();
            const allowedExts = ['pdf', 'doc', 'docx'];
            
            if (!allowedTypes.includes(file.type) && !allowedExts.includes(fileExt)) {
                showError('Only PDF, DOC, and DOCX files are allowed.');
                return;
            }
            
            // Check file size (5MB max)
            if (file.size > 5 * 1024 * 1024) {
                showError('File size must be less than 5MB.');
                return;
            }
            
            // Display file info
            selectedFileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            fileName.style.display = 'block';
            
            // Enable upload button
            uploadBtn.disabled = false;
        }
        
        // Format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        // Show error message
        function showError(message) {
            errorText.textContent = message;
            errorMessage.style.display = 'flex';
            uploadBtn.disabled = true;
        }
    </script>
</body>
</html>