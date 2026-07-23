<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Prevent search engines from indexing or following links on this page -->
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <title>Household Expense Tracker - Statistics</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<header>
    <h1>Household Expenses</h1>
    <nav>
        <a href="index.php">Dashboard</a>
        <a href="index.php?page=statistics" class="active">Statistics</a>
        <a href="index.php?page=settings">Settings</a>
    </nav>
</header>

<div class="container">
    <!-- 1. Full-width Yearly Total Expenses Chart (Line) -->
    <div class="card" style="margin-bottom: 25px;">
        <h2>Total Expenses by Year</h2>
        <div style="height: 350px; position: relative;">
            <canvas id="yearlyTotalChart"></canvas>
        </div>
    </div>

    <!-- 2. Full-width Yearly Category Expenses Chart (Multi-Line) -->
    <div class="card" style="margin-bottom: 25px;">
        <h2>Expenses by Category per Year</h2>
        <div style="height: 400px; position: relative;">
            <canvas id="yearlyCategoryChart"></canvas>
        </div>
    </div>

    <!-- 3. Full-width Cards by Year -->
    <h2 style="margin-bottom: 15px;">Yearly Breakdown & Comparisons</h2>
    <?php foreach ($years_list as $yr): 
        $data = $year_cards_data[$yr];
    ?>
        <div class="card" style="margin-bottom: 25px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 15px; border-bottom: 1px solid #e5e7eb; padding-bottom: 10px;">
                <h3 style="margin: 0; font-size: 1.5rem; color: #4f46e5;"><?php echo $yr; ?></h3>
                <div style="display: flex; gap: 20px; font-size: 0.95rem; flex-wrap: wrap;">
                    <span><strong>Total Spent:</strong> <?php echo number_format($data['total'], 2); ?> €</span>
                    <?php if ($data['prev_diff'] !== null): ?>
                        <span><strong>vs Previous Year (<?php echo intval($yr)-1; ?>):</strong> 
                            <span style="color: <?php echo $data['prev_diff'] >= 0 ? '#ef4444' : '#10b981'; ?>;">
                                <?php echo ($data['prev_diff'] >= 0 ? '+' : '') . number_format($data['prev_diff'], 2); ?> €
                            </span>
                        </span>
                    <?php endif; ?>
                    <?php if ($data['next_diff'] !== null): ?>
                        <span><strong>vs Next Year (<?php echo intval($yr)+1; ?>):</strong> 
                            <span style="color: <?php echo $data['next_diff'] >= 0 ? '#ef4444' : '#10b981'; ?>;">
                                <?php echo ($data['next_diff'] >= 0 ? '+' : '') . number_format($data['next_diff'], 2); ?> €
                            </span>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid" style="align-items: center;">
                <div>
                    <p class="balance-alert" style="margin-top: 0;">
                        <?php echo $data['balance_text']; ?>
                    </p>
                    <div class="user-totals" style="margin-bottom: 20px;">
                        <span><strong><?php echo htmlspecialchars($u1); ?>:</strong> <?php echo number_format($data['user_totals'][$u1] ?? 0, 2); ?> €</span>
                        <span><strong><?php echo htmlspecialchars($u2); ?>:</strong> <?php echo number_format($data['user_totals'][$u2] ?? 0, 2); ?> €</span>
                    </div>
                </div>
                <div class="chart-container" style="height: 250px;">
                    <canvas id="catChart_<?php echo $yr; ?>"></canvas>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
    // Chart 1: Yearly Totals Line Chart
    const yearlyYears = <?php echo json_encode($chart_years); ?>;
    const yearlyTotals = <?php echo json_encode($chart_year_totals); ?>;
    
    if (yearlyYears.length > 0) {
        new Chart(document.getElementById('yearlyTotalChart'), {
            type: 'line',
            data: {
                labels: yearlyYears,
                datasets: [{
                    label: 'Total Expenses (€)',
                    data: yearlyTotals,
                    backgroundColor: 'rgba(79, 70, 229, 0.1)', 
                    borderColor: '#4f46e5',                     
                    borderWidth: 2,
                    pointBackgroundColor: '#4f46e5',
                    pointRadius: 4,
                    fill: true,
                    tension: 0
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    // Chart 2: Yearly Category Multi-Line Chart
    const stackYears = <?php echo json_encode($chart_years); ?>;
    const rawYearlyCats = <?php echo json_encode($yearly_cat_data); ?>;
    const allCategories = <?php echo json_encode($unique_categories); ?>;
    const palette = ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#f43f5e', '#64748b'];

    const lineDatasets = allCategories.map((cat, idx) => {
        return {
            label: cat,
            data: stackYears.map(yr => (rawYearlyCats[yr] && rawYearlyCats[yr][cat]) ? rawYearlyCats[yr][cat] : 0),
            backgroundColor: palette[idx % palette.length],
            borderColor: palette[idx % palette.length],
            borderWidth: 2,
            pointRadius: 4,
            fill: false,
            tension: 0
        };
    });

    if (stackYears.length > 0) {
        new Chart(document.getElementById('yearlyCategoryChart'), {
            type: 'line',
            data: {
                labels: stackYears,
                datasets: lineDatasets
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true } 
                },
                plugins: {
                    legend: { position: 'right' }
                }
            }
        });
    }

    // Individual Year Doughnut Charts
    <?php foreach ($years_list as $yr): 
        $catDataYr = $year_cards_data[$yr]['cat_chart'];
        if (!empty($catDataYr)):
    ?>
    const catData_<?php echo $yr; ?> = <?php echo json_encode($catDataYr); ?>;
    new Chart(document.getElementById('catChart_<?php echo $yr; ?>'), {
        type: 'doughnut',
        data: {
            labels: Object.keys(catData_<?php echo $yr; ?>),
            datasets: [{
                data: Object.values(catData_<?php echo $yr; ?>),
                backgroundColor: ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#f43f5e']
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'right',
                    labels: { boxWidth: 12, padding: 8 }
                }
            }
        }
    });
    <?php endif; endforeach; ?>
</script>

</body>
</html>
