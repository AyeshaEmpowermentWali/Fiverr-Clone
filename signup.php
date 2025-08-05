<?php
require_once 'db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $full_name = sanitize($_POST['full_name']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $user_type = sanitize($_POST['user_type']);
    
    // Validation
    if (empty($username) || empty($email) || empty($full_name) || empty($password)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } else {
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetch()) {
            $error = 'Username or email already exists';
        } else {
            // Create new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, full_name, password, user_type) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$username, $email, $full_name, $hashed_password, $user_type])) {
                $user_id = $pdo->lastInsertId();
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['user_type'] = $user_type;
                
                header('Location: dashboard.php');
                exit();
            } else {
                $error = 'Registration failed. Please try again.';
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
    <title>Sign Up - FiverrClone</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .signup-container {
            background: white;
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo a {
            font-size: 2rem;
            font-weight: bold;
            color: #1dbf73;
            text-decoration: none;
        }

        .form-title {
            text-align: center;
            font-size: 1.8rem;
            margin-bottom: 2rem;
            color: #333;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #1dbf73;
            box-shadow: 0 0 0 3px rgba(29, 191, 115, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #1dbf73 0%, #00a652 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(29, 191, 115, 0.3);
        }

        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            text-align: center;
        }

        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }

        .user-type-selector {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .user-type-option {
            position: relative;
        }

        .user-type-option input[type="radio"] {
            display: none;
        }

        .user-type-label {
            display: block;
            padding: 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }

        .user-type-option input[type="radio"]:checked + .user-type-label {
            border-color: #1dbf73;
            background: rgba(29, 191, 115, 0.1);
            color: #1dbf73;
        }

        .user-type-label i {
            display: block;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .form-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e1e5e9;
        }

        .form-footer a {
            color: #1dbf73;
            text-decoration: none;
            font-weight: 500;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.9rem;
        }

        .strength-weak { color: #e74c3c; }
        .strength-medium { color: #f39c12; }
        .strength-strong { color: #27ae60; }

        @media (max-width: 768px) {
            .signup-container {
                padding: 2rem;
                margin: 1rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .user-type-selector {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="signup-container">
        <div class="logo">
            <a href="index.php">
                <i class="fas fa-briefcase"></i> FiverrClone
            </a>
        </div>

        <h2 class="form-title">Join FiverrClone</h2>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>I want to:</label>
                <div class="user-type-selector">
                    <div class="user-type-option">
                        <input type="radio" id="buyer" name="user_type" value="buyer" 
                               <?= (!isset($_POST['user_type']) || $_POST['user_type'] == 'buyer') ? 'checked' : '' ?>>
                        <label for="buyer" class="user-type-label">
                            <i class="fas fa-shopping-cart"></i>
                            <span>Buy Services</span>
                        </label>
                    </div>
                    <div class="user-type-option">
                        <input type="radio" id="seller" name="user_type" value="seller" 
                               <?= (isset($_POST['user_type']) && $_POST['user_type'] == 'seller') ? 'checked' : '' ?>>
                        <label for="seller" class="user-type-label">
                            <i class="fas fa-store"></i>
                            <span>Sell Services</span>
                        </label>
                    </div>
                    <div class="user-type-option">
                        <input type="radio" id="both" name="user_type" value="both" 
                               <?= (isset($_POST['user_type']) && $_POST['user_type'] == 'both') ? 'checked' : '' ?>>
                        <label for="both" class="user-type-label">
                            <i class="fas fa-exchange-alt"></i>
                            <span>Both</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="username">Username *</label>
                    <input type="text" id="username" name="username" class="form-control" required 
                           value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                </div>
                <div class="form-group">
                    <label for="full_name">Full Name *</label>
                    <input type="text" id="full_name" name="full_name" class="form-control" required 
                           value="<?= isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : '' ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email" class="form-control" required 
                       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" id="password" name="password" class="form-control" required 
                           onkeyup="checkPasswordStrength()">
                    <div id="passwordStrength" class="password-strength"></div>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>
            </div>

            <button type="submit" class="btn">
                <i class="fas fa-user-plus"></i> Create Account
            </button>
        </form>

        <div class="form-footer">
            <p>Already have an account? <a href="login.php">Sign in here</a></p>
        </div>
    </div>

    <script>
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthDiv = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthDiv.innerHTML = '';
                return;
            }
            
            let strength = 0;
            let feedback = [];
            
            if (password.length >= 8) strength++;
            else feedback.push('at least 8 characters');
            
            if (/[a-z]/.test(password)) strength++;
            else feedback.push('lowercase letter');
            
            if (/[A-Z]/.test(password)) strength++;
            else feedback.push('uppercase letter');
            
            if (/[0-9]/.test(password)) strength++;
            else feedback.push('number');
            
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            else feedback.push('special character');
            
            let className = '';
            let message = '';
            
            if (strength < 3) {
                className = 'strength-weak';
                message = 'Weak - Add: ' + feedback.slice(0, 2).join(', ');
            } else if (strength < 4) {
                className = 'strength-medium';
                message = 'Medium - Add: ' + feedback.slice(0, 1).join(', ');
            } else {
                className = 'strength-strong';
                message = 'Strong password!';
            }
            
            strengthDiv.className = 'password-strength ' + className;
            strengthDiv.innerHTML = message;
        }

        // Real-time password confirmation
        document.getElementById('confirm_password').addEventListener('keyup', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    this.style.borderColor = '#27ae60';
                } else {
                    this.style.borderColor = '#e74c3c';
                }
            } else {
                this.style.borderColor = '#e1e5e9';
            }
        });
    </script>
</body>
</html>
