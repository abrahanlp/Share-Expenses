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

// 2. Categories Table & Updated Seed List
$db->exec("CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE
)");

$cat_count = $db->querySingle("SELECT COUNT(*) FROM categories");
if ($cat_count == 0) {
    $default_cats = ['Food', 'Teleco', 'Water', 'Light', 'Rent', 'Appliances', 'Travel', 'Delivery'];
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

$htaccess_file = __DIR__ . '/.htaccess';
$htpasswd_file = __DIR__ . '/.htpasswd';

// 1. Generate the .htpasswd file with a default user and password
if (!file_exists($htpasswd_file)) {
    $username = 'admin';
    $password = 'admin'; // IMPORTANT: Change this immediately after setup
    
    // Apache 2.4+ natively supports PHP's standard bcrypt hashes
    $hash = password_hash($password, PASSWORD_BCRYPT);
    file_put_contents($htpasswd_file, $username . ':' . $hash . "\n");
}

// 2. Generate the .htaccess file with the required security rules
if (!file_exists($htaccess_file)) {
    // AuthUserFile requires an absolute file path on the server to function
    $absolute_path = $htpasswd_file;
    
    $rules = "Options -Indexes\n";
    $rules .= "RedirectMatch 403 \.db$\n\n";
    
    // HTTP Basic Authentication rules
    $rules .= "AuthType Basic\n";
    $rules .= "AuthName \"Restricted Area\"\n";
    $rules .= "AuthUserFile \"{$absolute_path}\"\n";
    $rules .= "Require valid-user\n";
    
    file_put_contents($htaccess_file, $rules);
}
