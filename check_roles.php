<?php
require_once __DIR__ . '\inc\functions.php';

try {
    $db = getPDO();
    
    // Check roles table
    $stmt = $db->query("SELECT * FROM roles");
    echo "<h3>Current Roles:</h3>";
    echo "<pre>";
    print_r($stmt->fetchAll());
    echo "</pre>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}