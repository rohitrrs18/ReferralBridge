<?php
include 'config.php';
checkAuth();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['receiver_id'])) {
    $receiver_id = $_POST['receiver_id'];
    $sender_id = $_SESSION['user_id'];
    
    // Check if request already exists
    $stmt = $pdo->prepare("
        SELECT id FROM friend_requests 
        WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
    ");
    $stmt->execute([$sender_id, $receiver_id, $receiver_id, $sender_id]);
    
    if ($stmt->rowCount() == 0) {
        // Create new connection request
        $stmt = $pdo->prepare("INSERT INTO friend_requests (sender_id, receiver_id, status) VALUES (?, ?, 'pending')");
        if ($stmt->execute([$sender_id, $receiver_id])) {
            $_SESSION['success'] = "Connection request sent successfully!";
        }
    } else {
        $_SESSION['info'] = "Connection request already exists.";
    }
}

header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'dashboard_student.php'));
exit();
?>