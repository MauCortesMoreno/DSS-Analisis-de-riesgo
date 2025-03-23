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
    
    // Parámetros para filtrar (año y magnitud mínima)
    $anio = isset($_GET['anio']) ? intval($_GET['anio']) : date('Y');
    $magnitud_minima = isset($_GET['magnitud']) ? floatval($_GET['magnitud']) : 5.0;

    // Establecer conexión a la base de datos de sismos
    $conn_sismos = new mysqli($servername, $username, $password, $database_sismos);
    handleDatabaseError($conn_sismos, "Conexión fallida a base de sismos");

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

    $sismos = [];
    while ($row = $result_sismos->fetch_assoc()) {
        $sismos[] = $row;
    }

    // Cerrar conexiones
    $stmt_sismos->close();
    $conn_sismos->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mapa de Sismos de México</title>
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
        .controls form {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .controls select, 
        .controls input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
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
        .earthquake-stats {
            display: flex;
            justify-content: space-around;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 20px;
            margin-top: 20px;
        }
        .stat-card {
            text-align: center;
            padding: 10px;
        }
        .stat-card h3 {
            color: #6c757d;
            margin-bottom: 10px;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
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
    </style>
</head>
<body>
<header>
    <h1>Sistema de Apoyo a Decisiones (DSS) - Mapa de Sismos</h1>
</header>

<div class="container">
    <div class="controls">
        <form method="GET" id="filterForm">
            <label>Año: 
                <select name="anio" id="anioSelect">
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
                       min="4.0" max="9.0" step="0.1">
            </label>
            <input type="submit" value="Filtrar" class="btn btn-primary">
        </form>
        <div class="map-toggle">
            <a href="mexico.php">Inicio</a>
            <a href="sismos.php" class="active">Sismos</a>
            <a href="poblacion.php">Población</a>
            <a href="aeconomica.php">Economia</a>
            <a href="riesgo.php">Riesgo</a>
            <a href="resultado_final.php" class="active">Graficar Torres</a>
        </div>
    </div>

    <div id="map"></div>

    <div class="earthquake-stats" id="earthquakeStats">
        <div class="stat-card">
            <h3>Total de Sismos</h3>
            <div class="stat-value" id="totalSismos"><?php echo count($sismos); ?></div>
        </div>
        <div class="stat-card">
            <h3>Sismo de Mayor Magnitud</h3>
            <div class="stat-value" id="sismoMayor">
                <?php 
                if (!empty($sismos)) {
                    $max_sismo = max(array_column($sismos, 'magnitud'));
                    echo $max_sismo;
                } else {
                    echo "N/A";
                }
                ?>
            </div>
        </div>
        <div class="stat-card">
            <h3>Región más Activa</h3>
            <div class="stat-value" id="regionActiva">
                <?php
                if (!empty($sismos)) {
                    $ubicaciones = array_column($sismos, 'referencia_localizacion');
                    $ubicacion_frecuente = array_count_values($ubicaciones);
                    arsort($ubicacion_frecuente);
                    $region_mas_activa = array_key_first($ubicacion_frecuente);
                    echo $region_mas_activa;
                } else {
                    echo "N/A";
                }
                ?>
            </div>
        </div>
</div>
<div style="text-align: center; margin: 30px 0;">
        <a href="datos_sismicos.php" style="
            display: inline-block;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 12px 24px;
            font-size: 18px;
            border-radius: 8px;
            text-decoration: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: all 0.3s ease;">
            <i class="fas fa-chart-bar"></i> Visualizar Datos Sísmicos Detallados
        </a>
    </div>

<script>
// Datos de sismos desde PHP
var sismos = <?php echo json_encode($sismos); ?>;

// Inicializar mapa con vista de todo México
var map = L.map('map').setView([23.6345, -102.5528], 5);

// Capa base de OpenStreetMap
var baseLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

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
        var radioKm = (20000 + (magnitud - 4) * 85000);
        
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
                radius: 12, // Tamaño fijo para todos los epicentros
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
</script>
</body>
</html>