<?php
// Incluir archivo de configuración
require_once 'config.php';

function obtener_datos_economia() {
    global $servername, $username, $password, $estados_nombres;
    
    // Conexión a la base de datos de economía
    $conn_economia = new mysqli($servername, $username, $password, "mexico_economia");
    
    // Verificar conexión
    if ($conn_economia->connect_error) {
        die("Error de conexión (economía): " . $conn_economia->connect_error);
    }
    
    // Consulta para obtener la producción bruta total por entidad
    $sql_economia = "SELECT 
                        entidad,
                        SUM(produccion_bruta_total) AS produccion_bruta,
                        AVG(produccion_bruta_total) AS produccion_promedio,
                        SUM(a121a) AS valor_activos,
                        SUM(a131a) AS valor_inversiones
                     FROM 
                        produccion_economica
                     WHERE
                        entidad IS NOT NULL AND entidad != ''
                     GROUP BY 
                        entidad";
    
    $result_economia = $conn_economia->query($sql_economia);
    
    // Procesar datos económicos
    $estados_economia = array();
    if ($result_economia && $result_economia->num_rows > 0) {
        while($row = $result_economia->fetch_assoc()) {
            $estado_codigo = $row["entidad"];
            
            // Convertir el código de entidad (01, 02, etc.) a nombre de estado si es necesario
            // o bien buscar la coincidencia en el array de estados_nombres
            $clave_estado = null;
            foreach ($estados_nombres as $clave => $datos) {
                // Si el código de la entidad coincide con el ID del estado o hay coincidencia de nombre
                if ($datos['id'] == intval($estado_codigo) || 
                    strpos(strtoupper($row["entidad"]), strtoupper($datos['nombre'])) !== false) {
                    $clave_estado = $clave;
                    break;
                }
            }
            
            if ($clave_estado) {
                $estados_economia[$clave_estado] = array(
                    "id" => $estados_nombres[$clave_estado]['id'],
                    "nombre" => $estados_nombres[$clave_estado]['nombre'],
                    "produccion_bruta" => $row["produccion_bruta"],
                    "produccion_promedio" => $row["produccion_promedio"],
                    "valor_activos" => $row["valor_activos"],
                    "valor_inversiones" => $row["valor_inversiones"]
                );
            }
        }
    } else {
        echo "Error en la consulta: " . $conn_economia->error;
    }
    
    // Calcular peso económico (directamente proporcional a la producción bruta)
    if (!empty($estados_economia)) {
        $max_produccion = max(array_column($estados_economia, "produccion_bruta"));
        foreach ($estados_economia as $estado => $datos) {
            $peso_economia = ($datos["produccion_bruta"] / $max_produccion) * 100;
            $estados_economia[$estado]["peso_economia"] = $peso_economia;
        }
    }
    
    // Cerrar conexión
    $conn_economia->close();
    
    return $estados_economia;
}
?>