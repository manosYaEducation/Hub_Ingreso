<?php
include "conexion.php";
session_start();
// Procesar el cierre de sesión
if (isset($_GET['logout'])) {
    // Antes de destruir la sesión, marcar como inactiva en la base de datos
    if (isset($_SESSION['usuario_id'])) {
        $rut = $_SESSION['usuario_id'];
        $sesion_activa = 0; // 0 = sesión inactiva

        // Actualizar el último registro para este usuario
        $db = new DBconexion();

        // Usar una sentencia SQL más específica para asegurar que se actualice correctamente
        $sql = "UPDATE registro_accesos_usuarios SET sesion_activa = 0 
                WHERE rut_usuario = '$rut' AND sesion_activa = 1";

        $db->conexion->query($sql);
    }
    // Destruir la sesión
    session_unset();
    session_destroy();

    // Si se está redirigiendo desde el panel de administrador, no redireccionar
    if (isset($_GET['admin_redirect'])) {
        echo "Sesión cerrada";
        exit;
    }
    // Limpiar cookies y forzar nueva carga
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Location: login_usuario.php");
    exit;
}
// Si ya hay una sesión activa de usuario, redirigir al panel
if (isset($_SESSION['usuario_id'])) {
    header("Location: panel_usuario.php");
    exit;
}

$db = new DBconexion();
$mensaje = '';
$paso = 1; // Iniciar en el primer paso
$datos_form = [];

// Si hay datos en sesión, recuperarlos
if (isset($_SESSION['temp_form_data'])) {
    $datos_form = $_SESSION['temp_form_data'];
    $paso = $_SESSION['temp_paso'];
}

// Procesar formulario paso a paso
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // PASO 1: Validar RUT
    if (isset($_POST['rut']) && $paso == 1) {
        $rut = $db->conexion->real_escape_string($_POST['rut']);

        // Verificar si el RUT existe
        $usuario = $db->search("datos_personales_unicos", "Rut = '$rut'");

        if ($usuario) {
            // Guardar datos y avanzar al siguiente paso
            $datos_form['rut'] = $rut;
            $datos_form['nombre'] = $usuario[0]['Nombre']; // Guardar nombre para mostrarlo después
            $paso = 2;
            $_SESSION['temp_form_data'] = $datos_form;
            $_SESSION['temp_paso'] = $paso;
        } else {
            // El RUT no existe, mostrar mensaje y enlace al formulario de registro
            $mensaje = "<div class='alert info'>Este RUT no está registrado en nuestro sistema.</div>";
            $_SESSION['rut_no_registrado'] = true;
        }
    }

    // PASO 2: Validar correo
    else if (isset($_POST['correo']) && $paso == 2) {
        $correo = $db->conexion->real_escape_string($_POST['correo']);
        $rut = $datos_form['rut'];

        // Verificar si la combinación de RUT y correo existe
        $usuario = $db->search("datos_personales_unicos", "Rut = '$rut' AND Correo = '$correo'");

        if ($usuario) {
            // Guardar datos y avanzar al siguiente paso
            $datos_form['correo'] = $correo;
            $paso = 3;
            $_SESSION['temp_form_data'] = $datos_form;
            $_SESSION['temp_paso'] = $paso;
        } else {
            $mensaje = "<div class='alert error'>El correo electrónico no coincide con nuestros registros para este RUT.</div>";
        }
    }

    // PASO 3: Registrar razón de visita y completar login
    else if (isset($_POST['razon_visita']) && $paso == 3) {
        $razon_visita = $db->conexion->real_escape_string($_POST['razon_visita']);
        $rut = $datos_form['rut'];
        $correo = $datos_form['correo'];

        // Validación final completada
        // Verificar que no tenga registros duplicados
        $usuario = $db->search("datos_personales_unicos", "Rut = '$rut'");

        if ($usuario && $usuario[0]['Cantidad_Ingresos'] == 1) {
            // Login exitoso - eliminar datos temporales
            unset($_SESSION['temp_form_data']);
            unset($_SESSION['temp_paso']);

            // Establecer sesión de usuario
            $_SESSION['usuario_id'] = $rut;
            $_SESSION['usuario_nombre'] = $usuario[0]['Nombre'];
            $_SESSION['usuario_correo'] = $correo;

            // Registrar acceso con sesión activa y razón de visita
            $sesion_activa = 1; // 1 = sesión activa
            $fecha = date('Y-m-d H:i:s');
            $db->insert(
                "registro_accesos_usuarios",
                "NULL, '$rut', '$fecha', $sesion_activa, '$razon_visita'",
                "id, rut_usuario, fecha_acceso, sesion_activa, razon_visita"
            );

            header("Location: panel_usuario.php");
            exit;
        } else {
            $mensaje = "<div class='alert error'>No puede iniciar sesión porque su RUT aparece en múltiples registros.</div>";
            // Reiniciar el proceso
            $paso = 1;
            unset($_SESSION['temp_form_data']);
            unset($_SESSION['temp_paso']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso de Usuario - HUB Providencia</title>
    <link rel="stylesheet" href="estilos.css">
    <style>
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }

        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #ddd;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
        }

        .step.active {
            background-color: #28c48d;
            color: white;
        }

        .step-title {
            text-align: center;
            margin-bottom: 20px;
            font-size: 1.2rem;
            color: #555;
        }

        .btn-primary {
            background-color: #28c48d;
            border: none;
            color: white;
            padding: 12px 20px;
            text-align: center;
            display: block;
            width: 100%;
            margin-top: 15px;
            cursor: pointer;
            border-radius: 5px;
            font-size: 16px;
        }

        .input-field {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }

        .input-field:focus {
            border-color: #28c48d;
            outline: none;
            box-shadow: 0 0 0 2px rgba(40, 196, 141, 0.2);
        }

        .alert {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 14px;
        }

        .alert.error {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }

        .alert.info {
            background-color: #e3f2fd;
            color: #0d47a1;
            border: 1px solid #90caf9;
        }

        .form-links {
            margin-top: 20px;
            text-align: center;
        }

        .register-btn {
            background-color: #2196F3;
            border: none;
            color: white;
            padding: 10px 20px;
            text-align: center;
            display: inline-block;
            margin-top: 15px;
            cursor: pointer;
            border-radius: 5px;
            font-size: 14px;
            text-decoration: none;
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
            <div class="card">
                <h1>Acceso de Usuario</h1>

                <?php if (!isset($_SESSION['rut_no_registrado'])): ?>
                    <!-- Indicador de pasos -->
                    <div class="step-indicator">
                        <div class="step <?php echo $paso >= 1 ? 'active' : ''; ?>">1</div>
                        <div class="step <?php echo $paso >= 2 ? 'active' : ''; ?>">2</div>
                        <div class="step <?php echo $paso >= 3 ? 'active' : ''; ?>">3</div>
                    </div>
                <?php endif; ?>

                <?php if ($mensaje): ?>
                    <?php echo $mensaje; ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['rut_no_registrado'])): ?>
                    <!-- Mostrar mensaje de registro -->
                    <div class="step-title">Usuario no registrado</div>
                    <p>Para poder acceder al sistema, necesitas registrarte primero.</p>
                    <a href="https://docs.google.com/forms/d/e/1FAIpQLSdtbdteq7R1DENSe02CTNAVqPKHP3BumzNwcrJI9IML7-qsjA/viewform?usp=header" class="register-btn" target="_blank">Registrarse como nuevo usuario</a>
                    <div class="form-links">
                        <p><a href="login_usuario.php" onclick="<?php unset($_SESSION['rut_no_registrado']); ?>">Volver al inicio de sesión</a></p>
                    </div>
                <?php else: ?>
                    <!-- PASO 1: RUT -->
                    <?php if ($paso == 1): ?>
                        <div class="step-title">Ingrese su RUT</div>
                        <form method="POST" action="">
                            <div class="form-group">
                                <input type="text" name="rut" class="input-field" placeholder="12345678-9" required>
                            </div>
                            <button type="submit" class="btn-primary">CONTINUAR</button>
                        </form>

                        <!-- PASO 2: Correo -->
                    <?php elseif ($paso == 2): ?>
                        <div class="step-title">Hola, <?php echo htmlspecialchars($datos_form['nombre']); ?>.</div>
                        <div class="step-title">Ingrese su correo electrónico</div>
                        <form method="POST" action="">
                            <div class="form-group">
                                <input type="email" name="correo" class="input-field" placeholder="correo@ejemplo.com" required>
                            </div>
                            <button type="submit" class="btn-primary">CONTINUAR</button>
                        </form>

                        <!-- PASO 3: Razón de visita -->
                    <?php elseif ($paso == 3): ?>
                        <div class="step-title">Ingrese la razón de su visita</div>
                        <form method="POST" action="">
                            <div class="form-group">
                                <textarea name="razon_visita" class="input-field" rows="3" placeholder="Describa el motivo de su visita" required></textarea>
                            </div>
                            <button type="submit" class="btn-primary">INICIAR SESIÓN</button>
                        </form>
                    <?php endif; ?>

                    <div class="form-links">
                        <p>¿Es administrador? <a href="login_admin.php">Acceda como administrador</a></p>
                    </div>
                <?php endif; ?>
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