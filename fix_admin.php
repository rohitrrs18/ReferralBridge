<?php
// fix_admin.php
include 'config.php';

// Generate a proper hash for admin123
$password = "admin123";
$hash = password_hash($password, PASSWORD_DEFAULT);

// Update the admin password in the database
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
if ($stmt->execute([$hash])) {
    echo "Admin password has been reset successfully!<br>";
    echo "You can now login with username: admin, password: admin123";
} else {
    echo "Error updating admin password.";
}
?>