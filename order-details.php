<?php
require_once 'db.php';
requireLogin();

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$current_user = getCurrentUser();

if (!$order_id) {
    header('Location: orders.php');
    exit();
}

// Get order details
$stmt = $pdo->prepare("
    SELECT o.*, g.title as gig_title, g.description as gig_description, g.featured_image,
           u_buyer.username as buyer_username, u_buyer.full_name as buyer_name, u_buyer.profile_image as buyer_image,
           u_seller.username as seller_username, u_seller.full_name as seller_name, u_seller.profile_image as seller_image,
           c.name as category_name
    FROM orders o
    JOIN gigs g ON o.gig_id = g.id
    JOIN users u_buyer ON o.buyer_id = u_buyer.id
    JOIN users u_seller ON o.seller_id = u_seller.id
    JOIN categories c ON g.category_id = c.id
    WHERE o.id = ? AND (o.buyer_id = ? OR o.seller_id = ?)
");
$stmt->execute([$order_id, $current_user['id'], $current_user['id']]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: orders.php');
    exit();
}

$is_buyer = $order['buyer_id'] == $current_user['id'];

// Get order messages
$stmt = $pdo->prepare("
    SELECT m.*, u.username, u.full_name 
    FROM messages m 
    JOIN users u ON m.sender_id = u.id 
    WHERE m.order_id = ? 
    ORDER BY m.created_at ASC
");
$stmt->execute([$order_id]);
$messages = $stmt->fetchAll();

// Get deliveries
$stmt = $pdo->prepare("SELECT * FROM order_deliveries WHERE order_id = ? ORDER BY delivered_at DESC");
$stmt->execute([$order_id]);
$deliveries = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - FiverrClone</title>
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
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .breadcrumb {
            background: white;
            padding: 1rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
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

        .order-header {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .order-title {
            font-size: 2rem;
            color: #333;
            margin-bottom: 1rem;
        }

        .order-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #666;
        }

        .meta-item i {
            color: #1dbf73;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-in_progress { background: #d1ecf1; color: #0c5460; }
        .status-delivered { background: #d4edda; color: #155724; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
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

        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .card-content {
            padding: 1.5rem;
        }

        .gig-info {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .gig-image {
            width: 100px;
            height: 80px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.9rem;
        }

        .gig-details h3 {
            color: #333;
            margin-bottom: 0.5rem;
        }

        .gig-details p {
            color: #666;
            font-size: 0.9rem;
        }

        .user-card {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #1dbf73;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .user-info h4 {
            color: #333;
            margin-bottom: 0.25rem;
        }

        .user-info p {
            color: #666;
            font-size: 0.9rem;
        }

        .price-breakdown {
            border-top: 1px solid #e1e5e9;
            padding-top: 1rem;
        }

        .price-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .price-total {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            font-size: 1.2rem;
            color: #1dbf73;
            border-top: 2px solid #e1e5e9;
            padding-top: 1rem;
            margin-top: 1rem;
        }

        .messages-section {
            max-height: 400px;
            overflow-y: auto;
        }

        .message {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 10px;
            max-width: 80%;
        }

        .message.sent {
            background: #1dbf73;
            color: white;
            margin-left: auto;
        }

        .message.received {
            background: #f8f9fa;
            color: #333;
        }

        .message-header {
            font-size: 0.8rem;
            opacity: 0.8;
            margin-bottom: 0.5rem;
        }

        .message-form {
            padding: 1rem;
            border-top: 1px solid #e1e5e9;
        }

        .message-input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            resize: vertical;
            min-height: 80px;
        }

        .message-input:focus {
            outline: none;
            border-color: #1dbf73;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            font-weight: 500;
            margin: 0.25rem;
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

        .btn-outline {
            border: 2px solid #1dbf73;
            color: #1dbf73;
            background: transparent;
        }

        .btn-outline:hover {
            background: #1dbf73;
            color: white;
        }

        .delivery-item {
            padding: 1rem;
            border: 1px solid #e1e5e9;
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .delivery-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .delivery-date {
            color: #666;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .order-meta {
                grid-template-columns: 1fr;
            }

            .gig-info {
                flex-direction: column;
            }

            .message {
                max-width: 95%;
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
        <div class="breadcrumb">
            <ul class="breadcrumb-list">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-separator"><i class="fas fa-chevron-right"></i></li>
                <li class="breadcrumb-item"><a href="orders.php">Orders</a></li>
                <li class="breadcrumb-separator"><i class="fas fa-chevron-right"></i></li>
                <li class="breadcrumb-item">Order #<?= $order['order_number'] ?></li>
            </ul>
        </div>

        <div class="order-header">
            <h1 class="order-title">Order #<?= $order['order_number'] ?></h1>
            
            <div class="order-meta">
                <div class="meta-item">
                    <i class="fas fa-calendar"></i>
                    <span>Ordered: <?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-tag"></i>
                    <span>Package: <?= ucfirst($order['package_type']) ?></span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-truck"></i>
                    <span>Delivery: <?= $order['delivery_time'] ?> days</span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-info-circle"></i>
                    <span class="status-badge status-<?= $order['status'] ?>">
                        <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="content-grid">
            <div class="main-content">
                <!-- Gig Information -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-briefcase"></i> Service Details
                    </div>
                    <div class="card-content">
                        <div class="gig-info">
                            <div class="gig-image">
                                <i class="fas fa-image"></i>
                            </div>
                            <div class="gig-details">
                                <h3><?= htmlspecialchars($order['gig_title']) ?></h3>
                                <p><?= htmlspecialchars(substr($order['gig_description'], 0, 150)) ?>...</p>
                                <p><strong>Category:</strong> <?= htmlspecialchars($order['category_name']) ?></p>
                            </div>
                        </div>
                        
                        <?php if (!empty($order['requirements'])): ?>
                        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e1e5e9;">
                            <h4>Requirements:</h4>
                            <p><?= nl2br(htmlspecialchars($order['requirements'])) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Messages -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-comments"></i> Messages
                    </div>
                    <div class="messages-section">
                        <?php if (empty($messages)): ?>
                            <div class="card-content">
                                <p style="text-align: center; color: #666;">No messages yet. Start the conversation!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($messages as $message): ?>
                                <div class="message <?= $message['sender_id'] == $current_user['id'] ? 'sent' : 'received' ?>">
                                    <div class="message-header">
                                        <?= htmlspecialchars($message['full_name']) ?> • <?= timeAgo($message['created_at']) ?>
                                    </div>
                                    <div><?= nl2br(htmlspecialchars($message['message'])) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <form class="message-form" method="POST" action="send-message.php">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <textarea name="message" class="message-input" placeholder="Type your message..." required></textarea>
                        <div style="margin-top: 1rem;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Send Message
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Deliveries -->
                <?php if (!empty($deliveries)): ?>
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-download"></i> Deliveries
                    </div>
                    <div class="card-content">
                        <?php foreach ($deliveries as $delivery): ?>
                            <div class="delivery-item">
                                <div class="delivery-header">
                                    <strong>Delivery</strong>
                                    <span class="delivery-date"><?= date('M j, Y g:i A', strtotime($delivery['delivered_at'])) ?></span>
                                </div>
                                <?php if ($delivery['message']): ?>
                                    <p><?= nl2br(htmlspecialchars($delivery['message'])) ?></p>
                                <?php endif; ?>
                                <?php if ($delivery['files']): ?>
                                    <div style="margin-top: 0.5rem;">
                                        <strong>Files:</strong>
                                        <!-- File download links would go here -->
                                        <p><i class="fas fa-file"></i> Delivery files attached</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="sidebar">
                <!-- Order Summary -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-receipt"></i> Order Summary
                    </div>
                    <div class="card-content">
                        <div class="price-breakdown">
                            <div class="price-item">
                                <span><?= ucfirst($order['package_type']) ?> Package</span>
                                <span><?= formatPrice($order['price']) ?></span>
                            </div>
                            <div class="price-total">
                                <span>Total</span>
                                <span><?= formatPrice($order['price']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- User Information -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-user"></i> <?= $is_buyer ? 'Seller' : 'Buyer' ?> Information
                    </div>
                    <div class="card-content">
                        <div class="user-card">
                            <div class="user-avatar">
                                <?= strtoupper(substr($is_buyer ? $order['seller_username'] : $order['buyer_username'], 0, 1)) ?>
                            </div>
                            <div class="user-info">
                                <h4><?= htmlspecialchars($is_buyer ? $order['seller_name'] : $order['buyer_name']) ?></h4>
                                <p>@<?= htmlspecialchars($is_buyer ? $order['seller_username'] : $order['buyer_username']) ?></p>
                            </div>
                        </div>
                        
                        <a href="profile.php?user=<?= $is_buyer ? $order['seller_username'] : $order['buyer_username'] ?>" class="btn btn-outline" style="width: 100%;">
                            <i class="fas fa-user"></i> View Profile
                        </a>
                    </div>
                </div>

                <!-- Order Actions -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-cogs"></i> Actions
                    </div>
                    <div class="card-content">
                        <?php if ($order['status'] === 'pending' && $is_buyer): ?>
                            <button class="btn btn-secondary" style="width: 100%;" onclick="cancelOrder(<?= $order['id'] ?>)">
                                <i class="fas fa-times"></i> Cancel Order
                            </button>
                        <?php endif; ?>

                        <?php if ($order['status'] === 'pending' && !$is_buyer): ?>
                            <button class="btn btn-primary" style="width: 100%; margin-bottom: 0.5rem;" onclick="acceptOrder(<?= $order['id'] ?>)">
                                <i class="fas fa-check"></i> Accept Order
                            </button>
                            <button class="btn btn-secondary" style="width: 100%;" onclick="rejectOrder(<?= $order['id'] ?>)">
                                <i class="fas fa-times"></i> Reject Order
                            </button>
                        <?php endif; ?>

                        <?php if ($order['status'] === 'in_progress' && !$is_buyer): ?>
                            <button class="btn btn-primary" style="width: 100%;" onclick="deliverOrder(<?= $order['id'] ?>)">
                                <i class="fas fa-upload"></i> Deliver Work
                            </button>
                        <?php endif; ?>

                        <?php if ($order['status'] === 'delivered' && $is_buyer): ?>
                            <button class="btn btn-primary" style="width: 100%; margin-bottom: 0.5rem;" onclick="acceptDelivery(<?= $order['id'] ?>)">
                                <i class="fas fa-check"></i> Accept Delivery
                            </button>
                            <button class="btn btn-outline" style="width: 100%;" onclick="requestRevision(<?= $order['id'] ?>)">
                                <i class="fas fa-redo"></i> Request Revision
                            </button>
                        <?php endif; ?>

                        <a href="orders.php" class="btn btn-outline" style="width: 100%; margin-top: 1rem;">
                            <i class="fas fa-arrow-left"></i> Back to Orders
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Same JavaScript functions as in orders.php
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

        function deliverOrder(orderId) {
            // In a real implementation, this would open a delivery form
            if (confirm('Mark this order as delivered?')) {
                updateOrderStatus(orderId, 'delivered');
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
                alert('Revision request sent to the seller.');
            }
        }

        function updateOrderStatus(orderId, status) {
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
    </script>
</body>
</html>
