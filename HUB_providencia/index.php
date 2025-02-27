<?php
include "conexion.php";
session_start();

// Procesar el cierre de sesión
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

// Si ya hay una sesión activa de administrador, redirigir a la página de carga CSV
if (isset($_SESSION['admin_id'])) {
    header("Location: cargar_csv.php");
    exit;
}

$error = '';

// Procesar el formulario de ingreso
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rut']) && isset($_POST['password'])) {
    $db = new DBconexion();
    $rut = $db->conexion->real_escape_string($_POST['rut']);
    $password = $_POST['password'];

    // Buscar el administrador por RUT
    $admin = $db->search("administradores", "rut = '$rut'");

    if ($admin && password_verify($password, $admin[0]['password'])) {
        // Credenciales correctas, crear sesión
        $_SESSION['admin_id'] = $admin[0]['id'];
        $_SESSION['admin_rut'] = $rut;
        $_SESSION['admin_nombre'] = $admin[0]['nombre'];
        // Ya no guardamos el estado de superadmin
        $_SESSION['es_super_admin'] = 0;

        // Actualizar último acceso
        $fecha = date('Y-m-d H:i:s');
        $db->update("administradores", "ultimo_acceso = '$fecha'", "id = " . $admin[0]['id']);

        header("Location: cargar_csv.php");
        exit;
    } else {
        // Credenciales incorrectas
        $error = "RUT o contraseña incorrectos";
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Administrador - HUB Providencia</title>
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
                <h1>Acceso Administrador</h1>

                <?php if ($error): ?>
                    <div class="alert error"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="rut">RUT:</label>
                        <input type="text" id="rut" name="rut" placeholder="Ej: 12345678-9" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Contraseña:</label>
                        <input type="password" id="password" name="password" required>
                    </div>

                    <button type="submit" class="btn-primary">INICIAR SESIÓN</button>
                </form>

                <div class="form-links">
                    <p>¿No tiene cuenta? <a href="registro.php">Regístrese como administrador</a></p>
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