<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // Configuración de la base de datos
    $servername = "localhost";
    $username = "root";     // Reemplaza con tu usuario de MySQL
    $password = "TU_CONTRASEÑA";  // Reemplaza con tu contraseña
    $database = "aeropuertos_mexico";  // Nombre correcto de la base de datos

    // Función para manejar errores de conexión
    function handleDatabaseError($connection, $errorMessage) {
        if ($connection->connect_error) {
            die($errorMessage . ": " . $connection->connect_error);
        }
    }

    // Conexión a la base de datos
    $conn = new mysqli($servername, $username, $password, $database);
    $conn->set_charset("utf8");
    handleDatabaseError($conn, "Error de conexión a la base de datos");

    // Consulta para obtener todos los aeropuertos (excluyendo helipuertos)
    $sql = "SELECT id, nombre, latitud, longitud, region, ciudad, iata, tipo FROM aeropuertos WHERE tipo LIKE '%airport%' AND tipo NOT LIKE '%small_airport%'";
    $result = $conn->query($sql);

    // Array para almacenar los datos de aeropuertos
    $aeropuertos = array();

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $aeropuertos[] = array(
                'id' => $row['id'],
                'nombre' => $row['nombre'],
                'lat' => $row['latitud'],
                'lng' => $row['longitud'],
                'region' => $row['region'],
                'ciudad' => $row['ciudad'],
                'iata' => $row['iata'],
                'tipo' => $row['tipo']
            );
        }
    }
    
    // Contar aeropuertos por estado
    $aeropuertos_por_estado = array();
    foreach ($aeropuertos as $aeropuerto) {
        $estado = $aeropuerto['region'];
        if (!empty($estado)) {
            if (isset($aeropuertos_por_estado[$estado])) {
                $aeropuertos_por_estado[$estado]++;
            } else {
                $aeropuertos_por_estado[$estado] = 1;
            }
        }
    }
    
    // Ordenar por cantidad de aeropuertos (de mayor a menor)
    arsort($aeropuertos_por_estado);
    
    // Preparar datos para la gráfica
    $labels = array_keys($aeropuertos_por_estado);
    $data = array_values($aeropuertos_por_estado);
    
    $conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sistema de Apoyo a Decisiones - Aeropuertos de México</title>
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
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Roboto', sans-serif; 
            margin: 0; 
            padding: 0; 
            background-color: #f8f9fa;
            color: #333;
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
            font-weight: 500;
        }
        .map-toggle a.active {
            background-color: #0056b3;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .map-toggle a:hover {
            background-color: #0069d9;
            transform: translateY(-2px);
        }
        #map { 
            height: 400px; 
            width: 100%; 
            border-radius: 8px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            margin-bottom: 30px;
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
        .info-box {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
            padding: 30px;
            margin-top: 20px;
            transition: transform 0.3s ease;
            border-left: 5px solid #007bff;
        }
        .info-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
        }
        .info-box h2 {
            font-family: 'Poppins', sans-serif;
            color: #007bff;
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 24px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        .info-box p {
            font-size: 16px;
            line-height: 1.6;
            color: #555;
            margin-bottom: 15px;
        }
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 25px;
        }
        .feature-item {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }
        .feature-item:hover {
            background-color: #007bff;
            color: white;
            transform: scale(1.03);
        }
        .feature-item:hover i {
            color: white;
        }
        .feature-item i {
            font-size: 32px;
            color: #007bff;
            margin-bottom: 15px;
        }
        .feature-item h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 18px;
            margin-bottom: 10px;
            font-weight: 500;
        }
        .feature-item p {
            font-size: 14px;
            color: inherit;
        }
        .sub-controls {
            background-color: #f1f8ff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            justify-content: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .sub-controls a {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
        }
        .sub-controls a:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }
        .sub-controls a.active {
            background-color: #1e7e34;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .chart-container {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
            padding: 20px;
            margin: 30px 0;
            height: 400px;
        }
        .chart-title {
            font-family: 'Poppins', sans-serif;
            color: #007bff;
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 24px;
            text-align: center;
        }
    </style>
</head>
<body>
<header>
    <h1>Sistema de Apoyo a Decisiones (DSS) - Análisis de Riesgo</h1>
</header>

<div class="container">
    <div class="controls">
        <div class="map-toggle">
            <a href="mexico.php">Inicio</a>
            <a href="sismos.php">Sismos</a>
            <a href="poblacion.php">Población</a>
            <a href="aeconomica.php">Economia</a>
            <a href="riesgo.php" class="active">Riesgo</a>
            <a href="resultado_final.php" class="active">Graficar Torres</a>
        </div>
    </div>

    <div class="sub-controls">
        <a href="aeropuertos.php" class="active">Aeropuertos</a>
        <a href="helipuertos.php">Helipuertos</a>
        <a href="delincuencia.php">Delincuencia</a>
    </div>

    <div id="map"></div>
    
    <!-- Nueva sección para la gráfica de aeropuertos por estado -->
    <div class="chart-container">
        <h2 class="chart-title"><i class="fas fa-chart-bar"></i> Distribución de Aeropuertos por Estado</h2>
        <canvas id="aeropuertosChart"></canvas>
    </div>
    
    <div class="info-box">
        <h2><i class="fas fa-plane-departure"></i> Aeropuertos de México</h2>
        <p>Este mapa muestra la ubicación de todos los aeropuertos registrados en México. La información geográfica permite visualizar su distribución en el territorio nacional para análisis de cobertura y accesibilidad.</p>
        <p>Utilice el mapa interactivo para explorar los diferentes aeropuertos. Puede hacer clic en cada marcador para obtener información detallada sobre el aeropuerto seleccionado.</p>
        <p>Los aeropuertos son infraestructura crítica para el transporte de recursos y personal durante emergencias, permitiendo una respuesta rápida ante situaciones de desastre.</p>
        <p>El gráfico de barras muestra la distribución de aeropuertos por estado, facilitando la identificación de las regiones con mayor y menor cobertura aeroportuaria en el país.</p>
    </div>
</div>

<script>
// Inicializar mapa con vista de todo México
var map = L.map('map').setView([23.6345, -102.5528], 5);

// Capa base de OpenStreetMap
var baseLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

// Icono personalizado para aeropuertos
var airportIcon = L.icon({
    iconUrl: 'https://cdn-icons-png.flaticon.com/512/7015/7015914.png',
    iconSize: [25, 25],
    iconAnchor: [12, 12],
    popupAnchor: [0, -10]
});

// Añadir marcadores para cada aeropuerto
<?php foreach ($aeropuertos as $aeropuerto): ?>
    // Verificar que las coordenadas sean válidas
    <?php if (!empty($aeropuerto['lat']) && !empty($aeropuerto['lng'])): ?>
    var marker = L.marker([<?php echo $aeropuerto['lat']; ?>, <?php echo $aeropuerto['lng']; ?>], {icon: airportIcon})
        .addTo(map)
        .bindPopup("<strong><?php echo htmlspecialchars($aeropuerto['nombre']); ?></strong><br>" +
                  "Tipo: <?php echo htmlspecialchars($aeropuerto['tipo']); ?><br>" +
                  "Ciudad: <?php echo htmlspecialchars($aeropuerto['ciudad']); ?><br>" +
                  "Región: <?php echo htmlspecialchars($aeropuerto['region']); ?><br>" +
                  "IATA: <?php echo htmlspecialchars($aeropuerto['iata']); ?>");
    <?php endif; ?>
<?php endforeach; ?>

// Crear gráfica de aeropuertos por estado
var ctx = document.getElementById('aeropuertosChart').getContext('2d');
var chart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_slice($labels, 0, 15)); ?>,
        datasets: [{
            label: 'Número de Aeropuertos',
            data: <?php echo json_encode(array_slice($data, 0, 15)); ?>,
            backgroundColor: 'rgba(0, 123, 255, 0.7)',
            borderColor: 'rgba(0, 123, 255, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: true,
                text: 'Top 15 Estados con Mayor Número de Aeropuertos'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Número de Aeropuertos'
                }
            },
            x: {
                title: {
                    display: true,
                    text: 'Estados'
                }
            }
        }
    }
});
</script>
</body>
</html>