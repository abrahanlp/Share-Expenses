<?php
// config.php
$db_file = __DIR__ . '/app_data.db';
$backup_dir = __DIR__ . '/backups';

// Create the secure backups folder if it doesn't exist
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
    // Block web access to the backups folder for security
    file_put_contents($backup_dir . '/.htaccess', "Deny from all");
}

// Check if a backup is needed (older than 7 days)
$needs_backup = true;
$backup_files = glob($backup_dir . '/*.db');

if (!empty($backup_files)) {
    $latest_backup_time = 0;
    foreach ($backup_files as $file) {
        $mtime = filemtime($file);
        if ($mtime > $latest_backup_time) {
            $latest_backup_time = $mtime;
        }
    }
    
    // 604800 seconds = 7 days
    if ((time() - $latest_backup_time) < 604800) {
        $needs_backup = false;
    }
}

// Perform the backup silently
if ($needs_backup && file_exists($db_file)) {
    $backup_name = $backup_dir . '/backup_' . date('Y-m-d_H-i') . '.db';
    copy($db_file, $backup_name);
    
    // Auto-Cleanup: Keep only the 8 most recent backups
    $all_backups = glob($backup_dir . '/*.db');
    if (count($all_backups) > 8) {
        // Sort files by oldest first
        usort($all_backups, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        // Delete the oldest files until only 8 remain
        $files_to_delete = count($all_backups) - 8;
        for ($i = 0; $i < $files_to_delete; $i++) {
            unlink($all_backups[$i]);
        }
    }
}

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
