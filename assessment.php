<?php
include 'config.php';
checkAuth();

// Check if user is student
$user = getUserData($_SESSION['user_id']);
if ($user['user_type'] != 'student') {
    header("Location: dashboard_{$user['user_type']}.php");
    exit();
}

// Get job and referral details
$job_id = $_GET['job_id'];
$referral_id = $_GET['referral_id'];

// Fetch assessment questions for this job
$stmt = $pdo->prepare("
    SELECT * FROM job_assessments 
    WHERE job_posting_id = ?
    ORDER BY RAND() 
    LIMIT 5
");
$stmt->execute([$job_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If no questions exist, create some based on job skills
if (count($questions) === 0) {
    // Get job skills to generate questions
    $stmt = $pdo->prepare("SELECT required_skills FROM job_postings WHERE id = ?");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($job && !empty($job['required_skills'])) {
        $skills = explode(',', $job['required_skills']);
        $skills = array_map('trim', $skills);
        
        // Generate questions for up to 3 skills
        $selected_skills = array_slice($skills, 0, 3);
        $basic_questions = [
            "What is your experience level with [SKILL]?",
            "Describe a project where you used [SKILL].",
            "What are the key concepts of [SKILL]?",
            "How would you solve a typical problem using [SKILL]?",
            "What resources would you use to improve your [SKILL]?"
        ];
        
        foreach ($selected_skills as $skill) {
            // Select a random question template
            $question_template = $basic_questions[array_rand($basic_questions)];
            $question = str_replace('[SKILL]', $skill, $question_template);
            
            // Insert into database (no specific correct answer for these generic questions)
            $stmt = $pdo->prepare("
                INSERT INTO job_assessments (job_posting_id, question, correct_answer) 
                VALUES (?, ?, '')
            ");
            $stmt->execute([$job_id, $question]);
        }
        
        // Reload questions
        $stmt = $pdo->prepare("SELECT * FROM job_assessments WHERE job_posting_id = ?");
        $stmt->execute([$job_id]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $total_points = 0;
    $awarded_points = 0;
    
    foreach ($_POST['answers'] as $question_id => $answer) {
        // Get question details
        $stmt = $pdo->prepare("SELECT * FROM job_assessments WHERE id = ?");
        $stmt->execute([$question_id]);
        $question = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $is_correct = false;
        $points = 0;
        
        // If there's a specific correct answer, check it
        if (!empty($question['correct_answer'])) {
            // Simple keyword matching for demonstration
            // In a real system, you might use more sophisticated checking
            $correct_keywords = explode(',', $question['correct_answer']);
            $correct_keywords = array_map('trim', $correct_keywords);
            $correct_keywords = array_map('strtolower', $correct_keywords);
            
            $answer_lower = strtolower($answer);
            $matched_keywords = 0;
            
            foreach ($correct_keywords as $keyword) {
                if (strpos($answer_lower, $keyword) !== false) {
                    $matched_keywords++;
                }
            }
            
            // If more than half the keywords are matched, consider it correct
            if ($matched_keywords >= ceil(count($correct_keywords) / 2)) {
                $is_correct = true;
                $points = $question['points'];
            }
        } else {
            // For questions without specific answers, check answer length
            // Longer answers suggest more knowledge (simple heuristic)
            if (strlen(trim($answer)) > 20) {
                $is_correct = true;
                $points = 1;
            }
        }
        
        // Save response
        $stmt = $pdo->prepare("
            INSERT INTO assessment_responses (assessment_id, referral_id, student_answer, is_correct, awarded_points) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$question_id, $referral_id, $answer, $is_correct, $points]);
        
        $total_points += $question['points'];
        $awarded_points += $points;
    }
    
    // Determine if student passed (60% or higher)
    $passing_threshold = 0.6;
    $score = $total_points > 0 ? $awarded_points / $total_points : 0;
    
    if ($score >= $passing_threshold) {
        $status = 'pending'; // Proceed to alumni review
        $message = "Congratulations! You passed the assessment. Your referral request has been submitted for review.";
    } else {
        $status = 'rejected';
        $message = "Unfortunately, you did not pass the assessment. Your referral request has been denied.";
    }
    
    // Update referral status
    $stmt = $pdo->prepare("UPDATE referrals SET status = ? WHERE id = ?");
    $stmt->execute([$status, $referral_id]);
    
    header("Location: dashboard_student.php?tab=applications&message=" . urlencode($message));
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skill Assessment - ReferralBridge</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Reuse your existing styles from dashboard_student.php */
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
            padding: 20px;
        }
        
        .assessment-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .assessment-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .assessment-header h1 {
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .assessment-header p {
            color: var(--gray);
        }
        
        .question-card {
            background: var(--light);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .question-text {
            font-weight: 500;
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        .answer-textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid var(--light-gray);
            border-radius: 10px;
            font-size: 16px;
            min-height: 100px;
            resize: vertical;
        }
        
        .answer-textarea:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        .form-actions {
            display: flex;
            justify-content: center;
            margin-top: 30px;
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
    </style>
</head>
<body>
    <div class="assessment-container">
        <div class="assessment-header">
            <h1><i class="fas fa-graduation-cap"></i> Skill Assessment</h1>
            <p>Please answer the following questions to demonstrate your skills for this position.</p>
        </div>
        
        <form method="POST" action="">
            <?php foreach ($questions as $index => $question): ?>
                <div class="question-card">
                    <div class="question-text">
                        <strong>Question <?php echo $index + 1; ?>:</strong> 
                        <?php echo htmlspecialchars($question['question']); ?>
                    </div>
                    <textarea 
                        class="answer-textarea" 
                        name="answers[<?php echo $question['id']; ?>]" 
                        placeholder="Type your answer here..." 
                        required
                    ></textarea>
                </div>
            <?php endforeach; ?>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Submit Assessment
                </button>
            </div>
        </form>
    </div>
</body>
</html>