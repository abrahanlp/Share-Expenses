<?php
// ==========================================
// 1. CONFIGURACIÓN PERSONALIZADA
// ==========================================
$personas = ['Ana', 'Carlos']; // Cambia por vuestros nombres
$categorias = ['Comida', 'Luz', 'Agua', 'Renta', 'Teléfono', 'Mobiliario', 'Viajes', 'Otro'];
$db_file = __DIR__ . '/gastos.db';

// Opcional: Para el futuro login, descomenta estas líneas y cambia la contraseña
/*
session_start();
if (!isset($_SESSION['logueado'])) {
    if (isset($_POST['password']) && $_POST['password'] === 'TuContraseñaSecreta') {
        $_SESSION['logueado'] = true;
    } else {
        echo '<form method="POST" style="text-align:center; margin-top:100px;">
                <input type="password" name="password" placeholder="Contraseña" required style="padding:10px;">
                <button type="submit" style="padding:10px;">Entrar</button>
              </form>';
        exit;
    }
}
*/

// ==========================================
// 2. CONEXIÓN Y CREACIÓN DE BASE DE DATOS
// ==========================================
$db = new SQLite3($db_file);

// Crear la tabla si no existe
$db->exec("CREATE TABLE IF NOT EXISTS gastos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    concepto TEXT NOT NULL,
    tipo TEXT NOT NULL,
    fecha TEXT NOT NULL,
    cantidad REAL NOT NULL,
    pagado_por TEXT NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Crear archivo .htaccess automáticamente para proteger seguridad y base de datos
if (!file_exists(__DIR__ . '/.htaccess')) {
    $reglas = "Options -Indexes\n"; // Impide que se liste el contenido de la carpeta
    $reglas .= "RedirectMatch 403 \.db$\n"; // Bloquea la descarga de la base de datos
    file_put_contents(__DIR__ . '/.htaccess', $reglas);
}

// ==========================================
// 3. PROCESAMIENTO DE ACCIONES (POST/GET)
// ==========================================
$mensaje = '';

// Guardar nuevo gasto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'nuevo_gasto') {
    $concepto = trim($_POST['concepto']);
    $tipo = $_POST['tipo'];
    $fecha = $_POST['fecha'];
    $cantidad = floatval($_POST['cantidad']);
    $pagado_por = $_POST['pagado_por'];

    if (!empty($concepto) && $cantidad > 0 && in_array($pagado_por, $personas)) {
        $stmt = $db->prepare("INSERT INTO gastos (concepto, tipo, fecha, cantidad, pagado_por) VALUES (:concepto, :tipo, :fecha, :cantidad, :pagado_por)");
        $stmt->bindValue(':concepto', htmlspecialchars($concepto), SQLITE3_TEXT);
        $stmt->bindValue(':tipo', $tipo, SQLITE3_TEXT);
        $stmt->bindValue(':fecha', $fecha, SQLITE3_TEXT);
        $stmt->bindValue(':cantidad', $cantidad, SQLITE3_FLOAT);
        $stmt->bindValue(':pagado_por', $pagado_por, SQLITE3_TEXT);
        $stmt->execute();
        $mensaje = "<div class='alerta exito'>¡Gasto registrado correctamente!</div>";
    } else {
        $mensaje = "<div class='alerta error'>Por favor, rellena todos los campos correctamente.</div>";
    }
}

// Eliminar gasto
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    $stmt = $db->prepare("DELETE FROM gastos WHERE id = :id");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->execute();
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

// ==========================================
// 4. CONSULTAS PARA ESTADÍSTICAS
// ==========================================

// Totales por persona
$res_totales = $db->query("SELECT pagado_por, SUM(cantidad) as total FROM gastos GROUP BY pagado_por");
$totales = array_fill_keys($personas, 0);
while ($row = $res_totales->fetchArray(SQLITE3_ASSOC)) {
    $totales[$row['pagado_por']] = $row['total'];
}

// Total General
$total_general = array_sum($totales);

// Cálculo de Balance (Quién debe a quién)
$mitad_ideal = $total_general / 2;
$balance_texto = "";
$p1 = $personas[0];
$p2 = $personas[1];

if ($totales[$p1] > $totales[$p2]) {
    $deuda = ($totales[$p1] - $totales[$p2]) / 2;
    $balance_texto = "<strong>$p2</strong> le debe a <strong>$p1</strong>: <span class='monto highlight'>" . number_format($deuda, 2) . " €</span>";
} elseif ($totales[$p2] > $totales[$p1]) {
    $deuda = ($totales[$p2] - $totales[$p1]) / 2;
    $balance_texto = "<strong>$p1</strong> le debe a <strong>$p2</strong>: <span class='monto highlight'>" . number_format($deuda, 2) . " €</span>";
} else {
    $balance_texto = "¡Están completamente a la par!";
}

// Gastos por Tipo (Categoría)
$res_tipo = $db->query("SELECT tipo, SUM(cantidad) as total FROM gastos GROUP BY tipo ORDER BY total DESC");
$gastos_tipo = [];
$max_tipo_gasto = 0;
while ($row = $res_tipo->fetchArray(SQLITE3_ASSOC)) {
    $gastos_tipo[$row['tipo']] = $row['total'];
    if ($row['total'] > $max_tipo_gasto) $max_tipo_gasto = $row['total'];
}

// Gastos por Año
$res_anio = $db->query("SELECT strftime('%Y', fecha) as anio, SUM(cantidad) as total FROM gastos GROUP BY anio ORDER BY anio DESC");
$gastos_anio = [];
while ($row = $res_anio->fetchArray(SQLITE3_ASSOC)) {
    $gastos_anio[$row['anio']] = $row['total'];
}

// Últimos 15 gastos para la tabla
$gastos_recientes = $db->query("SELECT * FROM gastos ORDER BY fecha DESC, id DESC LIMIT 15");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Gastos del Hogar</title>
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --bg: #f3f4f6;
            --card: #ffffff;
            --text: #1f2937;
            --text-muted: #4b5563;
            --border: #e5e7eb;
            --success: #10b981;
            --error: #ef4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        body { background-color: var(--bg); color: var(--text); padding: 20px; line-height: 1.5; }
        .container { max-width: 1000px; margin: 0 auto; }
        header { text-align: center; margin-bottom: 30px; }
        header h1 { font-size: 2rem; color: var(--primary); }
        header p { color: var(--text-muted); }
        
        .grid { display: grid; grid-template-columns: 1fr; gap: 20px; }
        @media(min-width: 768px) { .grid { grid-template-columns: 1fr 1fr; } }
        
        .card { background: var(--card); border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border: 1px solid var(--border); }
        .card h2 { font-size: 1.25rem; margin-bottom: 15px; border-bottom: 2px solid var(--bg); padding-bottom: 8px; color: var(--primary); }
        
        /* Formulario */
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 5px; font-size: 0.9rem; }
        .form-control { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 1rem; }
        .form-control:focus { outline: none; border-color: var(--primary); }
        .radio-group { display: flex; gap: 15px; margin-top: 5px; }
        .radio-group label { display: flex; align-items: center; gap: 5px; cursor: pointer; font-weight: normal; }
        .btn { display: inline-block; width: 100%; padding: 12px; background: var(--primary); color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 1rem; transition: background 0.2s; }
        .btn:hover { background: var(--primary-hover); }
        
        /* Alertas */
        .alerta { padding: 10px; border-radius: 6px; margin-bottom: 15px; font-weight: 500; font-size: 0.9rem; }
        .alerta.exito { background: #d1fae5; color: #065f46; }
        .alerta.error { background: #fee2e2; color: #991b1b; }

        /* Stats & Balance */
        .balance-box { background: #e0e7ff; border: 1px solid #c7d2fe; border-radius: 8px; padding: 15px; text-align: center; margin-bottom: 20px; }
        .balance-box p { font-size: 1.1rem; }
        .monto { font-size: 1.4rem; font-weight: bold; color: var(--primary); display: block; margin-top: 5px; }
        
        .resumen-personas { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; text-align: center; margin-bottom: 20px; }
        .persona-total { background: var(--bg); padding: 10px; border-radius: 6px; }
        .persona-total span { font-weight: bold; display: block; font-size: 1.1rem; }

        /* Gráfico de barras nativo CSS */
        .bar-chart { margin-top: 15px; }
        .bar-row { margin-bottom: 10px; }
        .bar-label { display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 2px; }
        .bar-outer { background: var(--bg); height: 12px; border-radius: 6px; overflow: hidden; }
        .bar-inner { background: var(--primary); height: 100%; border-radius: 6px; }

        /* Listas y Tablas */
        .tabla-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem; }
        th, td { padding: 10px; border-bottom: 1px solid var(--border); }
        th { background: var(--bg); font-weight: 600; }
        tr:hover { background-color: #f9fafb; }
        .btn-delete { color: var(--error); text-decoration: none; font-weight: bold; font-size: 1.1rem; }
        .btn-delete:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h1>Gastos Compartidos 🏠</h1>
        <p>Control rápido y sencillo de las finanzas del hogar</p>
    </header>

    <?php echo $mensaje; ?>

    <div class="grid">
        <!-- Tarjeta 1: Formulario -->
        <div class="card">
            <h2>Nuevo Gasto</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="nuevo_gasto">
                
                <div class="form-group">
                    <label for="concepto">Concepto / Descripción</label>
                    <input type="text" id="concepto" name="concepto" class="form-control" placeholder="Ej. Compra Mercadona" required autocomplete="off">
                </div>

                <div class="form-group">
                    <label for="tipo">Categoría</label>
                    <select id="tipo" name="tipo" class="form-control">
                        <?php foreach($categorias as $cat): ?>
                            <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="fecha">Fecha</label>
                    <input type="date" id="fecha" name="fecha" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label for="cantidad">Cantidad (€)</label>
                    <input type="number" id="cantidad" name="cantidad" class="form-control" step="0.01" min="0.01" placeholder="0.00" required>
                </div>

                <div class="form-group">
                    <label>¿Quién ha pagado?</label>
                    <div class="radio-group">
                        <?php foreach($personas as $p): ?>
                            <label>
                                <input type="radio" name="pagado_por" value="<?php echo $p; ?>" required> <?php echo $p; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" class="btn">Registrar Gasto</button>
            </form>
        </div>

        <!-- Tarjeta 2: Estadísticas y Balance -->
        <div class="card">
            <h2>Estado de Cuentas</h2>
            
            <div class="balance-box">
                <p><?php echo $balance_texto; ?></p>
            </div>

            <div class="resumen-personas">
                <div class="persona-total">
                    <small>Total <?php echo $personas[0]; ?></small>
                    <span><?php echo number_format($totales[$personas[0]], 2); ?> €</span>
                </div>
                <div class="persona-total">
                    <small>Total <?php echo $personas[1]; ?></small>
                    <span><?php echo number_format($totales[$personas[1]], 2); ?> €</span>
                </div>
            </div>

            <h3>Gastos por Categoría</h3>
            <div class="bar-chart">
                <?php if(empty($gastos_tipo)): ?>
                    <p style="font-size:0.9rem; color:var(--text-muted);">Aún no hay datos registrados.</p>
                <?php else: ?>
                    <?php foreach($gastos_tipo as $tipo => $total): 
                        $porcentaje = $max_tipo_gasto > 0 ? ($total / $max_tipo_gasto) * 100 : 0;
                    ?>
                        <div class="bar-row">
                            <div class="bar-label">
                                <span><?php echo $tipo; ?></span>
                                <strong><?php echo number_format($total, 2); ?> €</strong>
                            </div>
                            <div class="bar-outer">
                                <div class="bar-inner" style="width: <?php echo $porcentaje; ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <h3 style="margin-top: 20px;">Historial Anual</h3>
            <ul style="list-style: none; margin-top: 10px; font-size: 0.9rem;">
                <?php foreach($gastos_anio as $anio => $total): ?>
                    <li style="display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px dashed var(--border);">
                        <span>Año <?php echo $anio; ?></span>
                        <strong><?php echo number_format($total, 2); ?> €</strong>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- Sección Inferior: Historial Reciente -->
    <div class="card" style="margin-top: 20px;">
        <h2>Últimos Gastos Registrados</h2>
        <div class="tabla-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Concepto</th>
                        <th>Categoría</th>
                        <th>Pagado Por</th>
                        <th>Cantidad</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $tiene_gastos = false; ?>
                    <?php while ($gasto = $gastos_recientes->fetchArray(SQLITE3_ASSOC)): 
                        $tiene_gastos = true; ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($gasto['fecha'])); ?></td>
                            <td><?php echo htmlspecialchars($gasto['concepto']); ?></td>
                            <td><span style="background: var(--bg); padding: 3px 8px; border-radius: 12px; font-size: 0.8rem;"><?php echo $gasto['tipo']; ?></span></td>
                            <td><strong><?php echo $gasto['pagado_por']; ?></strong></td>
                            <td style="font-weight: bold;"><?php echo number_format($gasto['cantidad'], 2); ?> €</td>
                            <td>
                                <a href="?eliminar=<?php echo $gasto['id']; ?>" class="btn-delete" onclick="return confirm('¿Seguro que quieres borrar este gasto?');" title="Eliminar">×</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if (!$tiene_gastos): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--text-muted);">No hay gastos registrados todavía.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>
