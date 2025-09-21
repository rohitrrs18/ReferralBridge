<?php
include 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Handle connection requests
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($user_type != 'student') {
        $_SESSION['error'] = "Only students can send connection requests.";
        redirect('dashboard.php');
    }
    
    $alumni_id = $_POST['alumni_id'];
    $action = $_POST['action'];
    
    if ($action == 'connect') {
        // Check if connection already exists
        $stmt = $pdo->prepare("SELECT * FROM connections WHERE student_id = ? AND alumni_id = ?");
        $stmt->execute([$user_id, $alumni_id]);
        $existing_connection = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_connection) {
            $_SESSION['error'] = "Connection request already sent.";
        } else {
            // Create new connection request
            $stmt = $pdo->prepare("INSERT INTO connections (student_id, alumni_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $alumni_id]);
            $_SESSION['success'] = "Connection request sent successfully.";
        }
    } elseif (isset($_POST['request_id'])) {
        $request_id = $_POST['request_id'];
        $action = $_POST['action'];
        
        if ($action == 'accept') {
            $stmt = $pdo->prepare("UPDATE connections SET status = 'accepted' WHERE id = ?");
            $stmt->execute([$request_id]);
            $_SESSION['success'] = "Connection request accepted.";
        } elseif ($action == 'reject') {
            $stmt = $pdo->prepare("DELETE FROM connections WHERE id = ?");
            $stmt->execute([$request_id]);
            $_SESSION['success'] = "Connection request rejected.";
        }
    }
}

// Redirect back to previous page
$redirect_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'dashboard.php';
redirect($redirect_url);
?>