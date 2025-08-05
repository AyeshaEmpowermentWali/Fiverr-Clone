<?php
require_once 'db.php';

// Create sample users
$sample_users = [
    ['john_doe', 'john@example.com', 'John Doe', 'password123'],
    ['jane_smith', 'jane@example.com', 'Jane Smith', 'password123'],
    ['mike_wilson', 'mike@example.com', 'Mike Wilson', 'password123'],
    ['sarah_jones', 'sarah@example.com', 'Sarah Jones', 'password123'],
    ['david_brown', 'david@example.com', 'David Brown', 'password123']
];

echo "<h2>Creating Sample Data...</h2>";

// Insert sample users
foreach ($sample_users as $user) {
    $hashed_password = password_hash($user[3], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO users (username, email, full_name, password, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$user[0], $user[1], $user[2], $hashed_password]);
}
echo "✅ Sample users created<br>";

// Get user IDs
$stmt = $pdo->query("SELECT id, username FROM users ORDER BY id LIMIT 5");
$users = $stmt->fetchAll();

// Create sample categories
$categories = [
    ['Graphics & Design', 'Creative design services'],
    ['Digital Marketing', 'Marketing and promotion services'],
    ['Writing & Translation', 'Content and language services'],
    ['Video & Animation', 'Video production services'],
    ['Programming & Tech', 'Development and technical services']
];

foreach ($categories as $cat) {
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO categories (name, description, created_at) 
        VALUES (?, ?, NOW())
    ");
    $stmt->execute([$cat[0], $cat[1]]);
}
echo "✅ Sample categories created<br>";

// Get category IDs
$stmt = $pdo->query("SELECT id, name FROM categories");
$categories_data = $stmt->fetchAll();

// Create sample gigs
$sample_gigs = [
    ['I will design a professional logo for your business', 'Professional logo design with unlimited revisions', 50, 1, 1],
    ['I will create engaging social media content', 'Custom social media posts and graphics', 25, 2, 2],
    ['I will write SEO optimized blog posts', 'High-quality blog content that ranks', 30, 3, 3],
    ['I will create a promotional video for your product', 'Professional video editing and animation', 100, 4, 4],
    ['I will develop a responsive website', 'Modern website development with clean code', 200, 5, 5]
];

foreach ($sample_gigs as $gig) {
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO gigs (title, description, price, user_id, category_id, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute($gig);
}
echo "✅ Sample gigs created<br>";

// Get gig IDs
$stmt = $pdo->query("SELECT id, title, user_id FROM gigs");
$gigs = $stmt->fetchAll();

// Create sample orders
$sample_orders = [];
for ($i = 0; $i < 5; $i++) {
    $buyer_id = $users[$i]['id'];
    $gig = $gigs[($i + 1) % count($gigs)]; // Different gig for each order
    $seller_id = $gig['user_id'];
    
    if ($buyer_id != $seller_id) { // Don't let users buy from themselves
        $order_number = 'ORD' . str_pad($i + 1, 6, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO orders (order_number, buyer_id, seller_id, gig_id, amount, status, created_at) 
            VALUES (?, ?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->execute([$order_number, $buyer_id, $seller_id, $gig['id'], rand(25, 200)]);
        $sample_orders[] = $pdo->lastInsertId();
    }
}
echo "✅ Sample orders created<br>";

// Create sample messages
$sample_messages = [
    'Hello! I\'m excited to work on your project. When can we start?',
    'Hi there! I have some questions about the requirements.',
    'Great! I\'ll get started right away. Expect the first draft soon.',
    'Thank you for choosing my service. I\'ll deliver high-quality work.',
    'I\'ve completed the initial version. Please review and let me know your thoughts.',
    'The project looks amazing! Can you make a small revision?',
    'Perfect! I\'m very happy with the final result. Thank you!',
    'You\'re welcome! It was a pleasure working with you.'
];

// Get created orders
$stmt = $pdo->query("SELECT id, buyer_id, seller_id FROM orders WHERE id > 0");
$orders = $stmt->fetchAll();

foreach ($orders as $order) {
    // Create 3-4 messages per order
    $message_count = rand(3, 4);
    for ($j = 0; $j < $message_count; $j++) {
        $sender_id = $j % 2 == 0 ? $order['buyer_id'] : $order['seller_id'];
        $receiver_id = $j % 2 == 0 ? $order['seller_id'] : $order['buyer_id'];
        $message = $sample_messages[array_rand($sample_messages)];
        
        $stmt = $pdo->prepare("
            INSERT INTO messages (order_id, sender_id, receiver_id, message, created_at) 
            VALUES (?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? HOUR))
        ");
        $stmt->execute([$order['id'], $sender_id, $receiver_id, $message, rand(1, 48)]);
    }
}
echo "✅ Sample messages created<br>";

echo "<h3>✅ All sample data created successfully!</h3>";
echo "<p><a href='messages.php'>Go to Messages</a> | <a href='orders.php'>Go to Orders</a> | <a href='browse.php'>Browse Gigs</a></p>";
?>
