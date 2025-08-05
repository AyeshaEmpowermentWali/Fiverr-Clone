<?php
require_once 'db.php';
requireLogin();

$current_user = getCurrentUser();
$error = '';
$success = '';

// Get categories and subcategories
$categories = $pdo->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = sanitize($_POST['title']);
    $category_id = (int)$_POST['category_id'];
    $subcategory_id = !empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null;
    $description = sanitize($_POST['description']);
    $basic_price = (float)$_POST['basic_price'];
    $standard_price = !empty($_POST['standard_price']) ? (float)$_POST['standard_price'] : null;
    $premium_price = !empty($_POST['premium_price']) ? (float)$_POST['premium_price'] : null;
    $basic_description = sanitize($_POST['basic_description']);
    $standard_description = sanitize($_POST['standard_description']);
    $premium_description = sanitize($_POST['premium_description']);
    $delivery_time = (int)$_POST['delivery_time'];
    $revisions = (int)$_POST['revisions'];
    $tags = sanitize($_POST['tags']);
    $requirements = sanitize($_POST['requirements']);
    
    if (empty($title) || empty($description) || empty($basic_price) || empty($basic_description)) {
        $error = 'Please fill in all required fields';
    } elseif ($basic_price < 5) {
        $error = 'Minimum price is $5';
    } else {
        $slug = generateSlug($title) . '-' . time();
        
        $stmt = $pdo->prepare("
            INSERT INTO gigs (user_id, category_id, subcategory_id, title, slug, description, 
                            basic_price, standard_price, premium_price, basic_description, 
                            standard_description, premium_description, delivery_time, revisions, 
                            tags, requirements, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        
        if ($stmt->execute([
            $current_user['id'], $category_id, $subcategory_id, $title, $slug, $description,
            $basic_price, $standard_price, $premium_price, $basic_description,
            $standard_description, $premium_description, $delivery_time, $revisions,
            $tags, $requirements
        ])) {
            $success = 'Gig created successfully!';
            // Redirect to gig page
            header('Location: gig.php?slug=' . $slug);
            exit();
        } else {
            $error = 'Failed to create gig. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Gig - FiverrClone</title>
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

        .form-section {
            margin-bottom: 3rem;
        }

        .section-title {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #1dbf73;
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

        .required {
            color: #e74c3c;
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

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
        }

        .pricing-section {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }

        .pricing-tier {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            border: 2px solid #e1e5e9;
        }

        .pricing-tier.basic {
            border-color: #1dbf73;
        }

        .pricing-tier.standard {
            border-color: #ff6b35;
        }

        .pricing-tier.premium {
            border-color: #6c5ce7;
        }

        .tier-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .tier-icon {
            font-size: 1.5rem;
            margin-right: 0.5rem;
        }

        .tier-icon.basic { color: #1dbf73; }
        .tier-icon.standard { color: #ff6b35; }
        .tier-icon.premium { color: #6c5ce7; }

        .btn {
            padding: 1rem 2rem;
            background: linear-gradient(135deg, #1dbf73 0%, #00a652 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(29, 191, 115, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
            box-shadow: 0 10px 30px rgba(108, 117, 125, 0.3);
        }

        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
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

        .form-help {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.25rem;
        }

        .char-counter {
            text-align: right;
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.25rem;
        }

        @media (max-width: 768px) {
            .form-row,
            .form-row-3 {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 0 1rem;
            }
            
            .form-container {
                padding: 2rem;
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
                    <li><a href="orders.php">Orders</a></li>
                    <li><a href="messages.php">Messages</a></li>
                    <li><a href="profile.php">Profile</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Create a New Gig</h1>
            <p class="page-subtitle">Share your skills with millions of customers</p>
        </div>

        <div class="form-container">
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

            <form method="POST" action="">
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-info-circle"></i> Basic Information
                    </h3>
                    
                    <div class="form-group">
                        <label for="title">Gig Title <span class="required">*</span></label>
                        <input type="text" id="title" name="title" class="form-control" required 
                               placeholder="I will create a professional logo design"
                               value="<?= isset($_POST['title']) ? htmlspecialchars($_POST['title']) : '' ?>"
                               maxlength="80" onkeyup="updateCharCount('title', 80)">
                        <div class="char-counter" id="title-counter">0/80</div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="category_id">Category <span class="required">*</span></label>
                            <select id="category_id" name="category_id" class="form-control" required onchange="loadSubcategories()">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>" 
                                            <?= (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="subcategory_id">Subcategory</label>
                            <select id="subcategory_id" name="subcategory_id" class="form-control">
                                <option value="">Select Subcategory</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Description <span class="required">*</span></label>
                        <textarea id="description" name="description" class="form-control" required 
                                  placeholder="Describe your service in detail..."
                                  maxlength="1200" onkeyup="updateCharCount('description', 1200)"><?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>
                        <div class="char-counter" id="description-counter">0/1200</div>
                    </div>

                    <div class="form-group">
                        <label for="tags">Tags (comma separated)</label>
                        <input type="text" id="tags" name="tags" class="form-control" 
                               placeholder="logo, design, branding, creative"
                               value="<?= isset($_POST['tags']) ? htmlspecialchars($_POST['tags']) : '' ?>">
                        <div class="form-help">Add up to 5 tags to help buyers find your gig</div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-dollar-sign"></i> Pricing & Packages
                    </h3>
                    
                    <div class="pricing-section">
                        <div class="pricing-tier basic">
                            <div class="tier-header">
                                <i class="fas fa-star tier-icon basic"></i>
                                <h4>Basic Package</h4>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="basic_price">Price <span class="required">*</span></label>
                                    <input type="number" id="basic_price" name="basic_price" class="form-control" 
                                           min="5" step="0.01" required placeholder="25.00"
                                           value="<?= isset($_POST['basic_price']) ? $_POST['basic_price'] : '' ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="delivery_time">Delivery Time (days) <span class="required">*</span></label>
                                    <select id="delivery_time" name="delivery_time" class="form-control" required>
                                        <option value="1" <?= (isset($_POST['delivery_time']) && $_POST['delivery_time'] == '1') ? 'selected' : '' ?>>1 Day</option>
                                        <option value="2" <?= (isset($_POST['delivery_time']) && $_POST['delivery_time'] == '2') ? 'selected' : '' ?>>2 Days</option>
                                        <option value="3" <?= (isset($_POST['delivery_time']) && $_POST['delivery_time'] == '3') ? 'selected' : '' ?>>3 Days</option>
                                        <option value="7" <?= (isset($_POST['delivery_time']) && $_POST['delivery_time'] == '7') ? 'selected' : '' ?>>1 Week</option>
                                        <option value="14" <?= (isset($_POST['delivery_time']) && $_POST['delivery_time'] == '14') ? 'selected' : '' ?>>2 Weeks</option>
                                        <option value="30" <?= (isset($_POST['delivery_time']) && $_POST['delivery_time'] == '30') ? 'selected' : '' ?>>1 Month</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="basic_description">Package Description <span class="required">*</span></label>
                                <textarea id="basic_description" name="basic_description" class="form-control" required 
                                          placeholder="What's included in your basic package?"><?= isset($_POST['basic_description']) ? htmlspecialchars($_POST['basic_description']) : '' ?></textarea>
                            </div>
                        </div>

                        <div class="pricing-tier standard">
                            <div class="tier-header">
                                <i class="fas fa-star-half-alt tier-icon standard"></i>
                                <h4>Standard Package (Optional)</h4>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="standard_price">Price</label>
                                    <input type="number" id="standard_price" name="standard_price" class="form-control" 
                                           min="5" step="0.01" placeholder="50.00"
                                           value="<?= isset($_POST['standard_price']) ? $_POST['standard_price'] : '' ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="revisions">Revisions</label>
                                    <select id="revisions" name="revisions" class="form-control">
                                        <option value="1" <?= (isset($_POST['revisions']) && $_POST['revisions'] == '1') ? 'selected' : '' ?>>1 Revision</option>
                                        <option value="2" <?= (isset($_POST['revisions']) && $_POST['revisions'] == '2') ? 'selected' : '' ?>>2 Revisions</option>
                                        <option value="3" <?= (isset($_POST['revisions']) && $_POST['revisions'] == '3') ? 'selected' : '' ?>>3 Revisions</option>
                                        <option value="5" <?= (isset($_POST['revisions']) && $_POST['revisions'] == '5') ? 'selected' : '' ?>>5 Revisions</option>
                                        <option value="-1" <?= (isset($_POST['revisions']) && $_POST['revisions'] == '-1') ? 'selected' : '' ?>>Unlimited</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="standard_description">Package Description</label>
                                <textarea id="standard_description" name="standard_description" class="form-control" 
                                          placeholder="What's included in your standard package?"><?= isset($_POST['standard_description']) ? htmlspecialchars($_POST['standard_description']) : '' ?></textarea>
                            </div>
                        </div>

                        <div class="pricing-tier premium">
                            <div class="tier-header">
                                <i class="fas fa-crown tier-icon premium"></i>
                                <h4>Premium Package (Optional)</h4>
                            </div>
                            
                            <div class="form-group">
                                <label for="premium_price">Price</label>
                                <input type="number" id="premium_price" name="premium_price" class="form-control" 
                                       min="5" step="0.01" placeholder="100.00"
                                       value="<?= isset($_POST['premium_price']) ? $_POST['premium_price'] : '' ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="premium_description">Package Description</label>
                                <textarea id="premium_description" name="premium_description" class="form-control" 
                                          placeholder="What's included in your premium package?"><?= isset($_POST['premium_description']) ? htmlspecialchars($_POST['premium_description']) : '' ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-clipboard-list"></i> Requirements
                    </h3>
                    
                    <div class="form-group">
                        <label for="requirements">What do you need from the buyer to get started?</label>
                        <textarea id="requirements" name="requirements" class="form-control" 
                                  placeholder="Please provide your company name, preferred colors, style preferences..."><?= isset($_POST['requirements']) ? htmlspecialchars($_POST['requirements']) : '' ?></textarea>
                        <div class="form-help">This helps buyers provide you with the information you need to deliver great work</div>
                    </div>
                </div>

                <div class="form-section">
                    <button type="submit" class="btn">
                        <i class="fas fa-plus-circle"></i> Create Gig
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary" style="margin-left: 1rem;">
                        <i class="fas fa-times"></i> Cancel
                    </a>
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

        function loadSubcategories() {
            const categoryId = document.getElementById('category_id').value;
            const subcategorySelect = document.getElementById('subcategory_id');
            
            // Clear existing options
            subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';
            
            if (categoryId) {
                // You would typically make an AJAX call here to load subcategories
                // For now, we'll add some sample subcategories based on category
                const subcategories = {
                    '1': [
                        {id: 1, name: 'Logo Design'},
                        {id: 2, name: 'Web Design'},
                        {id: 3, name: 'Illustration'}
                    ],
                    '2': [
                        {id: 4, name: 'Social Media Marketing'},
                        {id: 5, name: 'SEO'}
                    ],
                    '3': [
                        {id: 6, name: 'Content Writing'},
                        {id: 7, name: 'Translation'}
                    ],
                    '6': [
                        {id: 8, name: 'Web Development'},
                        {id: 9, name: 'Mobile Apps'}
                    ]
                };
                
                if (subcategories[categoryId]) {
                    subcategories[categoryId].forEach(function(sub) {
                        const option = document.createElement('option');
                        option.value = sub.id;
                        option.textContent = sub.name;
                        subcategorySelect.appendChild(option);
                    });
                }
            }
        }

        // Initialize character counters
        document.addEventListener('DOMContentLoaded', function() {
            updateCharCount('title', 80);
            updateCharCount('description', 1200);
        });

        // Auto-save draft functionality (optional)
        let autoSaveTimer;
        function autoSave() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(function() {
                // Implement auto-save functionality here
                console.log('Auto-saving draft...');
            }, 5000);
        }

        // Add auto-save to form inputs
        document.querySelectorAll('input, textarea, select').forEach(function(element) {
            element.addEventListener('input', autoSave);
        });
    </script>
</body>
</html>
