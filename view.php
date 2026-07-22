<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Household Expense Tracker</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --primary: #4f46e5; --primary-hover: #4338ca; --bg: #f3f4f6; --text: #1f2937; }
        body { background-color: var(--bg); font-family: sans-serif; padding: 0; margin: 0; }
        
        header { background: white; padding: 15px 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        header h1 { margin: 0; font-size: 1.5rem; color: var(--primary); }
        nav a { text-decoration: none; color: var(--text); font-weight: bold; margin-left: 15px; padding: 8px 12px; border-radius: 5px; }
        nav a.active { background: var(--primary); color: white; }
        nav a:hover:not(.active) { background: #e5e7eb; }

        .container { max-width: 1000px; margin: auto; padding: 0 20px 20px 20px; }
        .card { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        
        .form-control { width: 100%; padding: 8px; margin: 5px 0 15px 0; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        .btn { width: 100%; padding: 10px; background: var(--primary); color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; text-align: center; text-decoration: none; display: block; box-sizing: border-box; }
        .btn:hover { background: var(--primary-hover); }
        .btn-secondary { background: #10b981; }
        .btn-secondary:hover { background: #059669; }
        
        .alert { padding: 10px; margin-bottom: 10px; text-align: center; border-radius: 5px; }
        .success { background: #d1fae5; color: #065f46; }
        .error { background: #fee2e2; color: #991b1b; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; border-bottom: 1px solid #eee; text-align: left; }
        .actions a { text-decoration: none; margin-right: 10px; font-weight: bold; }
        .edit-link { color: #f59e0b; }
        .del-link { color: #ef4444; }
    </style>
</head>
<body>

<header>
    <h1>Household Expenses</h1>
    <nav>
        <a href="index.php" class="<?php echo $page === 'home' ? 'active' : ''; ?>">Dashboard</a>
        <a href="index.php?page=settings" class="<?php echo $page === 'settings' ? 'active' : ''; ?>">Settings</a>
    </nav>
</header>

<div class="container">
    <?php echo $message; ?>

    <?php if ($page === 'home'): ?>
        <!-- ========================================== -->
        <!-- DASHBOARD VIEW -->
        <!-- ========================================== -->
        
        <div class="grid">
            <div class="card" id="form-section">
                <h2><?php echo $expense_to_edit ? 'Edit Expense' : 'Add Expense'; ?></h2>
                <form method="POST" action="index.php">
                    <input type="hidden" name="action" value="save_expense">
                    <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    
                    <?php if($expense_to_edit): ?>
                        <input type="hidden" name="id" value="<?php echo $expense_to_edit['id']; ?>">
                    <?php endif; ?>

                    <label>Concept</label>
                    <input type="text" name="concept" class="form-control" value="<?php echo $expense_to_edit ? htmlspecialchars($expense_to_edit['concept']) : ''; ?>" required>
                    
                    <label>Category</label>
                    <select name="category" class="form-control">
                        <?php foreach($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['name']); ?>" 
                                <?php echo ($expense_to_edit && $expense_to_edit['category'] === $cat['name']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <label>Date</label>
                    <input type="date" name="date" class="form-control" value="<?php echo $expense_to_edit ? $expense_to_edit['date'] : date('Y-m-d'); ?>" required>
                    
                    <label>Amount (€)</label>
                    <input type="number" name="amount" class="form-control" step="0.01" value="<?php echo $expense_to_edit ? $expense_to_edit['amount'] : ''; ?>" required>
                    
                    <label>Paid by</label>
                    <div style="display:flex; gap:10px; margin: 10px 0 15px 0;">
                        <?php foreach($users as $p): ?>
                            <label>
                                <input type="radio" name="paid_by" value="<?php echo htmlspecialchars($p); ?>" 
                                    <?php echo ($expense_to_edit && $expense_to_edit['paid_by'] === $p) ? 'checked' : ''; ?> required> 
                                <?php echo htmlspecialchars($p); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="submit" class="btn"><?php echo $expense_to_edit ? 'Update Expense' : 'Save Expense'; ?></button>
                    <?php if($expense_to_edit): ?>
                        <a href="index.php?start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" style="display:block; text-align:center; margin-top:10px; color:#666;">Cancel Edit</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="card">
                <!-- User Balance Text (Calculated globally) -->
                <p style="background: #e0e7ff; color: #3730a3; padding: 15px; border-radius: 8px; text-align: center; font-size: 1.4rem; font-weight: bold; margin-top: 0; border: 1px solid #c7d2fe;">
                    <?php echo $balance_text; ?>
                </p>

                <!-- Chart container (height increased slightly for the vertical legend) -->
                <div style="height:250px; margin-top:20px;">
                    <canvas id="catChart"></canvas>
                </div>
            </div>
        </div>

        <div class="card">
            <!-- Filter form acting as the section header -->
            <form method="GET" action="index.php" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee;">
                <strong style="margin-right: 10px; color: var(--text); font-size: 1.1rem;">Date Range:</strong>
                
                <!-- Added IDs to date inputs for the JavaScript logic -->
                <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="form-control" style="width: auto; margin: 0;">
                <span style="color: #6b7280;">to</span>
                <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="form-control" style="width: auto; margin: 0;">
                
                <!-- Dropdown for quick date range selection -->
                <select id="quick_ranges" onchange="applyQuickRange()" class="form-control" style="width: auto; margin: 0; cursor: pointer;">
                    <option value="">Custom...</option>
                    <option value="this_month">This month</option>
                    <option value="last_6_months">Last 6 month</option>
                    <?php 
                    // Safely loop through available years if the array exists
                    if (isset($available_years)) {
                        foreach($available_years as $year): 
                    ?>
                        <option value="<?php echo htmlspecialchars($year); ?>"><?php echo htmlspecialchars($year); ?></option>
                    <?php 
                        endforeach; 
                    }
                    ?>
                </select>

                <button type="submit" class="btn" style="width: auto; padding: 8px 20px; margin: 0;">Filter</button>
                <a href="index.php" style="text-decoration: none; color: #6b7280; font-size: 0.9rem; margin-left: 10px;">Reset</a>
            </form>

            <div style="overflow-x: auto; max-height: 500px; overflow-y: auto;">
                <table>
                    <thead style="position: sticky; top: 0; background: white; z-index: 1;">
                        <tr><th>Date</th><th>Concept</th><th>Category</th><th>Payer</th><th>Amount</th><th>Actions</th></tr>
                    </thead>
                    <?php while ($e = $recent_expenses->fetchArray(SQLITE3_ASSOC)): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($e['date'])); ?></td>
                        <td><?php echo htmlspecialchars($e['concept']); ?></td>
                        <td><span style="background: var(--bg); padding: 3px 8px; border-radius: 12px; font-size: 0.8rem;"><?php echo htmlspecialchars($e['category']); ?></span></td>
                        <td><strong><?php echo htmlspecialchars($e['paid_by']); ?></strong></td>
                        <td><strong><?php echo number_format($e['amount'], 2); ?> €</strong></td>
                        <td class="actions">
                            <a href="?edit=<?php echo $e['id']; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="edit-link">Edit</a>
                            <a href="?delete=<?php echo $e['id']; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="del-link" onclick="return confirm('Delete this expense?');">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </table>
            </div>
        </div>

        <script>
            // ----------------------------------------------------
            // Quick Range Selection Logic
            // ----------------------------------------------------
            function applyQuickRange() {
                const range = document.getElementById('quick_ranges').value;
                const startInput = document.getElementById('start_date');
                const endInput = document.getElementById('end_date');
                
                if (!range) return; // Do nothing if "Custom..." is selected

                const today = new Date();
                let start, end;

                if (range === 'this_month') {
                    start = new Date(today.getFullYear(), today.getMonth(), 1);
                    end = new Date(today.getFullYear(), today.getMonth() + 1, 0); // Last day of current month
                } else if (range === 'last_6_months') {
                    start = new Date(today.getFullYear(), today.getMonth() - 5, 1);
                    end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                } else {
                    // A specific year was selected
                    start = new Date(range, 0, 1); // January 1st
                    end = new Date(range, 11, 31); // December 31st
                }

                // Helper to format Date objects to YYYY-MM-DD
                const formatDate = (d) => {
                    const yyyy = d.getFullYear();
                    const mm = String(d.getMonth() + 1).padStart(2, '0');
                    const dd = String(d.getDate()).padStart(2, '0');
                    return `${yyyy}-${mm}-${dd}`;
                };

                startInput.value = formatDate(start);
                endInput.value = formatDate(end);
            }

            // ----------------------------------------------------
            // Chart Initialization
            // ----------------------------------------------------
            const catData = <?php echo json_encode($chart_categories); ?>;
            if(Object.keys(catData).length > 0) {
                new Chart(document.getElementById('catChart'), {
                    type: 'doughnut',
                    data: { 
                        labels: Object.keys(catData), 
                        datasets: [{ 
                            data: Object.values(catData), 
                            backgroundColor: ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#f43f5e'] 
                        }] 
                    },
                    options: { 
                        maintainAspectRatio: false, 
                        plugins: { 
                            legend: { 
                                display: true,
                                position: 'right', // Forces vertical layout for the legend
                                align: 'center',
                                labels: {
                                    boxWidth: 15,
                                    padding: 12
                                }
                            } 
                        } 
                    }
                });
            }
        </script>

    <?php elseif ($page === 'settings'): ?>
        <!-- ========================================== -->
        <!-- SETTINGS VIEW -->
        <!-- ========================================== -->
        <div class="grid">
            <!-- Manage Users Card -->
            <div class="card">
                <h2>Manage Users</h2>
                <form method="POST" action="index.php?page=settings">
                    <input type="hidden" name="action" value="update_users">
                    <label>User 1 Name</label>
                    <input type="text" name="user1" class="form-control" value="<?php echo htmlspecialchars($u1); ?>" required>
                    
                    <label>User 2 Name</label>
                    <input type="text" name="user2" class="form-control" value="<?php echo htmlspecialchars($u2); ?>" required>
                    
                    <button type="submit" class="btn">Update Users</button>
                </form>
            </div>

            <!-- Dynamic Category Modification Form -->
            <div class="card">
                <h2><?php echo $cat_to_edit ? 'Edit Category' : 'Add New Category'; ?></h2>
                <form method="POST" action="index.php?page=settings">
                    <input type="hidden" name="action" value="save_category">
                    <?php if($cat_to_edit): ?>
                        <input type="hidden" name="id" value="<?php echo $cat_to_edit['id']; ?>">
                    <?php endif; ?>
                    
                    <label>Category Name</label>
                    <input type="text" name="cat_name" class="form-control" value="<?php echo $cat_to_edit ? htmlspecialchars($cat_to_edit['name']) : ''; ?>" placeholder="e.g. Subscriptions" required>
                    
                    <button type="submit" class="btn"><?php echo $cat_to_edit ? 'Update Category' : 'Add Category'; ?></button>
                    <?php if($cat_to_edit): ?>
                        <a href="index.php?page=settings" style="display:block; text-align:center; margin-top:10px; color:#666;">Cancel Edit</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="grid">
            <!-- Portability Engine Card -->
            <div class="card">
                <h2>CSV Import / Export</h2>
                <div style="margin-bottom: 25px;">
                    <label style="display:block; margin-bottom:8px; font-weight:bold;">Export App Data</label>
                    <a href="index.php?action=export_csv" class="btn btn-secondary">Download expenses.csv</a>
                </div>
                <hr style="border:0; border-top:1px solid #eee; margin:20px 0;">
                <form method="POST" action="index.php?page=settings" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="import_csv">
                    <label style="display:block; font-weight:bold; margin-bottom:5px;">Import Data File</label>
                    <p style="font-size:0.8rem; color:#6b7280; margin:0 0 10px 0;">Required column order: <code>Concept,Date,Paid_by,Category,Amount</code> (Date format: <code>DD-MM-YYYY</code> or <code>YYYY-MM-DD</code>)</p>
                    <input type="file" name="csv_file" accept=".csv" required style="margin-bottom:15px; display:block;">
                    <button type="submit" class="btn">Process CSV Upload</button>
                </form>
            </div>

            <!-- Categorization Catalog Management Table -->
            <div class="card">
                <h2>Categories</h2>
                <table>
                    <tr><th>Name</th><th style="text-align:right;">Actions</th></tr>
                    <?php foreach($categories as $cat): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($cat['name']); ?></td>
                        <td style="text-align:right;" class="actions">
                            <a href="?page=settings&edit_cat=<?php echo $cat['id']; ?>" class="edit-link">Rename</a>
                            <a href="?delete_cat=<?php echo $cat['id']; ?>" class="del-link" onclick="return confirm('Delete category? Historic entries will preserve text values.');">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
