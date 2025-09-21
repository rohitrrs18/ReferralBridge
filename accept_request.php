<?php
include 'config.php';
checkAuth();

if (isset($_GET['request_id'])) {
    $request_id = $_GET['request_id'];
    
    // Verify the alumni owns this request
    $stmt = $pdo->prepare("UPDATE friend_requests SET status = 'accepted' WHERE id = ? AND receiver_id = ?");
    $stmt->execute([$request_id, $_SESSION['user_id']]);
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['success'] = "Connection request accepted!";
    }
}

header("Location: dashboard_alumni.php?tab=requests");
exit();
?>