<?php
require_once 'db.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = (int)$_POST['order_id'];
    $current_user = getCurrentUser();
    
    if ($order_id > 0) {
        try {
            // Verify user has access to this order
            $stmt = $pdo->prepare("SELECT buyer_id, seller_id FROM orders WHERE id = ? AND (buyer_id = ? OR seller_id = ?)");
            $stmt->execute([$order_id, $current_user['id'], $current_user['id']]);
            $order = $stmt->fetch();
            
            if ($order) {
                // Mark messages as read for current user
                $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE order_id = ? AND receiver_id = ?");
                $stmt->execute([$order_id, $current_user['id']]);
                
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Order not found']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>
