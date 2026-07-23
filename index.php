<?php
header("X-Robots-Tag: noindex, nofollow", true);

// index.php
require_once __DIR__ . '/config.php';

$message = '';

// ==========================================
// DATE FORMAT SANITIZATION & GLOBAL DATES
// ==========================================
// Automatically fix any malformed dates in the database (e.g. DD/MM/YYYY) so the filter logic works.
$res_dates = $db->query('SELECT id, "date" FROM expenses');
while ($row = $res_dates->fetchArray(SQLITE3_ASSOC)) {
    $d = trim($row['date']);
    // If date is not purely YYYY-MM-DD format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $d, $matches)) {
            $new_d = $matches[1]; // Extract just the date if it has time
        } else {
            $time = strtotime(str_replace('/', '-', $d));
            $new_d = $time ? date('Y-m-d', $time) : date('Y-m-d');
        }
        $stmt_fix = $db->prepare('UPDATE expenses SET "date" = :new_d WHERE id = :id');
        $stmt_fix->bindValue(':new_d', $new_d, SQLITE3_TEXT);
        $stmt_fix->bindValue(':id', $row['id'], SQLITE3_INTEGER);
        $stmt_fix->execute();
    }
}

$page = $_GET['page'] ?? 'home';

$raw_start = $_REQUEST['start_date'] ?? '';
if ($raw_start === 'all') {
    $min_date = $db->querySingle('SELECT MIN("date") FROM expenses');
    $start_date = $min_date ? $min_date : '2000-01-01';
    $max_date = $db->querySingle('SELECT MAX("date") FROM expenses');
    $end_date = $max_date ? max($max_date, date('Y-m-d')) : date('Y-m-d');
    $sd_param = '&start_date=all';
    $ed_param = '&end_date=' . urlencode($end_date);
} else {
    $start_date = !empty($raw_start) ? $raw_start : date('Y-m-d', strtotime('-1 year'));
    $end_date = !empty($_REQUEST['end_date']) ? $_REQUEST['end_date'] : date('Y-m-d');
    $sd_param = '&start_date=' . urlencode($start_date);
    $ed_param = '&end_date=' . urlencode($end_date);
}

// Ensure the end date includes the whole day up to midnight
$end_date_query = $end_date . ' 23:59:59';

// Fetch dynamic users
$users = [];
$users_data = []; 
$res_users = $db->query("SELECT * FROM users ORDER BY id ASC LIMIT 2");
while ($row = $res_users->fetchArray(SQLITE3_ASSOC)) {
    $users[] = $row['name'];
    $users_data[] = $row;
}
$u1 = $users[0] ?? 'User1';
$u2 = $users[1] ?? 'User2';

// Fetch dynamic categories
$categories = [];
$res_cats = $db->query("SELECT * FROM categories ORDER BY CASE name 
    WHEN 'Food' THEN 1 WHEN 'Delivery' THEN 2 WHEN 'Travel' THEN 3 
    WHEN 'Rent' THEN 4 WHEN 'Light' THEN 5 WHEN 'Teleco' THEN 6 
    WHEN 'Water' THEN 7 WHEN 'Appliances' THEN 8 ELSE 9 END, name ASC");
while ($row = $res_cats->fetchArray(SQLITE3_ASSOC)) {
    $categories[] = $row;
}

if (isset($_GET['status'])) {
    if ($_GET['status'] === 'imported') {
        $message = "<div class='alert success'>CSV Data imported successfully!</div>";
    } elseif ($_GET['status'] === 'credentials_updated') {
        $message = "<div class='alert success'>Login credentials updated! You are using your new access details.</div>";
    }
}

// ==========================================
// CSV EXPORT ROUTE
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=expenses_export_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Concept', 'Date', 'Paid_by', 'Category', 'Amount']);
    
    $res = $db->query('SELECT concept, "date", paid_by, category, amount FROM expenses ORDER BY "date" DESC, id DESC');
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $csv_date = date('d-m-Y', strtotime($row['date']));
        fputcsv($output, [$row['concept'], $csv_date, $row['paid_by'], $row['category'], $row['amount']]);
    }
    fclose($output);
    exit;
}

// ==========================================
// FORM PROCESSORS (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // UPDATE APP CREDENTIALS (.htpasswd)
    if ($action === 'update_credentials') {
        $new_username = trim($_POST['new_username']);
        $new_password = $_POST['new_password'];

        if (!empty($new_username) && !empty($new_password)) {
            $htpasswd_file = __DIR__ . '/.htpasswd';
            
            // Hash the new password and write it to the file
            $hash = password_hash($new_password, PASSWORD_BCRYPT);
            file_put_contents($htpasswd_file, $new_username . ':' . $hash . "\n");
            
            // Redirecting will immediately trigger a 401 Unauthorized prompt in the browser 
            // because the old cached password is no longer valid. This is expected.
            header("Location: index.php?page=settings&status=credentials_updated");
            exit;
        } else {
            $message = "<div class='alert error'>Username and password cannot be empty.</div>";
        }
    }
    
    // ADD OR EDIT EXPENSE
    if ($action === 'save_expense') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $concept = trim($_POST['concept']);
        $category = $_POST['category'];
        $date = $_POST['date'];
        $amount = floatval($_POST['amount']);
        $paid_by = $_POST['paid_by'];

        if (!empty($concept) && $amount > 0 && in_array($paid_by, $users)) {
            if ($id > 0) {
                $stmt = $db->prepare('UPDATE expenses SET concept=:concept, category=:category, "date"=:date, amount=:amount, paid_by=:paid_by WHERE id=:id');
                $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            } else {
                $stmt = $db->prepare('INSERT INTO expenses (concept, category, "date", amount, paid_by) VALUES (:concept, :category, :date, :amount, :paid_by)');
            }
            $stmt->bindValue(':concept', htmlspecialchars($concept), SQLITE3_TEXT);
            $stmt->bindValue(':category', $category, SQLITE3_TEXT);
            $stmt->bindValue(':date', $date, SQLITE3_TEXT);
            $stmt->bindValue(':amount', $amount, SQLITE3_FLOAT);
            $stmt->bindValue(':paid_by', $paid_by, SQLITE3_TEXT);
            $stmt->execute();
            
            header("Location: index.php?page=home" . $sd_param . $ed_param);
            exit;
        } else {
            $message = "<div class='alert error'>Invalid data provided.</div>";
        }
    }

    // SAVE OR UPDATE CATEGORY
    if ($action === 'save_category') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $cat_name = trim($_POST['cat_name']);
        
        if (!empty($cat_name)) {
            $clean_name = htmlspecialchars($cat_name);
            if ($id > 0) {
                $old_name = $db->querySingle("SELECT name FROM categories WHERE id = $id");
                if ($old_name && $old_name !== $clean_name) {
                    $stmt_exp = $db->prepare("UPDATE expenses SET category = :new WHERE category = :old");
                    $stmt_exp->bindValue(':new', $clean_name, SQLITE3_TEXT);
                    $stmt_exp->bindValue(':old', $old_name, SQLITE3_TEXT);
                    $stmt_exp->execute();
                }
                
                $stmt = $db->prepare("UPDATE categories SET name = :name WHERE id = :id");
                $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            } else {
                $stmt = $db->prepare("INSERT OR IGNORE INTO categories (name) VALUES (:name)");
            }
            $stmt->bindValue(':name', $clean_name, SQLITE3_TEXT);
            $stmt->execute();
            header("Location: index.php?page=settings");
            exit;
        }
    }

    // UPDATE USER NAMES
    if ($action === 'update_users') {
        $new_u1 = trim($_POST['user1']);
        $new_u2 = trim($_POST['user2']);

        if (!empty($new_u1) && !empty($new_u2)) {
            $stmt = $db->prepare("UPDATE expenses SET paid_by = :new WHERE paid_by = :old");
            $stmt->bindValue(':new', $new_u1, SQLITE3_TEXT); $stmt->bindValue(':old', $u1, SQLITE3_TEXT); $stmt->execute();
            $stmt->bindValue(':new', $new_u2, SQLITE3_TEXT); $stmt->bindValue(':old', $u2, SQLITE3_TEXT); $stmt->execute();

            $stmt = $db->prepare("UPDATE users SET name = :name WHERE id = :id");
            $stmt->bindValue(':name', htmlspecialchars($new_u1), SQLITE3_TEXT); $stmt->bindValue(':id', $users_data[0]['id'], SQLITE3_INTEGER); $stmt->execute();
            $stmt->bindValue(':name', htmlspecialchars($new_u2), SQLITE3_TEXT); $stmt->bindValue(':id', $users_data[1]['id'], SQLITE3_INTEGER); $stmt->execute();

            header("Location: index.php?page=settings");
            exit;
        }
    }

    // CSV IMPORT PROCESSING
    if ($action === 'import_csv') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            if (($handle = fopen($_FILES['csv_file']['tmp_name'], "r")) !== FALSE) {
                fgetcsv($handle, 1000, ",");
                
                $stmt = $db->prepare('INSERT INTO expenses (concept, "date", paid_by, category, amount) VALUES (:concept, :date, :paid_by, :category, :amount)');
                
                $db->exec('BEGIN TRANSACTION');
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (count($data) >= 5) {
                        $concept = trim($data[0]);
                        $date_raw = trim($data[1]); 
                        $paid_by = trim($data[2]);
                        $category = trim($data[3]);
                        $amount = floatval($data[4]);

                        // Improved date detection for robust importing
                        $date_obj = DateTime::createFromFormat('Y-m-d', $date_raw);
                        if (!$date_obj) $date_obj = DateTime::createFromFormat('d-m-Y', $date_raw);
                        $db_date = $date_obj ? $date_obj->format('Y-m-d') : date('Y-m-d');

                        if (!empty($concept) && !empty($date_raw) && $amount > 0) {
                            if (!in_array($paid_by, $users)) { $paid_by = $u1; }
                            if (!empty($category)) {
                                $cat_stmt = $db->prepare("INSERT OR IGNORE INTO categories (name) VALUES (:name)");
                                $cat_stmt->bindValue(':name', htmlspecialchars($category), SQLITE3_TEXT);
                                $cat_stmt->execute();
                            }

                            $stmt->bindValue(':concept', htmlspecialchars($concept), SQLITE3_TEXT);
                            $stmt->bindValue(':date', $db_date, SQLITE3_TEXT);
                            $stmt->bindValue(':paid_by', $paid_by, SQLITE3_TEXT);
                            $stmt->bindValue(':category', htmlspecialchars($category), SQLITE3_TEXT);
                            $stmt->bindValue(':amount', $amount, SQLITE3_FLOAT);
                            $stmt->execute();
                        }
                    }
                }
                $db->exec('COMMIT');
                fclose($handle);
                header("Location: index.php?page=settings&status=imported");
                exit;
            }
        }
        $message = "<div class='alert error'>Failed parsing data file. Verify design structure format matches exactly.</div>";
    }
}

// ==========================================
// GET ACTIONS (Deletions & Edits)
// ==========================================
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $db->exec("DELETE FROM expenses WHERE id = $id");
    header("Location: index.php?page=home" . $sd_param . $ed_param);
    exit;
}

if (isset($_GET['delete_cat'])) {
    $id = intval($_GET['delete_cat']);
    $db->exec("DELETE FROM categories WHERE id = $id");
    header("Location: index.php?page=settings");
    exit;
}

$expense_to_edit = null;
if (isset($_GET['edit'])) {
    $expense_to_edit = $db->query("SELECT * FROM expenses WHERE id = " . intval($_GET['edit']))->fetchArray(SQLITE3_ASSOC);
}

$cat_to_edit = null;
if (isset($_GET['edit_cat'])) {
    $cat_to_edit = $db->query("SELECT * FROM categories WHERE id = " . intval($_GET['edit_cat']))->fetchArray(SQLITE3_ASSOC);
}

// ==========================================
// DATA COMPILATION FOR HOME & STATISTICS
// ==========================================
if ($page === 'home') {
    // 1. BALANCE CALCULATION (Independent of date filter)
    $res_all = $db->query("SELECT paid_by, SUM(amount) as total FROM expenses GROUP BY paid_by");
    $all_totals = array_fill_keys($users, 0);
    while ($row = $res_all->fetchArray(SQLITE3_ASSOC)) { 
        $all_totals[$row['paid_by']] = $row['total']; 
    }
    
    $t1 = $all_totals[$u1] ?? 0;
    $t2 = $all_totals[$u2] ?? 0;
    $diff = $t1 - $t2;
    
    if ($diff > 0) {
        $balance_text = "<strong>$u1</strong> +" . number_format($diff, 2) . "€";
    } elseif ($diff < 0) {
        $balance_text = "<strong>$u2</strong> +" . number_format(abs($diff), 2) . "€";
    } else {
        $balance_text = "0.00€";
    }

    // 2. FILTERED DATA FOR GRAPH & TABLE (Dependent of date filter)
    $stmt_cat = $db->prepare('SELECT category, SUM(amount) as total FROM expenses WHERE "date" >= :start_date AND "date" <= :end_date GROUP BY category ORDER BY total DESC');
    $stmt_cat->bindValue(':start_date', $start_date, SQLITE3_TEXT);
    $stmt_cat->bindValue(':end_date', $end_date_query, SQLITE3_TEXT);
    $res_cat = $stmt_cat->execute();
    
    $chart_categories = [];
    while ($row = $res_cat->fetchArray(SQLITE3_ASSOC)) { 
        $chart_categories[$row['category']] = round($row['total'], 2); 
    }

    $stmt_recent = $db->prepare('SELECT * FROM expenses WHERE "date" >= :start_date AND "date" <= :end_date ORDER BY "date" DESC, id DESC');
    $stmt_recent->bindValue(':start_date', $start_date, SQLITE3_TEXT);
    $stmt_recent->bindValue(':end_date', $end_date_query, SQLITE3_TEXT);
    $recent_expenses = $stmt_recent->execute();

} elseif ($page === 'statistics') {
    // Fetch available years descending
    $res_years = $db->query('SELECT DISTINCT strftime("%Y", "date") as year FROM expenses WHERE "date" IS NOT NULL ORDER BY year DESC');
    $years_list = [];
    while ($row = $res_years->fetchArray(SQLITE3_ASSOC)) {
        if (!empty($row['year'])) {
            $years_list[] = $row['year'];
        }
    }

    // Overall yearly totals for Chart 1 (Ascending)
    $res_yearly_totals = $db->query('SELECT strftime("%Y", "date") as year, SUM(amount) as total FROM expenses WHERE "date" IS NOT NULL GROUP BY year ORDER BY year ASC');
    $chart_years = [];
    $chart_year_totals = [];
    $yearly_aggregate = [];
    while ($row = $res_yearly_totals->fetchArray(SQLITE3_ASSOC)) {
        $chart_years[] = $row['year'];
        $chart_year_totals[] = round($row['total'], 2);
        $yearly_aggregate[$row['year']] = $row['total'];
    }

    // Yearly category breakdown for Chart 2 (Stacked Bar)
    $res_yearly_cats = $db->query('SELECT strftime("%Y", "date") as year, category, SUM(amount) as total FROM expenses WHERE "date" IS NOT NULL GROUP BY year, category ORDER BY year ASC');
    $yearly_cat_data = [];
    $all_cats_set = [];
    while ($row = $res_yearly_cats->fetchArray(SQLITE3_ASSOC)) {
        $y = $row['year'];
        $c = $row['category'];
        $yearly_cat_data[$y][$c] = round($row['total'], 2);
        $all_cats_set[$c] = true;
    }
    $unique_categories = array_keys($all_cats_set);

    // Per-year card data
    $year_cards_data = [];
    foreach ($years_list as $yr) {
        $stmt_uy = $db->prepare('SELECT paid_by, SUM(amount) as total FROM expenses WHERE strftime("%Y", "date") = :year GROUP BY paid_by');
        $stmt_uy->bindValue(':year', $yr, SQLITE3_TEXT);
        $res_uy = $stmt_uy->execute();
        $yr_user_totals = [$u1 => 0, $u2 => 0];
        while ($row = $res_uy->fetchArray(SQLITE3_ASSOC)) {
            $yr_user_totals[$row['paid_by']] = $row['total'];
        }

        $t1_yr = $yr_user_totals[$u1] ?? 0;
        $t2_yr = $yr_user_totals[$u2] ?? 0;
        $diff_yr = $t1_yr - $t2_yr;
        
        if ($diff_yr > 0) {
            $yr_balance_text = "<strong>$u1</strong> +" . number_format($diff_yr, 2) . "€";
        } elseif ($diff_yr < 0) {
            $yr_balance_text = "<strong>$u2</strong> +" . number_format(abs($diff_yr), 2) . "€";
        } else {
            $yr_balance_text = "0.00€";
        }

        $stmt_cy = $db->prepare('SELECT category, SUM(amount) as total FROM expenses WHERE strftime("%Y", "date") = :year GROUP BY category ORDER BY total DESC');
        $stmt_cy->bindValue(':year', $yr, SQLITE3_TEXT);
        $res_cy = $stmt_cy->execute();
        $yr_cat_chart = [];
        while ($row = $res_cy->fetchArray(SQLITE3_ASSOC)) {
            $yr_cat_chart[$row['category']] = round($row['total'], 2);
        }

        $prev_yr = (string)(intval($yr) - 1);
        $next_yr = (string)(intval($yr) + 1);
        
        $current_total = $yearly_aggregate[$yr] ?? 0;
        $prev_total = $yearly_aggregate[$prev_yr] ?? null;
        $next_total = $yearly_aggregate[$next_yr] ?? null;

        $year_cards_data[$yr] = [
            'user_totals' => $yr_user_totals,
            'balance_text' => $yr_balance_text,
            'cat_chart' => $yr_cat_chart,
            'total' => $current_total,
            'prev_diff' => $prev_total !== null ? $current_total - $prev_total : null,
            'next_diff' => $next_total !== null ? $current_total - $next_total : null,
        ];
    }

    require_once __DIR__ . '/statistics.php';
    exit;
}

// ==========================================
// FETCH YEARS FOR FILTER DROPDOWN
// ==========================================
$res_years = $db->query('SELECT DISTINCT strftime("%Y", "date") as year FROM expenses WHERE "date" IS NOT NULL ORDER BY year DESC');
$available_years = [];
while ($row = $res_years->fetchArray(SQLITE3_ASSOC)) {
    if (!empty($row['year'])) {
        $available_years[] = $row['year'];
    }
}

require_once __DIR__ . '/view.php';
