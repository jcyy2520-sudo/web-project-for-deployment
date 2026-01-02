<?php

$pdo = new PDO('mysql:host=127.0.0.1', 'root', '');

// Query all databases to find users
$databases = ['web2', 'jxian', 'xian', 'web', 'laravel_backend'];

foreach ($databases as $db) {
    try {
        $result = $pdo->query("SELECT COUNT(*) as count FROM {$db}.users");
        if ($result) {
            $row = $result->fetch(PDO::FETCH_ASSOC);
            echo "{$db}: " . $row['count'] . " users\n";
            
            if ($row['count'] > 0) {
                $result = $pdo->query("SELECT id, email FROM {$db}.users LIMIT 3");
                while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                    echo "  - {$row['email']}\n";
                }
            }
        }
    } catch (Exception $e) {
        echo "{$db}: Error\n";
    }
}
