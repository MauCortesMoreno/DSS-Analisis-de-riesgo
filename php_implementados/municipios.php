<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // Configuración de la base de datos para población
    $servername = "localhost";
    $username = "root";     // Reemplaza con tu usuario de MySQL
    $password = "TU_CONTRASEÑA";  // Reemplaza con tu contraseña
    $database_poblacion = "poblacion_mexico_reducida";

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

    // Verificar si se ha seleccionado un estado
    $estado_id = isset($_GET['estado_id']) ? $_GET['estado_id'] : null;
    
    // Datos del estado seleccionado
    $estado_nombre = null;
    $estado_lat = null;
    $estado_lng = null;
    
    if ($estado_id && isset($estados[$estado_id])) {
        $estado_nombre = $estados[$estado_id]['nombre'];
        $estado_lat = $estados[$estado_id]['lat'];
        $estado_lng = $estados[$estado_id]['lng'];
    } else {
        // Redireccionar a poblacion.php si no hay estado seleccionado
        header("Location: poblacion.php");
        exit;
    }

    // Datos de municipios y estadísticas para el estado seleccionado
    $municipios = [];
    $estadisticas = [
        'total_municipios' => 0,
        'total_localidades' => 0,
        'poblacion_total' => 0,
        'poblacion_hombres' => 0,
        'poblacion_mujeres' => 0,
        'municipios_principales' => []
    ];

    try {
        // Conectar a la base de datos
        $conn = new mysqli($servername, $username, $password, $database_poblacion);
        handleDatabaseError($conn, "Error al conectar a la base de datos");
        
        // Consulta para obtener datos de municipios del estado seleccionado
        $sql_municipios = "SELECT 
            municipio, 
            municipio_nombre, 
            longitud, 
            latitud, 
            poblacion_total, 
            poblacion_masculina, 
            poblacion_femenina
        FROM poblacion_inegi_reducida
        WHERE entidad = ? AND municipio > 0 AND localidad = 0";
        
        $stmt = $conn->prepare($sql_municipios);
        $entidad_id = intval($estado_id);
        $stmt->bind_param("i", $entidad_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $municipios[] = $row;
            // Acumular estadísticas
            $estadisticas['total_municipios']++;
            $estadisticas['poblacion_total'] += $row['poblacion_total'];
            $estadisticas['poblacion_hombres'] += $row['poblacion_masculina'];
            $estadisticas['poblacion_mujeres'] += $row['poblacion_femenina'];
            
            // Guardar los 5 municipios principales por población
            if (count($estadisticas['municipios_principales']) < 5 || $row['poblacion_total'] > min(array_column($estadisticas['municipios_principales'], 'poblacion'))) {
                $estadisticas['municipios_principales'][] = [
                    'id' => $row['municipio'],
                    'nombre' => $row['municipio_nombre'],
                    'poblacion' => $row['poblacion_total']
                ];
                // Ordenar por población (mayor a menor)
                usort($estadisticas['municipios_principales'], function($a, $b) {
                    return $b['poblacion'] - $a['poblacion'];
                });
                // Mantener solo 5 si superamos ese número
                if (count($estadisticas['municipios_principales']) > 5) {
                    array_pop($estadisticas['municipios_principales']);
                }
            }
        }
        
        // Consulta para contar localidades del estado
        $sql_localidades = "SELECT COUNT(*) as total_localidades FROM poblacion_inegi_reducida WHERE entidad = ? AND localidad > 0";
        $stmt = $conn->prepare($sql_localidades);
        $stmt->bind_param("i", $entidad_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $estadisticas['total_localidades'] = $row['total_localidades'];
        
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        // En caso de error, dejar los arreglos vacíos
    }

    // Formatear porcentajes para estadísticas
    if ($estadisticas['poblacion_total'] > 0) {
        $estadisticas['porcentaje_hombres'] = round(($estadisticas['poblacion_hombres'] / $estadisticas['poblacion_total']) * 100, 2);
        $estadisticas['porcentaje_mujeres'] = round(($estadisticas['poblacion_mujeres'] / $estadisticas['poblacion_total']) * 100, 2);
    } else {
        $estadisticas['porcentaje_hombres'] = 0;
        $estadisticas['porcentaje_mujeres'] = 0;
    }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Municipios de <?php echo $estado_nombre; ?> - Sistema DSS</title>
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
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            height: 500px; 
            width: 100%; 
            border-radius: 8px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            margin-bottom: 20px;
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
        .back-button {
            background-color: #6c757d;
            color: white;
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
            margin-right: 10px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        .stat-card h2 {
            margin-top: 0;
            font-size: 1.3rem;
            color: #333;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #0056b3;
            margin: 10px 0;
        }
        .gender-stats {
            display: flex;
            justify-content: space-between;
        }
        .gender-stat {
            text-align: center;
            flex: 1;
        }
        .gender-stat i {
            font-size: 24px;
            margin-bottom: 5px;
        }
        .gender-value {
            font-size: 1.5rem;
            font-weight: bold;
        }
        .main-municipalities h2 {
            margin-top: 0;
            font-size: 1.3rem;
            color: #333;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        .municipality-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .municipality-name {
            font-weight: 500;
        }
        .municipality-population {
            font-weight: bold;
            color: #0056b3;
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
            margin-right: 8px;
            display: inline-block;
        }
        .chart-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<header>
    <h1>Sistema de Apoyo a Decisiones (DSS) - Municipios de <?php echo $estado_nombre; ?></h1>
</header>

<div class="container">
    <div class="controls">
        <div class="map-toggle">
            <a href="poblacion.php" class="back-button"><i class="fas fa-arrow-left"></i> Volver a Estados</a>
            <a href="mexico.php">Inicio</a>
            <a href="sismos.php">Sismos</a>
            <a href="poblacion.php">Población</a>
            <a href="" class="active">Municipios</a>
            <a href="">Economía</a>
            <a href="delincuencia.php">Delincuencia</a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div id="map"></div>
        </div>
    </div>

    <div class="row">
        <!-- Columna de estadísticas -->
        <div class="col-md-4">
            <div class="stat-card">
                <h2>Estadísticas de Población</h2>
                <p><strong>Total de Municipios:</strong> <?php echo $estadisticas['total_municipios']; ?></p>
                <p><strong>Total de Localidades:</strong> <?php echo $estadisticas['total_localidades']; ?></p>
                <div class="stat-value"><?php echo number_format($estadisticas['poblacion_total']); ?></div>
                <p>habitantes</p>
                <div class="gender-stats">
                    <div class="gender-stat">
                        <i class="fas fa-mars text-primary"></i>
                        <div class="gender-value text-primary"><?php echo number_format($estadisticas['poblacion_hombres']); ?></div>
                        <div><?php echo $estadisticas['porcentaje_hombres']; ?>%</div>
                    </div>
                    <div class="gender-stat">
                        <i class="fas fa-venus text-danger"></i>
                        <div class="gender-value text-danger"><?php echo number_format($estadisticas['poblacion_mujeres']); ?></div>
                        <div><?php echo $estadisticas['porcentaje_mujeres']; ?>%</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Columna de municipios principales -->
        <div class="col-md-4">
            <div class="stat-card main-municipalities">
                <h2>Principales Municipios</h2>
                <?php foreach ($estadisticas['municipios_principales'] as $municipio): ?>
                <div class="municipality-item">
                    <span class="municipality-name"><?php echo $municipio['nombre']; ?></span>
                    <span class="municipality-population"><?php echo number_format($municipio['poblacion']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Columna con gráfica -->
        <div class="col-md-4">
            <div class="chart-container">
                <canvas id="populationChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
// Datos de municipios desde PHP
var municipios = <?php echo json_encode($municipios); ?>;
var estadoNombre = "<?php echo $estado_nombre; ?>";
var estadoLat = <?php echo $estado_lat ?? 23.6345; ?>;
var estadoLng = <?php echo $estado_lng ?? -102.5528; ?>;
// Modificación en la sección de JavaScript - dentro del script

// Inicializar mapa
var map = L.map('map').setView([estadoLat, estadoLng], 8);

// Capa base de OpenStreetMap
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

// Preparar datos para el mapa de calor
var heatData = [];
var maxPoblacion = 0;

municipios.forEach(function(municipio) {
    if (municipio.latitud && municipio.longitud && municipio.poblacion_total) {
        // Importante: convertir valores a números
        var lat = parseFloat(municipio.latitud);
        var lng = parseFloat(municipio.longitud);
        var poblacion = parseInt(municipio.poblacion_total);
        
        // Verificar que las coordenadas son válidas (no NaN)
        if (!isNaN(lat) && !isNaN(lng) && !isNaN(poblacion)) {
            // Formato para leaflet-heat: [lat, lng, intensity]
            // Nota: No dividir la población por 100, eso reduce demasiado la intensidad
            heatData.push([lat, lng, poblacion]);
            
            // Actualizar población máxima para la escala
            if (poblacion > maxPoblacion) {
                maxPoblacion = poblacion;
            }
            
            // Añadir marcador para cada municipio
            var marker = L.marker([lat, lng])
                .bindPopup(
                    "<b>" + municipio.municipio_nombre + "</b><br>" +
                    "Población total: " + poblacion.toLocaleString() + "<br>" +
                    "Hombres: " + parseInt(municipio.poblacion_masculina).toLocaleString() + "<br>" +
                    "Mujeres: " + parseInt(municipio.poblacion_femenina).toLocaleString()
                );
            marker.addTo(map);
        }
    }
});

// Configurar y añadir capa de mapa de calor con parámetros ajustados
var heat = L.heatLayer(heatData, {
    radius: 25,  // Radio de cada punto de calor
    blur: 15,    // Difuminado del calor
    maxZoom: 10, // Nivel máximo de zoom para el efecto de calor
    max: maxPoblacion, // El valor máximo para la intensidad
    minOpacity: 0.4, // Opacidad mínima de los puntos de calor
    gradient: {
        0.4: 'blue',
        0.6: 'cyan',
        0.7: 'lime',
        0.8: 'yellow',
        1.0: 'red'
    }
}).addTo(map);

// Añadir leyenda al mapa
var legend = L.control({position: 'bottomright'});

legend.onAdd = function (map) {
    var div = L.DomUtil.create('div', 'legend');
    div.innerHTML = 
        '<h4>Densidad de Población</h4>' +
        '<div class="legend-item"><span class="legend-color" style="background: blue;"></span>Baja</div>' +
        '<div class="legend-item"><span class="legend-color" style="background: cyan;"></span>Media-Baja</div>' +
        '<div class="legend-item"><span class="legend-color" style="background: lime;"></span>Media</div>' +
        '<div class="legend-item"><span class="legend-color" style="background: yellow;"></span>Media-Alta</div>' +
        '<div class="legend-item"><span class="legend-color" style="background: red;"></span>Alta</div>';
    return div;
};

legend.addTo(map);

// Crear gráfica de población por municipio (top 5)
var ctx = document.getElementById('populationChart').getContext('2d');
var municipiosPrincipales = <?php echo json_encode($estadisticas['municipios_principales']); ?>;

var municipioNames = municipiosPrincipales.map(function(m) { return m.nombre; });
var municipioPoblacion = municipiosPrincipales.map(function(m) { return m.poblacion; });

var populationChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: municipioNames,
        datasets: [{
            label: 'Población',
            data: municipioPoblacion,
            backgroundColor: [
                'rgba(255, 99, 132, 0.7)',
                'rgba(54, 162, 235, 0.7)',
                'rgba(255, 206, 86, 0.7)',
                'rgba(75, 192, 192, 0.7)',
                'rgba(153, 102, 255, 0.7)'
            ],
            borderColor: [
                'rgba(255, 99, 132, 1)',
                'rgba(54, 162, 235, 1)',
                'rgba(255, 206, 86, 1)',
                'rgba(75, 192, 192, 1)',
                'rgba(153, 102, 255, 1)'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Principales Municipios por Población'
            },
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString();
                    }
                }
            }
        }
    }
});
</script>
</body>
</html>