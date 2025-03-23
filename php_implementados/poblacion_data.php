<?php
// Incluir archivo de configuración
require_once 'config.php';

function obtener_datos_poblacion() {
    global $servername, $username, $password, $estados_nombres;
    
    // Conexión a la base de datos de población
    $conn_poblacion = new mysqli($servername, $username, $password, "poblacion_mexico_reducida");
    
    // Verificar conexión
    if ($conn_poblacion->connect_error) {
        die("Error de conexión (población): " . $conn_poblacion->connect_error);
    }
    
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
    
    // Cerrar conexión
    $conn_poblacion->close();
    
    return $estados_poblacion;
}

function obtener_poblacion_zonas_riesgo() {
    global $servername, $username, $password;
    
    // Connect to population database
    $conn = new mysqli($servername, $username, $password, "poblacion_mexico_reducida");
    
    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }
    
    // States with high seismic risk
    $estados_riesgo = array("OAXACA", "GUERRERO", "CHIAPAS", "MICHOACAN", "COLIMA", "JALISCO");
    
    $placeholders = str_repeat('?,', count($estados_riesgo) - 1) . '?';
    
    $sql = "SELECT 
                entidad,
                entidad_nombre,
                municipio_nombre,
                localidad_nombre,
                poblacion_total,
                latitud,
                longitud,
                altitud
            FROM 
                poblacion_inegi_reducida
            WHERE 
                entidad_nombre IN ($placeholders)
                AND poblacion_total > 5000 
                AND latitud IS NOT NULL 
                AND longitud IS NOT NULL
            ORDER BY 
                poblacion_total DESC";
    
    $stmt = $conn->prepare($sql);
    
    // Solución: Crear referencias a cada elemento del array para bind_param
    $types = str_repeat('s', count($estados_riesgo));
    
    // Crear un array de referencias
    $params = array();
    $params[] = $types; // El primer parámetro es el string de tipos
    
    // Agregar referencias a cada estado
    foreach($estados_riesgo as $key => $value) {
        $params[] = &$estados_riesgo[$key];
    }
    
    // Usar call_user_func_array con las referencias
    call_user_func_array(array($stmt, 'bind_param'), $params);
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $poblaciones_riesgo = array();
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $poblaciones_riesgo[] = $row;
        }
    }
    
    $stmt->close();
    $conn->close();
    
    return $poblaciones_riesgo;
}

// Function to get municipalities with highest population density
function obtener_municipios_densidad() {
    global $servername, $username, $password;
    
    $conn = new mysqli($servername, $username, $password, "poblacion_mexico_reducida");
    
    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }
    
    $sql = "SELECT 
                entidad_nombre,
                municipio_nombre,
                SUM(poblacion_total) as poblacion_total
            FROM 
                poblacion_inegi_reducida
            WHERE 
                municipio > 0
            GROUP BY 
                entidad_nombre, municipio_nombre
            ORDER BY 
                poblacion_total DESC
            LIMIT 20";
    
    $result = $conn->query($sql);
    
    $municipios = array();
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $municipios[] = $row;
        }
    }
    
    $conn->close();
    
    return $municipios;
}

// Function to get geographic coordinates for all states
function obtener_coordenadas_estados() {
    global $servername, $username, $password;
    
    $conn = new mysqli($servername, $username, $password, "poblacion_mexico_reducida");
    
    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }
    
    $sql = "SELECT 
                entidad,
                entidad_nombre,
                AVG(longitud) as longitud_centro,
                AVG(latitud) as latitud_centro
            FROM 
                poblacion_inegi_reducida
            WHERE 
                latitud IS NOT NULL 
                AND longitud IS NOT NULL
                AND entidad > 0
            GROUP BY 
                entidad, entidad_nombre";
    
    $result = $conn->query($sql);
    
    $coordenadas = array();
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $coordenadas[$row['entidad_nombre']] = array(
                'latitud' => $row['latitud_centro'],
                'longitud' => $row['longitud_centro']
            );
        }
    }
    
    $conn->close();
    
    return $coordenadas;
}
?>