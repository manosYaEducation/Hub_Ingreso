<?php
class DBconexion
{
    private $host = "localhost";
    private $usuario = "root";
    private $clave = "";
    private $db = "hub_providencia";
    public $conexion;

    public function __construct()
    {
        $this->conexion = new mysqli($this->host, $this->usuario, $this->clave, $this->db) or die(mysqli_error($this->conexion));
        $this->conexion->set_charset("utf8"); // Para manejar caracteres especiales
    }

    public function insert($tabla, $datos, $columnas = "")
    {
        $sql = "INSERT INTO $tabla";
        if ($columnas != "") {
            $sql .= " ($columnas)";
        }
        $sql .= " VALUES ($datos)";

        $resultado = $this->conexion->query($sql) or die($this->conexion->error);
        if ($resultado)
            return true;
        return false;
    }

    public function search($tabla, $condicion)
    {
        $resultado = $this->conexion->query("SELECT * FROM $tabla WHERE $condicion") or die($this->conexion->error);
        if ($resultado)
            return $resultado->fetch_all(MYSQLI_ASSOC);
        return false;
    }

    // Función corregida para verificar sesión activa
    public function verificarSesionActiva($rut)
    {
        // Usamos $this en lugar de crear una nueva instancia
        $resultado = $this->search(
            "registro_accesos_usuarios",
            "rut_usuario = '$rut' AND sesion_activa = 1 AND fecha_acceso > DATE_SUB(NOW(), INTERVAL 8 HOUR)"
        );
        return !empty($resultado);
    }

    // Función para cerrar sesiones inactivas (más de 8 horas)
    public function cerrarSesionesInactivas()
    {
        return $this->update(
            "registro_accesos_usuarios",
            "sesion_activa = 0",
            "sesion_activa = 1 AND fecha_acceso < DATE_SUB(NOW(), INTERVAL 8 HOUR)"
        );
    }

    // Función para cerrar sesiones de un usuario específico
    public function cerrarSesionesUsuario($rut)
    {
        return $this->update(
            "registro_accesos_usuarios",
            "sesion_activa = 0",
            "rut_usuario = '$rut' AND sesion_activa = 1"
        );
    }

    public function searchCount($tabla, $condicion)
    {
        $resultado = $this->conexion->query("SELECT COUNT(*) as total FROM $tabla WHERE $condicion") or die($this->conexion->error);
        if ($resultado) {
            $row = $resultado->fetch_assoc();
            return $row['total'];
        }
        return 0;
    }

    public function update($tabla, $campos, $condicion)
    {
        $resultado = $this->conexion->query("UPDATE $tabla SET $campos WHERE $condicion") or die($this->conexion->error);
        if ($resultado)
            return true;
        return false;
    }

    public function delete($tabla, $condicion)
    {
        $resultado = $this->conexion->query("DELETE FROM $tabla WHERE $condicion") or die($this->conexion->error);
        if ($resultado)
            return true;
        return false;
    }
}
