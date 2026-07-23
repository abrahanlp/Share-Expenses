<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Household Expense Tracker</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<header>
    <h1>Household Expenses</h1>
    <nav>
        <a href="index.php" class="<?php echo $page === 'home' ? 'active' : ''; ?>">Dashboard</a>
        <a href="index.php?page=statistics" class="<?php echo $page === 'statistics' ? 'active' : ''; ?>">Statistics</a>
        <a href="index.php?page=settings" class="<?php echo $page === 'settings' ? 'active' : ''; ?>">Settings</a>
    </nav>
</header>

<div class="container">
    <?php if ($message) echo $message; ?>

    <?php if ($page === 'home'): ?>
        
        <?php 
        // ----------------------------------------------------
        // PRE-PROCESS FILTERED DATA FOR CHART AND TABLE
        // ----------------------------------------------------
        $effective_start_date = (isset($_GET['start_date']) && $_GET['start_date'] === 'all') ? 'all' : $start_date;
        
        $expenses_list = [];
        $filtered_totals = [$u1 => 0, $u2 => 0];
        
        if (isset($recent_expenses)) {
            while ($e = $recent_expenses->fetchArray(SQLITE3_ASSOC)) {
                $expenses_list[] = $e;
                $payer = $e['paid_by'];
                if (!isset($filtered_totals[$payer])) {
                    $filtered_totals[$payer] = 0;
                }
                $filtered_totals[$payer] += $e['amount'];
            }
        }
        ?>

        <div class="grid">
            <div class="card" id="form-section">
                <h2><?php echo $expense_to_edit ? 'Edit Expense' : 'Add Expense'; ?></h2>
                <form method="POST" action="index.php">
                    <input type="hidden" name="action" value="save_expense">
                    <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($effective_start_date); ?>">
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
                    <div class="radio-group">
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
                        <a href="index.php?start_date=<?php echo urlencode($effective_start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="cancel-link">Cancel Edit</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="card">
                <!-- User Balance Text -->
                <p class="balance-alert">
                    <?php echo $balance_text; ?>
                </p>

                <!-- Filtered amounts paid by user -->
                <div class="user-totals">
                    <span><strong><?php echo htmlspecialchars($u1); ?>:</strong> <?php echo number_format($filtered_totals[$u1] ?? 0, 2); ?> €</span>
                    <span><strong><?php echo htmlspecialchars($u2); ?>:</strong> <?php echo number_format($filtered_totals[$u2] ?? 0, 2); ?> €</span>
                </div>

                <!-- Chart container -->
                <div class="chart-container">
                    <canvas id="catChart"></canvas>
                </div>
            </div>
        </div>

        <div class="card">
            <!-- Filter Form -->
            <form id="filterForm" method="GET" action="index.php" class="filter-form">
                <strong class="filter-label">Date Range:</strong>
                
                <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="form-control auto-width" onchange="this.form.submit()">
                <span class="text-muted">to</span>
                <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="form-control auto-width" onchange="this.form.submit()">
                
                <select id="quick_ranges" onchange="applyQuickRange()" class="form-control auto-width" style="cursor: pointer;">
                    <option value="">Custom...</option>
                    <option value="this_month">This month</option>
                    <option value="last_6_months">Last 6 months</option>
                    <option value="last_year">Last year</option>
                    <?php 
                    if (isset($available_years)) {
                        foreach($available_years as $year): 
                    ?>
                        <option value="<?php echo htmlspecialchars($year); ?>"><?php echo htmlspecialchars($year); ?></option>
                    <?php 
                        endforeach; 
                    }
                    ?>
                    <option value="all" <?php echo (isset($_GET['start_date']) && $_GET['start_date'] === 'all') ? 'selected' : ''; ?>>All times</option>
                </select>
            </form>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Concept</th>
                            <th>Category</th>
                            <th>Payer</th>
                            <th>Amount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses_list as $e): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($e['date'])); ?></td>
                            <td><?php echo htmlspecialchars($e['concept']); ?></td>
                            <td><span class="badge"><?php echo htmlspecialchars($e['category']); ?></span></td>
                            <td><strong><?php echo htmlspecialchars($e['paid_by']); ?></strong></td>
                            <td><strong><?php echo number_format($e['amount'], 2); ?> €</strong></td>
                            <td class="actions">
                                <a href="?edit=<?php echo $e['id']; ?>&start_date=<?php echo urlencode($effective_start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="edit-link">Edit</a>
                                <a href="?delete=<?php echo $e['id']; ?>&start_date=<?php echo urlencode($effective_start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="del-link" onclick="return confirm('Delete this expense?');">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
            // Date Filter Logic
            function applyQuickRange() {
                const range = document.getElementById('quick_ranges').value;
                if (!range) return;

                if (range === 'all') {
                    window.location.href = 'index.php?start_date=all';
                    return;
                }

                const startInput = document.getElementById('start_date');
                const endInput = document.getElementById('end_date');
                
                const today = new Date();
                let start, end;

                if (range === 'this_month') {
                    start = new Date(today.getFullYear(), today.getMonth(), 1);
                    end = new Date(today.getFullYear(), today.getMonth() + 1, 0); 
                } else if (range === 'last_6_months') {
                    start = new Date(today.getFullYear(), today.getMonth() - 5, 1);
                    end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                } else if (range === 'last_year') {
                    start = new Date(today.getFullYear() - 1, 0, 1); 
                    end = new Date(today.getFullYear() - 1, 11, 31); 
                } else {
                    start = new Date(range, 0, 1); 
                    end = new Date(range, 11, 31); 
                }

                const formatDate = (d) => {
                    const yyyy = d.getFullYear();
                    const mm = String(d.getMonth() + 1).padStart(2, '0');
                    const dd = String(d.getDate()).padStart(2, '0');
                    return `${yyyy}-${mm}-${dd}`;
                };

                startInput.value = formatDate(start);
                endInput.value = formatDate(end);
                
                startInput.form.submit();
            }

            // Chart Initialization
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
                                position: 'right', 
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
        
        <div class="grid">
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
                        <a href="index.php?page=settings" class="cancel-link">Cancel Edit</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="grid">
            <div class="card">
                <h2>CSV Import / Export</h2>
                <div style="margin-bottom: 25px;">
                    <label style="display:block; margin-bottom:8px; font-weight:bold;">Export App Data</label>
                    <a href="index.php?action=export_csv" class="btn btn-secondary">Download expenses.csv</a>
                </div>
                
                <hr class="divider">
                
                <form method="POST" action="index.php?page=settings" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="import_csv">
                    <label style="display:block; font-weight:bold; margin-bottom:5px;">Import Data File</label>
                    <p class="text-muted" style="margin:0 0 10px 0;">Required column order: <code>Concept,Date,Paid_by,Category,Amount</code><br>(Date format: <code>DD-MM-YYYY</code> or <code>YYYY-MM-DD</code>)</p>
                    <input type="file" name="csv_file" accept=".csv" required style="margin-bottom:15px; display:block;">
                    <button type="submit" class="btn">Process CSV Upload</button>
                </form>
            </div>

            <div class="card">
                <h2>Categories</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($categories as $cat): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($cat['name']); ?></td>
                            <td style="text-align:right;" class="actions">
                                <a href="?page=settings&edit_cat=<?php echo $cat['id']; ?>" class="edit-link">Rename</a>
                                <a href="?delete_cat=<?php echo $cat['id']; ?>" class="del-link" onclick="return confirm('Delete category? Historic entries will preserve text values.');">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
