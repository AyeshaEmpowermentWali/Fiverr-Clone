<?php
require_once 'db.php';
requireLogin();

$current_user = getCurrentUser();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $bio = sanitize($_POST['bio']);
    $skills = sanitize($_POST['skills']);
    $location = sanitize($_POST['location']);
    $phone = sanitize($_POST['phone']);
    
    if (empty($full_name)) {
        $error = 'Full name is required';
    } else {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET full_name = ?, bio = ?, skills = ?, location = ?, phone = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        
        if ($stmt->execute([$full_name, $bio, $skills, $location, $phone, $current_user['id']])) {
            $success = 'Profile updated successfully!';
            // Refresh current user data
            $current_user = getCurrentUser();
        } else {
            $error = 'Failed to update profile. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - FiverrClone</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            line-height: 1.6;
            color: #333;
        }

        .header {
            background: linear-gradient(135deg, #1dbf73 0%, #00a652 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 2rem;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            text-decoration: none;
            color: white;
        }

        .nav-menu {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }

        .nav-menu a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .nav-menu a:hover {
            background: rgba(255,255,255,0.2);
        }

        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            text-align: center;
        }

        .page-title {
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: #666;
            font-size: 1.1rem;
        }

        .form-container {
            background: white;
            padding: 3rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .form-group {
            margin-bottom: 2rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
            font-size: 1rem;
        }

        .form-control {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: #1dbf73;
            box-shadow: 0 0 0 3px rgba(29, 191, 115, 0.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }

        .form-help {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.25rem;
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1dbf73 0%, #00a652 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(29, 191, 115, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }

        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }

        .alert-success {
            background: #efe;
            color: #363;
            border: 1px solid #cfc;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e1e5e9;
        }

        .profile-preview {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            text-align: center;
        }

        .preview-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #1dbf73;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
            margin: 0 auto 1rem;
        }

        .preview-name {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .preview-username {
            color: #666;
            margin-bottom: 1rem;
        }

        .char-counter {
            text-align: right;
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.25rem;
        }

        @media (max-width: 768px) {
            .form-container {
                padding: 2rem;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="nav-container">
            <a href="index.php" class="logo">
                <i class="fas fa-briefcase"></i> FiverrClone
            </a>
            
            <nav>
                <ul class="nav-menu">
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="profile.php">Profile</a></li>
                    <li><a href="orders.php">Orders</a></li>
                    <li><a href="messages.php">Messages</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Edit Profile</h1>
            <p class="page-subtitle">Update your profile information and showcase your skills</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $success ?>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <div class="profile-preview">
                <div class="preview-avatar">
                    <?= strtoupper(substr($current_user['full_name'], 0, 1)) ?>
                </div>
                <div class="preview-name"><?= htmlspecialchars($current_user['full_name']) ?></div>
                <div class="preview-username">@<?= htmlspecialchars($current_user['username']) ?></div>
            </div>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="full_name">Full Name *</label>
                    <input type="text" id="full_name" name="full_name" class="form-control" required 
                           value="<?= htmlspecialchars($current_user['full_name']) ?>"
                           maxlength="100" onkeyup="updateCharCount('full_name', 100)">
                    <div class="char-counter" id="full_name-counter">0/100</div>
                </div>

                <div class="form-group">
                    <label for="skills">Professional Title</label>
                    <input type="text" id="skills" name="skills" class="form-control" 
                           placeholder="e.g., Graphic Designer, Web Developer, Content Writer"
                           value="<?= htmlspecialchars($current_user['skills']) ?>"
                           maxlength="150" onkeyup="updateCharCount('skills', 150)">
                    <div class="form-help">What do you do professionally? This appears as your headline.</div>
                    <div class="char-counter" id="skills-counter">0/150</div>
                </div>

                <div class="form-group">
                    <label for="bio">Bio</label>
                    <textarea id="bio" name="bio" class="form-control" 
                              placeholder="Tell us about yourself, your experience, and what makes you unique..."
                              maxlength="1000" onkeyup="updateCharCount('bio', 1000)"><?= htmlspecialchars($current_user['bio']) ?></textarea>
                    <div class="form-help">Share your story, experience, and what makes you stand out.</div>
                    <div class="char-counter" id="bio-counter">0/1000</div>
                </div>

                <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location" class="form-control" 
                           placeholder="e.g., New York, USA"
                           value="<?= htmlspecialchars($current_user['location']) ?>"
                           maxlength="100">
                    <div class="form-help">Where are you based? This helps clients understand your timezone.</div>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-control" 
                           placeholder="+1 (555) 123-4567"
                           value="<?= htmlspecialchars($current_user['phone']) ?>">
                    <div class="form-help">Optional. This will only be visible to you and used for account security.</div>
                </div>

                <div class="form-actions">
                    <a href="profile.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function updateCharCount(fieldId, maxLength) {
            const field = document.getElementById(fieldId);
            const counter = document.getElementById(fieldId + '-counter');
            const currentLength = field.value.length;
            counter.textContent = currentLength + '/' + maxLength;
            
            if (currentLength > maxLength * 0.9) {
                counter.style.color = '#e74c3c';
            } else if (currentLength > maxLength * 0.7) {
                counter.style.color = '#f39c12';
            } else {
                counter.style.color = '#666';
            }
        }

        // Initialize character counters
        document.addEventListener('DOMContentLoaded', function() {
            updateCharCount('full_name', 100);
            updateCharCount('skills', 150);
            updateCharCount('bio', 1000);
        });

        // Real-time preview updates
        document.getElementById('full_name').addEventListener('input', function() {
            document.querySelector('.preview-name').textContent = this.value || 'Your Name';
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const fullName = document.getElementById('full_name').value.trim();
            
            if (!fullName) {
                e.preventDefault();
                alert('Please enter your full name.');
                document.getElementById('full_name').focus();
                return false;
            }
            
            // Show loading state
            const submitBtn = document.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            submitBtn.disabled = true;
        });

        // Auto-save draft functionality (optional)
        let autoSaveTimer;
        function autoSave() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(function() {
                // Implement auto-save functionality here
                console.log('Auto-saving draft...');
            }, 3000);
        }

        // Add auto-save to form inputs
        document.querySelectorAll('input, textarea').forEach(function(element) {
            element.addEventListener('input', autoSave);
        });
    </script>
</body>
</html>
