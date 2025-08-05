<?php
require_once 'db.php';

// Get featured gigs and categories
$stmt = $pdo->query("
    SELECT g.*, u.username, u.profile_image, c.name as category_name 
    FROM gigs g 
    JOIN users u ON g.user_id = u.id 
    JOIN categories c ON g.category_id = c.id 
    WHERE g.status = 'active' 
    ORDER BY g.is_featured DESC, g.views DESC 
    LIMIT 12
");
$featured_gigs = $stmt->fetchAll();

$categories = $pdo->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name")->fetchAll();

$current_user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiverr Clone - Freelance Services Marketplace</title>
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

        /* Header Styles */
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

        .btn-primary:hover {
            background: #e55a2b;
            transform: translateY(-2px);
        }

        .btn-outline {
            border: 2px solid white;
            color: white;
            background: transparent;
        }

        .btn-outline:hover {
            background: white;
            color: #1dbf73;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4rem 0;
            text-align: center;
        }

        .hero-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .hero h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .hero p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        .search-container {
            max-width: 600px;
            margin: 0 auto;
            position: relative;
        }

        .search-box {
            width: 100%;
            padding: 1rem 1.5rem;
            border: none;
            border-radius: 50px;
            font-size: 1.1rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        .search-btn {
            position: absolute;
            right: 5px;
            top: 5px;
            bottom: 5px;
            background: #1dbf73;
            border: none;
            border-radius: 50px;
            padding: 0 2rem;
            color: white;
            cursor: pointer;
            font-weight: 600;
        }

        /* Categories Section */
        .categories {
            padding: 4rem 0;
            background: white;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .section-title {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 3rem;
            color: #333;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .category-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }

        .category-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
            border-color: #1dbf73;
        }

        .category-icon {
            font-size: 3rem;
            color: #1dbf73;
            margin-bottom: 1rem;
        }

        .category-card h3 {
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .category-card p {
            color: #666;
            font-size: 0.9rem;
        }

        /* Featured Gigs */
        .featured-gigs {
            padding: 4rem 0;
            background: #f8f9fa;
        }

        .gigs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .gig-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .gig-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        .gig-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .gig-content {
            padding: 1.5rem;
        }

        .gig-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .gig-seller {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .seller-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #1dbf73;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-right: 0.5rem;
        }

        .seller-name {
            color: #666;
            font-size: 0.9rem;
        }

        .gig-rating {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stars {
            color: #ffc107;
            margin-right: 0.5rem;
        }

        .gig-price {
            font-size: 1.2rem;
            font-weight: bold;
            color: #1dbf73;
            text-align: right;
        }

        /* Footer */
        .footer {
            background: #333;
            color: white;
            padding: 3rem 0 1rem;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .footer-section h3 {
            margin-bottom: 1rem;
            color: #1dbf73;
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section ul li {
            margin-bottom: 0.5rem;
        }

        .footer-section ul li a {
            color: #ccc;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-section ul li a:hover {
            color: #1dbf73;
        }

        .footer-bottom {
            border-top: 1px solid #555;
            padding-top: 1rem;
            text-align: center;
            color: #ccc;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 1rem;
            }

            .nav-menu {
                flex-direction: column;
                gap: 1rem;
            }

            .hero h1 {
                font-size: 2rem;
            }

            .search-container {
                padding: 0 1rem;
            }

            .categories-grid,
            .gigs-grid {
                grid-template-columns: 1fr;
            }
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

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Find the perfect freelance services for your business</h1>
            <p>Millions of people use FiverrClone to turn their ideas into reality.</p>
            
            <div class="search-container">
                <form action="search.php" method="GET">
                    <input type="text" name="q" class="search-box" placeholder="Try 'building mobile app'" required>
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- Categories Section -->
    <section class="categories">
        <div class="container">
            <h2 class="section-title">Popular Categories</h2>
            <div class="categories-grid">
                <?php foreach ($categories as $category): ?>
                <div class="category-card" onclick="redirectTo('category.php?slug=<?= $category['slug'] ?>')">
                    <div class="category-icon">
                        <i class="<?= $category['icon'] ?>"></i>
                    </div>
                    <h3><?= htmlspecialchars($category['name']) ?></h3>
                    <p><?= htmlspecialchars($category['description']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Featured Gigs -->
    <section class="featured-gigs">
        <div class="container">
            <h2 class="section-title">Featured Services</h2>
            <div class="gigs-grid">
                <?php foreach ($featured_gigs as $gig): ?>
                <div class="gig-card" onclick="redirectTo('gig.php?slug=<?= $gig['slug'] ?>')">
                    <div class="gig-image">
                        <i class="fas fa-image"></i> Featured Service
                    </div>
                    <div class="gig-content">
                        <h3 class="gig-title"><?= htmlspecialchars($gig['title']) ?></h3>
                        <div class="gig-seller">
                            <div class="seller-avatar">
                                <?= strtoupper(substr($gig['username'], 0, 1)) ?>
                            </div>
                            <span class="seller-name"><?= htmlspecialchars($gig['username']) ?></span>
                        </div>
                        <div class="gig-rating">
                            <div class="stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star<?= $i <= $gig['rating'] ? '' : '-o' ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <span>(<?= $gig['total_reviews'] ?>)</span>
                        </div>
                        <div class="gig-price">
                            Starting at <?= formatPrice($gig['basic_price']) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Categories</h3>
                    <ul>
                        <li><a href="#">Graphics & Design</a></li>
                        <li><a href="#">Digital Marketing</a></li>
                        <li><a href="#">Writing & Translation</a></li>
                        <li><a href="#">Programming & Tech</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>About</h3>
                    <ul>
                        <li><a href="#">Careers</a></li>
                        <li><a href="#">Press & News</a></li>
                        <li><a href="#">Partnerships</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Support</h3>
                    <ul>
                        <li><a href="#">Help & Support</a></li>
                        <li><a href="#">Trust & Safety</a></li>
                        <li><a href="#">Selling</a></li>
                        <li><a href="#">Buying</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Community</h3>
                    <ul>
                        <li><a href="#">Events</a></li>
                        <li><a href="#">Blog</a></li>
                        <li><a href="#">Forum</a></li>
                        <li><a href="#">Community Standards</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 FiverrClone. All rights reserved.</p>
            </div>
        </div>
    </footer>

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

        // Search functionality
        document.querySelector('.search-box').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const query = this.value.trim();
                if (query) {
                    redirectTo('search.php?q=' + encodeURIComponent(query));
                }
            }
        });
    </script>
</body>
</html>
