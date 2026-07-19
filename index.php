<?php
// index.php
require_once __DIR__ . '/config.php';

$message = '';
$page = $_GET['page'] ?? 'home';

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

// Fetch dynamic categories with explicit default sorting order
$categories = [];
$res_cats = $db->query("SELECT * FROM categories ORDER BY CASE name 
    WHEN 'Food' THEN 1 
    WHEN 'Delivery' THEN 2 
    WHEN 'Travel' THEN 3 
    WHEN 'Rent' THEN 4 
    WHEN 'Light' THEN 5 
    WHEN 'Teleco' THEN 6 
    WHEN 'Water' THEN 7 
    WHEN 'Appliances' THEN 8 
    ELSE 9 END, name ASC");
while ($row = $res_cats->fetchArray(SQLITE3_ASSOC)) {
    $categories[] = $row;
}

// Status message intercepts
if (isset($_GET['status']) && $_GET['status'] === 'imported') {
    $message = "<div class='alert success'>CSV Data imported successfully!</div>";
}

// ==========================================
// CSV EXPORT ROUTE (Transforms Date to DD-MM-YYYY)
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=expenses_export_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Concept', 'Date', 'Paid_by', 'Category', 'Amount']);
    
    $res = $db->query("SELECT concept, date, paid_by, category, amount FROM expenses ORDER BY date DESC, id DESC");
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
                $stmt = $db->prepare("UPDATE expenses SET concept=:concept, category=:category, date=:date, amount=:amount, paid_by=:paid_by WHERE id=:id");
                $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            } else {
                $stmt = $db->prepare("INSERT INTO expenses (concept, category, date, amount, paid_by) VALUES (:concept, :category, :date, :amount, :paid_by)");
            }
            $stmt->bindValue(':concept', htmlspecialchars($concept), SQLITE3_TEXT);
            $stmt->bindValue(':category', $category, SQLITE3_TEXT);
            $stmt->bindValue(':date', $date, SQLITE3_TEXT);
            $stmt->bindValue(':amount', $amount, SQLITE3_FLOAT);
            $stmt->bindValue(':paid_by', $paid_by, SQLITE3_TEXT);
            $stmt->execute();
            
            header("Location: index.php");
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
                
                $stmt = $db->prepare("INSERT INTO expenses (concept, date, paid_by, category, amount) VALUES (:concept, :date, :paid_by, :category, :amount)");
                
                $db->exec('BEGIN TRANSACTION');
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (count($data) >= 5) {
                        $concept = trim($data[0]);
                        $date_raw = trim($data[1]); 
                        $paid_by = trim($data[2]);
                        $category = trim($data[3]);
                        $amount = floatval($data[4]);

                        $date_obj = DateTime::createFromFormat('d-m-Y', $date_raw);
                        $db_date = $date_obj ? $date_obj->format('Y-m-d') : date('Y-m-d');

                        if (!empty($concept) && !empty($date_raw) && $amount > 0) {
                            if (!in_array($paid_by, $users)) {
                                $paid_by = $u1; 
                            }
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
// GET ACTIONS
// ==========================================
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $db->exec("DELETE FROM expenses WHERE id = $id");
    header("Location: index.php");
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
// DATA COMPILATION FOR GRAPH & HISTORY
// ==========================================
if ($page === 'home') {
    $res_totals = $db->query("SELECT paid_by, SUM(amount) as total FROM expenses GROUP BY paid_by");
    $totals = array_fill_keys($users, 0);
    while ($row = $res_totals->fetchArray(SQLITE3_ASSOC)) { $totals[$row['paid_by']] = $row['total']; }

    if ($totals[$u1] > $totals[$u2]) {
        $balance_text = "<strong>$u2</strong> owes <strong>$u1</strong>: " . number_format(($totals[$u1] - $totals[$u2]) / 2, 2) . " €";
    } elseif ($totals[$u2] > $totals[$u1]) {
        $balance_text = "<strong>$u1</strong> owes <strong>$u2</strong>: " . number_format(($totals[$u2] - $totals[$u1]) / 2, 2) . " €";
    } else {
        $balance_text = "Accounts are balanced!";
    }

    $res_cat = $db->query("SELECT category, SUM(amount) as total FROM expenses GROUP BY category ORDER BY total DESC");
    $chart_categories = [];
    while ($row = $res_cat->fetchArray(SQLITE3_ASSOC)) { $chart_categories[$row['category']] = round($row['total'], 2); }

    // Updated query to fetch expenses spanning the last 365 days
    $recent_expenses = $db->query("SELECT * FROM expenses WHERE date >= date('now', '-365 days') ORDER BY date DESC, id DESC");
}

require_once __DIR__ . '/view.php';
