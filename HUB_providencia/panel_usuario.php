<?php
session_start();
// Verificar si hay una sesión activa de usuario
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login_usuario.php");
    exit;
}

$nombre_usuario = $_SESSION['usuario_nombre'];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Usuario - HUB Providencia</title>
    <link rel="stylesheet" href="estilos.css">
    <style>
        .logout-link {
            color: #dc3545;
            text-decoration: none;
            font-weight: bold;
        }

        .logout-link:hover {
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
            <div class="card">
                <h1>Panel de Usuario</h1>
                <p class="welcome">Bienvenido(a), <strong><?php echo htmlspecialchars($nombre_usuario); ?></strong></p>

                <div class="message-box">
                    <p>¡Has accedido exitosamente al panel de usuario!</p>
                    <p>Próximamente tendrás acceso a más funcionalidades.</p>
                </div>

                <div class="form-links">
                    <p><a href="javascript:void(0)" class="logout-link" onclick="cerrarSesion()">Cerrar sesión</a></p>
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
        // Función para asegurar el cierre de sesión completo
        function cerrarSesion() {
            // Primero, forzar la actualización de la base de datos
            fetch('login_usuario.php?logout=1', {
                    method: 'GET',
                    cache: 'no-cache',
                    credentials: 'same-origin'
                })
                .finally(function() {
                    // Después, redirigir a la página de login
                    window.location.href = 'login_usuario.php';
                });
        }
    </script>
</body>

</html>