<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $email = $_POST['email'];
    $user_type = $_POST['user_type'];
    
    // Check if username already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    
    if ($stmt->rowCount() > 0) {
        $error = "Username already exists!";
    } else {
        // Insert new user
        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, user_type) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$username, $password, $email, $user_type])) {
            $user_id = $pdo->lastInsertId();
            
            // Create profile entry
            $stmt = $pdo->prepare("INSERT INTO profiles (user_id, first_name, last_name) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $_POST['first_name'], $_POST['last_name']]);
            
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_type'] = $user_type;
            
            // Redirect based on user type
            if ($user_type == 'admin') {
                header("Location: dashboard_admin.php");
            } elseif ($user_type == 'alumni') {
                header("Location: dashboard_alumni.php");
            } else {
                header("Location: dashboard_student.php");
            }
            exit();
        } else {
            $error = "Registration failed. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - AlumniConnect</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
        
      .auth-form {
            flex: 1;
            padding: 40px;
        }
        
        .auth-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .auth-header h2 {
            color: var(--primary);
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .auth-header p {
            color: var(--gray);
            font-size: 16px;
        }
        
    .logo {
            margin-bottom: 20px;
            color: var(--primary);
            font-size: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .logo i {
            font-size: 36px;
        }
</style>
</head>
<body>
    
    <div class="auth-container">
        
        <div class="auth-form">
            <div class="logo">
               <img src="1758439766575.png" alt="logo" style="height: 100px; border-radius: 100px;">
                <span>ReferralBridge</span>
            </div>
            <div class="auth-header">
                <h2>Create an Account</h2>
                <p>Join our network of students and alumni.</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="user_type">I am a</label>
                    <select id="user_type" name="user_type" required>
                        <option value="student">Student</option>
                        <option value="alumni">Alumni</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full">Register</button>
            </form>
            
            <div class="auth-footer">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>
    
    <script src="script.js"></script>
</body>
</html>