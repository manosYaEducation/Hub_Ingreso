<?php
include "conexion.php";
session_start();

// Si ya hay una sesión activa de administrador, redirigir a la página de carga CSV
if (isset($_SESSION['admin_id'])) {
    header("Location: cargar_csv.php");
    exit;
}
$error = '';
$rut = '';
// Si viene de la página de login, llenar el campo RUT
if (isset($_GET['rut'])) {
    $rut = htmlspecialchars($_GET['rut']);
}
// Procesar el formulario de registro
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $db = new DBconexion();
    // Obtener y escapar datos del formulario
    $rut = $db->conexion->real_escape_string($_POST['rut']);
    $nombre = $db->conexion->real_escape_string($_POST['nombre']);
    $correo = $db->conexion->real_escape_string($_POST['correo']);
    $telefono = $db->conexion->real_escape_string($_POST['telefono']);
    $password = $db->conexion->real_escape_string($_POST['password']);
    $confirmar_password = $db->conexion->real_escape_string($_POST['confirmar_password']);
    // Validaciones
    if ($password !== $confirmar_password) {
        $error = "Las contraseñas no coinciden";
    } else {
        // Verificar si el RUT ya existe en la tabla de administradores
        $existe = $db->search("administradores", "rut = '$rut'");

        if (!$existe) {
            // Encriptar la contraseña
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Fecha actual
            $fecha = date('Y-m-d H:i:s');

            // Insertar nuevo administrador (adaptado a los campos reales de la tabla)
            $resultado = $db->insert(
                "administradores",
                "NULL, '$rut', '$nombre', '$correo', '$telefono', '$password_hash', '$fecha', NULL"
            );

            if ($resultado) {
                // Buscar el ID del administrador recién creado
                $admin = $db->search("administradores", "rut = '$rut'");

                if ($admin) {
                    // Crear sesión
                    $_SESSION['admin_id'] = $admin[0]['id'];
                    $_SESSION['admin_rut'] = $rut;
                    $_SESSION['admin_nombre'] = $nombre;

                    // Actualizar último acceso
                    $db->update("administradores", "ultimo_acceso = '$fecha'", "id = " . $admin[0]['id']);

                    header("Location: cargar_csv.php");
                    exit;
                } else {
                    $error = "Error al iniciar sesión. Por favor intente nuevamente.";
                }
            } else {
                $error = "Error al registrar el administrador. Por favor intente nuevamente.";
            }
        } else {
            $error = "Este RUT ya está registrado como administrador. <a href='index.php'>Inicie sesión</a>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Administrador - HUB Providencia</title>
    <link rel="stylesheet" href="estilos.css">
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
                <h1>Registro de Administrador</h1>

                <?php if ($error): ?>
                    <div class="alert error"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="rut">RUT:</label>
                        <input type="text" id="rut" name="rut" value="<?php echo $rut; ?>" placeholder="Ej: 12345678-9" required>
                    </div>

                    <div class="form-group">
                        <label for="nombre">Nombre completo:</label>
                        <input type="text" id="nombre" name="nombre" placeholder="Nombre y apellidos" required>
                    </div>

                    <div class="form-group">
                        <label for="correo">Correo electrónico:</label>
                        <input type="email" id="correo" name="correo" placeholder="ejemplo@correo.com" required>
                    </div>

                    <div class="form-group">
                        <label for="telefono">Número telefónico:</label>
                        <input type="tel" id="telefono" name="telefono" placeholder="Ej: 912345678" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Contraseña:</label>
                        <input type="password" id="password" name="password" placeholder="Mínimo 6 caracteres" required minlength="6">
                    </div>

                    <div class="form-group">
                        <label for="confirmar_password">Confirmar contraseña:</label>
                        <input type="password" id="confirmar_password" name="confirmar_password" placeholder="Repita la contraseña" required minlength="6">
                    </div>

                    <button type="submit" class="btn-primary">REGISTRAR ADMINISTRADOR</button>
                </form>

                <div class="form-links">
                    <p>¿Ya está registrado? <a href="index.php">Inicie sesión</a></p>
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