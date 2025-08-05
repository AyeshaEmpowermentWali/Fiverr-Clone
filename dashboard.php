<?php
require_once 'db.php';
requireLogin();

$current_user = getCurrentUser();

// Get user statistics
$stats = [];

// Total gigs
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM gigs WHERE user_id = ?");
$stmt->execute([$current_user['id']]);
$stats['total_gigs'] = $stmt->fetchColumn();

// Active orders as seller
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE seller_id = ? AND status IN ('pending', 'in_progress')");
$stmt->execute([$current_user['id']]);
$stats['active_orders_selling'] = $stmt->fetchColumn();

// Active orders as buyer
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE buyer_id = ? AND status IN ('pending', 'in_progress')");
$stmt->execute([$current_user['id']]);
$stats['active_orders_buying'] = $stmt->fetchColumn();

// Unread messages
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND is_read = 0");
$stmt->execute([$current_user['id']]);
$stats['unread_messages'] = $stmt->fetchColumn();

// Recent orders
$stmt = $pdo->prepare("
    SELECT o.*, g.title as gig_title, 
           CASE 
               WHEN o.buyer_id = ? THEN u_seller.username 
               ELSE u_buyer.username 
           END as other_user
    FROM orders o
    JOIN gigs g ON o.gig_id = g.id
    JOIN users u_seller ON o.seller_id = u_seller.id
    JOIN users u_buyer ON o.buyer_id = u_buyer.id
    WHERE o.buyer_id = ? OR o.seller_id = ?
    ORDER BY o.created_at DESC
    LIMIT 5
");
$stmt->execute([$current_user['id'], $current_user['id'], $current_user['id']]);
$recent_orders = $stmt->fetchAll();

// Recent gigs
$stmt = $pdo->prepare("
    SELECT * FROM gigs 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$current_user['id']]);
$recent_gigs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - FiverrClone</title>
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

        .dashboard-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .welcome-section {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .welcome-title {
            font-size: 2rem;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .welcome-subtitle {
            color: #666;
            font-size: 1.1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        .stat-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #1dbf73;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-size: 1rem;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .section-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .section-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            font-size: 1.3rem;
            font-weight: 600;
        }

        .section-content {
            padding: 1.5rem;
        }

        .order-item, .gig-item {
            padding: 1rem;
            border-bottom: 1px solid #e1e5e9;
            transition: all 0.3s ease;
        }

        .order-item:last-child, .gig-item:last-child {
            border-bottom: none;
        }

        .order-item:hover, .gig-item:hover {
            background: #f8f9fa;
            cursor: pointer;
        }

        .order-title, .gig-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .order-meta, .gig-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            color: #666;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-in_progress { background: #d1ecf1; color: #0c5460; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-active { background: #d4edda; color: #155724; }
        .status-draft { background: #f8d7da; color: #721c24; }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 3rem;
        }

        .action-btn {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            text-decoration: none;
            color: #333;
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .action-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
            border-color: #1dbf73;
        }

        .action-icon {
            font-size: 2rem;
            color: #1dbf73;
            margin-bottom: 1rem;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #666;
        }

        .empty-state i {
            font-size: 3rem;
            color: #ccc;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
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
                    <li><a href="index.php">Home</a></li>
                    <li><a href="browse.php">Browse</a></li>
                    <li><a href="create-gig.php">Sell</a></li>
                    <li><a href="orders.php">Orders</a></li>
                    <li><a href="messages.php">Messages</a></li>
                    <li><a href="profile.php">Profile</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="dashboard-container">
        <div class="welcome-section">
            <h1 class="welcome-title">Welcome back, <?= htmlspecialchars($current_user['full_name']) ?>!</h1>
            <p class="welcome-subtitle">Here's what's happening with your account today.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-store"></i>
                </div>
                <div class="stat-number"><?= $stats['total_gigs'] ?></div>
                <div class="stat-label">Active Gigs</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-number"><?= $stats['active_orders_selling'] ?></div>
                <div class="stat-label">Orders Selling</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="stat-number"><?= $stats['active_orders_buying'] ?></div>
                <div class="stat-label">Orders Buying</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="stat-number"><?= $stats['unread_messages'] ?></div>
                <div class="stat-label">Unread Messages</div>
            </div>
        </div>

        <div class="quick-actions">
            <a href="create-gig.php" class="action-btn">
                <div class="action-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <h3>Create New Gig</h3>
                <p>Start selling your services</p>
            </a>
            
            <a href="browse.php" class="action-btn">
                <div class="action-icon">
                    <i class="fas fa-search"></i>
                </div>
                <h3>Browse Services</h3>
                <p>Find services you need</p>
            </a>
            
            <a href="orders.php" class="action-btn">
                <div class="action-icon">
                    <i class="fas fa-list-alt"></i>
                </div>
                <h3>Manage Orders</h3>
                <p>Track your orders</p>
            </a>
            
            <a href="messages.php" class="action-btn">
                <div class="action-icon">
                    <i class="fas fa-comments"></i>
                </div>
                <h3>Messages</h3>
                <p>Chat with clients</p>
            </a>
        </div>

        <div class="content-grid">
            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-clock"></i> Recent Orders
                </div>
                <div class="section-content">
                    <?php if (empty($recent_orders)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No recent orders</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_orders as $order): ?>
                            <div class="order-item" onclick="redirectTo('order.php?id=<?= $order['id'] ?>')">
                                <div class="order-title"><?= htmlspecialchars($order['gig_title']) ?></div>
                                <div class="order-meta">
                                    <span>with <?= htmlspecialchars($order['other_user']) ?></span>
                                    <span class="status-badge status-<?= $order['status'] ?>">
                                        <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="section-card">
                <div class="section-header">
                    <i class="fas fa-store"></i> My Gigs
                </div>
                <div class="section-content">
                    <?php if (empty($recent_gigs)): ?>
                        <div class="empty-state">
                            <i class="fas fa-store-slash"></i>
                            <p>No gigs created yet</p>
                            <a href="create-gig.php" style="color: #1dbf73;">Create your first gig</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_gigs as $gig): ?>
                            <div class="gig-item" onclick="redirectTo('gig.php?slug=<?= $gig['slug'] ?>')">
                                <div class="gig-title"><?= htmlspecialchars($gig['title']) ?></div>
                                <div class="gig-meta">
                                    <span><?= formatPrice($gig['basic_price']) ?></span>
                                    <span class="status-badge status-<?= $gig['status'] ?>">
                                        <?= ucfirst($gig['status']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function redirectTo(url) {
            window.location.href = url;
        }

        // Auto-refresh stats every 30 seconds
        setInterval(function() {
            // You can implement AJAX refresh here if needed
        }, 30000);
    </script>
</body>
</html>
