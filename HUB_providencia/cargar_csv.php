<?php
include "conexion.php";
session_start();

// Verificar si hay una sesión activa de administrador
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
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

// Obtener nombre del administrador
$nombre_admin = $_SESSION['admin_nombre'];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrador - HUB Providencia</title>
    <link rel="stylesheet" href="estilos.css">
    <style>
        /* Estilos adicionales para la tabla de datos personales */
        .admin-section {
            margin-bottom: 30px;
        }

        .flex-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .flex-item {
            flex: 1;
            min-width: 300px;
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

                <section class="admin-section">
                    <h2>Cargar Datos desde CSV</h2>
                    <p>Seleccione el archivo CSV descargado desde Google Forms para importar los datos.</p>
                    <p class="note">Nota: Los registros que tengan la misma combinación de RUT y Nombre serán considerados duplicados y no se importarán nuevamente.</p>

                    <form action="" method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="archivo_csv">Seleccionar archivo CSV:</label>
                            <input type="file" name="archivo_csv" id="archivo_csv" accept=".csv,.txt" required>
                        </div>
                        <button type="submit" class="btn-primary">Importar Datos</button>
                    </form>
                </section>

                <div class="flex-container">
                    <section class="admin-section flex-item">
                        <h2>Historial de Cargas</h2>

                        <?php if ($historial_cargas): ?>
                            <div class="table-responsive">
                                <table class="admin-table">
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
                    </section>

                    <section class="admin-section flex-item">
                        <h2>Datos Personales Registrados</h2>

                        <?php if ($datos_personales): ?>
                            <div class="table-responsive">
                                <table class="admin-table">
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
                    </section>
                </div>

                <div class="form-links">
                    <p><a href="index.php?logout=1" class="logout-link">Cerrar sesión</a></p>
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
</body>

</html>