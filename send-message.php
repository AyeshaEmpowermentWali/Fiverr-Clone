<?php
require_once 'db.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = (int)$_POST['order_id'];
    $message = sanitize($_POST['message']);
    $current_user = getCurrentUser();
    
    // Validate input
    if (empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
        exit();
    }
    
    if ($order_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
        exit();
    }
    
    try {
        // Verify user has access to this order
        $stmt = $pdo->prepare("SELECT buyer_id, seller_id FROM orders WHERE id = ? AND (buyer_id = ? OR seller_id = ?)");
        $stmt->execute([$order_id, $current_user['id'], $current_user['id']]);
        $order = $stmt->fetch();
        
        if (!$order) {
            echo json_encode(['success' => false, 'error' => 'Order not found or access denied']);
            exit();
        }
        
        // Determine receiver
        $receiver_id = $order['buyer_id'] == $current_user['id'] ? $order['seller_id'] : $order['buyer_id'];
        
        // Insert message
        $stmt = $pdo->prepare("
            INSERT INTO messages (order_id, sender_id, receiver_id, message, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        if ($stmt->execute([$order_id, $current_user['id'], $receiver_id, $message])) {
            // Get the inserted message with user info
            $message_id = $pdo->lastInsertId();
            $stmt = $pdo->prepare("
                SELECT m.*, u.username, u.full_name 
                FROM messages m 
                JOIN users u ON m.sender_id = u.id 
                WHERE m.id = ?
            ");
            $stmt->execute([$message_id]);
            $new_message = $stmt->fetch();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Message sent successfully',
                'data' => [
                    'id' => $new_message['id'],
                    'message' => $new_message['message'],
                    'sender_id' => $new_message['sender_id'],
                    'username' => $new_message['username'],
                    'full_name' => $new_message['full_name'],
                    'created_at' => $new_message['created_at'],
                    'formatted_time' => date('M j, g:i A', strtotime($new_message['created_at']))
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to send message']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
exit();
?>
