<?php
include "conexion.php";
session_start();
// Verificar si hay una sesión activa de administrador
if (!isset($_SESSION['admin_id'])) {
    header("Location: login_admin.php");
    exit;
}
$db = new DBconexion();
$mensaje = '';
$admin_id = $_SESSION['admin_id'];
// Procesar carga de CSV
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['archivo_csv']) && $_FILES['archivo_csv']['error'] == 0) {
    $archivo = $_FILES['archivo_csv'];
    $nombre_archivo = $archivo['name'];

    // Verificar si el archivo es un CSV
    if ($archivo['type'] == 'text/csv' || $archivo['type'] == 'application/vnd.ms-excel' || $archivo['type'] == 'application/octet-stream') {
        $fp = fopen($archivo['tmp_name'], "r");
        // Leer la primera línea que contiene los encabezados
        $encabezados = fgetcsv($fp, 1000, ",");

        $linea = 0;
        $registros_importados = 0;
        $registros_invalidos = 0;

        while ($data = fgetcsv($fp, 1000, ",")):
            $linea++;

            // Verificar que la línea tenga suficientes columnas
            if (count($data) < 8) {
                $registros_invalidos++;
                continue; // Saltar al siguiente registro
            }

            // Manejar la fecha desde el formato de Google Forms
            $fecha_original = isset($data[0]) ? $data[0] : ""; // "2025/02/26 7:29:52 p.Â m. GMT-3"

            // Limpiar y convertir la fecha
            $fecha_limpia = preg_replace('/\s+GMT-\d+/', '', $fecha_original);
            $fecha_limpia = str_replace('Â', '', $fecha_limpia);

            try {
                $fecha_objeto = new DateTime($fecha_limpia);
                $fecha_convertida = $fecha_objeto->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                $fecha_convertida = "0000-00-00 00:00:00"; // Valor por defecto
            }

            // Escapar datos para prevenir inyección SQL - con validación
            $rut = isset($data[1]) ? $db->conexion->real_escape_string($data[1]) : "";
            $nombre = isset($data[2]) ? $db->conexion->real_escape_string($data[2]) : "";
            $correo = isset($data[3]) ? $db->conexion->real_escape_string($data[3]) : "";
            $comuna = isset($data[4]) ? $db->conexion->real_escape_string($data[4]) : "";
            $celular = isset($data[5]) ? $db->conexion->real_escape_string($data[5]) : "";
            $profesion = isset($data[6]) ? $db->conexion->real_escape_string($data[6]) : "";
            $genero = isset($data[7]) ? $db->conexion->real_escape_string($data[7]) : "";

            // Verificar que datos esenciales no estén vacíos
            if (empty($rut) || empty($nombre)) {
                $registros_invalidos++;
                continue; // Saltar al siguiente registro
            }

            // Validación simplificada: verificar solo por RUT+NOMBRE
            // Esto permitirá insertar registros con el mismo RUT pero nombres diferentes
            $condicion = "Rut = '$rut' AND Nombre = '$nombre'";

            $existe = $db->search("datos_personales", $condicion);

            if (!$existe) {
                $resultado = $db->insert(
                    "datos_personales",
                    "'$fecha_convertida','$rut','$nombre','$correo','$comuna','$celular','$profesion','$genero'"
                );

                if ($resultado) {
                    $registros_importados++;

                    // Verificar si el RUT ya existe en la tabla espejo
                    $existe_en_espejo = $db->search("datos_personales_unicos", "Rut = '$rut'");

                    if (!$existe_en_espejo) {
                        // Si no existe, insertar nuevo registro
                        $db->insert(
                            "datos_personales_unicos",
                            "'$rut','$nombre','$correo','$comuna','$celular','$profesion','$genero','$fecha_convertida','$fecha_convertida',1"
                        );
                    } else {
                        // Si ya existe, actualizar cantidad de ingresos y última fecha
                        $cantidad_actual = $existe_en_espejo[0]['Cantidad_Ingresos'];
                        $nueva_cantidad = $cantidad_actual + 1;

                        $db->update(
                            "datos_personales_unicos",
                            "Nombre = '$nombre', 
                        Correo = '$correo', 
                        Comuna = '$comuna', 
                        Celular = '$celular', 
                        Profesion = '$profesion', 
                        Genero = '$genero', 
                        Ultima_Fecha = '$fecha_convertida', 
                        Cantidad_Ingresos = $nueva_cantidad",
                            "Rut = '$rut'"
                        );
                    }
                }
            }
        endwhile;
        fclose($fp);
        // Registrar la carga de CSV
        $ip = $_SERVER['REMOTE_ADDR'];
        $fecha_carga = date('Y-m-d H:i:s');
        $db->insert(
            "cargas_csv",
            "$admin_id, '$nombre_archivo', $registros_importados, '$fecha_carga', '$ip'",
            "id_admin, nombre_archivo, registros_importados, fecha_carga, ip"
        );

        if ($registros_importados > 0) {
            $mensaje = "<div class='alert success'>Datos importados correctamente! Se importaron $registros_importados registros nuevos.</div>";
            if ($registros_invalidos > 0) {
                $mensaje .= "<div class='alert warning'>Se encontraron $registros_invalidos registros inválidos o incompletos que fueron ignorados.</div>";
            }
        } else {
            if ($registros_invalidos > 0) {
                $mensaje = "<div class='alert warning'>No se importaron registros. Se encontraron $registros_invalidos registros inválidos o incompletos.</div>";
            } else {
                $mensaje = "<div class='alert warning'>No se encontraron registros nuevos para importar.</div>";
            }
        }
    } else {
        $mensaje = "<div class='alert error'>Por favor, seleccione un archivo CSV. Tipo detectado: " . $archivo['type'] . "</div>";
    }
} else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Si se envió el formulario pero hay error con el archivo
    if (!isset($_FILES['archivo_csv']) || $_FILES['archivo_csv']['error'] != 0) {
        $error_code = isset($_FILES['archivo_csv']) ? $_FILES['archivo_csv']['error'] : 'No se recibió archivo';
        $mensaje = "<div class='alert error'>Error al cargar el archivo. Código de error: $error_code</div>";
    }
}

// Obtener historial de cargas para este administrador
$historial_cargas = $db->search("cargas_csv", "id_admin = $admin_id ORDER BY fecha_carga DESC LIMIT 10");

// Obtener los datos personales registrados - Limitado a 50 registros para no sobrecargar la página
$datos_personales = $db->search("datos_personales", "1=1 ORDER BY Fecha DESC LIMIT 50");

// Obtener los datos personales únicos - Limitado a 50 registros
$datos_personales_unicos = $db->search("datos_personales_unicos", "1=1 ORDER BY Cantidad_Ingresos DESC LIMIT 50");

// Obtener registros de accesos de usuarios - Limitado a 100 registros
$sql_accesos = "SELECT rau.*, dpu.Nombre as nombre_usuario 
               FROM registro_accesos_usuarios rau 
               LEFT JOIN datos_personales_unicos dpu ON rau.rut_usuario = dpu.Rut
               ORDER BY rau.fecha_acceso DESC LIMIT 100";

$registros_accesos = [];
$resultado_accesos = $db->conexion->query($sql_accesos);

if ($resultado_accesos) {
    while ($row = $resultado_accesos->fetch_assoc()) {
        // Verificar si la sesión realmente debería considerarse activa
        // 1. Si la sesión está marcada como inactiva, mantener como inactiva
        // 2. Si la sesión está marcada como activa, verificar el tiempo:
        //    - Si fue en las últimas 8 horas, mantener como activa
        //    - Si fue hace más de 8 horas, cambiar a inactiva

        if ($row['sesion_activa'] == 1) {
            $fecha_acceso = strtotime($row['fecha_acceso']);
            $tiempo_limite = strtotime('-8 hours');

            if ($fecha_acceso < $tiempo_limite) {
                // La sesión es vieja, debería estar inactiva
                $row['estado_real'] = 0; // Inactiva

                // Actualizar en la base de datos para futuras consultas
                $db->update(
                    "registro_accesos_usuarios",
                    "sesion_activa = 0",
                    "id = {$row['id']}"
                );
            } else {
                // La sesión es reciente, mantenerla activa
                $row['estado_real'] = 1; // Activa
            }
        } else {
            // Ya está marcada como inactiva
            $row['estado_real'] = 0; // Inactiva
        }

        $registros_accesos[] = $row;
    }
}

// Obtener nombre del administrador
$nombre_admin = $_SESSION['admin_nombre'];
// Botón para cerrar todas las sesiones inactivas
if (isset($_GET['cerrar_sesiones_antiguas'])) {
    $tiempo_limite = date('Y-m-d H:i:s', strtotime('-8 hours'));

    $db->update(
        "registro_accesos_usuarios",
        "sesion_activa = 0",
        "sesion_activa = 1 AND fecha_acceso < '$tiempo_limite'"
    );

    $filas_actualizadas = $db->conexion->affected_rows;
    if ($filas_actualizadas > 0) {
        $mensaje = "<div class='alert success'>Se han cerrado $filas_actualizadas sesiones antiguas.</div>";
    } else {
        $mensaje = "<div class='alert info'>No se encontraron sesiones antiguas para cerrar.</div>";
    }

    // Recargar la página sin el parámetro de URL
    header("Location: panel_admin.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrador - HUB Providencia</title>
    <link rel="stylesheet" href="estilos.css">
    <style>
        /* Estilos para el grid layout */
        .admin-section {
            margin-bottom: 30px;
        }

        .grid-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-rows: auto auto;
            gap: 20px;
            margin-bottom: 30px;
            width: 100%;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .full-width {
            grid-column: 1 / span 2;
        }

        .grid-item {
            background-color: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .card.admin-panel {
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .admin-table th,
        .admin-table td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .admin-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }

        .admin-table tr:hover {
            background-color: #f5f5f5;
        }

        .table-responsive {
            overflow-x: auto;
            /* Scroll horizontal */
            max-height: 350px;
            /* Altura máxima */
            overflow-y: auto;
            /* Scroll vertical */
        }

        .admin-table thead {
            position: sticky;
            top: 0;
            background-color: #f2f2f2;
            z-index: 1;
        }


        /* Alineación del botón de importar */
        .btn-primary {
            background-color: #28c48d;
            border: none;
            color: white;
            padding: 10px 20px;
            text-align: center;
            display: block;
            margin-top: 15px;
            cursor: pointer;
            border-radius: 5px;
        }

        .btn-secondary {
            background-color: #6c757d;
            border: none;
            color: white;
            padding: 8px 16px;
            text-align: center;
            display: inline-block;
            margin-right: 10px;
            cursor: pointer;
            border-radius: 5px;
            font-size: 14px;
            text-decoration: none;
        }

        .acciones-container {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 15px;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Estilos para la búsqueda */
        .search-container {
            margin-bottom: 15px;
        }

        .search-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .search-input:focus {
            border-color: #28c48d;
            outline: none;
            box-shadow: 0 0 0 2px rgba(40, 196, 141, 0.2);
        }

        /* Estilos para la alerta de éxito */
        .alert {
            padding: 12px 15px;
            margin-bottom: 15px;
            border-radius: 4px;
        }

        .alert.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert.warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .alert.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert.info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        /* Estilos para la tabla de accesos */
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-success {
            background-color: #28c48d;
            color: white;
        }

        .badge-danger {
            background-color: #dc3545;
            color: white;
        }

        .action-link {
            color: #0066cc;
            text-decoration: none;
            font-size: 13px;
            margin-left: 5px;
        }

        .action-link:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="container">
        <header>
            <div class="logo-container">
                <img src="img/logo_providencia.png" alt="Logo Providencia" class="logo">
                <div class="logo-text">
                    <span class="soypro">soypro</span><span class="videncia">videncia</span>
                </div>
            </div>
        </header>

        <main>
            <div class="card admin-panel">
                <div class="admin-header">
                    <h1>Panel de Administrador</h1>
                    <p class="admin-welcome">Bienvenido, <strong><?php echo htmlspecialchars($nombre_admin); ?></strong></p>
                </div>

                <?php if ($mensaje): ?>
                    <?php echo $mensaje; ?>
                <?php endif; ?>

                <div class="grid-container">
                    <!-- Primer panel: Cargar datos CSV -->
                    <div class="grid-item">
                        <h2>Cargar datos CSV</h2>
                        <p>Seleccione el archivo CSV descargado desde Google Forms para importar los datos.</p>
                        <form action="" method="POST" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="archivo_csv">Seleccionar archivo CSV:</label>
                                <input type="file" name="archivo_csv" id="archivo_csv" accept=".csv,.txt" required>
                            </div>
                            <button type="submit" class="btn-primary">Importar Datos</button>
                        </form>
                    </div>

                    <!-- Segundo panel: Historial cargas -->
                    <div class="grid-item">
                        <h2>Historial cargas</h2>
                        <div class="search-container">
                            <input type="text" id="buscarHistorial" placeholder="Buscar en historial..." class="search-input">
                        </div>
                        <?php if ($historial_cargas): ?>
                            <div class="table-responsive">
                                <table class="admin-table" id="tablaHistorial">
                                    <thead>
                                        <tr>
                                            <th>Archivo</th>
                                            <th>Registros</th>
                                            <th>Fecha</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($historial_cargas as $carga): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($carga['nombre_archivo']); ?></td>
                                                <td><?php echo $carga['registros_importados']; ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($carga['fecha_carga'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p>No hay cargas registradas aún.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Tercer panel: Datos personales registrados -->
                    <div class="grid-item">
                        <h2>Datos personales registrados</h2>
                        <div class="search-container">
                            <input type="text" id="buscarDatos" placeholder="Buscar en datos personales..." class="search-input">
                        </div>
                        <?php if ($datos_personales): ?>
                            <div class="table-responsive">
                                <table class="admin-table" id="tablaDatos">
                                    <thead>
                                        <tr>
                                            <th>RUT</th>
                                            <th>Nombre</th>
                                            <th>Correo</th>
                                            <th>Celular</th>
                                            <th>Fecha Registro</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($datos_personales as $persona): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($persona['Rut']); ?></td>
                                                <td><?php echo htmlspecialchars($persona['Nombre']); ?></td>
                                                <td><?php echo htmlspecialchars($persona['Correo']); ?></td>
                                                <td><?php echo htmlspecialchars($persona['Celular']); ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($persona['Fecha'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p>No hay datos personales registrados aún.</p>
                        <?php endif; ?>
                    </div>
                    <!-- Cuarto panel: Personas Únicas por RUT -->
                    <div class="grid-item">
                        <?php
                        // Filtrar solo personas con más de un ingreso
                        $personas_repetidas = [];
                        $contador_repetidos = 0;

                        if ($datos_personales_unicos) {
                            foreach ($datos_personales_unicos as $persona) {
                                if ($persona['Cantidad_Ingresos'] > 1) {
                                    $personas_repetidas[] = $persona;
                                    $contador_repetidos++;
                                }
                            }
                        }
                        ?>
                        <h2>Personas Únicas por RUT <span class="contador">(<?php echo $contador_repetidos; ?>)</span></h2>
                        <div class="search-container">
                            <input type="text" id="buscarUnicos" placeholder="Buscar en personas únicas..." class="search-input">
                        </div>
                        <?php if ($contador_repetidos > 0): ?>
                            <div class="table-responsive">
                                <table class="admin-table" id="tablaUnicos">
                                    <thead>
                                        <tr>
                                            <th>RUT</th>
                                            <th>Nombre</th>
                                            <th>Veces registrado</th>
                                            <th>Primera vez</th>
                                            <th>Última vez</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($personas_repetidas as $persona): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($persona['Rut']); ?></td>
                                                <td><?php echo htmlspecialchars($persona['Nombre']); ?></td>
                                                <td><?php echo $persona['Cantidad_Ingresos']; ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($persona['Primera_Fecha'])); ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($persona['Ultima_Fecha'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p>No hay personas registradas más de una vez.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Nueva sección: Registro de accesos de usuarios -->
                    <div class="grid-item full-width">
                        <h2>Registro de Accesos de Usuarios</h2>

                        <div class="acciones-container">
                            <a href="panel_admin.php?cerrar_sesiones_antiguas=1" class="btn-secondary">Cerrar sesiones antiguas</a>
                        </div>

                        <div class="search-container">
                            <input type="text" id="buscarAccesos" placeholder="Buscar en registros de acceso..." class="search-input">
                        </div>
                        <?php if ($registros_accesos): ?>
                            <div class="table-responsive">
                                <table class="admin-table" id="tablaAccesos">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>RUT Usuario</th>
                                            <th>Nombre Usuario</th>
                                            <th>Fecha y Hora</th>
                                            <th>Estado</th>
                                            <th>Razón de Visita</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($registros_accesos as $acceso): ?>
                                            <tr>
                                                <td><?php echo $acceso['id']; ?></td>
                                                <td><?php echo htmlspecialchars($acceso['rut_usuario']); ?></td>
                                                <td><?php echo htmlspecialchars($acceso['nombre_usuario'] ?? 'Desconocido'); ?></td>
                                                <td><?php echo date('d/m/Y H:i:s', strtotime($acceso['fecha_acceso'])); ?></td>
                                                <td>
                                                    <?php if ($acceso['estado_real'] == 1): ?>
                                                        <span class="badge badge-success">Activa</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-danger">Cerrada</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo isset($acceso['razon_visita']) ? htmlspecialchars($acceso['razon_visita']) : 'No especificada'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p>No hay registros de acceso de usuarios.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-links">
                    <p><a href="javascript:void(0)" class="logout-link" onclick="cerrarSesionCompleta()">Cerrar sesión</a></p>
                </div>
            </div>
        </main>

        <footer>
            <div class="logo-container small">
                <img src="img/logo_providencia.png" alt="Logo Providencia" class="logo small">
                <div class="logo-text small">
                    <span class="soypro">soypro</span><span class="videncia">videncia</span>
                </div>
            </div>
        </footer>
    </div>

    <script>
        // Función para cerrar sesión de administrador y usuario
        function cerrarSesionCompleta() {
            if (confirm('¿Seguro que desea cerrar la sesión?')) {
                // Simplemente redirigimos al cierre de sesión de administrador
                window.location.href = 'login_admin.php?logout=1';
            }
            return false;
        }

        // Función general para filtrar tablas
        function filtrarTabla(inputId, tableId) {
            document.getElementById(inputId).addEventListener('keyup', function() {
                const searchText = this.value.toLowerCase();
                const table = document.getElementById(tableId);
                const rows = table.getElementsByTagName('tr');

                // Empezamos desde 1 para saltar la fila de encabezados
                for (let i = 1; i < rows.length; i++) {
                    let found = false;
                    const cells = rows[i].getElementsByTagName('td');

                    for (let j = 0; j < cells.length; j++) {
                        const cellText = cells[j].textContent || cells[j].innerText;
                        if (cellText.toLowerCase().indexOf(searchText) > -1) {
                            found = true;
                            break;
                        }
                    }

                    rows[i].style.display = found ? '' : 'none';
                }
            });
        }

        // Inicializar la búsqueda para todas las tablas
        document.addEventListener('DOMContentLoaded', function() {
            filtrarTabla('buscarHistorial', 'tablaHistorial');
            filtrarTabla('buscarDatos', 'tablaDatos');
            filtrarTabla('buscarUnicos', 'tablaUnicos');
            filtrarTabla('buscarAccesos', 'tablaAccesos');
        });
    </script>
</body>

</html>