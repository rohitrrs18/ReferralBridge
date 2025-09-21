<?php
include 'config.php';
checkAuth();

if (isset($_GET['user'])) {
    $selected_user_id = $_GET['user'];
    
    // Verify the users can message each other (allow viewing even if not connected)
    $can_view = canMessage($_SESSION['user_id'], $selected_user_id) || 
                canMessage($selected_user_id, $_SESSION['user_id']);
    
    if ($can_view) {
        // Get latest messages
        $stmt = $pdo->prepare("
            SELECT m.*, u.username, u.user_type
            FROM messages m 
            JOIN users u ON m.sender_id = u.id 
            WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?) 
            ORDER BY m.sent_at ASC
        ");
        $stmt->execute([$_SESSION['user_id'], $selected_user_id, $selected_user_id, $_SESSION['user_id']]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode($messages);
        exit();
    }
}

http_response_code(403);
echo json_encode(['error' => 'Not authorized']);