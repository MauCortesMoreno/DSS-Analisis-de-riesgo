<?php
// Configuración de la conexión a la base de datos
$servername = "localhost";
$username = "root";
$password = "TU_CONTRASEÑA";

// Conexión a la base de datos de sismos
$conn_sismos = new mysqli($servername, $username, $password, "sismos_mexico");

// Verificar conexión
if ($conn_sismos->connect_error) {
    die("Error de conexión (sismos): " . $conn_sismos->connect_error);
}

// Conexión a la base de datos de población
$conn_poblacion = new mysqli($servername, $username, $password, "poblacion_mexico_reducida");

// Verificar conexión
if ($conn_poblacion->connect_error) {
    die("Error de conexión (población): " . $conn_poblacion->connect_error);
}

// Mapa de nombres completos de estados y sus IDs
$estados_nombres = array(
    'AGS' => array('id' => 1, 'nombre' => 'Aguascalientes'),
    'BC' => array('id' => 2, 'nombre' => 'Baja California'),
    'BCS' => array('id' => 3, 'nombre' => 'Baja California Sur'),
    'CAMP' => array('id' => 4, 'nombre' => 'Campeche'),
    'CHIS' => array('id' => 5, 'nombre' => 'Chiapas'),
    'CHIH' => array('id' => 6, 'nombre' => 'Chihuahua'),
    'COAH' => array('id' => 7, 'nombre' => 'Coahuila'),
    'COL' => array('id' => 8, 'nombre' => 'Colima'),
    'CDMX' => array('id' => 9, 'nombre' => 'Ciudad de México'),
    'DGO' => array('id' => 10, 'nombre' => 'Durango'),
    'GTO' => array('id' => 11, 'nombre' => 'Guanajuato'),
    'GRO' => array('id' => 12, 'nombre' => 'Guerrero'),
    'HGO' => array('id' => 13, 'nombre' => 'Hidalgo'),
    'JAL' => array('id' => 14, 'nombre' => 'Jalisco'),
    'MEX' => array('id' => 15, 'nombre' => 'Estado de México'),
    'MICH' => array('id' => 16, 'nombre' => 'Michoacán'),
    'MOR' => array('id' => 17, 'nombre' => 'Morelos'),
    'NAY' => array('id' => 18, 'nombre' => 'Nayarit'),
    'NL' => array('id' => 19, 'nombre' => 'Nuevo León'),
    'OAX' => array('id' => 20, 'nombre' => 'Oaxaca'),
    'PUE' => array('id' => 21, 'nombre' => 'Puebla'),
    'QRO' => array('id' => 22, 'nombre' => 'Querétaro'),
    'QR' => array('id' => 23, 'nombre' => 'Quintana Roo'),
    'SLP' => array('id' => 24, 'nombre' => 'San Luis Potosí'),
    'SIN' => array('id' => 25, 'nombre' => 'Sinaloa'),
    'SON' => array('id' => 26, 'nombre' => 'Sonora'),
    'TAB' => array('id' => 27, 'nombre' => 'Tabasco'),
    'TAMS' => array('id' => 28, 'nombre' => 'Tamaulipas'),
    'TLAX' => array('id' => 29, 'nombre' => 'Tlaxcala'),
    'VER' => array('id' => 30, 'nombre' => 'Veracruz'),
    'YUC' => array('id' => 31, 'nombre' => 'Yucatán'),
    'ZAC' => array('id' => 32, 'nombre' => 'Zacatecas')
);

// Consulta para obtener sismos por estado
$sql_sismos = "SELECT 
                SUBSTRING_INDEX(referencia_localizacion, ', ', -1) AS estado,
                COUNT(*) AS numero_sismos,
                AVG(magnitud) AS magnitud_promedio,
                MAX(magnitud) AS magnitud_maxima
              FROM 
                registros_sismos
              WHERE
                estatus = 'revisado'
              GROUP BY 
                estado
              ORDER BY 
                numero_sismos ASC";

$result_sismos = $conn_sismos->query($sql_sismos);

// Consulta para obtener población por entidad
$sql_poblacion = "SELECT 
                    entidad,
                    entidad_nombre,
                    SUM(poblacion_total) AS poblacion_total,
                    SUM(poblacion_masculina) AS poblacion_masculina,
                    SUM(poblacion_femenina) AS poblacion_femenina
                  FROM 
                    poblacion_inegi_reducida
                  WHERE
                    entidad > 0
                  GROUP BY 
                    entidad, entidad_nombre
                  ORDER BY 
                    poblacion_total DESC";

$result_poblacion = $conn_poblacion->query($sql_poblacion);

// Procesar datos de sismos
$estados_sismos = array();
if ($result_sismos->num_rows > 0) {
    while($row = $result_sismos->fetch_assoc()) {
        // Filtrar estados válidos
        if ($row["estado"] != "N" && array_key_exists($row["estado"], $estados_nombres)) {
            $estados_sismos[$row["estado"]] = array(
                "id" => $estados_nombres[$row["estado"]]['id'],
                "nombre" => $estados_nombres[$row["estado"]]['nombre'],
                "numero_sismos" => $row["numero_sismos"],
                "magnitud_promedio" => $row["magnitud_promedio"],
                "magnitud_maxima" => $row["magnitud_maxima"]
            );
        }
    }
}

// Calcular peso sísmico (inversamente proporcional al número de sismos)
if (!empty($estados_sismos)) {
    $max_sismos = max(array_column($estados_sismos, "numero_sismos"));
    foreach ($estados_sismos as $estado => $datos) {
        $peso_sismos = 100 - ($datos["numero_sismos"] / $max_sismos * 100);
        $estados_sismos[$estado]["peso_sismos"] = $peso_sismos;
    }
}

// Procesar datos de población
$estados_poblacion = array();
if ($result_poblacion->num_rows > 0) {
    while($row = $result_poblacion->fetch_assoc()) {
        $entidad_nombre = $row["entidad_nombre"];
        $clave_estado = null;
        
        // Encontrar la clave del estado desde el nombre
        foreach ($estados_nombres as $clave => $datos) {
            if (strtoupper($datos['nombre']) == strtoupper($entidad_nombre)) {
                $clave_estado = $clave;
                break;
            }
        }
        
        if ($clave_estado) {
            $estados_poblacion[$clave_estado] = array(
                "id" => $estados_nombres[$clave_estado]['id'],
                "nombre" => $entidad_nombre,
                "poblacion_total" => $row["poblacion_total"],
                "poblacion_masculina" => $row["poblacion_masculina"],
                "poblacion_femenina" => $row["poblacion_femenina"]
            );
        }
    }
}

// Calcular peso poblacional (directamente proporcional a la población)
if (!empty($estados_poblacion)) {
    $max_poblacion = max(array_column($estados_poblacion, "poblacion_total"));
    foreach ($estados_poblacion as $estado => $datos) {
        $peso_poblacion = ($datos["poblacion_total"] / $max_poblacion) * 100;
        $estados_poblacion[$estado]["peso_poblacion"] = $peso_poblacion;
    }
}

// Combinar datos y calcular puntaje final
$estados_combinados = array();
foreach ($estados_nombres as $estado => $datos) {
    if (isset($estados_sismos[$estado]) && isset($estados_poblacion[$estado])) {
        $peso_sismos = isset($estados_sismos[$estado]["peso_sismos"]) ? $estados_sismos[$estado]["peso_sismos"] : 0;
        $peso_poblacion = isset($estados_poblacion[$estado]["peso_poblacion"]) ? $estados_poblacion[$estado]["peso_poblacion"] : 0;
        
        // Fórmula ponderada: 40% importancia a pocos sismos, 60% a alta población
        $puntaje_final = ($peso_sismos * 0.4) + ($peso_poblacion * 0.6);
        
        $estados_combinados[$estado] = array(
            "id" => $datos['id'],
            "clave" => $estado,
            "nombre" => $datos['nombre'],
            "numero_sismos" => isset($estados_sismos[$estado]["numero_sismos"]) ? $estados_sismos[$estado]["numero_sismos"] : 0,
            "magnitud_promedio" => isset($estados_sismos[$estado]["magnitud_promedio"]) ? $estados_sismos[$estado]["magnitud_promedio"] : 0,
            "magnitud_maxima" => isset($estados_sismos[$estado]["magnitud_maxima"]) ? $estados_sismos[$estado]["magnitud_maxima"] : 0,
            "peso_sismos" => $peso_sismos,
            "poblacion_total" => isset($estados_poblacion[$estado]["poblacion_total"]) ? $estados_poblacion[$estado]["poblacion_total"] : 0,
            "peso_poblacion" => $peso_poblacion,
            "puntaje_final" => $puntaje_final
        );
    }
}

// Ordenar por puntaje final (de mayor a menor)
uasort($estados_combinados, function($a, $b) {
    return $b["puntaje_final"] <=> $a["puntaje_final"];
});

// Mostrar resultados
echo "<h2>Análisis Combinado - Estados Óptimos para Construcción de Torres</h2>";
echo "<table border='1'>";
echo "<tr>
        <th>ID</th>
        <th>Clave</th>
        <th>Nombre</th>
        <th>Número de Sismos</th>
        <th>Magnitud Promedio</th>
        <th>Magnitud Máxima</th>
        <th>Peso Sismos (40%)</th>
        <th>Población Total</th>
        <th>Peso Población (60%)</th>
        <th>Puntaje Final</th>
      </tr>";

foreach ($estados_combinados as $estado => $datos) {
    // Destacar los 3 mejores estados
    $estilo = ($datos["puntaje_final"] >= array_values($estados_combinados)[2]["puntaje_final"]) ? 
              "background-color: #d4edda; font-weight: bold;" : "";
    
    echo "<tr style='".$estilo."'>";
    echo "<td>" . $datos["id"] . "</td>";
    echo "<td>" . $datos["clave"] . "</td>";
    echo "<td>" . $datos["nombre"] . "</td>";
    echo "<td>" . $datos["numero_sismos"] . "</td>";
    echo "<td>" . number_format($datos["magnitud_promedio"], 2) . "</td>";
    echo "<td>" . $datos["magnitud_maxima"] . "</td>";
    echo "<td>" . number_format($datos["peso_sismos"], 2) . "</td>";
    echo "<td>" . number_format($datos["poblacion_total"]) . "</td>";
    echo "<td>" . number_format($datos["peso_poblacion"], 2) . "</td>";
    echo "<td>" . number_format($datos["puntaje_final"], 2) . "</td>";
    echo "</tr>";
}
echo "</table>";

// Destacar los 3 mejores estados para la construcción
echo "<h3>Los 3 estados óptimos para la construcción de torres:</h3>";
echo "<ol>";
$contador = 0;
foreach ($estados_combinados as $estado => $datos) {
    echo "<li><strong>" . $datos["nombre"] . " (" . $datos["clave"] . ")</strong> - Puntaje: " . 
         number_format($datos["puntaje_final"], 2) . "</li>";
    $contador++;
    if ($contador >= 3) break;
}
echo "</ol>";

// Cerrar conexiones
$conn_sismos->close();
$conn_poblacion->close();
?>