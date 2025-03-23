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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sistema de Apoyo a Decisiones - Sismos y Población de México</title>
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
            height: 600px; 
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
    </style>
</head>
<body>
<header>
    <h1>Sistema de Apoyo a Decisiones (DSS) - Impacto de Sismos en Población</h1>
</header>

<div class="container">
    <div class="controls">
        <div class="map-toggle">
            <a href="mexico.php" class="active">Inicio</a>
            <a href="sismos.php">Sismos</a>
            <a href="poblacion.php">Población</a>
            <a href="aeconomica.php">Economia</a>
            <a href="riesgo.php">Riesgo</a>
            <a href="resultado_final.php" class="active">Graficar Torres</a>
        </div>
    </div>

    <div id="map"></div>
    
    <div class="info-box">
        <h2><i class="fas fa-info-circle"></i> Bienvenido al Sistema de Apoyo a Decisiones</h2>
        <p>Este sistema de apoyo a decisiones proporciona una plataforma integral para visualizar y analizar datos sobre sismos y población en México, permitiendo a los usuarios tomar decisiones informadas basadas en datos geoespaciales precisos.</p>
        
        <div class="feature-grid">
            <div class="feature-item">
                <i class="fas fa-earthquake"></i>
                <h3>Análisis de Sismos</h3>
                <p>Visualice la actividad sísmica de México filtrada por año, magnitud y ubicación para identificar patrones y zonas de riesgo.</p>
            </div>
            
            <div class="feature-item">
                <i class="fas fa-users"></i>
                <h3>Datos Demográficos</h3>
                <p>Acceda a información detallada sobre la población en cada estado para evaluar el impacto potencial de desastres naturales.</p>
            </div>
            
            <div class="feature-item">
                <i class="fas fa-chart-line"></i>
                <h3>Estadísticas Avanzadas</h3>
                <p>Obtenga análisis estadísticos sobre la frecuencia y magnitud de sismos a lo largo del tiempo en diferentes regiones.</p>
            </div>
        </div>
    </div>
</div>

<script>
// Inicializar mapa con vista de todo México
var map = L.map('map').setView([23.6345, -102.5528], 5);

// Capa base de OpenStreetMap
var baseLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);
</script>
</body>
</html>
