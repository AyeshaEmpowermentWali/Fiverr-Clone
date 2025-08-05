<?php
require_once 'db.php';
requireLogin();

$current_user = getCurrentUser();

// Get filter parameters
$filter = isset($_GET['filter']) ? sanitize($_GET['filter']) : 'all';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Build query based on filters
$where_conditions = ["(o.buyer_id = ? OR o.seller_id = ?)"];
$params = [$current_user['id'], $current_user['id']];

if ($filter === 'buying') {
    $where_conditions = ["o.buyer_id = ?"];
    $params = [$current_user['id']];
} elseif ($filter === 'selling') {
    $where_conditions = ["o.seller_id = ?"];
    $params = [$current_user['id']];
}

if (!empty($status)) {
    $where_conditions[] = "o.status = ?";
    $params[] = $status;
}

$where_clause = implode(' AND ', $where_conditions);

// Get orders
$stmt = $pdo->prepare("
    SELECT o.*, g.title as gig_title, g.featured_image,
           u_buyer.username as buyer_username, u_buyer.full_name as buyer_name,
           u_seller.username as seller_username, u_seller.full_name as seller_name,
           c.name as category_name
    FROM orders o
    JOIN gigs g ON o.gig_id = g.id
    JOIN users u_buyer ON o.buyer_id = u_buyer.id
    JOIN users u_seller ON o.seller_id = u_seller.id
    JOIN categories c ON g.category_id = c.id
    WHERE $where_clause
    ORDER BY o.created_at DESC
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Get order statistics
$stats = [];

// Total orders as buyer
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE buyer_id = ?");
$stmt->execute([$current_user['id']]);
$stats['buying'] = $stmt->fetchColumn();

// Total orders as seller
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE seller_id = ?");
$stmt->execute([$current_user['id']]);
$stats['selling'] = $stmt->fetchColumn();

// Active orders
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE (buyer_id = ? OR seller_id = ?) AND status IN ('pending', 'in_progress')");
$stmt->execute([$current_user['id'], $current_user['id']]);
$stats['active'] = $stmt->fetchColumn();

// Completed orders
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE (buyer_id = ? OR seller_id = ?) AND status = 'completed'");
$stmt->execute([$current_user['id'], $current_user['id']]);
$stats['completed'] = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - FiverrClone</title>
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
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
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
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #1dbf73;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .filters-section {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .filters-container {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-btn {
            padding: 0.75rem 1.5rem;
            border: 2px solid #e1e5e9;
            background: white;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: #333;
            font-weight: 500;
        }

        .filter-btn:hover,
        .filter-btn.active {
            background: #1dbf73;
            color: white;
            border-color: #1dbf73;
        }

        .filter-select {
            padding: 0.75rem 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            background: white;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .filter-select:focus {
            outline: none;
            border-color: #1dbf73;
        }

        .orders-section {
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

        .orders-list {
            padding: 0;
        }

        .order-item {
            padding: 2rem;
            border-bottom: 1px solid #e1e5e9;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .order-item:hover {
            background: #f8f9fa;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .order-info {
            flex: 1;
        }

        .order-number {
            font-size: 1.1rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .order-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1dbf73;
            margin-bottom: 0.5rem;
        }

        .order-meta {
            display: flex;
            gap: 2rem;
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .order-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .order-status {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.5rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-in_progress {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-delivered {
            background: #d4edda;
            color: #155724;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .status-disputed {
            background: #ffeaa7;
            color: #6c5ce7;
        }

        .order-price {
            font-size: 1.3rem;
            font-weight: bold;
            color: #1dbf73;
        }

        .order-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .btn-primary {
            background: #1dbf73;
            color: white;
        }

        .btn-primary:hover {
            background: #00a652;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
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
            color: #333;
        }

        .empty-state p {
            margin-bottom: 2rem;
        }

        .role-indicator {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        .role-buyer {
            background: #e3f2fd;
            color: #1976d2;
        }

        .role-seller {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .delivery-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            margin-top: 1rem;
            border-left: 4px solid #1dbf73;
        }

        .delivery-date {
            font-weight: 600;
            color: #333;
        }

        .time-remaining {
            color: #666;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .filters-container {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                justify-content: center;
            }

            .order-header {
                flex-direction: column;
                gap: 1rem;
            }

            .order-status {
                align-items: flex-start;
            }

            .order-meta {
                flex-direction: column;
                gap: 0.5rem;
            }

            .order-actions {
                flex-wrap: wrap;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">My Orders</h1>
            <p class="page-subtitle">Manage your buying and selling activities</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-number"><?= $stats['buying'] ?></div>
                <div class="stat-label">Orders Placed</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-store"></i>
                </div>
                <div class="stat-number"><?= $stats['selling'] ?></div>
                <div class="stat-label">Orders Received</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?= $stats['active'] ?></div>
                <div class="stat-label">Active Orders</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?= $stats['completed'] ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>

        <div class="filters-section">
            <div class="filters-container">
                <div class="filter-group">
                    <a href="orders.php?filter=all" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
                        <i class="fas fa-list"></i> All Orders
                    </a>
                    <a href="orders.php?filter=buying" class="filter-btn <?= $filter === 'buying' ? 'active' : '' ?>">
                        <i class="fas fa-shopping-cart"></i> Buying
                    </a>
                    <a href="orders.php?filter=selling" class="filter-btn <?= $filter === 'selling' ? 'active' : '' ?>">
                        <i class="fas fa-store"></i> Selling
                    </a>
                </div>
                
                <div class="filter-group">
                    <select class="filter-select" onchange="filterByStatus(this.value)">
                        <option value="">All Statuses</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="delivered" <?= $status === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                        <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="orders-section">
            <div class="section-header">
                <i class="fas fa-receipt"></i> 
                <?php if ($filter === 'buying'): ?>
                    Orders You've Placed
                <?php elseif ($filter === 'selling'): ?>
                    Orders You've Received
                <?php else: ?>
                    All Your Orders
                <?php endif; ?>
            </div>
            
            <div class="orders-list">
                <?php if (empty($orders)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No orders found</h3>
                        <p>
                            <?php if ($filter === 'buying'): ?>
                                You haven't placed any orders yet. Start by browsing our services!
                            <?php elseif ($filter === 'selling'): ?>
                                You haven't received any orders yet. Create a gig to start selling!
                            <?php else: ?>
                                You don't have any orders yet. Start by browsing services or creating a gig!
                            <?php endif; ?>
                        </p>
                        <div>
                            <a href="browse.php" class="btn btn-primary">
                                <i class="fas fa-search"></i> Browse Services
                            </a>
                            <a href="create-gig.php" class="btn btn-outline">
                                <i class="fas fa-plus"></i> Create Gig
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <?php 
                        $is_buyer = $order['buyer_id'] == $current_user['id'];
                        $other_user = $is_buyer ? $order['seller_username'] : $order['buyer_username'];
                        $role = $is_buyer ? 'buyer' : 'seller';
                        
                        // Calculate delivery date
                        $delivery_date = date('M j, Y', strtotime($order['created_at'] . ' + ' . $order['delivery_time'] . ' days'));
                        $days_remaining = ceil((strtotime($delivery_date) - time()) / (60 * 60 * 24));
                        ?>
                        <div class="order-item" onclick="redirectTo('order-details.php?id=<?= $order['id'] ?>')">
                            <div class="order-header">
                                <div class="order-info">
                                    <div class="order-number">
                                        Order #<?= $order['order_number'] ?>
                                        <span class="role-indicator role-<?= $role ?>">
                                            <?= $is_buyer ? 'Buying' : 'Selling' ?>
                                        </span>
                                    </div>
                                    <div class="order-title"><?= htmlspecialchars($order['gig_title']) ?></div>
                                    <div class="order-meta">
                                        <span>
                                            <i class="fas fa-user"></i>
                                            <?= $is_buyer ? 'Seller' : 'Buyer' ?>: <?= htmlspecialchars($other_user) ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-calendar"></i>
                                            Ordered: <?= date('M j, Y', strtotime($order['created_at'])) ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-tag"></i>
                                            <?= ucfirst($order['package_type']) ?> Package
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="order-status">
                                    <div class="status-badge status-<?= $order['status'] ?>">
                                        <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                                    </div>
                                    <div class="order-price">
                                        <?= formatPrice($order['price']) ?>
                                    </div>
                                </div>
                            </div>

                            <?php if ($order['status'] === 'in_progress' || $order['status'] === 'pending'): ?>
                            <div class="delivery-info">
                                <div class="delivery-date">
                                    <i class="fas fa-truck"></i>
                                    Expected delivery: <?= $delivery_date ?>
                                </div>
                                <div class="time-remaining">
                                    <?php if ($days_remaining > 0): ?>
                                        <?= $days_remaining ?> days remaining
                                    <?php elseif ($days_remaining == 0): ?>
                                        Due today
                                    <?php else: ?>
                                        <?= abs($days_remaining) ?> days overdue
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="order-actions">
                                <a href="order-details.php?id=<?= $order['id'] ?>" class="btn btn-primary">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                                
                                <a href="messages.php?order_id=<?= $order['id'] ?>" class="btn btn-outline">
                                    <i class="fas fa-comments"></i> Message
                                </a>

                                <?php if ($order['status'] === 'pending' && $is_buyer): ?>
                                    <button class="btn btn-secondary" onclick="cancelOrder(<?= $order['id'] ?>)">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                <?php endif; ?>

                                <?php if ($order['status'] === 'pending' && !$is_buyer): ?>
                                    <button class="btn btn-primary" onclick="acceptOrder(<?= $order['id'] ?>)">
                                        <i class="fas fa-check"></i> Accept
                                    </button>
                                    <button class="btn btn-secondary" onclick="rejectOrder(<?= $order['id'] ?>)">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                <?php endif; ?>

                                <?php if ($order['status'] === 'delivered' && $is_buyer): ?>
                                    <button class="btn btn-primary" onclick="acceptDelivery(<?= $order['id'] ?>)">
                                        <i class="fas fa-check"></i> Accept Delivery
                                    </button>
                                    <button class="btn btn-secondary" onclick="requestRevision(<?= $order['id'] ?>)">
                                        <i class="fas fa-redo"></i> Request Revision
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function redirectTo(url) {
            window.location.href = url;
        }

        function filterByStatus(status) {
            const currentUrl = new URL(window.location.href);
            if (status) {
                currentUrl.searchParams.set('status', status);
            } else {
                currentUrl.searchParams.delete('status');
            }
            window.location.href = currentUrl.toString();
        }

        function acceptOrder(orderId) {
            if (confirm('Are you sure you want to accept this order?')) {
                updateOrderStatus(orderId, 'in_progress');
            }
        }

        function rejectOrder(orderId) {
            if (confirm('Are you sure you want to reject this order?')) {
                updateOrderStatus(orderId, 'cancelled');
            }
        }

        function cancelOrder(orderId) {
            if (confirm('Are you sure you want to cancel this order?')) {
                updateOrderStatus(orderId, 'cancelled');
            }
        }

        function acceptDelivery(orderId) {
            if (confirm('Are you satisfied with the delivery? This will mark the order as completed.')) {
                updateOrderStatus(orderId, 'completed');
            }
        }

        function requestRevision(orderId) {
            const reason = prompt('Please provide a reason for the revision request:');
            if (reason && reason.trim()) {
                // You would implement revision request functionality here
                alert('Revision request sent to the seller.');
            }
        }

        function updateOrderStatus(orderId, status) {
            // Create a form and submit it
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'update-order-status.php';
            
            const orderIdInput = document.createElement('input');
            orderIdInput.type = 'hidden';
            orderIdInput.name = 'order_id';
            orderIdInput.value = orderId;
            
            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'status';
            statusInput.value = status;
            
            form.appendChild(orderIdInput);
            form.appendChild(statusInput);
            document.body.appendChild(form);
            form.submit();
        }

        // Auto-refresh every 30 seconds for real-time updates
        setInterval(function() {
            // You can implement AJAX refresh here if needed
        }, 30000);
    </script>
</body>
</html>
