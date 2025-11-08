<?php
// Simple PDO helper for the saving_ant app
function getPDO(): PDO
{
    // Assumptions: XAMPP default MySQL credentials. Adjust if different.
    $host = '127.0.0.1';
    $db   = 'saving_ant';
    $user = 'root';
    $pass = '';
    $charset = 'utf8mb4';
    
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        // In production, you might want to log this instead
        throw new PDOException($e->getMessage(), (int)$e->getCode());
    }
}