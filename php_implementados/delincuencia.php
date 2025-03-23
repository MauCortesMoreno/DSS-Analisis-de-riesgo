<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // Configuración de la base de datos para delincuencia
    $servername = "localhost";
    $username = "root";     // Reemplaza con tu usuario de MySQL
    $password = "TU_CONTRASEÑA";  // Reemplaza con tu contraseña
    $database_delincuencia = "delincuencia";

    // Conexión a la base de datos
    $conn = new mysqli($servername, $username, $password, $database_delincuencia);

    // Verificar conexión
    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }

    // Array para mapear los nombres de los estados en la base de datos a los nombres en el GeoJSON
    $estados_mapping = [
        'Veracruz de Ignacio de la Llave' => 'Veracruz',
        'Michoacán de Ocampo' => 'Michoacán',
        'Ciudad de México' => 'Ciudad de México',
        'Coahuila de Zaragoza' => 'Coahuila',
        'Estado de México' => 'México',
        // Añade aquí más mapeos si es necesario
    ];

    // Función para normalizar nombres de estados
    function normalizar_estado($nombre) {
        global $estados_mapping;
        if (isset($estados_mapping[$nombre])) {
            return $estados_mapping[$nombre];
        }
        return $nombre;
    }

    // Obtener datos de prevalencia delictiva general
    $prevalencia_general = [];
    $sql = "SELECT entidad, tasa_2023 FROM prevalenciadelictiva";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $entidad_normalizada = normalizar_estado($row["entidad"]);
            $prevalencia_general[$entidad_normalizada] = $row["tasa_2023"];
        }
    }

    // Obtener datos de prevalencia delictiva en hombres
    $prevalencia_hombres = [];
    $sql = "SELECT entidad, tasa_2023 FROM prevalenciadelictivahombres";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $entidad_normalizada = normalizar_estado($row["entidad"]);
            $prevalencia_hombres[$entidad_normalizada] = $row["tasa_2023"];
        }
    }

    // Obtener datos de prevalencia delictiva en mujeres
    $prevalencia_mujeres = [];
    $sql = "SELECT entidad, tasa_2023 FROM prevalenciadelictivamujeres";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $entidad_normalizada = normalizar_estado($row["entidad"]);
            $prevalencia_mujeres[$entidad_normalizada] = $row["tasa_2023"];
        }
    }

    // Obtener datos de incidencia delictiva
    $incidencia = [];
    $sql = "SELECT entidad, tasa_2023 FROM incidenciadelictiva";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $entidad_normalizada = normalizar_estado($row["entidad"]);
            $incidencia[$entidad_normalizada] = $row["tasa_2023"];
        }
    }

    // Cerrar conexión
    $conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sistema de Apoyo a Decisiones - Delincuencia en México</title>
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css"/>
    <!-- Leaflet JavaScript -->
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <!-- Leaflet Heat Plugin -->
    <script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
    <!-- Leaflet Choropleth Plugin -->
    <script src="https://unpkg.com/leaflet-choropleth@1.1.4/dist/choropleth.js"></script>
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
            flex-wrap: wrap;
        }
        .map-toggle {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
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
            border-left: 5px solid #dc3545;
        }
        .info-box h2 {
            font-family: 'Poppins', sans-serif;
            color: #dc3545;
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
        .data-selector {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .data-selector button {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            font-weight: 500;
        }
        .data-selector button.active {
            background-color: #dc3545;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .data-selector button:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }
        .legend {
            background: white;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        .legend i {
            width: 18px;
            height: 18px;
            float: left;
            margin-right: 8px;
            opacity: 0.7;
        }
        .info {
            padding: 6px 8px;
            font: 14px/16px Arial, Helvetica, sans-serif;
            background: white;
            background: rgba(255,255,255,0.8);
            box-shadow: 0 0 15px rgba(0,0,0,0.2);
            border-radius: 5px;
        }
        .info h4 {
            margin: 0 0 5px;
            color: #777;
        }
        /* Estilo para el panel de información detallada del estado */
        #estado-info {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
            padding: 20px;
            margin-top: 20px;
            border-left: 5px solid #dc3545;
            display: none;
        }
        #estado-info h3 {
            font-family: 'Poppins', sans-serif;
            color: #dc3545;
            margin-bottom: 15px;
            font-weight: 600;
            font-size: 20px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 8px;
        }
        .datos-tabla {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .datos-tabla th, .datos-tabla td {
            border: 1px solid #dee2e6;
            padding: 10px;
            text-align: left;
        }
        .datos-tabla th {
            background-color: #f8f9fa;
            font-weight: 500;
        }
        .datos-tabla tr:nth-child(even) {
            background-color: #f8f9fa;
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
    </style>
</head>
<body>
<header>
    <h1>Sistema de Apoyo a Decisiones (DSS) - Delincuencia en México</h1>
</header>

<div class="container">
    <div class="controls">
        <div class="map-toggle">
            <a href="mexico.php">Inicio</a>
            <a href="sismos.php">Sismos</a>
            <a href="poblacion.php">Población</a>
            <a href="aeconomica.php">Economia</a>
            <a href="riesgo.php">Riesgo</a>
            <a href="resultado_final.php" class="active">Graficar Torres</a>
        </div>
    </div>
    <!-- Add the sub-controls section here -->
    <div class="sub-controls">
        <a href="aeropuertos.php">Aeropuertos</a>
        <a href="helipuertos.php">Helipuertos</a>
        <a href="delincuencia.php" class="active">Delincuencia</a>
    </div>
    <div class="data-selector">
            <button id="btn-prevalencia" class="active">Prevalencia General</button>
            <button id="btn-hombres">Prevalencia Hombres</button>
            <button id="btn-mujeres">Prevalencia Mujeres</button>
            <button id="btn-incidencia">Incidencia Delictiva</button>
        </div>
    <div id="map"></div>
    
    <!-- Panel de información detallada del estado -->
    <div id="estado-info">
        <h3 id="estado-titulo">Información Detallada: <span id="nombre-estado"></span></h3>
        <table class="datos-tabla">
            <thead>
                <tr>
                    <th>Indicador</th>
                    <th>Tasa 2023</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Incidencia Delictiva</td>
                    <td id="incidencia-valor">-</td>
                </tr>
                <tr>
                    <td>Prevalencia Delictiva General</td>
                    <td id="prevalencia-valor">-</td>
                </tr>
                <tr>
                    <td>Prevalencia Delictiva Hombres</td>
                    <td id="hombres-valor">-</td>
                </tr>
                <tr>
                    <td>Prevalencia Delictiva Mujeres</td>
                    <td id="mujeres-valor">-</td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="info-box">
        <h2><i class="fas fa-chart-bar"></i> Datos de Delincuencia por Estado (2023)</h2>
        <p>Este mapa muestra la tasa de delincuencia por estado en México, utilizando datos de prevalencia e incidencia delictiva del año 2023. La intensidad del color indica la magnitud de la tasa: los tonos más oscuros representan mayores tasas de delincuencia.</p>
        
        <p><strong>Definiciones:</strong></p>
        <ul>
            <li><strong>Prevalencia delictiva:</strong> Proporción de la población de 18 años y más que experimentó al menos un delito durante el período de referencia.</li>
            <li><strong>Incidencia delictiva:</strong> Número total de delitos reportados durante el período de referencia.</li>
        </ul>
        
        <p>Utilice los botones superiores para alternar entre los diferentes conjuntos de datos y haga clic en un estado para ver información detallada de todos los indicadores.</p>
    </div>
</div>

<script>
// Inicializar mapa con vista de todo México
var map = L.map('map').setView([23.6345, -102.5528], 5);

// Capa base de OpenStreetMap
var baseLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

// Cargar el GeoJSON de estados de México
fetch('https://raw.githubusercontent.com/angelnmara/geojson/master/mexicoHigh.json')
    .then(response => response.json())
    .then(mexicoData => {
        // Pasar los datos de PHP a JavaScript
        var prevalenciaGeneral = <?php echo json_encode($prevalencia_general); ?>;
        var prevalenciaHombres = <?php echo json_encode($prevalencia_hombres); ?>;
        var prevalenciaMujeres = <?php echo json_encode($prevalencia_mujeres); ?>;
        var incidenciaDelictiva = <?php echo json_encode($incidencia); ?>;
        
        // Mapeo de nombres de estados entre GeoJSON y base de datos
        const stateNameMapping = {
            'Veracruz': 'Veracruz',
            'Michoacan': 'Michoacán',
            'Mexico City': 'Ciudad de México',
            'Coahuila': 'Coahuila',
            'Mexico': 'México',
            'Nuevo Leon': 'Nuevo León',
            'San Luis Potosi': 'San Luis Potosí',
            'Yucatan': 'Yucatán',
            'Queretaro': 'Querétaro'
            // Añade más mapeos si es necesario
        };
        
        // Función para obtener el nombre normalizado del estado
        function getNormalizedStateName(name) {
            return stateNameMapping[name] || name;
        }
        
        // Añadir propiedades a las características GeoJSON
        mexicoData.features.forEach(function(feature) {
            var stateName = feature.properties.name;
            var normalizedStateName = getNormalizedStateName(stateName);
            
            feature.properties.prevalenciaGeneral = prevalenciaGeneral[normalizedStateName] || 0;
            feature.properties.prevalenciaHombres = prevalenciaHombres[normalizedStateName] || 0;
            feature.properties.prevalenciaMujeres = prevalenciaMujeres[normalizedStateName] || 0;
            feature.properties.incidenciaDelictiva = incidenciaDelictiva[normalizedStateName] || 0;
        });
        
        // Registrar estados sin datos para depuración
        let estadosSinDatos = [];
        mexicoData.features.forEach(function(feature) {
            var stateName = feature.properties.name;
            var normalizedStateName = getNormalizedStateName(stateName);
            
            if (!prevalenciaGeneral[normalizedStateName]) {
                estadosSinDatos.push(stateName + ' -> ' + normalizedStateName);
            }
        });
        
        if (estadosSinDatos.length > 0) {
            console.log("Estados sin datos:", estadosSinDatos);
        }
        
        // Control de información al pasar el mouse
        var info = L.control();
        info.onAdd = function (map) {
            this._div = L.DomUtil.create('div', 'info');
            this.update();
            return this._div;
        };
        
        info.update = function (props) {
            this._div.innerHTML = '<h4>Delincuencia en México</h4>' +  
                (props ? 
                '<b>' + props.name + '</b><br />' + 
                'Tasa: ' + getActiveData(props) : 'Pase el cursor sobre un estado');
        };
        
        info.addTo(map);
        
        // Función para determinar qué conjunto de datos está activo
        function getActiveData(props) {
            if (document.getElementById('btn-hombres').classList.contains('active')) {
                return props.prevalenciaHombres;
            } else if (document.getElementById('btn-mujeres').classList.contains('active')) {
                return props.prevalenciaMujeres;
            } else if (document.getElementById('btn-incidencia').classList.contains('active')) {
                return props.incidenciaDelictiva;
            } else {
                return props.prevalenciaGeneral;
            }
        }
        
        // Función para obtener color basado en el valor (tonos rojos)
        function getColor(d) {
            return d > 40000 ? '#67000d' :
                   d > 30000 ? '#a50f15' :
                   d > 20000 ? '#cb181d' :
                   d > 10000 ? '#ef3b2c' :
                   d > 5000  ? '#fb6a4a' :
                   d > 1000  ? '#fc9272' :
                              '#fee0d2';
        }
        
        function style(feature) {
            return {
                fillColor: getColor(getActiveData(feature.properties)),
                weight: 2,
                opacity: 1,
                color: 'white',
                dashArray: '3',
                fillOpacity: 0.7
            };
        }
        
        // Eventos de interacción
        function highlightFeature(e) {
            var layer = e.target;
            layer.setStyle({
                weight: 5,
                color: '#666',
                dashArray: '',
                fillOpacity: 0.7
            });
            layer.bringToFront();
            info.update(layer.feature.properties);
        }
        
        function resetHighlight(e) {
            geojson.resetStyle(e.target);
            info.update();
        }
        
        // Función para mostrar información detallada del estado al hacer clic
        function showStateDetails(e) {
            var props = e.target.feature.properties;
            var normalizedStateName = getNormalizedStateName(props.name);
            
            // Actualizar el panel de información
            document.getElementById('nombre-estado').textContent = normalizedStateName;
            document.getElementById('incidencia-valor').textContent = props.incidenciaDelictiva || '-';
            document.getElementById('prevalencia-valor').textContent = props.prevalenciaGeneral || '-';
            document.getElementById('hombres-valor').textContent = props.prevalenciaHombres || '-';
            document.getElementById('mujeres-valor').textContent = props.prevalenciaMujeres || '-';
            
            // Mostrar el panel de información
            document.getElementById('estado-info').style.display = 'block';
            
            // Ajustar el mapa si es necesario
            map.fitBounds(e.target.getBounds());
        }
        
        function onEachFeature(feature, layer) {
            layer.on({
                mouseover: highlightFeature,
                mouseout: resetHighlight,
                click: showStateDetails
            });
        }
        
        // Crear capa GeoJSON
        var geojson = L.geoJson(mexicoData, {
            style: style,
            onEachFeature: onEachFeature
        }).addTo(map);
        
        // Leyenda
        var legend = L.control({position: 'bottomright'});
        legend.onAdd = function (map) {
            var div = L.DomUtil.create('div', 'info legend'),
                grades = [0, 1000, 5000, 10000, 20000, 30000, 40000],
                labels = [],
                from, to;
                
            for (var i = 0; i < grades.length; i++) {
                from = grades[i];
                to = grades[i + 1];
                
                labels.push(
                    '<i style="background:' + getColor(from + 1) + '"></i> ' +
                    from + (to ? '&ndash;' + to : '+'));
            }
            
            div.innerHTML = labels.join('<br>');
            return div;
        };
        
        legend.addTo(map);
        
        // Cambiar entre diferentes conjuntos de datos
        document.getElementById('btn-prevalencia').addEventListener('click', function() {
            setActiveButton(this);
            geojson.setStyle(style);
        });
        
        document.getElementById('btn-hombres').addEventListener('click', function() {
            setActiveButton(this);
            geojson.setStyle(style);
        });
        
        document.getElementById('btn-mujeres').addEventListener('click', function() {
            setActiveButton(this);
            geojson.setStyle(style);
        });
        
        document.getElementById('btn-incidencia').addEventListener('click', function() {
            setActiveButton(this);
            geojson.setStyle(style);
        });
        
        function setActiveButton(button) {
            // Quitar la clase active de todos los botones
            document.querySelectorAll('.data-selector button').forEach(function(btn) {
                btn.classList.remove('active');
            });
            // Añadir la clase active al botón seleccionado
            button.classList.add('active');
        }
    })
    .catch(error => console.error('Error loading GeoJSON:', error));
</script>
</body>
</html>