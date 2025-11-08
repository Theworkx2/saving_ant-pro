<?php
require_once __DIR__ . '\inc\functions.php';

// Get database connection
$db = getPDO();

// Check if admin role exists
$stmt = $db->query("SELECT * FROM roles WHERE name = 'admin'");
$adminRole = $stmt->fetch();

echo "<pre>Admin role: ";
print_r($adminRole);
echo "\n\n";

// Check users with admin role
$stmt = $db->query("
    SELECT u.*, GROUP_CONCAT(r.name) as roles
    FROM users u
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.id
    WHERE r.name = 'admin'
    GROUP BY u.id
");
$adminUsers = $stmt->fetchAll();

echo "Admin users: ";
print_r($adminUsers);
echo "</pre>";