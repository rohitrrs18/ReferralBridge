<?php
include 'config.php';
checkAuth();

$user = getUserData($_SESSION['user_id']);
$profile = getProfileData($_SESSION['user_id']);

// Get conversations
$stmt = $pdo->prepare("
    SELECT DISTINCT u.id, u.username, u.user_type, p.first_name, p.last_name, p.headline,
           (SELECT message FROM messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id) ORDER BY sent_at DESC LIMIT 1) as last_message,
           (SELECT sent_at FROM messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id) ORDER BY sent_at DESC LIMIT 1) as last_message_time
    FROM users u
    JOIN profiles p ON u.id = p.user_id
    WHERE u.id != ? AND (
        EXISTS (
            SELECT 1 FROM messages m 
            WHERE (m.sender_id = u.id AND m.receiver_id = ?) OR 
                  (m.sender_id = ? AND m.receiver_id = u.id)
        )
    )
    GROUP BY u.id
    ORDER BY last_message_time DESC, u.username ASC
");
$stmt->execute([
    $_SESSION['user_id'], $_SESSION['user_id'], 
    $_SESSION['user_id'], $_SESSION['user_id'],
    $_SESSION['user_id'], $_SESSION['user_id'],
    $_SESSION['user_id']
]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get messages for a specific conversation
$selected_user = null;
$messages = [];

if (isset($_GET['user'])) {
    $selected_user_id = $_GET['user'];
    $selected_user = getUserData($selected_user_id);
    
    if ($selected_user) {
        $selected_profile = getProfileData($selected_user_id);
        
        // Get messages
        $stmt = $pdo->prepare("
            SELECT m.*, u.username, u.user_type
            FROM messages m 
            JOIN users u ON m.sender_id = u.id 
            WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?) 
            ORDER BY m.sent_at ASC
        ");
        $stmt->execute([$_SESSION['user_id'], $selected_user_id, $selected_user_id, $_SESSION['user_id']]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Mark messages as read
        $stmt = $pdo->prepare("UPDATE messages SET is_read = TRUE WHERE receiver_id = ? AND sender_id = ?");
        $stmt->execute([$_SESSION['user_id'], $selected_user_id]);
        
        // Handle AJAX request for sending message
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message']) && !empty($_POST['message'])) {
            $message = trim($_POST['message']);
            
            if (!empty($message)) {
                $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
                if ($stmt->execute([$_SESSION['user_id'], $selected_user_id, $message])) {
                    // If it's an AJAX request, return JSON response
                    if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true]);
                        exit();
                    } else {
                        // Regular form submission - refresh the page
                        header("Location: chat.php?user=" . $selected_user_id);
                        exit();
                    }
                } else {
                    $error_msg = "Failed to send message.";
                    if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'error' => $error_msg]);
                        exit();
                    } else {
                        $_SESSION['error'] = $error_msg;
                    }
                }
            }
        }
    }
}

// Get all users for the "New Conversation" section
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.user_type, p.first_name, p.last_name, p.headline
    FROM users u
    JOIN profiles p ON u.id = p.user_id
    WHERE u.id != ?
    ORDER BY p.first_name, p.last_name
");
$stmt->execute([$_SESSION['user_id']]);
$all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - AlumniConnect</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --success: #06d6a0;
            --danger: #ef476f;
            --warning: #ffd166;
            --info: #118ab2;
            --light: #f8f9fa;
            --dark: #212529;
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
            background-color: #f5f7fa;
            color: #333;
            min-height: 100vh;
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
        .chat-container {
            display: flex;
            height: 100vh;
            max-width: 1400px;
            margin: 0 auto;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        
        .conversations-sidebar {
            width: 100%;
            background: white;
            border-right: 1px solid var(--light-gray);
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            max-width: 400px;
            margin-left: 100px;
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid var(--light-gray);
            background: var(--primary);
            color: white;
        }
        
        .new-conversation-header {
            padding: 15px;
            border-bottom: 1px solid var(--light-gray);
            background-color: #f8f9fa;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .new-conversation-content {
            display: none;
            padding: 15px;
            border-bottom: 1px solid var(--light-gray);
            background-color: #f8f9fa;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .user-item {
            padding: 10px;
            margin-bottom: 10px;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-item:hover {
            background-color: #e9ecef;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        .user-info h4 {
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .user-type {
            font-size: 0.8rem;
            padding: 2px 8px;
            border-radius: 12px;
            background: var(--light-gray);
        }
        
        .user-type.alumni {
            background: var(--info);
            color: white;
        }
        
        .conversation-list {
            flex: 1;
            overflow-y: auto;
        }
        
        .conversation-item {
            padding: 15px;
            border-bottom: 1px solid var(--light-gray);
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            justify-content: space-between;
        }
        
        .conversation-item:hover {
            background-color: var(--light);
        }
        
        .conversation-item.active {
            background-color: var(--light);
        }
        
        .conversation-info h4 {
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .conversation-info p {
            color: var(--gray);
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .conversation-time {
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .chat-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--light-gray);
            background-color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background-color: #f9f9f9;
            display: flex;
            flex-direction: column;
        }
        
        .message {
            margin-bottom: 15px;
            display: flex;
            max-width: 80%;
        }
        
        .message.sent {
            align-self: flex-end;
        }
        
        .message.received {
            align-self: flex-start;
        }
        
        .message-content {
            padding: 10px 15px;
            border-radius: 18px;
            position: relative;
        }
        
        .message.sent .message-content {
            background-color: var(--primary);
            color: white;
            border-bottom-right-radius: 4px;
        }
        
        .message.received .message-content {
            background-color: white;
            color: var(--dark);
            border: 1px solid var(--light-gray);
            border-bottom-left-radius: 4px;
        }
        
        .message-time {
            font-size: 0.75rem;
            color: var(--gray);
            margin-top: 5px;
        }
        
        .message.sent .message-time {
            text-align: right;
        }
        
        .chat-input {
            padding: 15px 20px;
            border-top: 1px solid var(--light-gray);
            background-color: white;
        }
        
        .message-form {
            display: flex;
            gap: 10px;
        }
        
        .message-input {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid var(--light-gray);
            border-radius: 20px;
            outline: none;
            resize: none;
            height: 45px;
        }
        
        .send-button {
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        .no-conversation {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--gray);
            flex-direction: column;
            gap: 20px;
            text-align: center;
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            z-index: 1000;
            opacity: 0;
            transform: translateY(-20px);
            transition: opacity 0.3s, transform 0.3s;
        }
        
        .notification.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        .notification.success {
            background-color: #28a745;
        }
        
        .notification.error {
            background-color: #dc3545;
        }
        
        @media (max-width: 768px) {
            .chat-container {
                flex-direction: column;
            }
            
            .conversations-sidebar {
                width: 100%;
                height: 40vh;
            }
            
            .chat-main {
                height: 60vh;
            }
        }
    </style>
</head>
<body>
    <!-- Notification area -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="notification success" id="notification">
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="notification error" id="notification">
            <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

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
    
    <div class="chat-container">
        <div class="conversations-sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-comments"></i> Messages</h2>
                <p>Chat with students and alumni</p>
            </div>
            
            <div class="new-conversation-header" onclick="toggleNewConversation()">
                <h3><i class="fas fa-plus-circle"></i> Start New Conversation</h3>
                <i class="fas fa-chevron-down" id="newConvArrow"></i>
            </div>
            
            <div class="new-conversation-content" id="newConversationContent">
                <?php if (count($all_users) > 0): ?>
                    <?php foreach ($all_users as $user_item): ?>
                        <div class="user-item" onclick="startConversation(<?php echo $user_item['id']; ?>)">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($user_item['first_name'], 0, 1) . substr($user_item['last_name'], 0, 1)); ?>
                            </div>
                            <div class="user-info">
                                <h4>
                                    <?php echo $user_item['first_name'] . ' ' . $user_item['last_name']; ?>
                                    <span class="user-type <?php echo $user_item['user_type']; ?>">
                                        <?php echo ucfirst($user_item['user_type']); ?>
                                    </span>
                                </h4>
                                <p><?php echo $user_item['headline']; ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No users available</p>
                <?php endif; ?>
            </div>
            
            <div class="conversation-list">
                <h3 style="padding: 15px; border-bottom: 1px solid var(--light-gray);">Recent Conversations</h3>
                
                <?php if (count($conversations) > 0): ?>
                    <?php foreach ($conversations as $conversation): ?>
                        <a href="chat.php?user=<?php echo $conversation['id']; ?>">
                            <div class="conversation-item <?php echo (isset($selected_user) && $selected_user['id'] == $conversation['id']) ? 'active' : ''; ?>">
                                <div class="conversation-info">
                                    <h4>
                                        <?php echo $conversation['first_name'] . ' ' . $conversation['last_name']; ?>
                                        <span class="user-type <?php echo $conversation['user_type']; ?>">
                                            <?php echo ucfirst($conversation['user_type']); ?>
                                        </span>
                                    </h4>
                                    <p><?php echo $conversation['last_message'] ? substr($conversation['last_message'], 0, 50) . '...' : 'No messages yet'; ?></p>
                                </div>
                                <?php if ($conversation['last_message_time']): ?>
                                    <div class="conversation-time">
                                        <?php echo date('M j, g:i a', strtotime($conversation['last_message_time'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="padding: 40px 20px; text-align: center; color: var(--gray);">
                        <i class="fas fa-comments" style="font-size: 3rem; margin-bottom: 15px;"></i>
                        <p>No conversations yet</p>
                        <p>Start a new conversation with someone!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="chat-main">
            <?php if (isset($selected_user)): ?>
                <div class="chat-header">
                    <div>
                        <h3><?php echo $selected_profile['first_name'] . ' ' . $selected_profile['last_name']; ?></h3>
                        <p><?php echo $selected_profile['headline']; ?></p>
                    </div>
                    <div>
                        <span class="user-type <?php echo $selected_user['user_type']; ?>">
                            <?php echo ucfirst($selected_user['user_type']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="chat-messages" id="chat-messages">
                    <?php if (count($messages) > 0): ?>
                        <?php foreach ($messages as $message): ?>
                            <div class="message <?php echo $message['sender_id'] == $_SESSION['user_id'] ? 'sent' : 'received'; ?>">
                                <div class="message-content">
                                    <p><?php echo htmlspecialchars($message['message']); ?></p>
                                    <div class="message-time">
                                        <?php echo date('g:i a', strtotime($message['sent_at'])); ?>
                                        <?php if ($message['sender_id'] == $_SESSION['user_id']): ?>
                                            <?php if ($message['is_read']): ?>
                                                <i class="fas fa-check-double" style="color: #28a745; margin-left: 5px;"></i>
                                            <?php else: ?>
                                                <i class="fas fa-check" style="color: #6c757d; margin-left: 5px;"></i>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px 20px; color: var(--gray);">
                            <i class="fas fa-comments" style="font-size: 3rem; margin-bottom: 15px;"></i>
                            <p>No messages yet</p>
                            <p>Start a conversation with <?php echo $selected_profile['first_name']; ?>!</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="chat-input">
                    <form method="POST" class="message-form" id="messageForm">
                        <input type="text" name="message" class="message-input" placeholder="Type your message..." required>
                        <button type="submit" class="send-button">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="no-conversation">
                    <i class="fas fa-comments" style="font-size: 4rem; color: #dee2e6;"></i>
                    <h3>Welcome to Messages</h3>
                    <p>Select a conversation from the sidebar or start a new one</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Show notification if it exists
        document.addEventListener('DOMContentLoaded', function() {
            const notification = document.getElementById('notification');
            if (notification) {
                notification.classList.add('show');
                
                // Hide notification after 5 seconds
                setTimeout(() => {
                    notification.classList.remove('show');
                    setTimeout(() => {
                        if (notification.parentNode) {
                            notification.parentNode.removeChild(notification);
                        }
                    }, 300);
                }, 5000);
            }
            
            // Scroll to bottom of chat messages
            const chatMessages = document.getElementById('chat-messages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        });
        
        function toggleNewConversation() {
            const content = document.getElementById('newConversationContent');
            const arrow = document.getElementById('newConvArrow');
            
            if (content.style.display === 'block') {
                content.style.display = 'none';
                arrow.classList.remove('fa-chevron-up');
                arrow.classList.add('fa-chevron-down');
            } else {
                content.style.display = 'block';
                arrow.classList.remove('fa-chevron-down');
                arrow.classList.add('fa-chevron-up');
            }
        }
        
        function startConversation(userId) {
            window.location.href = 'chat.php?user=' + userId;
        }
        
        // Auto-refresh messages every 5 seconds if in a conversation
        <?php if (isset($selected_user)): ?>
        setInterval(function() {
            fetch('get_messages.php?user=<?php echo $selected_user_id; ?>')
                .then(response => response.json())
                .then(data => {
                    if (data.length > <?php echo count($messages); ?>) {
                        location.reload();
                    }
                })
                .catch(error => console.error('Error:', error));
        }, 5000);
        <?php endif; ?>
        
        // Handle message form submission with AJAX for better UX
        document.getElementById('messageForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('ajax', 'true');
            
            fetch('chat.php?user=<?php echo isset($selected_user_id) ? $selected_user_id : ''; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Clear input and reload messages
                    this.reset();
                    location.reload();
                } else {
                    alert('Error sending message: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error sending message');
            });
        });
    </script>
</body>
</html>