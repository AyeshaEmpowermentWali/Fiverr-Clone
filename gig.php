<?php
require_once 'db.php';

$slug = isset($_GET['slug']) ? sanitize($_GET['slug']) : '';

if (empty($slug)) {
    header('Location: browse.php');
    exit();
}

// Get gig details
$stmt = $pdo->prepare("
    SELECT g.*, u.username, u.full_name, u.profile_image, u.rating as user_rating, 
           u.total_reviews as user_reviews, c.name as category_name, c.slug as category_slug
    FROM gigs g 
    JOIN users u ON g.user_id = u.id 
    JOIN categories c ON g.category_id = c.id 
    WHERE g.slug = ? AND g.status = 'active'
");
$stmt->execute([$slug]);
$gig = $stmt->fetch();

if (!$gig) {
    header('Location: browse.php');
    exit();
}

// Get gig reviews
$stmt = $pdo->prepare("
    SELECT r.*, u.username, u.full_name 
    FROM reviews r 
    JOIN users u ON r.reviewer_id = u.id 
    WHERE r.gig_id = ? 
    ORDER BY r.created_at DESC 
    LIMIT 10
");
$stmt->execute([$gig['id']]);
$reviews = $stmt->fetchAll();

// Get related gigs
$stmt = $pdo->prepare("
    SELECT g.*, u.username 
    FROM gigs g 
    JOIN users u ON g.user_id = u.id 
    WHERE g.category_id = ? AND g.id != ? AND g.status = 'active' 
    ORDER BY g.views DESC 
    LIMIT 4
");
$stmt->execute([$gig['category_id'], $gig['id']]);
$related_gigs = $stmt->fetchAll();

$current_user = getCurrentUser();
$is_owner = $current_user && $current_user['id'] == $gig['user_id'];

// Update view count
$stmt = $pdo->prepare("UPDATE gigs SET views = views + 1 WHERE id = ?");
$stmt->execute([$gig['id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($gig['title']) ?> - FiverrClone</title>
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

        .gig-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 3rem;
            padding: 2rem 0;
        }

        .gig-main {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .gig-image {
            width: 100%;
            height: 400px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
        }

        .gig-content {
            padding: 2rem;
        }

        .gig-title {
            font-size: 2rem;
            color: #333;
            margin-bottom: 1rem;
            line-height: 1.3;
        }

        .gig-meta {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
            color: #666;
        }

        .gig-meta span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .gig-description {
            font-size: 1.1rem;
            line-height: 1.8;
            color: #555;
            margin-bottom: 2rem;
        }

        .gig-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }

        .tag {
            background: #e3f2fd;
            color: #1976d2;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .seller-info {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }

        .seller-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .seller-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #1dbf73;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.5rem;
        }

        .seller-details h3 {
            color: #333;
            margin-bottom: 0.25rem;
        }

        .seller-rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #666;
        }

        .stars {
            color: #ffc107;
        }

        .gig-sidebar {
            position: sticky;
            top: 2rem;
            height: fit-content;
        }

        .pricing-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .pricing-header {
            background: linear-gradient(135deg, #1dbf73 0%, #00a652 100%);
            color: white;
            padding: 1.5rem;
            text-align: center;
        }

        .pricing-tabs {
            display: flex;
            background: #f8f9fa;
        }

        .pricing-tab {
            flex: 1;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            background: transparent;
            font-weight: 500;
        }

        .pricing-tab.active {
            background: white;
            color: #1dbf73;
        }

        .pricing-content {
            padding: 2rem;
        }

        .package-price {
            font-size: 2rem;
            font-weight: bold;
            color: #1dbf73;
            margin-bottom: 1rem;
        }

        .package-description {
            color: #666;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .package-features {
            list-style: none;
            margin-bottom: 2rem;
        }

        .package-features li {
            padding: 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .package-features li i {
            color: #1dbf73;
        }

        .delivery-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .btn {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1dbf73 0%, #00a652 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(29, 191, 115, 0.3);
        }

        .btn-outline {
            border: 2px solid #1dbf73;
            color: #1dbf73;
            background: transparent;
        }

        .btn-outline:hover {
            background: #1dbf73;
            color: white;
        }

        .reviews-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-top: 2rem;
        }

        .section-title {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 1.5rem;
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

        .reviewer-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .review-rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .review-text {
            color: #666;
            line-height: 1.6;
        }

        .related-gigs {
            margin-top: 3rem;
        }

        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .related-gig {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .related-gig:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        .related-gig-image {
            width: 100%;
            height: 150px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .related-gig-content {
            padding: 1rem;
        }

        .related-gig-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .related-gig-price {
            color: #1dbf73;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .gig-container {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .gig-meta {
                flex-direction: column;
                gap: 0.5rem;
            }

            .pricing-tabs {
                flex-direction: column;
            }

            .delivery-info {
                flex-direction: column;
                gap: 0.5rem;
            }

            .related-grid {
                grid-template-columns: 1fr;
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
                    <?php else: ?>
                        <li><a href="login.php">Sign In</a></li>
                        <li><a href="signup.php">Join</a></li>
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
                <li class="breadcrumb-item"><a href="categories.php">Categories</a></li>
                <li class="breadcrumb-separator"><i class="fas fa-chevron-right"></i></li>
                <li class="breadcrumb-item"><a href="category.php?slug=<?= $gig['category_slug'] ?>"><?= htmlspecialchars($gig['category_name']) ?></a></li>
                <li class="breadcrumb-separator"><i class="fas fa-chevron-right"></i></li>
                <li class="breadcrumb-item"><?= htmlspecialchars($gig['title']) ?></li>
            </ul>
        </div>
    </div>

    <div class="container">
        <div class="gig-container">
            <div class="gig-main">
                <div class="gig-image">
                    <i class="fas fa-image"></i> Service Preview
                </div>
                
                <div class="gig-content">
                    <h1 class="gig-title"><?= htmlspecialchars($gig['title']) ?></h1>
                    
                    <div class="gig-meta">
                        <span><i class="fas fa-eye"></i> <?= number_format($gig['views']) ?> views</span>
                        <span><i class="fas fa-shopping-cart"></i> <?= $gig['orders_completed'] ?> orders</span>
                        <span><i class="fas fa-star"></i> <?= number_format($gig['rating'], 1) ?> (<?= $gig['total_reviews'] ?> reviews)</span>
                    </div>

                    <div class="gig-description">
                        <?= nl2br(htmlspecialchars($gig['description'])) ?>
                    </div>

                    <?php if ($gig['tags']): ?>
                    <div class="gig-tags">
                        <?php foreach (explode(',', $gig['tags']) as $tag): ?>
                            <span class="tag"><?= htmlspecialchars(trim($tag)) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="seller-info">
                        <div class="seller-header">
                            <div class="seller-avatar">
                                <?= strtoupper(substr($gig['username'], 0, 1)) ?>
                            </div>
                            <div class="seller-details">
                                <h3><?= htmlspecialchars($gig['full_name']) ?></h3>
                                <div class="seller-rating">
                                    <div class="stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star<?= $i <= $gig['user_rating'] ? '' : '-o' ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <span><?= number_format($gig['user_rating'], 1) ?> (<?= $gig['user_reviews'] ?> reviews)</span>
                                </div>
                            </div>
                        </div>
                        <a href="profile.php?user=<?= $gig['username'] ?>" class="btn btn-outline">
                            <i class="fas fa-user"></i> View Profile
                        </a>
                    </div>
                </div>
            </div>

            <div class="gig-sidebar">
                <div class="pricing-card">
                    <div class="pricing-header">
                        <h3>Choose Your Package</h3>
                    </div>
                    
                    <div class="pricing-tabs">
                        <button class="pricing-tab active" onclick="showPackage('basic')">Basic</button>
                        <?php if ($gig['standard_price']): ?>
                            <button class="pricing-tab" onclick="showPackage('standard')">Standard</button>
                        <?php endif; ?>
                        <?php if ($gig['premium_price']): ?>
                            <button class="pricing-tab" onclick="showPackage('premium')">Premium</button>
                        <?php endif; ?>
                    </div>

                    <div class="pricing-content">
                        <div id="basic-package" class="package-content">
                            <div class="package-price"><?= formatPrice($gig['basic_price']) ?></div>
                            <div class="package-description">
                                <?= nl2br(htmlspecialchars($gig['basic_description'])) ?>
                            </div>
                            <ul class="package-features">
                                <li><i class="fas fa-check"></i> Basic package features</li>
                                <li><i class="fas fa-clock"></i> <?= $gig['delivery_time'] ?> days delivery</li>
                                <li><i class="fas fa-redo"></i> <?= $gig['revisions'] ?> revision<?= $gig['revisions'] != 1 ? 's' : '' ?></li>
                            </ul>
                        </div>

                        <?php if ($gig['standard_price']): ?>
                        <div id="standard-package" class="package-content" style="display: none;">
                            <div class="package-price"><?= formatPrice($gig['standard_price']) ?></div>
                            <div class="package-description">
                                <?= nl2br(htmlspecialchars($gig['standard_description'])) ?>
                            </div>
                            <ul class="package-features">
                                <li><i class="fas fa-check"></i> Everything in Basic</li>
                                <li><i class="fas fa-plus"></i> Additional features</li>
                                <li><i class="fas fa-clock"></i> <?= $gig['delivery_time'] ?> days delivery</li>
                                <li><i class="fas fa-redo"></i> <?= $gig['revisions'] ?> revision<?= $gig['revisions'] != 1 ? 's' : '' ?></li>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <?php if ($gig['premium_price']): ?>
                        <div id="premium-package" class="package-content" style="display: none;">
                            <div class="package-price"><?= formatPrice($gig['premium_price']) ?></div>
                            <div class="package-description">
                                <?= nl2br(htmlspecialchars($gig['premium_description'])) ?>
                            </div>
                            <ul class="package-features">
                                <li><i class="fas fa-check"></i> Everything in Standard</li>
                                <li><i class="fas fa-crown"></i> Premium features</li>
                                <li><i class="fas fa-clock"></i> <?= $gig['delivery_time'] ?> days delivery</li>
                                <li><i class="fas fa-redo"></i> Unlimited revisions</li>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <div class="delivery-info">
                            <span><i class="fas fa-truck"></i> Delivery</span>
                            <span><?= $gig['delivery_time'] ?> days</span>
                        </div>

                        <?php if ($current_user && !$is_owner): ?>
                            <button class="btn btn-primary" onclick="orderGig('basic')">
                                <i class="fas fa-shopping-cart"></i> Order Now
                            </button>
                            <button class="btn btn-outline" style="margin-top: 1rem;" onclick="contactSeller()">
                                <i class="fas fa-comments"></i> Contact Seller
                            </button>
                        <?php elseif ($is_owner): ?>
                            <a href="edit-gig.php?id=<?= $gig['id'] ?>" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Edit Gig
                            </a>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt"></i> Login to Order
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reviews Section -->
        <?php if (!empty($reviews)): ?>
        <div class="reviews-section">
            <h2 class="section-title">
                <i class="fas fa-star"></i> Reviews (<?= count($reviews) ?>)
            </h2>
            
            <?php foreach ($reviews as $review): ?>
                <div class="review-item">
                    <div class="review-header">
                        <div class="reviewer-info">
                            <strong><?= htmlspecialchars($review['full_name']) ?></strong>
                            <span>@<?= htmlspecialchars($review['username']) ?></span>
                        </div>
                        <div class="review-rating">
                            <div class="stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star<?= $i <= $review['rating'] ? '' : '-o' ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <span><?= timeAgo($review['created_at']) ?></span>
                        </div>
                    </div>
                    <div class="review-text">
                        <?= nl2br(htmlspecialchars($review['review'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Related Gigs -->
        <?php if (!empty($related_gigs)): ?>
        <div class="related-gigs">
            <h2 class="section-title">Related Services</h2>
            <div class="related-grid">
                <?php foreach ($related_gigs as $related): ?>
                    <div class="related-gig" onclick="redirectTo('gig.php?slug=<?= $related['slug'] ?>')">
                        <div class="related-gig-image">
                            <i class="fas fa-image"></i>
                        </div>
                        <div class="related-gig-content">
                            <div class="related-gig-title">
                                <?= htmlspecialchars(substr($related['title'], 0, 50)) ?>...
                            </div>
                            <div class="related-gig-price">
                                Starting at <?= formatPrice($related['basic_price']) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function redirectTo(url) {
            window.location.href = url;
        }

        function showPackage(packageType) {
            // Hide all packages
            document.querySelectorAll('.package-content').forEach(pkg => {
                pkg.style.display = 'none';
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.pricing-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected package
            document.getElementById(packageType + '-package').style.display = 'block';
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        function orderGig(packageType) {
            const gigId = <?= $gig['id'] ?>;
            window.location.href = `place-order.php?gig_id=${gigId}&package=${packageType}`;
        }

        function contactSeller() {
            // In a real implementation, this would open a contact form or redirect to messages
            alert('Contact seller functionality would be implemented here');
        }

        // Auto-scroll to reviews if coming from a review link
        if (window.location.hash === '#reviews') {
            document.querySelector('.reviews-section').scrollIntoView();
        }
    </script>
</body>
</html>
