<?php
require_once 'db.php';
requireLogin();

$current_user = getCurrentUser();

// Get filter parameters
$filter = isset($_GET['filter']) ? sanitize($_GET['filter']) : 'all';
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

try {
    // Get conversations (grouped messages)
    $conversations_sql = "
        SELECT 
            m.order_id,
            m.sender_id,
            m.receiver_id,
            MAX(m.created_at) as last_message_time,
            COUNT(CASE WHEN m.is_read = 0 AND m.receiver_id = ? THEN 1 END) as unread_count,
            (SELECT message FROM messages WHERE order_id = m.order_id ORDER BY created_at DESC LIMIT 1) as last_message,
            COALESCE(o.order_number, 'Direct') as order_number,
            COALESCE(g.title, 'Direct Message') as gig_title,
            CASE 
                WHEN m.sender_id = ? THEN u_receiver.username 
                ELSE u_sender.username 
            END as other_username,
            CASE 
                WHEN m.sender_id = ? THEN COALESCE(u_receiver.full_name, u_receiver.username)
                ELSE COALESCE(u_sender.full_name, u_sender.username)
            END as other_name,
            CASE 
                WHEN m.sender_id = ? THEN u_receiver.profile_image 
                ELSE u_sender.profile_image 
            END as other_image
        FROM messages m
        LEFT JOIN orders o ON m.order_id = o.id
        LEFT JOIN gigs g ON o.gig_id = g.id
        LEFT JOIN users u_sender ON m.sender_id = u_sender.id
        LEFT JOIN users u_receiver ON m.receiver_id = u_receiver.id
        WHERE (m.sender_id = ? OR m.receiver_id = ?)
        GROUP BY m.order_id
        ORDER BY last_message_time DESC
    ";

    $stmt = $pdo->prepare($conversations_sql);
    $stmt->execute([
        $current_user['id'], $current_user['id'], $current_user['id'], 
        $current_user['id'], $current_user['id'], $current_user['id']
    ]);
    $conversations = $stmt->fetchAll();

    // Get selected conversation messages
    $selected_messages = [];
    $selected_conversation = null;

    if ($order_id > 0) {
        // Get messages for specific order
        $stmt = $pdo->prepare("
            SELECT m.*, u.username, COALESCE(u.full_name, u.username) as full_name, u.profile_image,
                   COALESCE(o.order_number, 'Direct') as order_number, 
                   COALESCE(g.title, 'Direct Message') as gig_title
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            LEFT JOIN orders o ON m.order_id = o.id
            LEFT JOIN gigs g ON o.gig_id = g.id
            WHERE m.order_id = ? AND (m.sender_id = ? OR m.receiver_id = ?)
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$order_id, $current_user['id'], $current_user['id']]);
        $selected_messages = $stmt->fetchAll();
        
        // Get conversation info
        if (!empty($selected_messages)) {
            $first_message = $selected_messages[0];
            
            // Find the other user's name
            $other_user_name = '';
            foreach ($selected_messages as $msg) {
                if ($msg['sender_id'] != $current_user['id']) {
                    $other_user_name = $msg['full_name'];
                    break;
                }
            }
            if (empty($other_user_name)) {
                // If all messages are from current user, get receiver info
                $stmt = $pdo->prepare("
                    SELECT COALESCE(u.full_name, u.username) as full_name 
                    FROM messages m 
                    JOIN users u ON m.receiver_id = u.id 
                    WHERE m.order_id = ? AND m.sender_id = ? 
                    LIMIT 1
                ");
                $stmt->execute([$order_id, $current_user['id']]);
                $receiver = $stmt->fetch();
                $other_user_name = $receiver ? $receiver['full_name'] : 'Unknown User';
            }
            
            $selected_conversation = [
                'order_id' => $order_id,
                'order_number' => $first_message['order_number'],
                'gig_title' => $first_message['gig_title'],
                'other_user' => $other_user_name
            ];
        }
        
        // Mark messages as read
        $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE order_id = ? AND receiver_id = ?");
        $stmt->execute([$order_id, $current_user['id']]);
    } elseif (!empty($conversations)) {
        // Select first conversation by default
        $first_conv = $conversations[0];
        $order_id = $first_conv['order_id'];
        
        if ($order_id) {
            $stmt = $pdo->prepare("
                SELECT m.*, u.username, COALESCE(u.full_name, u.username) as full_name, u.profile_image,
                       COALESCE(o.order_number, 'Direct') as order_number, 
                       COALESCE(g.title, 'Direct Message') as gig_title
                FROM messages m
                JOIN users u ON m.sender_id = u.id
                LEFT JOIN orders o ON m.order_id = o.id
                LEFT JOIN gigs g ON o.gig_id = g.id
                WHERE m.order_id = ? AND (m.sender_id = ? OR m.receiver_id = ?)
                ORDER BY m.created_at ASC
            ");
            $stmt->execute([$order_id, $current_user['id'], $current_user['id']]);
            $selected_messages = $stmt->fetchAll();
            
            if (!empty($selected_messages)) {
                $first_message = $selected_messages[0];
                $selected_conversation = [
                    'order_id' => $order_id,
                    'order_number' => $first_message['order_number'],
                    'gig_title' => $first_message['gig_title'],
                    'other_user' => $first_conv['other_name']
                ];
            }
        }
    }

    // Get total unread count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->execute([$current_user['id']]);
    $total_unread = $stmt->fetchColumn();

} catch (Exception $e) {
    $conversations = [];
    $selected_messages = [];
    $selected_conversation = null;
    $total_unread = 0;
    $error_message = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - FiverrClone</title>
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
            height: 100vh;
            overflow: hidden;
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

        .messages-container {
            display: flex;
            height: calc(100vh - 80px);
            background: white;
        }

        .conversations-sidebar {
            width: 350px;
            border-right: 1px solid #e1e5e9;
            display: flex;
            flex-direction: column;
            background: #f8f9fa;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e1e5e9;
            background: white;
        }

        .sidebar-title {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .unread-count {
            background: #1dbf73;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .search-box {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 25px;
            margin-top: 1rem;
            font-size: 0.9rem;
        }

        .search-box:focus {
            outline: none;
            border-color: #1dbf73;
        }

        .conversations-list {
            flex: 1;
            overflow-y: auto;
            padding: 0;
        }

        .conversation-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e1e5e9;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
            margin-bottom: 1px;
        }

        .conversation-item:hover {
            background: #f8f9fa;
        }

        .conversation-item.active {
            background: #1dbf73;
            color: white;
        }

        .conversation-item.active .conversation-meta,
        .conversation-item.active .conversation-preview {
            color: rgba(255,255,255,0.8);
        }

        .conversation-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }

        .conversation-user {
            font-weight: 600;
            color: #333;
        }

        .conversation-item.active .conversation-user {
            color: white;
        }

        .conversation-time {
            font-size: 0.8rem;
            color: #666;
        }

        .conversation-meta {
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 0.5rem;
        }

        .conversation-preview {
            font-size: 0.9rem;
            color: #666;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .unread-indicator {
            width: 8px;
            height: 8px;
            background: #1dbf73;
            border-radius: 50%;
            margin-left: 0.5rem;
        }

        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .chat-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e1e5e9;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .chat-title {
            font-size: 1.3rem;
            color: #333;
            margin-bottom: 0.25rem;
        }

        .chat-subtitle {
            color: #666;
            font-size: 0.9rem;
        }

        .messages-area {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            background: #f8f9fa;
        }

        .message {
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .message.sent {
            flex-direction: row-reverse;
        }

        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #1dbf73;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .message-content {
            max-width: 70%;
            background: white;
            padding: 1rem;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: relative;
        }

        .message.sent .message-content {
            background: #1dbf73;
            color: white;
        }

        .message-content::before {
            content: '';
            position: absolute;
            top: 15px;
            width: 0;
            height: 0;
            border: 8px solid transparent;
        }

        .message-content::before {
            left: -16px;
            border-right-color: white;
        }

        .message.sent .message-content::before {
            left: auto;
            right: -16px;
            border-left-color: #1dbf73;
            border-right-color: transparent;
        }

        .message-text {
            margin-bottom: 0.5rem;
            line-height: 1.5;
        }

        .message-time {
            font-size: 0.8rem;
            opacity: 0.7;
        }

        .message-form {
            padding: 1.5rem;
            border-top: 1px solid #e1e5e9;
            background: white;
        }

        .message-input-container {
            display: flex;
            gap: 1rem;
            align-items: flex-end;
        }

        .message-input {
            flex: 1;
            padding: 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 25px;
            resize: none;
            min-height: 50px;
            max-height: 120px;
            font-family: inherit;
            font-size: 0.9rem;
        }

        .message-input:focus {
            outline: none;
            border-color: #1dbf73;
        }

        .send-btn {
            background: #1dbf73;
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .send-btn:hover {
            background: #00a652;
            transform: scale(1.05);
        }

        .send-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #666;
            text-align: center;
        }

        .empty-state i {
            font-size: 4rem;
            color: #ccc;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .alert {
            padding: 0.75rem 1rem;
            margin: 1rem;
            border-radius: 5px;
            font-size: 0.9rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .no-data-message {
            text-align: center;
            padding: 2rem;
            color: #666;
        }

        .create-sample-btn {
            background: #1dbf73;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin-top: 1rem;
            transition: all 0.3s ease;
        }

        .create-sample-btn:hover {
            background: #00a652;
            color: white;
        }

        @media (max-width: 768px) {
            .messages-container {
                flex-direction: column;
            }

            .conversations-sidebar {
                width: 100%;
                height: 40%;
                border-right: none;
                border-bottom: 1px solid #e1e5e9;
            }

            .chat-area {
                height: 60%;
            }

            .message-content {
                max-width: 85%;
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

    <div class="messages-container">
        <!-- Conversations Sidebar -->
        <div class="conversations-sidebar">
            <div class="sidebar-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h2 class="sidebar-title">Messages</h2>
                    <?php if ($total_unread > 0): ?>
                        <span class="unread-count"><?= $total_unread ?> unread</span>
                    <?php endif; ?>
                </div>
                <input type="text" class="search-box" placeholder="Search conversations..." id="searchBox">
            </div>
            
            <div class="conversations-list" id="conversationsList">
                <?php if (empty($conversations)): ?>
                    <div class="no-data-message">
                        <i class="fas fa-comments" style="font-size: 3rem; color: #ccc; margin-bottom: 1rem;"></i>
                        <h3>No conversations yet</h3>
                        <p>You need orders to start messaging.</p>
                        <a href="create-sample-data.php" class="create-sample-btn">
                            <i class="fas fa-plus"></i> Create Sample Data
                        </a>
                        <br><br>
                        <a href="browse.php" class="create-sample-btn" style="background: #6c757d;">
                            <i class="fas fa-search"></i> Browse Gigs
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($conversations as $conv): ?>
                        <div class="conversation-item <?= $conv['order_id'] == $order_id ? 'active' : '' ?>" 
                             onclick="selectConversation(<?= $conv['order_id'] ?>)">
                            <div class="conversation-header">
                                <div class="conversation-user">
                                    <?= htmlspecialchars($conv['other_name']) ?>
                                    <?php if ($conv['unread_count'] > 0): ?>
                                        <span class="unread-indicator"></span>
                                    <?php endif; ?>
                                </div>
                                <div class="conversation-time">
                                    <?= timeAgo($conv['last_message_time']) ?>
                                </div>
                            </div>
                            <?php if ($conv['order_number'] && $conv['order_number'] != 'Direct'): ?>
                                <div class="conversation-meta">
                                    Order #<?= $conv['order_number'] ?> • <?= htmlspecialchars($conv['gig_title']) ?>
                                </div>
                            <?php endif; ?>
                            <div class="conversation-preview">
                                <?= htmlspecialchars(substr($conv['last_message'], 0, 100)) ?>
                                <?= strlen($conv['last_message']) > 100 ? '...' : '' ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Chat Area -->
        <div class="chat-area">
            <?php if ($selected_conversation): ?>
                <div class="chat-header">
                    <h3 class="chat-title"><?= htmlspecialchars($selected_conversation['other_user']) ?></h3>
                    <div class="chat-subtitle">
                        <?php if ($selected_conversation['order_number'] != 'Direct'): ?>
                            Order #<?= $selected_conversation['order_number'] ?> • 
                            <?= htmlspecialchars($selected_conversation['gig_title']) ?>
                        <?php else: ?>
                            Direct Message
                        <?php endif; ?>
                    </div>
                </div>

                <div class="messages-area" id="messagesArea">
                    <?php if (empty($selected_messages)): ?>
                        <div class="empty-state">
                            <i class="fas fa-comment-dots"></i>
                            <h3>Start the conversation</h3>
                            <p>Send your first message below</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($selected_messages as $message): ?>
                            <div class="message <?= $message['sender_id'] == $current_user['id'] ? 'sent' : 'received' ?>">
                                <div class="message-avatar">
                                    <?= strtoupper(substr($message['username'], 0, 1)) ?>
                                </div>
                                <div class="message-content">
                                    <div class="message-text">
                                        <?= nl2br(htmlspecialchars($message['message'])) ?>
                                    </div>
                                    <div class="message-time">
                                        <?= date('M j, g:i A', strtotime($message['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div id="messageAlert"></div>

                <form class="message-form" id="messageForm">
                    <input type="hidden" name="order_id" value="<?= $order_id ?>">
                    <div class="message-input-container">
                        <textarea name="message" class="message-input" placeholder="Type your message..." 
                                  required id="messageInput" rows="1"></textarea>
                        <button type="submit" class="send-btn" id="sendBtn">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="empty-state">
                    <?php if (empty($conversations)): ?>
                        <i class="fas fa-comments"></i>
                        <h3>No Messages Yet</h3>
                        <p>You need to have orders to start messaging.</p>
                        <a href="create-sample-data.php" class="create-sample-btn">
                            <i class="fas fa-plus"></i> Create Sample Data
                        </a>
                        <br><br>
                        <a href="browse.php" class="create-sample-btn" style="background: #6c757d;">
                            <i class="fas fa-search"></i> Browse Gigs
                        </a>
                    <?php else: ?>
                        <i class="fas fa-comments"></i>
                        <h3>Select a conversation</h3>
                        <p>Choose a conversation from the sidebar to start messaging</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function selectConversation(orderId) {
            window.location.href = 'messages.php?order_id=' + orderId;
        }

        function showAlert(message, type = 'success') {
            const alertDiv = document.getElementById('messageAlert');
            alertDiv.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
            setTimeout(() => {
                alertDiv.innerHTML = '';
            }, 3000);
        }

        function addMessageToChat(messageData, currentUserId) {
            const messagesArea = document.getElementById('messagesArea');
            const isSent = messageData.sender_id == currentUserId;
            
            // Remove empty state if exists
            const emptyState = messagesArea.querySelector('.empty-state');
            if (emptyState) {
                emptyState.remove();
            }
            
            const messageHtml = `
                <div class="message ${isSent ? 'sent' : 'received'}">
                    <div class="message-avatar">
                        ${messageData.username.charAt(0).toUpperCase()}
                    </div>
                    <div class="message-content">
                        <div class="message-text">
                            ${messageData.message.replace(/\n/g, '<br>')}
                        </div>
                        <div class="message-time">
                            ${messageData.formatted_time}
                        </div>
                    </div>
                </div>
            `;
            
            messagesArea.insertAdjacentHTML('beforeend', messageHtml);
            messagesArea.scrollTop = messagesArea.scrollHeight;
        }

        // Auto-resize textarea
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });

            // Send message on Ctrl+Enter
            messageInput.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'Enter') {
                    e.preventDefault();
                    document.getElementById('messageForm').dispatchEvent(new Event('submit'));
                }
            });
        }

        // Auto-scroll to bottom of messages
        const messagesArea = document.getElementById('messagesArea');
        if (messagesArea) {
            messagesArea.scrollTop = messagesArea.scrollHeight;
        }

        // Search functionality
        const searchBox = document.getElementById('searchBox');
        if (searchBox) {
            searchBox.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const conversations = document.querySelectorAll('.conversation-item');
                
                conversations.forEach(conv => {
                    const text = conv.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        conv.style.display = 'block';
                    } else {
                        conv.style.display = 'none';
                    }
                });
            });
        }

        // Handle form submission with AJAX
        const messageForm = document.getElementById('messageForm');
        if (messageForm) {
            messageForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const sendBtn = document.getElementById('sendBtn');
                const messageInput = document.getElementById('messageInput');
                const messageText = messageInput.value.trim();
                
                if (!messageText) {
                    showAlert('Please enter a message', 'error');
                    return;
                }
                
                sendBtn.disabled = true;
                sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                
                fetch('send-message.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        addMessageToChat(data.data, <?= $current_user['id'] ?>);
                        messageInput.value = '';
                        messageInput.style.height = 'auto';
                        showAlert('Message sent successfully');
                    } else {
                        showAlert(data.error || 'Failed to send message', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Network error. Please try again.', 'error');
                })
                .finally(() => {
                    sendBtn.disabled = false;
                    sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
                });
            });
        }

        // Mark messages as read when page loads
        if (<?= $order_id ?> > 0) {
            fetch('mark-messages-read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'order_id=<?= $order_id ?>'
            });
        }
    </script>
</body>
</html>
