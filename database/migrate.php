<?php

/**
 * Database Migration Runner
 * Executes all SQL migration files in order
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

try {
    // Connect to MySQL
    $host = $_ENV['DB_HOST'];
    $database = $_ENV['DB_DATABASE'];
    $username = $_ENV['DB_USERNAME'];
    $password = $_ENV['DB_PASSWORD'];

    $pdo = new PDO(
        "mysql:host={$host};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    // Create database if not exists
    echo "Creating database if not exists: {$database}\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$database}`");

    // Create migrations tracking table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS migrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_migration (migration)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Get executed migrations
    $stmt = $pdo->query("SELECT migration FROM migrations");
    $executedMigrations = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get migration files
    $migrationFiles = glob(__DIR__ . '/migrations/*.sql');
    sort($migrationFiles);

    if (empty($migrationFiles)) {
        echo "No migration files found.\n";
        exit(0);
    }

    echo "\nExecuting migrations...\n";
    echo str_repeat('-', 50) . "\n";

    $executedCount = 0;
    foreach ($migrationFiles as $file) {
        $migrationName = basename($file);

        if (in_array($migrationName, $executedMigrations)) {
            echo "⏭  Skipping (already executed): {$migrationName}\n";
            continue;
        }

        echo "▶  Executing: {$migrationName}\n";

        $sql = file_get_contents($file);

        try {
            $pdo->exec($sql);

            // Record migration
            $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
            $stmt->execute([$migrationName]);

            echo "✓  Success: {$migrationName}\n";
            $executedCount++;
        } catch (PDOException $e) {
            echo "✗  Failed: {$migrationName}\n";
            echo "   Error: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    echo str_repeat('-', 50) . "\n";
    echo "Migration complete! Executed {$executedCount} migration(s).\n";
} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
