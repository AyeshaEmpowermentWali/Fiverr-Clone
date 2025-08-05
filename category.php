<?php
require_once 'db.php';

$slug = isset($_GET['slug']) ? sanitize($_GET['slug']) : '';

if (empty($slug)) {
    header('Location: categories.php');
    exit();
}

try {
    // Get category details
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ? AND status = 'active'");
    $stmt->execute([$slug]);
    $category = $stmt->fetch();

    if (!$category) {
        header('Location: categories.php');
        exit();
    }

    // Get subcategories
    $stmt = $pdo->prepare("
        SELECT s.*, COALESCE(COUNT(g.id), 0) as gig_count 
        FROM subcategories s 
        LEFT JOIN gigs g ON s.id = g.subcategory_id AND g.status = 'active'
        WHERE s.category_id = ? AND s.status = 'active' 
        GROUP BY s.id 
        ORDER BY s.name
    ");
    $stmt->execute([$category['id']]);
    $subcategories = $stmt->fetchAll();

    // Get gigs in this category
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 12;
    $offset = ($page - 1) * $limit;

    // Build WHERE clause for filters
    $where_conditions = ["g.category_id = ?", "g.status = 'active'"];
    $params = [$category['id']];

    // Price filter
    if (isset($_GET['price']) && !empty($_GET['price'])) {
        $price_range = $_GET['price'];
        switch ($price_range) {
            case '0-25':
                $where_conditions[] = "g.basic_price BETWEEN 0 AND 25";
                break;
            case '25-50':
                $where_conditions[] = "g.basic_price BETWEEN 25 AND 50";
                break;
            case '50-100':
                $where_conditions[] = "g.basic_price BETWEEN 50 AND 100";
                break;
            case '100+':
                $where_conditions[] = "g.basic_price > 100";
                break;
        }
    }

    // Build ORDER BY clause
    $order_by = "g.created_at DESC";
    if (isset($_GET['sort']) && !empty($_GET['sort'])) {
        switch ($_GET['sort']) {
            case 'newest':
                $order_by = "g.created_at DESC";
                break;
            case 'popular':
                $order_by = "g.views DESC";
                break;
            case 'price_low':
                $order_by = "g.basic_price ASC";
                break;
            case 'price_high':
                $order_by = "g.basic_price DESC";
                break;
            case 'rating':
                $order_by = "COALESCE(g.rating, 0) DESC";
                break;
        }
    }

    $where_clause = implode(' AND ', $where_conditions);

    $stmt = $pdo->prepare("
        SELECT g.*, u.username, u.profile_image, c.name as category_name,
               COALESCE(g.rating, 0) as rating,
               COALESCE(g.total_reviews, 0) as total_reviews,
               COALESCE(g.is_featured, 0) as is_featured
        FROM gigs g 
        JOIN users u ON g.user_id = u.id 
        JOIN categories c ON g.category_id = c.id 
        WHERE {$where_clause}
        ORDER BY {$order_by}
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $gigs = $stmt->fetchAll();

    // Get total count for pagination
    $count_params = array_slice($params, 0, -2); // Remove limit and offset
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM gigs g WHERE {$where_clause}");
    $stmt->execute($count_params);
    $total_gigs = $stmt->fetchColumn();
    $total_pages = ceil($total_gigs / $limit);

} catch (Exception $e) {
    error_log("Category page error: " . $e->getMessage());
    $category = ['name' => 'Category', 'description' => 'Browse services', 'icon' => 'fas fa-folder'];
    $subcategories = [];
    $gigs = [];
    $total_gigs = 0;
    $total_pages = 1;
}

$current_user = getCurrentUser();

// Helper function for pagination URLs
function buildPaginationUrl($page, $slug) {
    $params = $_GET;
    $params['page'] = $page;
    $params['slug'] = $slug;
    return 'category.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($category['name']) ?> - FiverrClone</title>
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

        .category-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0;
            text-align: center;
        }

        .category-title {
            font-size: 3rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .category-description {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }

        .subcategories-section {
            background: white;
            padding: 2rem 0;
            border-bottom: 1px solid #e1e5e9;
        }

        .subcategories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .subcategory-card {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .subcategory-card:hover {
            background: white;
            border-color: #1dbf73;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .subcategory-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .subcategory-count {
            color: #666;
            font-size: 0.9rem;
        }

        .gigs-section {
            padding: 3rem 0;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 2rem;
            color: #333;
        }

        .filters {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .filter-select {
            padding: 0.5rem 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            background: white;
            cursor: pointer;
            min-width: 150px;
        }

        .filter-select:focus {
            outline: none;
            border-color: #1dbf73;
        }

        .gigs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
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
            position: relative;
        }

        .featured-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ff6b35;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .gig-content {
            padding: 1.5rem;
        }

        .gig-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
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
            font-size: 0.8rem;
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

        .rating-text {
            color: #666;
            font-size: 0.9rem;
        }

        .gig-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid #e1e5e9;
        }

        .gig-price {
            font-size: 1.2rem;
            font-weight: bold;
            color: #1dbf73;
        }

        .gig-views {
            color: #666;
            font-size: 0.8rem;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .pagination a,
        .pagination span {
            padding: 0.75rem 1rem;
            border: 1px solid #e1e5e9;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
            min-width: 40px;
            text-align: center;
        }

        .pagination a:hover {
            background: #1dbf73;
            color: white;
            border-color: #1dbf73;
        }

        .pagination .current {
            background: #1dbf73;
            color: white;
            border-color: #1dbf73;
        }

        .pagination .ellipsis {
            border: none;
            background: none;
            color: #666;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #666;
        }

        .empty-state i {
            font-size: 4rem;
            color: #ccc;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
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

            .category-title {
                font-size: 2rem;
            }

            .section-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .filters {
                width: 100%;
                justify-content: space-between;
                flex-wrap: wrap;
            }

            .filter-select {
                min-width: 120px;
                flex: 1;
            }

            .gigs-grid {
                grid-template-columns: 1fr;
            }

            .subcategories-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .pagination {
                gap: 0.25rem;
            }

            .pagination a,
            .pagination span {
                padding: 0.5rem 0.75rem;
                font-size: 0.9rem;
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
                <li class="breadcrumb-item"><a href="categories.php">Categories</a></li>
                <li class="breadcrumb-separator"><i class="fas fa-chevron-right"></i></li>
                <li class="breadcrumb-item"><?= htmlspecialchars($category['name']) ?></li>
            </ul>
        </div>
    </div>

    <!-- Category Header -->
    <section class="category-header">
        <div class="container">
            <h1 class="category-title">
                <i class="<?= isset($category['icon']) ? $category['icon'] : 'fas fa-folder' ?>"></i>
                <?= htmlspecialchars($category['name']) ?>
            </h1>
            <p class="category-description"><?= htmlspecialchars($category['description']) ?></p>
        </div>
    </section>

    <!-- Subcategories -->
    <?php if (!empty($subcategories)): ?>
    <section class="subcategories-section">
        <div class="container">
            <div class="subcategories-grid">
                <?php foreach ($subcategories as $subcategory): ?>
                <div class="subcategory-card" onclick="redirectTo('subcategory.php?slug=<?= urlencode($subcategory['slug']) ?>')">
                    <div class="subcategory-name"><?= htmlspecialchars($subcategory['name']) ?></div>
                    <div class="subcategory-count"><?= $subcategory['gig_count'] ?> services</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Gigs Section -->
    <section class="gigs-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title"><?= number_format($total_gigs) ?> services available</h2>
                <div class="filters">
                    <select class="filter-select" onchange="applyFilter('sort', this.value)">
                        <option value="">Sort by</option>
                        <option value="newest" <?= (isset($_GET['sort']) && $_GET['sort'] == 'newest') ? 'selected' : '' ?>>Newest</option>
                        <option value="popular" <?= (isset($_GET['sort']) && $_GET['sort'] == 'popular') ? 'selected' : '' ?>>Most Popular</option>
                        <option value="price_low" <?= (isset($_GET['sort']) && $_GET['sort'] == 'price_low') ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_high" <?= (isset($_GET['sort']) && $_GET['sort'] == 'price_high') ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="rating" <?= (isset($_GET['sort']) && $_GET['sort'] == 'rating') ? 'selected' : '' ?>>Best Rating</option>
                    </select>
                    <select class="filter-select" onchange="applyFilter('price', this.value)">
                        <option value="">Price Range</option>
                        <option value="0-25" <?= (isset($_GET['price']) && $_GET['price'] == '0-25') ? 'selected' : '' ?>>$0 - $25</option>
                        <option value="25-50" <?= (isset($_GET['price']) && $_GET['price'] == '25-50') ? 'selected' : '' ?>>$25 - $50</option>
                        <option value="50-100" <?= (isset($_GET['price']) && $_GET['price'] == '50-100') ? 'selected' : '' ?>>$50 - $100</option>
                        <option value="100+" <?= (isset($_GET['price']) && $_GET['price'] == '100+') ? 'selected' : '' ?>>$100+</option>
                    </select>
                </div>
            </div>

            <?php if (empty($gigs)): ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h3>No services found</h3>
                    <p>Be the first to offer services in this category!</p>
                    <?php if ($current_user): ?>
                        <br>
                        <a href="create-gig.php" style="color: #1dbf73; text-decoration: none; font-weight: 600;">
                            <i class="fas fa-plus"></i> Create a Gig
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="gigs-grid">
                    <?php foreach ($gigs as $gig): ?>
                    <div class="gig-card" onclick="redirectTo('gig.php?slug=<?= urlencode($gig['slug']) ?>')">
                        <div class="gig-image">
                            <?php if ($gig['is_featured']): ?>
                                <div class="featured-badge">Featured</div>
                            <?php endif; ?>
                            <i class="fas fa-image"></i> Service Preview
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
                                    <?php 
                                    $rating = (float)$gig['rating'];
                                    for ($i = 1; $i <= 5; $i++): 
                                    ?>
                                        <i class="fas fa-star<?= $i <= $rating ? '' : '-o' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="rating-text">
                                    <?= $rating > 0 ? number_format($rating, 1) : 'New' ?> 
                                    (<?= number_format($gig['total_reviews']) ?>)
                                </span>
                            </div>
                            <div class="gig-footer">
                                <div class="gig-price">
                                    Starting at <?= formatPrice($gig['basic_price']) ?>
                                </div>
                                <div class="gig-views">
                                    <i class="fas fa-eye"></i> <?= number_format($gig['views']) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?= buildPaginationUrl($page - 1, $slug) ?>">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>

                    <?php
                    // Show page numbers with ellipsis
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    if ($start > 1) {
                        echo '<a href="' . buildPaginationUrl(1, $slug) . '">1</a>';
                        if ($start > 2) {
                            echo '<span class="ellipsis">...</span>';
                        }
                    }
                    
                    for ($i = $start; $i <= $end; $i++) {
                        if ($i == $page) {
                            echo '<span class="current">' . $i . '</span>';
                        } else {
                            echo '<a href="' . buildPaginationUrl($i, $slug) . '">' . $i . '</a>';
                        }
                    }
                    
                    if ($end < $total_pages) {
                        if ($end < $total_pages - 1) {
                            echo '<span class="ellipsis">...</span>';
                        }
                        echo '<a href="' . buildPaginationUrl($total_pages, $slug) . '">' . $total_pages . '</a>';
                    }
                    ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="<?= buildPaginationUrl($page + 1, $slug) ?>">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <script>
        function redirectTo(url) {
            window.location.href = url;
        }

        function applyFilter(filterType, value) {
            const currentUrl = new URL(window.location.href);
            
            if (value) {
                currentUrl.searchParams.set(filterType, value);
            } else {
                currentUrl.searchParams.delete(filterType);
            }
            
            currentUrl.searchParams.delete('page'); // Reset to first page
            window.location.href = currentUrl.toString();
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
