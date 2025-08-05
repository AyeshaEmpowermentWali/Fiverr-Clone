<?php
require_once 'db.php';

// Get all categories with gig counts
$stmt = $pdo->query("
    SELECT c.*, COUNT(g.id) as gig_count 
    FROM categories c 
    LEFT JOIN gigs g ON c.id = g.category_id AND g.status = 'active'
    WHERE c.status = 'active' 
    GROUP BY c.id 
    ORDER BY c.name
");
$categories = $stmt->fetchAll();

$current_user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - FiverrClone</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
        }

        .header {
            background: linear-gradient(135deg, #1dbf73 0%, #00a652 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
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
            font-size: 2rem;
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

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .btn-primary {
            background: #ff6b35;
            color: white;
        }

        .btn-outline {
            border: 2px solid white;
            color: white;
            background: transparent;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4rem 0;
            text-align: center;
        }

        .page-title {
            font-size: 3rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .page-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        .categories-section {
            padding: 4rem 0;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }

        .category-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }

        .category-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px rgba(0,0,0,0.15);
            border-color: #1dbf73;
        }

        .category-header {
            background: linear-gradient(135deg, #1dbf73 0%, #00a652 100%);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .category-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .category-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .category-name {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .category-count {
            font-size: 1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .category-content {
            padding: 2rem;
        }

        .category-description {
            color: #666;
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .category-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid #e1e5e9;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #1dbf73;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #666;
        }

        .explore-btn {
            background: linear-gradient(135deg, #1dbf73 0%, #00a652 100%);
            color: white;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .explore-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(29, 191, 115, 0.4);
        }

        .breadcrumb {
            background: white;
            padding: 1rem 0;
            border-bottom: 1px solid #e1e5e9;
        }

        .breadcrumb-list {
            display: flex;
            list-style: none;
            gap: 0.5rem;
            align-items: center;
        }

        .breadcrumb-item a {
            color: #666;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .breadcrumb-item a:hover {
            color: #1dbf73;
        }

        .breadcrumb-separator {
            color: #ccc;
        }

        .user-menu {
            position: relative;
            display: inline-block;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #ff6b35;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            cursor: pointer;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background: white;
            min-width: 200px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border-radius: 10px;
            padding: 1rem 0;
            z-index: 1000;
        }

        .dropdown-menu.show {
            display: block;
        }

        .dropdown-menu a {
            display: block;
            padding: 0.5rem 1rem;
            color: #333;
            text-decoration: none;
            transition: background 0.3s ease;
        }

        .dropdown-menu a:hover {
            background: #f8f9fa;
        }

        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 1rem;
            }

            .nav-menu {
                flex-direction: column;
                gap: 1rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .categories-grid {
                grid-template-columns: 1fr;
            }

            .category-stats {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="nav-container">
            <a href="index.php" class="logo">
                <i class="fas fa-briefcase"></i> FiverrClone
            </a>
            
            <nav>
                <ul class="nav-menu">
                    <li><a href="browse.php">Browse</a></li>
                    <li><a href="categories.php">Categories</a></li>
                    <?php if ($current_user): ?>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="create-gig.php">Sell</a></li>
                        <li><a href="orders.php">Orders</a></li>
                        <li><a href="messages.php">Messages</a></li>
                        <li class="user-menu">
                            <div class="user-avatar" onclick="toggleDropdown()">
                                <?= strtoupper(substr($current_user['username'], 0, 1)) ?>
                            </div>
                            <div class="dropdown-menu" id="userDropdown">
                                <a href="profile.php">Profile</a>
                                <a href="settings.php">Settings</a>
                                <a href="logout.php">Logout</a>
                            </div>
                        </li>
                    <?php else: ?>
                        <li><a href="login.php" class="btn btn-outline">Sign In</a></li>
                        <li><a href="signup.php" class="btn btn-primary">Join</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <div class="container">
            <ul class="breadcrumb-list">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-separator"><i class="fas fa-chevron-right"></i></li>
                <li class="breadcrumb-item">Categories</li>
            </ul>
        </div>
    </div>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="page-title">Explore Categories</h1>
            <p class="page-subtitle">Discover services across all categories</p>
        </div>
    </section>

    <!-- Categories Section -->
    <section class="categories-section">
        <div class="container">
            <div class="categories-grid">
                <?php foreach ($categories as $category): ?>
                <div class="category-card" onclick="redirectTo('category.php?slug=<?= $category['slug'] ?>')">
                    <div class="category-header">
                        <div class="category-icon">
                            <i class="<?= $category['icon'] ?>"></i>
                        </div>
                        <h3 class="category-name"><?= htmlspecialchars($category['name']) ?></h3>
                        <p class="category-count"><?= $category['gig_count'] ?> services available</p>
                    </div>
                    
                    <div class="category-content">
                        <p class="category-description">
                            <?= htmlspecialchars($category['description']) ?>
                        </p>
                        
                        <div class="category-stats">
                            <div class="stat-item">
                                <div class="stat-number"><?= $category['gig_count'] ?></div>
                                <div class="stat-label">Services</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?= rand(50, 500) ?></div>
                                <div class="stat-label">Sellers</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?= rand(100, 1000) ?></div>
                                <div class="stat-label">Orders</div>
                            </div>
                        </div>
                        
                        <div style="text-align: center; margin-top: 1.5rem;">
                            <a href="category.php?slug=<?= $category['slug'] ?>" class="explore-btn">
                                <i class="fas fa-arrow-right"></i> Explore
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <script>
        function redirectTo(url) {
            window.location.href = url;
        }

        function toggleDropdown() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('show');
        }

        // Close dropdown when clicking outside
        window.onclick = function(event) {
            if (!event.target.matches('.user-avatar')) {
                const dropdown = document.getElementById('userDropdown');
                if (dropdown && dropdown.classList.contains('show')) {
                    dropdown.classList.remove('show');
                }
            }
        }
    </script>
</body>
</html>
