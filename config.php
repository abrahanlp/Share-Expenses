<?php
// config.php
$db_file = __DIR__ . '/app_data.db';

$db = new SQLite3($db_file);

// 1. Expenses Table
$db->exec("CREATE TABLE IF NOT EXISTS expenses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    concept TEXT NOT NULL,
    category TEXT NOT NULL,
    date TEXT NOT NULL,
    amount REAL NOT NULL,
    paid_by TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// 2. Categories Table & Seed
$db->exec("CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE
)");

$cat_count = $db->querySingle("SELECT COUNT(*) FROM categories");
if ($cat_count == 0) {
    $default_cats = ['Food', 'Electricity', 'Water', 'Rent', 'Phone', 'Furniture', 'Travel', 'Other'];
    $stmt = $db->prepare("INSERT INTO categories (name) VALUES (:name)");
    foreach($default_cats as $cat) {
        $stmt->bindValue(':name', $cat);
        $stmt->execute();
    }
}

// 3. Users Table & Seed
$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL
)");

$user_count = $db->querySingle("SELECT COUNT(*) FROM users");
if ($user_count == 0) {
    $db->exec("INSERT INTO users (id, name) VALUES (1, 'User1'), (2, 'User2')");
}

if (!file_exists(__DIR__ . '/.htaccess')) {
    $rules = "Options -Indexes\nRedirectMatch 403 \.db$\n";
    file_put_contents(__DIR__ . '/.htaccess', $rules);
}
