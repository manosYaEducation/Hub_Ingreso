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
