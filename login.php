<?php
// Start session only if not already started

include 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $dbPassword = $user['password'];
        
        // ✅ Check if password matches (plain OR hashed)
        if ($password === $dbPassword || password_verify($password, $dbPassword)) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = $user['user_type'];
            
            // Redirect based on user type
            if ($user['user_type'] == 'admin') {
                header("Location: dashboard_admin.php");
            } elseif ($user['user_type'] == 'alumni') {
                header("Location: dashboard_alumni.php");
            } else {
                header("Location: dashboard_student.php");
            }
            exit();
        } else {
            $error = "Invalid username or password!";
        }
    } else {
        $error = "Invalid username or password!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AlumniConnect</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google API for Sign-In -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --danger: #e63946;
            --border-radius: 10px;
            --box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .auth-container {
            display: flex;
            max-width: 900px;
            width: 100%;
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
        }
        
        .auth-visual {
            flex: 1;
            background: linear-gradient(135deg, #ffffff 0%, #130b94 100%);
            color: white;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        
        .auth-visual i {
            font-size: 80px;
            margin-bottom: 20px;
        }
        
        .auth-visual h2 {
            font-size: 28px;
            margin-bottom: 15px;
        }
        
        .auth-visual p {
            font-size: 16px;
            opacity: 0.9;
        }
        
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
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-group input {
            width: 100%;
            padding: 14px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        .password-container {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--gray);
        }
        
        .btn {
            display: block;
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: var(--border-radius);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--secondary);
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 12px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #ffebee;
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }
        
        .auth-footer {
            text-align: center;
            margin-top: 20px;
            color: var(--gray);
        }
        
        .auth-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        
        .auth-footer a:hover {
            text-decoration: underline;
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 20px 0;
        }
        
        .divider::before, .divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #ddd;
        }
        
        .divider span {
            padding: 0 15px;
            color: var(--gray);
            font-size: 14px;
        }
        
        .social-login {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .social-btn {
            flex: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .social-btn:hover {
            background: #f5f5f5;
        }
        
        .social-btn i {
            font-size: 18px;
        }
        
        .google-btn {
            color: #DB4437;
        }
        
        .facebook-btn {
            color: #4267B2;
        }
        
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .remember {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .remember input {
            width: auto;
        }
        
        .forgot-password {
            color: var(--primary);
            text-decoration: none;
        }
        
        .forgot-password:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .auth-container {
                flex-direction: column;
            }
            
            .auth-visual {
                display: none;
            }
        }
        
        /* Loading spinner */
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
            display: none;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .google-signin-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            color: #757575;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            padding: 10px 15px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
        }
        
        .google-signin-btn:hover {
            background: #f5f5f5;
        }
        
        .google-signin-btn img {
            width: 18px;
            height: 18px;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-visual">
           <img src="1758439766575.png" alt="logo" style="height: 100px; border-radius: 100px;">
            <h2>Welcome to ReferralBridge</h2>
            <p>Connect with your alumni community, discover opportunities, and stay updated with your institution.</p>
        </div>
        
        <div class="auth-form">
            <div class="logo">
               <img src="1758439766575.png" alt="logo" style="height: 100px; border-radius: 100px;">
                <span>ReferralBridge</span>
            </div>
            
            <div class="auth-header">
                <h2>Sign In to Your Account</h2>
                <p>Welcome back! Please enter your credentials.</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required placeholder="Enter your username">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-container">
                        <input type="password" id="password" name="password" required placeholder="Enter your password">
                        <i class="toggle-password fas fa-eye" id="togglePassword"></i>
                    </div>
                </div>
                
                <div class="remember-forgot">
                    <label class="remember">
                        <input type="checkbox" name="remember"> Remember me
                    </label>
                    <a href="forgot-password.php" class="forgot-password">Forgot Password?</a>
                </div>
                
                <button type="submit" class="btn btn-primary" id="loginBtn">
                    <span class="spinner" id="loginSpinner"></span>
                    <span>Login</span>
                </button>
            </form>
            
            <div class="divider">
                <span>Or continue with</span>
            </div>
            
            <div class="social-login">
                <div id="g_id_onload"
                    data-client_id="YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com"
                    data-context="signin"
                    data-ux_mode="popup"
                    data-callback="handleGoogleSignIn"
                    data-auto_prompt="false">
                </div>

                <div class="g_id_signin"
                    data-type="standard"
                    data-shape="rectangular"
                    data-theme="outline"
                    data-text="signin_with"
                    data-size="large"
                    data-logo_alignment="left">
                </div>
            </div>
            
            <div class="auth-footer">
                <p>Don't have an account? <a href="register.php">Register here</a></p>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle password visibility
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');
        
        togglePassword.addEventListener('click', function () {
            // Toggle the type attribute
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            
            // Toggle the eye icon
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
        
        // Form submission with loading indicator
        const loginForm = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');
        const loginSpinner = document.getElementById('loginSpinner');
        
        loginForm.addEventListener('submit', function() {
            loginSpinner.style.display = 'inline-block';
            loginBtn.disabled = true;
        });
        
        // Handle Google Sign-In
        function handleGoogleSignIn(response) {
            // This function is called after Google Sign-In is complete
            console.log("Google Sign-In successful!", response);
            
            // Show loading state
            loginSpinner.style.display = 'inline-block';
            loginBtn.disabled = true;
            
            // Extract the credential token from the response
            const credential = response.credential;
            
            // Send the credential to your server for verification
            fetch('verify_google_signin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ credential: credential })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Redirect based on user type
                    if (data.user_type === 'admin') {
                        window.location.href = 'dashboard_admin.php';
                    } else if (data.user_type === 'alumni') {
                        window.location.href = 'dashboard_alumni.php';
                    } else {
                        window.location.href = 'dashboard_student.php';
                    }
                } else {
                    alert('Google authentication failed: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred during authentication');
            })
            .finally(() => {
                loginSpinner.style.display = 'none';
                loginBtn.disabled = false;
            });
        }
        
        // Simple animation for input focus
        const inputs = document.querySelectorAll('input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
            });
        });
        
        // Demo Google Sign-In function for testing purposes
        function demoGoogleSignIn() {
            alert("In a real implementation, this would redirect to Google's authentication service. For a complete implementation, you need to:\n\n1. Create a project in Google Cloud Console\n2. Configure OAuth consent screen\n3. Create credentials (OAuth Client ID)\n4. Replace 'YOUR_GOOGLE_CLIENT_ID' with your actual client ID");
        }
    </script>
</body>
</html>