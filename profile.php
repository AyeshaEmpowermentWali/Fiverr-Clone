<?php
require_once 'db.php';

// Get user ID from URL or current user
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : ($_SESSION['user_id'] ?? 0);

if (!$user_id) {
    header('Location: login.php');
    exit();
}

// Get user profile data
try {
    $stmt = $pdo->prepare("
        SELECT u.*, 
               COUNT(DISTINCT g.id) as total_gigs,
               COUNT(DISTINCT o.id) as total_orders,
               AVG(r.rating) as avg_rating,
               COUNT(DISTINCT r.id) as total_reviews
        FROM users u
        LEFT JOIN gigs g ON u.id = g.user_id AND g.status = 'active'
        LEFT JOIN orders o ON u.id = o.seller_id AND o.status = 'completed'
        LEFT JOIN reviews r ON u.id = r.seller_id
        WHERE u.id = ?
        GROUP BY u.id
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        header('Location: index.php');
        exit();
    }

    // Get user's active gigs
    $stmt = $pdo->prepare("
        SELECT g.*, c.name as category_name 
        FROM gigs g 
        JOIN categories c ON g.category_id = c.id 
        WHERE g.user_id = ? AND g.status = 'active' 
        ORDER BY g.created_at DESC 
        LIMIT 6
    ");
    $stmt->execute([$user_id]);
    $user_gigs = $stmt->fetchAll();

    // Get recent reviews
    $stmt = $pdo->prepare("
        SELECT r.*, u.username as buyer_name, g.title as gig_title
        FROM reviews r
        JOIN users u ON r.buyer_id = u.id
        JOIN gigs g ON r.gig_id = g.id
        WHERE r.seller_id = ?
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_reviews = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Profile page error: " . $e->getMessage());
    header('Location: index.php');
    exit();
}

$current_user = getCurrentUser();
$is_own_profile = $current_user && $current_user['id'] == $user_id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($user['username']) ?>'s Profile - FiverrClone</title>
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

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .profile-header {
            background: white;
            margin-top: 2rem;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .cover-photo {
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
        }

        .profile-info {
            padding: 2rem;
            position: relative;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: #1dbf73;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: bold;
            border: 5px solid white;
            position: absolute;
            top: -60px;
            left: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .profile-details {
            margin-left: 150px;
            margin-top: 20px;
        }

        .profile-name {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .profile-title {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }

        .profile-stats {
            display: flex;
            gap: 2rem;
            margin-bottom: 1.5rem;
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
            color: #666;
            font-size: 0.9rem;
        }

        .profile-badges {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge.verified {
            background: #e3f2fd;
            color: #1976d2;
        }

        .badge.pro {
            background: #fff3e0;
            color: #f57c00;
        }

        .badge.top-rated {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .profile-actions {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #1dbf73;
            color: white;
        }

        .btn-primary:hover {
            background: #00a652;
        }

        .btn-secondary {
            background: white;
            color: #333;
            border: 2px solid #e1e5e9;
        }

        .btn-secondary:hover {
            border-color: #1dbf73;
            color: #1dbf73;
        }

        .profile-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }

        .main-content {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .section {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 1.5rem;
            color: #333;
        }

        .gigs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .gig-card {
            border: 1px solid #e1e5e9;
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .gig-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .gig-image {
            height: 150px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .gig-info {
            padding: 1rem;
        }

        .gig-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .gig-price {
            color: #1dbf73;
            font-weight: bold;
        }

        .review-item {
            padding: 1.5rem 0;
            border-bottom: 1px solid #e1e5e9;
        }

        .review-item:last-child {
            border-bottom: none;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .reviewer-name {
            font-weight: 600;
        }

        .review-rating {
            color: #ffc107;
        }

        .review-text {
            color: #666;
            line-height: 1.5;
        }

        .about-text {
            color: #666;
            line-height: 1.6;
        }

        .skills-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .skill-tag {
            background: #f0f0f0;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            color: #666;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #666;
        }

        .empty-state i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #ccc;
        }

        @media (max-width: 768px) {
            .profile-content {
                grid-template-columns: 1fr;
            }

            .profile-details {
                margin-left: 0;
                margin-top: 80px;
                text-align: center;
            }

            .profile-avatar {
                left: 50%;
                transform: translateX(-50%);
            }

            .profile-stats {
                justify-content: center;
            }

            .profile-actions {
                justify-content: center;
            }

            .gigs-grid {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 0 1rem;
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
                        <li><a href="profile.php">Profile</a></li>
                        <li><a href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Sign In</a></li>
                        <li><a href="signup.php">Join</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="cover-photo"></div>
            <div class="profile-info">
                <div class="profile-avatar">
                    <?= strtoupper(substr($user['username'], 0, 1)) ?>
                </div>
                <div class="profile-details">
                    <h1 class="profile-name"><?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></h1>
                    <p class="profile-title"><?= htmlspecialchars($user['title'] ?: 'Professional Freelancer') ?></p>
                    
                    <div class="profile-stats">
                        <div class="stat-item">
                            <div class="stat-number"><?= number_format($user['avg_rating'] ?: 0, 1) ?></div>
                            <div class="stat-label">Rating</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?= $user['total_reviews'] ?></div>
                            <div class="stat-label">Reviews</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?= $user['total_orders'] ?></div>
                            <div class="stat-label">Orders</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?= $user['total_gigs'] ?></div>
                            <div class="stat-label">Active Gigs</div>
                        </div>
                    </div>

                    <div class="profile-badges">
                        <?php if ($user['is_verified']): ?>
                            <span class="badge verified">
                                <i class="fas fa-check-circle"></i> Verified
                            </span>
                        <?php endif; ?>
                        <?php if ($user['avg_rating'] >= 4.8): ?>
                            <span class="badge top-rated">
                                <i class="fas fa-star"></i> Top Rated
                            </span>
                        <?php endif; ?>
                        <?php if ($user['total_orders'] >= 50): ?>
                            <span class="badge pro">
                                <i class="fas fa-crown"></i> Pro Seller
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="profile-actions">
                        <?php if ($is_own_profile): ?>
                            <a href="edit-profile.php" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Edit Profile
                            </a>
                            <a href="settings.php" class="btn btn-secondary">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                        <?php else: ?>
                            <a href="messages.php?user=<?= $user['id'] ?>" class="btn btn-primary">
                                <i class="fas fa-envelope"></i> Contact Me
                            </a>
                            <button class="btn btn-secondary" onclick="shareProfile()">
                                <i class="fas fa-share"></i> Share
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Content -->
        <div class="profile-content">
            <div class="main-content">
                <!-- Active Gigs -->
                <div class="section">
                    <h2 class="section-title">Active Gigs (<?= count($user_gigs) ?>)</h2>
                    <?php if (empty($user_gigs)): ?>
                        <div class="empty-state">
                            <i class="fas fa-briefcase"></i>
                            <p>No active gigs yet</p>
                            <?php if ($is_own_profile): ?>
                                <a href="create-gig.php" class="btn btn-primary" style="margin-top: 1rem;">
                                    Create Your First Gig
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="gigs-grid">
                            <?php foreach ($user_gigs as $gig): ?>
                            <div class="gig-card" onclick="redirectTo('gig.php?slug=<?= urlencode($gig['slug']) ?>')">
                                <div class="gig-image">
                                    <i class="fas fa-image"></i>
                                </div>
                                <div class="gig-info">
                                    <h3 class="gig-title"><?= htmlspecialchars($gig['title']) ?></h3>
                                    <p class="gig-price">Starting at <?= formatPrice($gig['basic_price']) ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Reviews -->
                <div class="section">
                    <h2 class="section-title">Recent Reviews (<?= count($recent_reviews) ?>)</h2>
                    <?php if (empty($recent_reviews)): ?>
                        <div class="empty-state">
                            <i class="fas fa-star"></i>
                            <p>No reviews yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_reviews as $review): ?>
                        <div class="review-item">
                            <div class="review-header">
                                <span class="reviewer-name"><?= htmlspecialchars($review['buyer_name']) ?></span>
                                <div class="review-rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star<?= $i <= $review['rating'] ? '' : '-o' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <p class="review-text"><?= htmlspecialchars($review['comment']) ?></p>
                            <small style="color: #999;">For: <?= htmlspecialchars($review['gig_title']) ?></small>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sidebar">
                <!-- About -->
                <div class="section">
                    <h3 class="section-title">About</h3>
                    <p class="about-text">
                        <?= nl2br(htmlspecialchars($user['bio'] ?: 'Professional freelancer ready to help you with your projects.')) ?>
                    </p>
                </div>

                <!-- Skills -->
                <div class="section">
                    <h3 class="section-title">Skills</h3>
                    <div class="skills-list">
                        <?php 
                        $skills = $user['skills'] ? explode(',', $user['skills']) : ['Web Development', 'Design', 'Writing'];
                        foreach ($skills as $skill): 
                        ?>
                            <span class="skill-tag"><?= htmlspecialchars(trim($skill)) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Contact Info -->
                <div class="section">
                    <h3 class="section-title">Contact Info</h3>
                    <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($user['email']) ?></p>
                    <?php if ($user['phone']): ?>
                        <p><i class="fas fa-phone"></i> <?= htmlspecialchars($user['phone']) ?></p>
                    <?php endif; ?>
                    <?php if ($user['website']): ?>
                        <p><i class="fas fa-globe"></i> <a href="<?= htmlspecialchars($user['website']) ?>" target="_blank">Website</a></p>
                    <?php endif; ?>
                    <p><i class="fas fa-calendar"></i> Member since <?= date('M Y', strtotime($user['created_at'])) ?></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function redirectTo(url) {
            window.location.href = url;
        }

        function shareProfile() {
            if (navigator.share) {
                navigator.share({
                    title: '<?= htmlspecialchars($user['username']) ?>\'s Profile',
                    text: 'Check out <?= htmlspecialchars($user['username']) ?>\'s profile on FiverrClone',
                    url: window.location.href
                });
            } else {
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(window.location.href).then(() => {
                    alert('Profile link copied to clipboard!');
                });
            }
        }
    </script>
</body>
</html>
