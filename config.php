<?php
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'alumni_network');
define('DB_USER', 'root');
define('DB_PASS', '');

// Create connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Redirect if not logged in
function checkAuth() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

// Get user data
function getUserData($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get profile data
function getProfileData($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function areUsersConnected($user1_id, $user2_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT id FROM friend_requests 
        WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) 
        AND status = 'accepted'
    ");
    $stmt->execute([$user1_id, $user2_id, $user2_id, $user1_id]);
    return $stmt->rowCount() > 0;
}
function canMessage($sender_id, $receiver_id) {
    global $pdo;
    
    // Admin can message anyone
    $sender = getUserData($sender_id);
    $receiver = getUserData($receiver_id);
    
    if ($sender['user_type'] == 'admin' || $receiver['user_type'] == 'admin') {
        return true;
    }
    
    // Only allow messaging if there's an accepted connection
    return areUsersConnected($sender_id, $receiver_id);
}

function getConnectionStatus($user1_id, $user2_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT status, sender_id FROM friend_requests 
        WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
    ");
    $stmt->execute([$user1_id, $user2_id, $user2_id, $user1_id]);
    
    if ($stmt->rowCount() > 0) {
        $connection = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If I sent the request
        if ($connection['sender_id'] == $user1_id) {
            if ($connection['status'] == 'pending') {
                return 'request_sent';
            } elseif ($connection['status'] == 'accepted') {
                return 'connected';
            } elseif ($connection['status'] == 'rejected') {
                return 'rejected';
            }
        } 
        // If I received the request
        else {
            if ($connection['status'] == 'pending') {
                return 'request_received';
            } elseif ($connection['status'] == 'accepted') {
                return 'connected';
            } elseif ($connection['status'] == 'rejected') {
                return 'rejected_by_me';
            }
        }
    }
    
    return 'not_connected';
}

function updateMessageReadStatus($receiver_id, $sender_id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE messages SET is_read = TRUE WHERE receiver_id = ? AND sender_id = ? AND is_read = FALSE");
    $stmt->execute([$receiver_id, $sender_id]);
    return $stmt->rowCount();
}
?>