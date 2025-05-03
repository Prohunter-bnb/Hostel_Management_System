<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include '../config/db_connect.php'; // Ensure the correct database connection path

if (isset($_SESSION['user_type'])) {
    switch ($_SESSION['user_type']) {
        case 'admin':
            header("Location: ../admin/dashboard.php");
            break;
        case 'student':
            header("Location: ../student/dashboard.php");
            break;
        case 'management':
            header("Location: ../management/dashboard.php");
            break;
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate required fields
    if (!isset($_POST['email']) || !isset($_POST['password']) || !isset($_POST['user_type'])) {
        $error = "All fields are required.";
    } else {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $user_type = trim($_POST['user_type']);

        // Validate user type
        $allowed_types = ['admin', 'student', 'management'];
        if (!in_array($user_type, $allowed_types)) {
            $error = "Invalid user type selected.";
        } else {
            // Check if user exists using PDO
            $stmt = $conn->prepare("SELECT id, name, email, password, user_type FROM users WHERE email = ? AND user_type = ?");
            $stmt->execute([$email, $user_type]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Verify password
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_type'] = $user['user_type'];
                    
                    // Redirect based on user type
                    switch ($user['user_type']) {
                        case 'admin':
                            header("Location: ../admin/dashboard.php");
                            break;
                        case 'student':
                            header("Location: ../student/dashboard.php");
                            break;
                        case 'management':
                            header("Location: ../management/dashboard.php");
                            break;
                    }
                    exit();
                } else {
                    $error = "Invalid credentials. Please try again.";
                }
            } else {
                $error = "Invalid credentials. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Hostel Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --error-color: #e74c3c;
            --success-color: #2ecc71;
            --text-color: #333;
            --light-gray: #f5f6fa;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-color);
        }

        .login-container {
            background: white;
            padding: 2.5rem;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            transition: transform 0.3s ease;
        }

        .login-container:hover {
            transform: translateY(-5px);
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header h1 {
            color: var(--primary-color);
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: #666;
            font-size: 0.9rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--secondary-color);
            outline: none;
        }

        .error-message {
            background-color: #ffeaea;
            color: var(--error-color);
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            text-align: center;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .btn-login:hover {
            background-color: var(--secondary-color);
        }

        .footer-text {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: #666;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 1.5rem;
                margin: 1rem;
            }
        }

        .form-select {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23666' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: calc(100% - 15px) center;
            background-color: white;
        }

        .form-select:focus {
            border-color: var(--secondary-color);
            outline: none;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Login</h1>
            <p>Welcome back! Please login to your account.</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" autocomplete="off">
            <div class="form-group">
                <i class="fas fa-user-shield"></i>
                <select name="user_type" class="form-select" required>
                    <option value="">Select User Type</option>
                    <option value="admin">Administrator</option>
                    <option value="student">Student</option>
                    <option value="management">Management</option>
                </select>
            </div>

            <div class="form-group">
                <i class="fas fa-envelope"></i>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    class="form-control" 
                    placeholder="Email Address"
                    required
                    autocomplete="off"
                >
            </div>
            
            <div class="form-group">
                <i class="fas fa-lock"></i>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-control" 
                    placeholder="Password"
                    required
                >
            </div>

            <button type="submit" class="btn-login">
                Sign In <i class="fas fa-sign-in-alt"></i>
            </button>
        </form>

        <div class="footer-text">
            Hostel Management System &copy; <?php echo date('Y'); ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add subtle animation to form inputs and select
            const inputs = document.querySelectorAll('.form-control, .form-select');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'translateX(5px)';
                });
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'translateX(0)';
                });
            });

            // Update page title based on selected user type
            const userTypeSelect = document.querySelector('select[name="user_type"]');
            const loginHeader = document.querySelector('.login-header h1');
            
            userTypeSelect.addEventListener('change', function() {
                const selectedType = this.value;
                if (selectedType) {
                    const typeName = this.options[this.selectedIndex].text;
                    loginHeader.textContent = typeName + ' Login';
                } else {
                    loginHeader.textContent = 'Login';
                }
            });
        });
    </script>
</body>
</html>
