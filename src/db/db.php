<?php


$pdo = null;

try {

    $db_path = __DIR__ . '/../../db_sqlite/bilet.sqlite';

    // Create a new PDO instance (PHP Data Objects)
    $pdo = new PDO('sqlite:' . $db_path);

    // Set PDO attributes for error handling and fetch mode
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Enable foreign key constraints for SQLite
    $pdo->exec('PRAGMA foreign_keys = ON;');

} catch (PDOException $e) {
    // If the connection fails, stop the script and show an error.
    // In a real application, you'd log this error instead of showing it to the user.
    die("Database connection failed: " . $e->getMessage());
}


