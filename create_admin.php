<?php
require_once __DIR__ . '\inc\functions.php';

try {
    $db = getPDO();
    $db->beginTransaction();

    // Check if admin role exists, create if it doesn't
    $stmt = $db->query("SELECT id FROM roles WHERE name = 'admin'");
    $adminRole = $stmt->fetch();
    
    if (!$adminRole) {
        $stmt = $db->query("INSERT INTO roles (name) VALUES ('admin')");
        $adminRoleId = $db->lastInsertId();
    } else {
        $adminRoleId = $adminRole['id'];
    }

    // Create admin user if it doesn't exist
    $stmt = $db->prepare("SELECT id FROM users WHERE username = 'admin'");
    $stmt->execute();
    $adminUser = $stmt->fetch();

    if (!$adminUser) {
        // Create admin user
        $stmt = $db->prepare("
            INSERT INTO users (username, email, password_hash, full_name, is_active)
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            'admin',
            'admin@example.com',
            password_hash('admin123', PASSWORD_DEFAULT),
            'System Administrator'
        ]);
        $adminUserId = $db->lastInsertId();

        // Assign admin role
        $stmt = $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
        $stmt->execute([$adminUserId, $adminRoleId]);

        echo "Admin user created successfully!<br>";
        echo "Username: admin<br>";
        echo "Password: admin123<br>";
        echo "Please change these credentials after logging in.";
    } else {
        echo "Admin user already exists.";
    }

    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    echo "Error: " . $e->getMessage();
}