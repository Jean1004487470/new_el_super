<?php
define('SECURE_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Verificar permisos
verificarAcceso('ver_actividad');

$db = getDBConnection();

$message = '';
$message_type = '';

// Paginación
$registros_por_pagina = 10;
$pagina = isset($_GET['pagina']) ? filter_var($_GET['pagina'], FILTER_VALIDATE_INT) : 1;
$offset = ($pagina - 1) * $registros_por_pagina;

// Búsqueda
$search = sanitizeInput($_GET['search'] ?? '');
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';
$search_query = '';
$search_params = [];

if (!empty($search)) {
    $search_query .= " WHERE (u.usuario LIKE :search OR a.accion LIKE :search_accion OR a.detalle LIKE :search_detalle)";
    $search_params[':search'] = '%' . $search . '%';
    $search_params[':search_accion'] = '%' . $search . '%';
    $search_params[':search_detalle'] = '%' . $search . '%';
}
if (!empty($fecha_inicio)) {
    $search_query .= (empty($search_query) ? ' WHERE ' : ' AND ') . "ra.fecha_actividad >= :fecha_inicio";
    $search_params[':fecha_inicio'] = $fecha_inicio . ' 00:00:00';
}
if (!empty($fecha_fin)) {
    $search_query .= (empty($search_query) ? ' WHERE ' : ' AND ') . "ra.fecha_actividad <= :fecha_fin";
    $search_params[':fecha_fin'] = $fecha_fin . ' 23:59:59';
}

// Consulta total de registros para paginación
$stmt_count = $db->prepare("SELECT COUNT(*) FROM registro_actividades ra JOIN usuarios u ON ra.id_usuario = u.id" . $search_query);
$stmt_count->execute($search_params);
$total_registros = $stmt_count->fetchColumn();
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Consulta de actividades
$stmt_actividades = $db->prepare("
    SELECT ra.id, ra.accion, ra.detalle, ra.fecha_actividad, u.usuario
    FROM registro_actividades ra
    JOIN usuarios u ON ra.id_usuario = u.id
    " . $search_query . "
    ORDER BY ra.fecha_actividad DESC
    LIMIT :limit OFFSET :offset
");
$stmt_actividades->bindParam(':limit', $registros_por_pagina, PDO::PARAM_INT);
$stmt_actividades->bindParam(':offset', $offset, PDO::PARAM_INT);
foreach ($search_params as $key => &$val) {
    $stmt_actividades->bindParam($key, $val, PDO::PARAM_STR);
}
$stmt_actividades->execute();
$actividades = $stmt_actividades->fetchAll();

// Obtener información del usuario actual para el navbar
$user_info = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $db->prepare("
        SELECT e.nombre, e.apellido, r.nombre_rol
        FROM usuarios u
        LEFT JOIN empleados e ON u.id = e.id_usuario
        JOIN roles r ON u.id_rol = r.id
        WHERE u.id = :user_id
    ");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user_info = $stmt->fetch();

    if (!$user_info) {
        // Fallback si no hay información de empleado
        $stmt = $db->prepare("
            SELECT u.usuario as nombre, r.nombre_rol
            FROM usuarios u
            JOIN roles r ON u.id_rol = r.id
            WHERE u.id = :user_id
        ");
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        $user_info = $stmt->fetch();
        $user_info['apellido'] = ''; // Para evitar errores si se espera el apellido
    }
}

if (!isset($user_info)) {
    $user_info = ['nombre' => 'Usuario', 'apellido' => '', 'nombre_rol' => ''];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Actividad - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/modern.css" rel="stylesheet">
</head>
<body>
    <?php include_once '../includes/navbar.php'; ?>
    <aside class="sidebar">
        <div class="sidebar-title">Menú</div>
        <ul class="sidebar-nav">
            <li><a href="../clientes/consulta.php"><i class="bi bi-people"></i> <span>Clientes</span></a></li>
            <li><a href="../empleados/consulta.php"><i class="bi bi-person-badge"></i> <span>Empleados</span></a></li>
            <li><a href="../productos/consulta.php"><i class="bi bi-box"></i> <span>Productos</span></a></li>
            <li><a href="../ventas/consulta.php"><i class="bi bi-cash-stack"></i> <span>Ventas</span></a></li>
            <li><a href="../inventario/consulta.php"><i class="bi bi-bar-chart"></i> <span>Inventario</span></a></li>
            <li><a href="../usuarios/consulta.php"><i class="bi bi-gear"></i> <span>Gestión de Usuarios</span></a></li>
            <li><a href="../actividad/consulta.php" class="active"><i class="bi bi-clipboard-data"></i> <span>Actividad</span></a></li>
        </ul>
    </aside>
    <main class="main-content">
        <div class="container-fluid">
            <h2 class="mb-4"><i class="bi bi-clipboard-data"></i> Registro de Actividad</h2>
            <div class="card mb-3">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-center">
                        <div class="col-md-4">
                            <input type="text" class="form-control" name="search" placeholder="Buscar por usuario, acción o detalle" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <input type="date" class="form-control" name="fecha_inicio" value="<?php echo htmlspecialchars($fecha_inicio); ?>" placeholder="Fecha inicio">
                        </div>
                        <div class="col-md-3">
                            <input type="date" class="form-control" name="fecha_fin" value="<?php echo htmlspecialchars($fecha_fin); ?>" placeholder="Fecha fin">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Buscar</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Usuario</th>
                                    <th>Acción</th>
                                    <th>Detalle</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($actividades)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No se encontraron registros de actividad</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($actividades as $act): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($act['id']); ?></td>
                                            <td><?php echo htmlspecialchars($act['usuario']); ?></td>
                                            <td><?php echo htmlspecialchars($act['accion']); ?></td>
                                            <td><?php echo htmlspecialchars($act['detalle']); ?></td>
                                            <td><?php echo htmlspecialchars($act['fecha_actividad']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($total_paginas > 1): ?>
                        <nav aria-label="Navegación de páginas">
                            <ul class="pagination justify-content-center">
                                <?php if ($pagina > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?pagina=<?php echo $pagina - 1; ?>&search=<?php echo urlencode($search); ?>&fecha_inicio=<?php echo urlencode($fecha_inicio); ?>&fecha_fin=<?php echo urlencode($fecha_fin); ?>">Anterior</a>
                                    </li>
                                <?php endif; ?>
                                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                    <li class="page-item <?php echo ($i == $pagina) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?pagina=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&fecha_inicio=<?php echo urlencode($fecha_inicio); ?>&fecha_fin=<?php echo urlencode($fecha_fin); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <?php if ($pagina < $total_paginas): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?pagina=<?php echo $pagina + 1; ?>&search=<?php echo urlencode($search); ?>&fecha_inicio=<?php echo urlencode($fecha_inicio); ?>&fecha_fin=<?php echo urlencode($fecha_fin); ?>">Siguiente</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 