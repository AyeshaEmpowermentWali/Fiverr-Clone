<?php
require_once 'db.php';

// Get search parameters
$query = isset($_GET['q']) ? sanitize($_GET['q']) : '';

if (empty($query)) {
    header('Location: browse.php');
    exit();
}

// Redirect to browse page with search query
header('Location: browse.php?q=' . urlencode($query));
exit();
?>
