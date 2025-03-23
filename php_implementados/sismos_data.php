<?php
// Incluir archivo de configuración
require_once 'config.php';

function obtener_datos_sismos($fecha_inicio = '', $fecha_fin = '', $magnitud_min = 0) {
    global $servername, $username, $password, $estados_nombres;
    
    // Conexión a la base de datos de sismos
    $conn_sismos = new mysqli($servername, $username, $password, "sismos_mexico");
    
    // Verificar conexión
    if ($conn_sismos->connect_error) {
        die("Error de conexión (sismos): " . $conn_sismos->connect_error);
    }
    
    // Base de la consulta SQL
    $sql_sismos = "SELECT 
                    SUBSTRING_INDEX(referencia_localizacion, ', ', -1) AS estado,
                    COUNT(*) AS numero_sismos,
                    AVG(magnitud) AS magnitud_promedio,
                    MAX(magnitud) AS magnitud_maxima
                  FROM 
                    registros_sismos
                  WHERE
                    estatus = 'revisado'";
    
    // Añadir filtros si existen
    $params = array();
    $types = "";
    
    if (!empty($fecha_inicio) && !empty($fecha_fin)) {
        $sql_sismos .= " AND fecha BETWEEN ? AND ?";
        $params[] = $fecha_inicio;
        $params[] = $fecha_fin;
        $types .= "ss";
    }
    
    if ($magnitud_min > 0) {
        $sql_sismos .= " AND magnitud >= ?";
        $params[] = $magnitud_min;
        $types .= "d";
    }
    
    $sql_sismos .= " GROUP BY 
                      estado
                    ORDER BY 
                      numero_sismos ASC";
    
    // Preparar y ejecutar la consulta con parámetros
    $stmt = $conn_sismos->prepare($sql_sismos);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result_sismos = $stmt->get_result();
    
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
    
    // Cerrar conexión
    $stmt->close();
    $conn_sismos->close();
    
    return $estados_sismos;
}

// New function to get seismic activity by time period
function obtener_sismos_por_periodo($fecha_inicio, $fecha_fin) {
    global $servername, $username, $password;
    
    $conn = new mysqli($servername, $username, $password, "sismos_mexico");
    
    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }
    
    $sql = "SELECT 
                fecha, 
                hora, 
                magnitud, 
                latitud, 
                longitud, 
                profundidad, 
                referencia_localizacion 
            FROM 
                registros_sismos 
            WHERE 
                fecha BETWEEN ? AND ? 
                AND estatus = 'revisado' 
            ORDER BY 
                fecha DESC, hora DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sismos = array();
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $sismos[] = $row;
        }
    }
    
    $stmt->close();
    $conn->close();
    
    return $sismos;
}

// Function to get seismic data by magnitude range
function obtener_sismos_por_magnitud($min_magnitud, $max_magnitud) {
    global $servername, $username, $password;
    
    $conn = new mysqli($servername, $username, $password, "sismos_mexico");
    
    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }
    
    $sql = "SELECT 
                fecha, 
                hora, 
                magnitud, 
                latitud, 
                longitud, 
                profundidad, 
                referencia_localizacion 
            FROM 
                registros_sismos 
            WHERE 
                magnitud BETWEEN ? AND ? 
                AND estatus = 'revisado' 
            ORDER BY 
                magnitud DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("dd", $min_magnitud, $max_magnitud);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sismos = array();
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $sismos[] = $row;
        }
    }
    
    $stmt->close();
    $conn->close();
    
    return $sismos;
}

// Function to get seismic data for map visualization
function obtener_datos_mapa_sismos() {
    global $servername, $username, $password;
    
    $conn = new mysqli($servername, $username, $password, "sismos_mexico");
    
    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }
    
    $sql = "SELECT 
                latitud, 
                longitud, 
                magnitud, 
                profundidad,
                referencia_localizacion,
                fecha,
                hora
            FROM 
                registros_sismos 
            WHERE 
                latitud IS NOT NULL 
                AND longitud IS NOT NULL 
                AND estatus = 'revisado'
            ORDER BY 
                fecha DESC, hora DESC 
            LIMIT 500";
    
    $result = $conn->query($sql);
    
    $puntos_mapa = array();
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $puntos_mapa[] = $row;
        }
    }
    
    $conn->close();
    
    return $puntos_mapa;
}

// Function to get statistics for histograms - Modified to accept filters
function obtener_estadisticas_sismos($fecha_inicio = '', $fecha_fin = '', $magnitud_min = 0) {
    global $servername, $username, $password;
    
    $conn = new mysqli($servername, $username, $password, "sismos_mexico");
    
    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }
    
    // Base de la consulta SQL para magnitudes
    $sql_magnitudes = "SELECT 
                        FLOOR(magnitud) as rango_magnitud,
                        COUNT(*) as cantidad
                      FROM 
                        registros_sismos
                      WHERE 
                        estatus = 'revisado'";
    
    // Base de la consulta SQL para profundidades
    $sql_profundidad = "SELECT 
                        CASE
                            WHEN profundidad < 20 THEN 'Superficial (< 20 km)'
                            WHEN profundidad < 70 THEN 'Intermedio (20-70 km)'
                            ELSE 'Profundo (> 70 km)'
                        END as tipo_profundidad,
                        COUNT(*) as cantidad
                      FROM 
                        registros_sismos
                      WHERE 
                        estatus = 'revisado'";
    
    // Añadir filtros si existen para ambas consultas
    $params_magnitudes = array();
    $params_profundidad = array();
    $types_magnitudes = "";
    $types_profundidad = "";
    
    if (!empty($fecha_inicio) && !empty($fecha_fin)) {
        $sql_magnitudes .= " AND fecha BETWEEN ? AND ?";
        $sql_profundidad .= " AND fecha BETWEEN ? AND ?";
        
        $params_magnitudes[] = $fecha_inicio;
        $params_magnitudes[] = $fecha_fin;
        $params_profundidad[] = $fecha_inicio;
        $params_profundidad[] = $fecha_fin;
        
        $types_magnitudes .= "ss";
        $types_profundidad .= "ss";
    }
    
    if ($magnitud_min > 0) {
        $sql_magnitudes .= " AND magnitud >= ?";
        $sql_profundidad .= " AND magnitud >= ?";
        
        $params_magnitudes[] = $magnitud_min;
        $params_profundidad[] = $magnitud_min;
        
        $types_magnitudes .= "d";
        $types_profundidad .= "d";
    }
    
    // Finalizar consultas con GROUP BY
    $sql_magnitudes .= " GROUP BY 
                        FLOOR(magnitud)
                      ORDER BY 
                        rango_magnitud";
    
    $sql_profundidad .= " GROUP BY 
                        tipo_profundidad";
    
    // Preparar y ejecutar la consulta de magnitudes
    $stmt_magnitudes = $conn->prepare($sql_magnitudes);
    
    if (!empty($params_magnitudes)) {
        $stmt_magnitudes->bind_param($types_magnitudes, ...$params_magnitudes);
    }
    
    $stmt_magnitudes->execute();
    $result_magnitudes = $stmt_magnitudes->get_result();
    
    $estadisticas = array('magnitudes' => array());
    if ($result_magnitudes->num_rows > 0) {
        while($row = $result_magnitudes->fetch_assoc()) {
            $estadisticas['magnitudes'][] = $row;
        }
    }
    
    // Preparar y ejecutar la consulta de profundidades
    $stmt_profundidad = $conn->prepare($sql_profundidad);
    
    if (!empty($params_profundidad)) {
        $stmt_profundidad->bind_param($types_profundidad, ...$params_profundidad);
    }
    
    $stmt_profundidad->execute();
    $result_profundidad = $stmt_profundidad->get_result();
    
    $estadisticas['profundidad'] = array();
    if ($result_profundidad->num_rows > 0) {
        while($row = $result_profundidad->fetch_assoc()) {
            $estadisticas['profundidad'][] = $row;
        }
    }
    
    // Cerrar las declaraciones y la conexión
    $stmt_magnitudes->close();
    $stmt_profundidad->close();
    $conn->close();
    
    return $estadisticas;
}

function obtener_sismos_serie_temporal($fecha_inicio = '', $fecha_fin = '', $magnitud_min = 0) {
    global $servername, $username, $password;
    
    $conn = new mysqli($servername, $username, $password, "sismos_mexico");
    
    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }
    
    // Base SQL query
    $sql = "SELECT 
                DATE_FORMAT(fecha, '%Y-%m') as mes,
                COUNT(*) as numero_sismos,
                AVG(magnitud) as magnitud_promedio,
                MAX(magnitud) as magnitud_maxima
            FROM 
                registros_sismos
            WHERE 
                estatus = 'revisado'";
    
    // Add filters if they exist
    $params = array();
    $types = "";
    
    if (!empty($fecha_inicio) && !empty($fecha_fin)) {
        $sql .= " AND fecha BETWEEN ? AND ?";
        $params[] = $fecha_inicio;
        $params[] = $fecha_fin;
        $types .= "ss";
    }
    
    if ($magnitud_min > 0) {
        $sql .= " AND magnitud >= ?";
        $params[] = $magnitud_min;
        $types .= "d";
    }
    
    $sql .= " GROUP BY 
                mes
            ORDER BY 
                mes ASC";
    
    // Prepare and execute query with parameters
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $serie_temporal = array();
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $serie_temporal[] = $row;
        }
    }
    
    $stmt->close();
    $conn->close();
    
    return $serie_temporal;
}
?>