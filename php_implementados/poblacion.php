<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // Configuración de la base de datos para sismos
    $servername = "localhost";
    $username = "root";     // Reemplaza con tu usuario de MySQL
    $password = "TU_CONTRASEÑA";  // Reemplaza con tu contraseña
    $database_sismos = "sismos_mexico";

    // Función para manejar errores de conexión
    function handleDatabaseError($connection, $errorMessage) {
        if ($connection->connect_error) {
            die($errorMessage . ": " . $connection->connect_error);
        }
    }

    // Mapeo de códigos de estado a nombres de entidades federativas y coordenadas
    $estados = [
        "01" => ["nombre" => "Aguascalientes", "lat" => 21.8853, "lng" => -102.2916],
        "02" => ["nombre" => "Baja California", "lat" => 30.7665, "lng" => -116.0057],
        "03" => ["nombre" => "Baja California Sur", "lat" => 25.8443, "lng" => -111.9730],
        "04" => ["nombre" => "Campeche", "lat" => 18.9341, "lng" => -90.2475],
        "05" => ["nombre" => "Coahuila", "lat" => 27.0587, "lng" => -101.7068],
        "06" => ["nombre" => "Colima", "lat" => 19.1223, "lng" => -104.0072],
        "07" => ["nombre" => "Chiapas", "lat" => 16.7535, "lng" => -93.1027],
        "08" => ["nombre" => "Chihuahua", "lat" => 28.6353, "lng" => -106.0889],
        "09" => ["nombre" => "Ciudad de México", "lat" => 19.4326, "lng" => -99.1332],
        "10" => ["nombre" => "Durango", "lat" => 24.0277, "lng" => -104.6532],
        "11" => ["nombre" => "Guanajuato", "lat" => 20.9176, "lng" => -101.1663],
        "12" => ["nombre" => "Guerrero", "lat" => 17.5400, "lng" => -99.5014],
        "13" => ["nombre" => "Hidalgo", "lat" => 20.0911, "lng" => -98.7624],
        "14" => ["nombre" => "Jalisco", "lat" => 20.6595, "lng" => -103.3494],
        "15" => ["nombre" => "México", "lat" => 19.3556, "lng" => -99.5904],
        "16" => ["nombre" => "Michoacán", "lat" => 19.5665, "lng" => -101.7068],
        "17" => ["nombre" => "Morelos", "lat" => 18.6813, "lng" => -99.1013],
        "18" => ["nombre" => "Nayarit", "lat" => 21.7514, "lng" => -104.8455],
        "19" => ["nombre" => "Nuevo León", "lat" => 25.5922, "lng" => -99.9962],
        "20" => ["nombre" => "Oaxaca", "lat" => 17.0732, "lng" => -96.7266],
        "21" => ["nombre" => "Puebla", "lat" => 19.0414, "lng" => -98.2063],
        "22" => ["nombre" => "Querétaro", "lat" => 20.5888, "lng" => -100.3899],
        "23" => ["nombre" => "Quintana Roo", "lat" => 19.5793, "lng" => -87.9303],
        "24" => ["nombre" => "San Luis Potosí", "lat" => 22.1565, "lng" => -100.9855],
        "25" => ["nombre" => "Sinaloa", "lat" => 25.0000, "lng" => -107.5000],
        "26" => ["nombre" => "Sonora", "lat" => 29.2972, "lng" => -110.3309],
        "27" => ["nombre" => "Tabasco", "lat" => 17.9719, "lng" => -92.5320],
        "28" => ["nombre" => "Tamaulipas", "lat" => 24.2667, "lng" => -98.8363],
        "29" => ["nombre" => "Tlaxcala", "lat" => 19.3139, "lng" => -98.2404],
        "30" => ["nombre" => "Veracruz", "lat" => 19.4640, "lng" => -96.4280],
        "31" => ["nombre" => "Yucatán", "lat" => 20.9670, "lng" => -89.5926],
        "32" => ["nombre" => "Zacatecas", "lat" => 22.7709, "lng" => -102.5832]
    ];

    // Parámetros para filtrar sismos (año y magnitud mínima)
    $anio = isset($_GET['anio']) ? intval($_GET['anio']) : date('Y');
    $magnitud_minima = isset($_GET['magnitud']) ? floatval($_GET['magnitud']) : 5.0;

    // Cargar datos de sismos si está disponible la base de datos
    $sismos = [];
    try {
        // Establecer conexión a la base de datos de sismos
        $conn_sismos = new mysqli($servername, $username, $password, $database_sismos);
        if (!$conn_sismos->connect_error) {
            // Consulta para obtener sismos
            $sql_sismos = "SELECT 
                latitud, 
                longitud, 
                magnitud, 
                fecha, 
                referencia_localizacion, 
                profundidad 
            FROM registros_sismos 
            WHERE magnitud >= ? AND YEAR(fecha) = ?
            ORDER BY magnitud DESC 
            LIMIT 500";

            // Preparar y ejecutar la consulta de sismos
            $stmt_sismos = $conn_sismos->prepare($sql_sismos);
            $stmt_sismos->bind_param("di", $magnitud_minima, $anio);
            $stmt_sismos->execute();
            $result_sismos = $stmt_sismos->get_result();

            while ($row = $result_sismos->fetch_assoc()) {
                $sismos[] = $row;
            }

            // Cerrar conexiones
            $stmt_sismos->close();
            $conn_sismos->close();
        }
    } catch (Exception $e) {
        // En caso de error, dejar el arreglo de sismos vacío
    }

    // Función para obtener datos de población desde la API de INEGI
    function obtenerPoblacionINEGI($codigo_estado) {
        // Token y URL base de la API
        $token = "81001fb1-5caa-574a-7d43-0a42c80042d6";
        $codigo_formateado = sprintf("%02d", $codigo_estado);
        $url = "https://www.inegi.org.mx/app/api/indicadores/desarrolladores/jsonxml/INDICATOR/1002000001/es/{$codigo_formateado}/false/BISE/2.0/{$token}?type=json";
        
        // Intentar obtener los datos
        $data = @file_get_contents($url);
        if (!$data) {
            return [
                'poblacion_total' => 'N/A',
                'anio' => 'N/A',
                'error' => true
            ];
        }
        
        $json = json_decode($data, true);
        
        // Verificar si se obtuvieron datos correctamente
        if (!$json || !isset($json['Series'][0]['OBSERVATIONS'])) {
            return [
                'poblacion_total' => 'N/A',
                'anio' => 'N/A',
                'error' => true
            ];
        }
        
        // Extraer datos de población total
        $observaciones = $json['Series'][0]['OBSERVATIONS'];
        $poblacion_total = end($observaciones)['OBS_VALUE']; // Último dato disponible
        $anio = end($observaciones)['TIME_PERIOD']; // Año del último dato
        
        // Calcular población de hombres y mujeres (estimado)
        $poblacion_hombres = $poblacion_total * 0.49;
        $poblacion_mujeres = $poblacion_total * 0.51;
        
        return [
            'poblacion_total' => $poblacion_total,
            'anio' => $anio,
            'poblacion_hombres' => $poblacion_hombres,
            'poblacion_mujeres' => $poblacion_mujeres,
            'error' => false
        ];
    }

    // Variable para almacenar el estado seleccionado
    $estado_seleccionado = isset($_GET['estado_id']) ? $_GET['estado_id'] : null;
    $datos_poblacion = null;
    $nombre_estado = null;
    
    // Si hay un estado seleccionado, obtener sus datos de población
    if ($estado_seleccionado && isset($estados[$estado_seleccionado])) {
        $datos_poblacion = obtenerPoblacionINEGI(intval($estado_seleccionado));
        $nombre_estado = $estados[$estado_seleccionado]['nombre'];
    }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Datos de Población de México - Sistema DSS</title>
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css"/>
    <!-- Leaflet JavaScript -->
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <!-- Leaflet Heat Plugin -->
    <script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"/>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            font-family: 'Arial', sans-serif; 
            margin: 0; 
            padding: 0; 
            background-color: #f4f4f4;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .controls {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .map-toggle {
            display: flex;
            gap: 10px;
        }
        .map-toggle a {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .map-toggle a.active {
            background-color: #0056b3;
        }
        #map { 
            height: 600px; 
            width: 100%; 
            border-radius: 8px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        header {
            background: linear-gradient(135deg, #ff6b6b, #ffa502);
            padding: 20px;
            text-align: center;
            border-radius: 10px;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2);
            margin-bottom: 20px;
        }
        header h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 28px;
            color: white;
            margin: 0;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        .selected-state {
            background-color: #007bff;
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 1.5rem;
        }
        .population-display {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        .population-card {
            border-radius: 10px;
            box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.1);
            border: none;
            width: 250px;
            text-align: center;
            background-color: white;
            padding: 15px;
            margin: 10px;
        }
        .icon {
            font-size: 30px;
            margin-bottom: 10px;
        }
        .state-info {
            margin-top: 20px;
        }
        .instructions {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            background: linear-gradient(to right, #f8f9fa, #e9ecef);
            border-left: 5px solid #20c997;
        }
        .instructions h3 {
            color: #343a40;
            font-weight: bold;
            margin-bottom: 15px;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 10px;
        }
        .instructions p {
            color: #495057;
            line-height: 1.6;
            margin-bottom: 10px;
        }
        .filter-controls {
            display: flex;
            align-items: center;
            gap: 15px;
            background-color: #f0f8ff;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .legend {
            padding: 10px;
            background: white;
            border-radius: 5px;
            box-shadow: 0 0 15px rgba(0,0,0,0.2);
            line-height: 1.5;
            max-width: 250px;
        }
        .legend h4 {
            margin: 0 0 10px;
            font-size: 16px;
            font-weight: bold;
        }
        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-right: 8px;
            border: 1px solid #000;
        }
    </style>
</head>
<body>
<header>
    <h1>Sistema de Apoyo a Decisiones (DSS) - Datos de Población</h1>
</header>

<div class="container">
    <div class="controls">
        <div class="map-toggle">
            <a href="mexico.php">Inicio</a>
            <a href="sismos.php">Sismos</a>
            <a href="poblacion.php" class="active">Población</a>
            <a href="aeconomica.php">Economia</a>
            <a href="riesgo.php">Riesgo</a>
            <a href="resultado_final.php" class="active">Graficar Torres</a>
        </div>
    </div>

    <!-- Añadir controles de filtro de sismos -->
    <div class="filter-controls">
        <form method="GET" id="filterForm">
            <input type="hidden" name="estado_id" value="<?php echo $estado_seleccionado; ?>">
            <label>Año de sismos: 
                <select name="anio" id="anioSelect" class="form-control form-control-sm">
                    <?php 
                    $current_year = date('Y');
                    for ($y = 2010; $y <= $current_year; $y++) {
                        $selected = ($y == $anio) ? 'selected' : '';
                        echo "<option value='$y' $selected>$y</option>";
                    }
                    ?>
                </select>
            </label>
            <label>Magnitud Mínima: 
                <input type="number" name="magnitud" id="magnitudInput"
                       value="<?php echo $magnitud_minima; ?>" 
                       min="4.0" max="9.0" step="0.1"
                       class="form-control form-control-sm">
            </label>
            <input type="submit" value="Filtrar" class="btn btn-primary btn-sm">
        </form>
    </div>

    <div id="map"></div>

    <!-- Contenedor para la información de estados -->
    <div id="stateInfo" class="state-info" style="<?php echo $estado_seleccionado ? 'display: block;' : 'display: none;'; ?>">
        <div class="selected-state" id="selectedStateName">
            <?php echo $estado_seleccionado ? $nombre_estado : 'Seleccione un estado'; ?>
        </div>
        <div class="population-display" id="populationDisplay">
            <?php if ($estado_seleccionado && $datos_poblacion && !$datos_poblacion['error']): ?>
            <div class="population-card">
                <h5>Población total</h5>
                <h2><?php echo number_format($datos_poblacion['poblacion_total']); ?></h2>
                <p><?php echo $datos_poblacion['anio']; ?></p>
            </div>
            <div class="population-card">
                <i class="fas fa-mars icon text-primary"></i>
                <h5>Hombres</h5>
                <h2 class="text-primary"><?php echo number_format(round($datos_poblacion['poblacion_hombres'])); ?></h2>
            </div>
            <div class="population-card">
                <i class="fas fa-venus icon text-danger"></i>
                <h5>Mujeres</h5>
                <h2 class="text-danger"><?php echo number_format(round($datos_poblacion['poblacion_mujeres'])); ?></h2>
            </div>
            <?php elseif ($estado_seleccionado): ?>
            <div class="alert alert-danger">Error al cargar datos de población para este estado.</div>
            <?php endif; ?>
        </div>
         </div>
        <?php if ($estado_seleccionado): ?>
        <div class="text-center mt-4">
            <a href="municipios.php?estado_id=<?php echo $estado_seleccionado; ?>" class="btn btn-success btn-lg">
                <i class="fas fa-map-marked-alt"></i> Ver por Municipios
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Instrucciones cuando no hay estado seleccionado -->
    <div class="instructions" style="<?php echo !$estado_seleccionado ? 'display: block;' : 'display: none;'; ?>">
        <h3><i class="fas fa-info-circle mr-2"></i> Consulta Datos de Población</h3>
        <p>Selecciona un estado en el mapa para ver información detallada sobre su población. Los datos incluyen:</p>
        <ul>
            <li>Población total según el censo más reciente</li>
            <li>Distribución por género (estimación)</li>
            <li>Densidad poblacional</li>
        </ul>
        <p>Los datos de población se obtienen directamente desde la API del INEGI (Instituto Nacional de Estadística y Geografía).</p>
        <p><i class="fas fa-map-marker-alt text-danger"></i> Adicionalmente, puedes visualizar los sismos registrados utilizando los filtros en la parte superior.</p>
    </div>

    
</div>

<script>
// Datos de estados desde PHP
var estados = <?php echo json_encode($estados); ?>;
var estadoSeleccionado = <?php echo $estado_seleccionado ? "'$estado_seleccionado'" : 'null'; ?>;
var sismos = <?php echo json_encode($sismos); ?>;

// Inicializar mapa
var map;
if (estadoSeleccionado && estados[estadoSeleccionado]) {
    // Si hay un estado seleccionado, centrar el mapa en ese estado con zoom más cercano
    var estadoCoords = [estados[estadoSeleccionado].lat, estados[estadoSeleccionado].lng];
    map = L.map('map').setView(estadoCoords, 8); // Zoom nivel 8 para ver el estado
} else {
    // Vista por defecto de todo México
    map = L.map('map').setView([23.6345, -102.5528], 5);
}

// Capa base de OpenStreetMap
var baseLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

// Crear marcadores para los estados
for (let codigo in estados) {
    let estado = estados[codigo];
    let marker = L.marker([estado.lat, estado.lng], {
        icon: L.divIcon({
            className: 'state-marker',
            html: `<div style="background-color: ${codigo === estadoSeleccionado ? '#0056b3' : '#007bff'}; color: white; padding: 5px; border-radius: 50%; width: 30px; height: 30px; text-align: center; line-height: 20px; cursor: pointer;">${codigo}</div>`,
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        })
    }).bindTooltip(estado.nombre);
    
    // Agregar evento de clic para seleccionar estado
    marker.on('click', function() {
        // Mantener los parámetros de filtro de sismos al cambiar de estado
        let anio = document.getElementById('anioSelect').value;
        let magnitud = document.getElementById('magnitudInput').value;
        window.location.href = 'poblacion.php?estado_id=' + codigo + '&anio=' + anio + '&magnitud=' + magnitud;
    });
    
    marker.addTo(map);
}

// Función para obtener color según magnitud
function getColorSismo(magnitud) {
    return magnitud > 7 ? '#ff0000' :
           magnitud > 6 ? '#ff8c00' :
           magnitud > 5 ? '#ffd700' : '#32cd32';
}

// Variable para capa de sismos
var sismoLayer = L.layerGroup().addTo(map);

// Crear capas de sismos
sismos.forEach(function(sismo) {
    if (sismo.latitud && sismo.longitud) {
        // Coordenadas del sismo
        var coords = [parseFloat(sismo.latitud), parseFloat(sismo.longitud)];
        
        // Fórmula ajustada para radio menos variable
        // Base pequeña + incremento logarítmico moderado
        var magnitud = parseFloat(sismo.magnitud);
        var radioKm = (20000 + (magnitud - 4) * 95000);
        
        // 1. Crear círculo de área de impacto
        var circle = L.circle(
            coords, 
            {
                radius: radioKm, // Radio más consistente entre magnitudes
                color: getColorSismo(magnitud),
                fillColor: getColorSismo(magnitud),
                fillOpacity: 0.2,
                weight: 2
            }
        );
        
        // 2. Crear marcador para el epicentro
        var epicenter = L.circleMarker(
            coords,
            {
                radius: 8, // Tamaño fijo para todos los epicentros
                fillColor: getColorSismo(magnitud),
                color: "#000",
                weight: 1,
                opacity: 1,
                fillOpacity: 1
            }
        );
        
        // Añadir popup solo al marcador del epicentro
        epicenter.bindPopup(`
            <b>Fecha:</b> ${sismo.fecha}<br>
            <b>Magnitud:</b> ${sismo.magnitud}<br>
            <b>Ubicación:</b> ${sismo.referencia_localizacion}<br>
            <b>Profundidad:</b> ${sismo.profundidad} km
        `);
        
        // Añadir ambos elementos a la capa de sismos
        sismoLayer.addLayer(circle);
        sismoLayer.addLayer(epicenter);
    }
});
legend.addTo(map);
</script>
</body>
</html>