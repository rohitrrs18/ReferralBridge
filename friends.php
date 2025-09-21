<?php
include 'config.php';
checkAuth();

$user = getUserData($_SESSION['user_id']);

// Get user's connections
$stmt = $pdo->prepare("
    SELECT u.id, u.username, p.first_name, p.last_name, p.headline, fr.status, fr.sender_id
    FROM friend_requests fr
    JOIN users u ON (fr.sender_id = u.id OR fr.receiver_id = u.id) AND u.id != ?
    JOIN profiles p ON u.id = p.user_id
    WHERE (fr.sender_id = ? OR fr.receiver_id = ?) AND fr.status = 'accepted'
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$connections = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending sent requests
$stmt = $pdo->prepare("
    SELECT u.id, u.username, p.first_name, p.last_name, p.headline, fr.sent_at
    FROM friend_requests fr
    JOIN users u ON fr.receiver_id = u.id
    JOIN profiles p ON u.id = p.user_id
    WHERE fr.sender_id = ? AND fr.status = 'pending'
");
$stmt->execute([$_SESSION['user_id']]);
$sent_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending received requests
$stmt = $pdo->prepare("
    SELECT u.id, u.username, p.first_name, p.last_name, p.headline, fr.sent_at, fr.id as request_id
    FROM friend_requests fr
    JOIN users u ON fr.sender_id = u.id
    JOIN profiles p ON u.id = p.user_id
    WHERE fr.receiver_id = ? AND fr.status = 'pending'
");
$stmt->execute([$_SESSION['user_id']]);
$received_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle connection actions
if (isset($_GET['accept'])) {
    $request_id = $_GET['accept'];
    $stmt = $pdo->prepare("UPDATE friend_requests SET status = 'accepted' WHERE id = ?");
    $stmt->execute([$request_id]);
    header("Location: friends.php");
    exit();
}

if (isset($_GET['reject'])) {
    $request_id = $_GET['reject'];
    $stmt = $pdo->prepare("UPDATE friend_requests SET status = 'rejected' WHERE id = ?");
    $stmt->execute([$request_id]);
    header("Location: friends.php");
    exit();
}

if (isset($_GET['cancel'])) {
    $request_id = $_GET['cancel'];
    $stmt = $pdo->prepare("DELETE FROM friend_requests WHERE id = ? AND sender_id = ?");
    $stmt->execute([$request_id, $_SESSION['user_id']]);
    header("Location: friends.php");
    exit();
}

if (isset($_GET['disconnect'])) {
    $user_id = $_GET['disconnect'];
    $stmt = $pdo->prepare("DELETE FROM friend_requests WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) AND status = 'accepted'");
    $stmt->execute([$_SESSION['user_id'], $user_id, $user_id, $_SESSION['user_id']]);
    header("Location: friends.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connections - AlumniConnect</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="dashboard-content">
            <div class="dashboard-header">
                <h2>Your Connections</h2>
                <p>Manage your network of alumni and students</p>
            </div>
            
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <h3>Your Connections (<?php echo count($connections); ?>)</h3>
                    
                    <?php if (count($connections) > 0): ?>
                        <div class="connections-list">
                            <?php foreach ($connections as $connection): ?>
                                <div class="connection-item">
                                    <div class="connection-avatar">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="connection-info">
                                        <h4><?php echo $connection['first_name'] . ' ' . $connection['last_name']; ?></h4>
                                        <p><?php echo $connection['headline']; ?></p>
                                        <span>@<?php echo $connection['username']; ?></span>
                                    </div>
                                    <div class="connection-actions">
                                        <a href="chat.php?user=<?php echo $connection['id']; ?>" class="btn btn-success btn-sm">Message</a>
                                        <a href="?disconnect=<?php echo $connection['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to remove this connection?')">Remove</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-user-friends"></i>
                            <p>You don't have any connections yet</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="dashboard-card">
                    <h3>Pending Requests</h3>
                    
                    <h4>Received Requests (<?php echo count($received_requests); ?>)</h4>
                    <?php if (count($received_requests) > 0): ?>
                        <div class="requests-list">
                            <?php foreach ($received_requests as $request): ?>
                                <div class="request-item">
                                    <div class="request-info">
                                        <h4><?php echo $request['first_name'] . ' ' . $request['last_name']; ?></h4>
                                        <p><?php echo $request['headline']; ?></p>
                                        <span>@<?php echo $request['username']; ?></span>
                                    </div>
                                    <div class="request-actions">
                                        <a href="?accept=<?php echo $request['request_id']; ?>" class="btn btn-success btn-sm">Accept</a>
                                        <a href="?reject=<?php echo $request['request_id']; ?>" class="btn btn-danger btn-sm">Reject</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>No pending received requests</p>
                    <?php endif; ?>
                    
                    <h4>Sent Requests (<?php echo count($sent_requests); ?>)</h4>
                    <?php if (count($sent_requests) > 0): ?>
                        <div class="requests-list">
                            <?php foreach ($sent_requests as $request): ?>
                                <div class="request-item">
                                    <div class="request-info">
                                        <h4><?php echo $request['first_name'] . ' ' . $request['last_name']; ?></h4>
                                        <p><?php echo $request['headline']; ?></p>
                                        <span>@<?php echo $request['username']; ?></span>
                                    </div>
                                    <div class="request-actions">
                                        <a href="?cancel=<?php echo $request['id']; ?>" class="btn btn-danger btn-sm">Cancel</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>No pending sent requests</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="script.js"></script>
</body>
</html>