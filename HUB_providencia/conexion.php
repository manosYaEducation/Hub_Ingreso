<?php
class DBconexion{
    private $host = "localhost";
    private $usuario = "root";
    private $clave = "";
    private $db = "hub_providencia";
    public $conexion;
    public function __construct(){
        $this->conexion = new mysqli($this->host, $this->usuario, $this->clave, $this->db) or die(mysql_error());
    }
    public function insert($tabla, $datos){
        $resultado = $this->conexion->query("INSERT INTO $tabla VALUES ($datos)") or die($this->conexion->error);
        if($resultado)
        return true;
    return false;
    }
    public function search($tabla, $condicion){
        $resultado = $this->conexion->query("SELECT * FROM $tabla WHERE $condicion") or die($this->conexion->error);
        if($resultado)
        return $resultado->fetch_all(MYSQLI_ASSOC);
    return false;
    }
}
 ?>