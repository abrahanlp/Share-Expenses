<?php
// index.php
require_once __DIR__ . '/config.php';

$message = '';
$page = $_GET['page'] ?? 'home';

// Fetch dynamic users
$users = [];
$users_data = []; // Store IDs for updating later
$res_users = $db->query("SELECT * FROM users ORDER BY id ASC LIMIT 2");
while ($row = $res_users->fetchArray(SQLITE3_ASSOC)) {
    $users[] = $row['name'];
    $users_data[] = $row;
}
$u1 = $users[0] ?? 'User1';
$u2 = $users[1] ?? 'User2';

// Fetch dynamic categories
$categories = [];
$res_cats = $db->query("SELECT * FROM categories ORDER BY name ASC");
while ($row = $res_cats->fetchArray(SQLITE3_ASSOC)) {
    $categories[] = $row;
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

    // ADD CATEGORY
    if ($action === 'add_category') {
        $cat_name = trim($_POST['cat_name']);
        if (!empty($cat_name)) {
            $stmt = $db->prepare("INSERT OR IGNORE INTO categories (name) VALUES (:name)");
            $stmt->bindValue(':name', htmlspecialchars($cat_name), SQLITE3_TEXT);
            $stmt->execute();
            header("Location: index.php?page=settings");
            exit;
        }
    }

    // UPDATE USERS
    if ($action === 'update_users') {
        $new_u1 = trim($_POST['user1']);
        $new_u2 = trim($_POST['user2']);

        if (!empty($new_u1) && !empty($new_u2)) {
            // 1. Update past expenses to keep the balance intact
            $stmt = $db->prepare("UPDATE expenses SET paid_by = :new_name WHERE paid_by = :old_name");
            $stmt->bindValue(':new_name', $new_u1, SQLITE3_TEXT);
            $stmt->bindValue(':old_name', $u1, SQLITE3_TEXT);
            $stmt->execute();

            $stmt->bindValue(':new_name', $new_u2, SQLITE3_TEXT);
            $stmt->bindValue(':old_name', $u2, SQLITE3_TEXT);
            $stmt->execute();

            // 2. Update users table
            $stmt = $db->prepare("UPDATE users SET name = :name WHERE id = :id");
            $stmt->bindValue(':name', htmlspecialchars($new_u1), SQLITE3_TEXT);
            $stmt->bindValue(':id', $users_data[0]['id'], SQLITE3_INTEGER);
            $stmt->execute();

            $stmt->bindValue(':name', htmlspecialchars($new_u2), SQLITE3_TEXT);
            $stmt->bindValue(':id', $users_data[1]['id'], SQLITE3_INTEGER);
            $stmt->execute();

            header("Location: index.php?page=settings");
            exit;
        }
    }
}

// ==========================================
// GET ACTIONS (DELETE & EDIT PRE-FILL)
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
    $id = intval($_GET['edit']);
    $res = $db->query("SELECT * FROM expenses WHERE id = $id");
    $expense_to_edit = $res->fetchArray(SQLITE3_ASSOC);
}

// ==========================================
// DATA FOR DASHBOARD
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

    $recent_expenses = $db->query("SELECT * FROM expenses ORDER BY date DESC, id DESC LIMIT 15");
}

require_once __DIR__ . '/view.php';
