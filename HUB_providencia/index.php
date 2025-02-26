<?php
include "conexion.php";
$user = new DBconexion();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['archivo_csv'])) {
    $archivo = $_FILES['archivo_csv'];

    // Verificar si el archivo es un CSV
    if ($archivo['type'] == 'text/csv') {
        $fp = fopen($archivo['tmp_name'], "r");

        $linea = false;
        while ($data = fgetcsv($fp, 1000, ",")):
            $fecha_original = $data[0]; //fecha en la columna

            // Convertir fecha del formato d/m/Y H:i:s a Y-m-d H:i:s
            $fecha_objeto = DateTime::createFromFormat('d/m/Y H:i:s', $fecha_original);
            if ($fecha_objeto) {
                $fecha_convertida = $fecha_objeto->format('Y-m-d H:i:s'); // Convertir a formato MySQL
            } else {
                $fecha_convertida = "0000-00-00 00:00:00"; // Si la conversión falla, insertar un valor por defecto
            }

            // Verificar si la fecha ya existe en la base de datos
            $existe = $user->search("datos", "Rut = '$data[1]'");

            if (!$existe) {
                if($linea)
                    $user->insert("datos", "'".$fecha_convertida."','".$data[1]."','".$data[2]."','".$data[3]."','".$data[4]."','".$data[5]."','".$data[6]."','".$data[7]."'");
                $linea = true;
            }

        endwhile;
        fclose($fp);
        $user = null;
        echo "Datos importados correctamente!";
    } else {
        echo "Por favor, seleccione un archivo CSV.";
        $user = null;
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar CSV</title>
</head>
<body>

    <h1>Subir archivo CSV</h1>
    <!-- Formulario para seleccionar el archivo CSV -->
    <form action="index.php" method="POST" enctype="multipart/form-data">
        <label for="archivo_csv">Selecciona el archivo CSV:</label>
        <input type="file" name="archivo_csv" id="archivo_csv" accept=".csv" required>
        <button type="submit">Subir y procesar</button>
    </form>

</body>
</html>