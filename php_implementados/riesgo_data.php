<?php
// Incluir archivo de configuración
require_once 'config.php';

function obtener_datos_delincuencia() {
    global $servername, $username, $password, $estados_nombres;
    
    // Conexión a la base de datos de delincuencia
    $conn_delincuencia = new mysqli($servername, $username, $password, "delincuencia");
    
    // Verificar conexión
    if ($conn_delincuencia->connect_error) {
        die("Error de conexión (delincuencia): " . $conn_delincuencia->connect_error);
    }
    
    // Consulta para obtener datos de incidencia delictiva por entidad
    $sql_delincuencia = "SELECT 
                            id,
                            entidad,
                            tasa_2023
                         FROM 
                            incidenciadelictiva
                         WHERE
                            entidad IS NOT NULL AND entidad != ''";
    
    $result_delincuencia = $conn_delincuencia->query($sql_delincuencia);
    
    // Procesar datos de incidencia delictiva
    $estados_delincuencia = array();
    if ($result_delincuencia && $result_delincuencia->num_rows > 0) {
        while($row = $result_delincuencia->fetch_assoc()) {
            $id_estado = $row["id"];
            $nombre_entidad = $row["entidad"];
            
            // Buscar el código del estado por nombre o ID
            $clave_estado = null;
            foreach ($estados_nombres as $clave => $datos) {
                if ($datos['id'] == $id_estado || 
                    strpos(strtoupper($nombre_entidad), strtoupper($datos['nombre'])) !== false) {
                    $clave_estado = $clave;
                    break;
                }
            }
            
            if ($clave_estado) {
                $estados_delincuencia[$clave_estado] = array(
                    "id" => $estados_nombres[$clave_estado]['id'],
                    "nombre" => $estados_nombres[$clave_estado]['nombre'],
                    "tasa_delictiva" => $row["tasa_2023"]
                );
            }
        }
    } else {
        echo "Error en la consulta: " . $conn_delincuencia->error;
    }
    
    // Calcular índice de seguridad (inversamente proporcional a la tasa delictiva)
    // Los estados con menor tasa delictiva reciben mayor índice de seguridad
    if (!empty($estados_delincuencia)) {
        $max_tasa = max(array_column($estados_delincuencia, "tasa_delictiva"));
        
        foreach ($estados_delincuencia as $estado => $datos) {
            // Invirtiendo la proporción para que menor delincuencia = mayor seguridad
            $indice_seguridad = 100 - (($datos["tasa_delictiva"] / $max_tasa) * 100);
            $estados_delincuencia[$estado]["indice_seguridad"] = $indice_seguridad;
            
            // Asignar peso al factor de seguridad (escala del 1-10)
            // Dando mayor peso a estados más seguros
            $peso_seguridad = ($indice_seguridad / 10);
            $estados_delincuencia[$estado]["peso_seguridad"] = $peso_seguridad;
        }
    }
    
    // Cerrar conexión
    $conn_delincuencia->close();
    
    return $estados_delincuencia;
}

// Función para calcular riesgo combinado (usando tanto datos económicos como de seguridad)
function calcular_riesgo_combinado() {
    // Obtenemos datos económicos (asumiendo que la función ya existe)
    $datos_economia = obtener_datos_economia();
    
    // Obtenemos datos de delincuencia
    $datos_delincuencia = obtener_datos_delincuencia();
    
    $estados_riesgo = array();
    
    // Combinar datos y calcular riesgo
    foreach ($datos_economia as $clave => $datos_eco) {
        if (isset($datos_delincuencia[$clave])) {
            // Factores para ponderar cada componente (ajustar según importancia)
            $factor_economia = 0.6;  // 60% del peso total
            $factor_seguridad = 0.4; // 40% del peso total
            
            // Calcular riesgo combinado (menor valor = mayor riesgo)
            $peso_economia_ajustado = $datos_eco["peso_economia"] * $factor_economia;
            $peso_seguridad_ajustado = $datos_delincuencia[$clave]["indice_seguridad"] * $factor_seguridad;
            
            $indice_riesgo = $peso_economia_ajustado + $peso_seguridad_ajustado;
            
            $estados_riesgo[$clave] = array(
                "id" => $datos_eco["id"],
                "nombre" => $datos_eco["nombre"],
                "produccion_bruta" => $datos_eco["produccion_bruta"],
                "tasa_delictiva" => $datos_delincuencia[$clave]["tasa_delictiva"],
                "peso_economia" => $datos_eco["peso_economia"],
                "indice_seguridad" => $datos_delincuencia[$clave]["indice_seguridad"],
                "indice_riesgo" => $indice_riesgo
            );
        }
    }
    
    // Ordenar estados por índice de riesgo (de menor a mayor riesgo)
    uasort($estados_riesgo, function($a, $b) {
        return $b["indice_riesgo"] <=> $a["indice_riesgo"];
    });
    
    return $estados_riesgo;
}

// Ejemplo de uso
// $resultados = calcular_riesgo_combinado();
// print_r($resultados);
?>