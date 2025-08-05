<?php
require_once 'db.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = (int)$_POST['order_id'];
    $new_status = sanitize($_POST['status']);
    $current_user = getCurrentUser();
    
    // Verify user has permission to update this order
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND (buyer_id = ? OR seller_id = ?)");
    $stmt->execute([$order_id, $current_user['id'], $current_user['id']]);
    $order = $stmt->fetch();
    
    if (!$order) {
        header('Location: orders.php?error=Order not found');
        exit();
    }
    
    // Validate status transitions
    $valid_transitions = [
        'pending' => ['in_progress', 'cancelled'],
        'in_progress' => ['delivered', 'cancelled'],
        'delivered' => ['completed', 'in_progress'], // in_progress for revisions
        'completed' => [],
        'cancelled' => []
    ];
    
    if (!in_array($new_status, $valid_transitions[$order['status']])) {
        header('Location: orders.php?error=Invalid status transition');
        exit();
    }
    
    // Check permissions for specific actions
    $is_buyer = $order['buyer_id'] == $current_user['id'];
    $is_seller = $order['seller_id'] == $current_user['id'];
    
    $allowed = false;
    
    switch ($new_status) {
        case 'in_progress':
            $allowed = $is_seller; // Only seller can start work
            break;
        case 'delivered':
            $allowed = $is_seller; // Only seller can deliver
            break;
        case 'completed':
            $allowed = $is_buyer; // Only buyer can mark as completed
            break;
        case 'cancelled':
            $allowed = true; // Both can cancel (with different conditions)
            break;
    }
    
    if (!$allowed) {
        header('Location: orders.php?error=Permission denied');
        exit();
    }
    
    // Update order status
    $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
    
    if ($stmt->execute([$new_status, $order_id])) {
        // Add notification
        $notification_title = '';
        $notification_message = '';
        $recipient_id = $is_buyer ? $order['seller_id'] : $order['buyer_id'];
        
        switch ($new_status) {
            case 'in_progress':
                $notification_title = 'Order Started';
                $notification_message = 'The seller has started working on your order #' . $order['order_number'];
                break;
            case 'delivered':
                $notification_title = 'Order Delivered';
                $notification_message = 'Your order #' . $order['order_number'] . ' has been delivered';
                break;
            case 'completed':
                $notification_title = 'Order Completed';
                $notification_message = 'Order #' . $order['order_number'] . ' has been marked as completed';
                break;
            case 'cancelled':
                $notification_title = 'Order Cancelled';
                $notification_message = 'Order #' . $order['order_number'] . ' has been cancelled';
                break;
        }
        
        if ($notification_title) {
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type) 
                VALUES (?, ?, ?, 'order')
            ");
            $stmt->execute([$recipient_id, $notification_title, $notification_message]);
        }
        
        header('Location: orders.php?success=Order status updated successfully');
    } else {
        header('Location: orders.php?error=Failed to update order status');
    }
} else {
    header('Location: orders.php');
}
exit();
?>
