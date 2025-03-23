<?php
// Incluir archivos de datos
require_once 'config.php';
require_once 'sismos_data.php';
require_once 'poblacion_data.php';
require_once 'economia_data.php';
require_once 'riesgo_data.php';

// Obtener datos de cada fuente
$estados_sismos = obtener_datos_sismos();
$estados_poblacion = obtener_datos_poblacion();
$estados_economia = obtener_datos_economia();
$estados_delincuencia = obtener_datos_delincuencia();

// Datos de capitales de estados específicos con los nuevos nombres de torres
$capitales = array(
    "CDMX" => array("nombre" => "Ciudad de México", "lat" => 19.4326, "lng" => -99.1332, "torre" => "Torre Alfa: El edificio más alto de México"),
    "JAL" => array("nombre" => "Guadalajara", "lat" => 20.6767, "lng" => -103.3475, "torre" => "Torre Beta: El edificio más alto de América"),
    "NL" => array("nombre" => "Monterrey", "lat" => 25.6866, "lng" => -100.3161, "torre" => "Torre Omega: El edificio más alto del mundo")
);

// Combinar datos y calcular puntaje final
$estados_combinados = array();
foreach ($estados_nombres as $estado => $datos) {
    // Verificar si el estado existe en todos los conjuntos de datos necesarios
    if (isset($estados_sismos[$estado]) && isset($estados_poblacion[$estado])) {
        $peso_sismos = isset($estados_sismos[$estado]["peso_sismos"]) ? $estados_sismos[$estado]["peso_sismos"] : 0;
        $peso_poblacion = isset($estados_poblacion[$estado]["peso_poblacion"]) ? $estados_poblacion[$estado]["peso_poblacion"] : 0;
        $peso_economia = isset($estados_economia[$estado]["peso_economia"]) ? $estados_economia[$estado]["peso_economia"] : 0;
        $peso_seguridad = isset($estados_delincuencia[$estado]["peso_seguridad"]) ? $estados_delincuencia[$estado]["peso_seguridad"] : 0;
        
        // Fórmula ponderada: 35% sismos, 30% población, 20% economía, 15% seguridad
        $puntaje_final = ($peso_sismos * 0.35) + ($peso_poblacion * 0.30) + ($peso_economia * 0.20) + ($peso_seguridad * 0.15);
        
        $estados_combinados[$estado] = array(
            "id" => $datos['id'],
            "clave" => $estado,
            "nombre" => $datos['nombre'],
            "numero_sismos" => isset($estados_sismos[$estado]["numero_sismos"]) ? $estados_sismos[$estado]["numero_sismos"] : 0,
            "magnitud_promedio" => isset($estados_sismos[$estado]["magnitud_promedio"]) ? $estados_sismos[$estado]["magnitud_promedio"] : 0,
            "peso_sismos" => $peso_sismos,
            "poblacion_total" => isset($estados_poblacion[$estado]["poblacion_total"]) ? $estados_poblacion[$estado]["poblacion_total"] : 0,
            "peso_poblacion" => $peso_poblacion,
            "produccion_bruta" => isset($estados_economia[$estado]["produccion_bruta"]) ? $estados_economia[$estado]["produccion_bruta"] : 0,
            "peso_economia" => $peso_economia,
            "tasa_delictiva" => isset($estados_delincuencia[$estado]["tasa_delictiva"]) ? $estados_delincuencia[$estado]["tasa_delictiva"] : 0,
            "peso_seguridad" => $peso_seguridad,
            "puntaje_final" => $puntaje_final,
            "capital" => isset($capitales[$estado]) ? $capitales[$estado] : null
        );
    }
}

// Ordenar por puntaje final (de mayor a menor)
uasort($estados_combinados, function($a, $b) {
    return $b["puntaje_final"] <=> $a["puntaje_final"];
});

// Crear un array con solo los tres estados específicos
$torres_estados = array();
foreach (["CDMX", "JAL", "NL"] as $estado_clave) {
    if (isset($estados_combinados[$estado_clave])) {
        $torres_estados[$estado_clave] = $estados_combinados[$estado_clave];
    }
}
$torres_json = json_encode($torres_estados);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Análisis para Construcción de Torres en México</title>
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
        #mapa-mexico { 
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        th {
            background-color: #007bff;
            color: white;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
        }
        tr:hover {
            background-color: #f1f8ff;
        }
        tr.top-estado {
            background-color: #e3f2fd;
            font-weight: 500;
        }
        tr.top-estado:hover {
            background-color: #c8e6ff;
        }
        .torre-info {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
            border-left: 5px solid #28a745;
        }
        .torre-info h3 {
            font-family: 'Poppins', sans-serif;
            color: #28a745;
            margin-bottom: 15px;
            font-weight: 600;
            font-size: 20px;
        }
        .torre-info ul {
            list-style-type: none;
            padding: 0;
        }
        .torre-info ul li {
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
        }
        .torre-info ul li:before {
            content: '\f1ad';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            margin-right: 10px;
            color: #28a745;
        }
        .tower-icon {
            color: #28a745;
            filter: drop-shadow(0 2px 3px rgba(0, 0, 0, 0.3));
        }
    </style>
</head>
<body>
    <header>
        <h1>Análisis para Construcción de Torres en México</h1>
    </header>

    <div class="container">
        <div class="controls">
            <div class="map-toggle">
                <a href="mexico.php">Inicio</a>
                <a href="sismos.php">Sismos</a>
                <a href="poblacion.php">Población</a>
                <a href="aeconomica.php">Economía</a>
                <a href="riesgo.php">Riesgo</a>
                <a href="#" class="active">Graficar Torres</a>
            </div>
        </div>
        
        <!-- Mapa de México -->
        <div id="mapa-mexico"></div>
        
        <!-- Información de torres -->
        <div class="torre-info">
            <h3><i class="fas fa-building"></i> Torres propuestas en las capitales de los estados seleccionados</h3>
            <ul id="torres-lista"></ul>
        </div>
        
        <!-- Información de factores de análisis -->
        <div class="info-box">
            <h2><i class="fas fa-balance-scale"></i> Factores de análisis para la construcción de torres</h2>
            <p>La selección de ubicaciones óptimas para la construcción de torres se basa en un análisis multifactorial ponderado que considera los siguientes elementos:</p>
            
            <div class="feature-grid">
                <div class="feature-item">
                    <i class="fas fa-earthquake"></i>
                    <h3>Actividad sísmica (35%)</h3>
                    <p>Se valora inversamente la cantidad de sismos registrados y su magnitud para garantizar la seguridad estructural.</p>
                </div>
                
                <div class="feature-item">
                    <i class="fas fa-users"></i>
                    <h3>Población (30%)</h3>
                    <p>Se valora directamente el tamaño poblacional como indicador de demanda potencial y uso futuro.</p>
                </div>
                
                <div class="feature-item">
                    <i class="fas fa-chart-line"></i>
                    <h3>Economía (20%)</h3>
                    <p>Se considera la producción bruta total como indicador de desarrollo económico y viabilidad financiera.</p>
                </div>
                
                <div class="feature-item">
                    <i class="fas fa-shield-alt"></i>
                    <h3>Seguridad (15%)</h3>
                    <p>Se valora inversamente la tasa de incidencia delictiva como factor de seguridad regional para la inversión.</p>
                </div>
            </div>
        </div>
        
        <!-- Tabla de resultados -->
        <div class="info-box">
            <h2><i class="fas fa-table"></i> Top 5 Estados para Construcción de Torres</h2>
            <table>
                <tr>
                    <th>Posición</th>
                    <th>Estado</th>
                    <th>Número de Sismos</th>
                    <th>Magnitud Promedio</th>
                    <th>Población Total</th>
                    <th>Producción Bruta (millones)</th>
                    <th>Tasa Delictiva</th>
                    <th>Puntaje Final</th>
                </tr>
                <?php
                $posicion = 1;
                foreach ($estados_combinados as $estado => $datos) {
                    echo "<tr class='top-estado'>";
                    echo "<td>" . $posicion . "</td>";
                    echo "<td>" . $datos["nombre"] . " (" . $datos["clave"] . ")</td>";
                    echo "<td>" . number_format($datos["numero_sismos"]) . "</td>";
                    echo "<td>" . number_format($datos["magnitud_promedio"], 2) . "</td>";
                    echo "<td>" . number_format($datos["poblacion_total"]) . "</td>";
                    echo "<td>$" . number_format($datos["produccion_bruta"]/1000000, 2) . "</td>";
                    echo "<td>" . number_format($datos["tasa_delictiva"], 1) . "</td>";
                    echo "<td>" . number_format($datos["puntaje_final"], 2) . "</td>";
                    echo "</tr>";
                    
                    $posicion++;
                    if ($posicion > 5) break;  // Mostrar solo los 5 primeros estados
                }
                ?>
            </table>
        </div>
    </div>

    <script>
        // Datos de torres
        var torresDatos = <?php echo $torres_json; ?>;
        
        // Inicializar mapa centrado en México
        var mapa = L.map('mapa-mexico').setView([23.6345, -102.5528], 5);
        
        // Añadir capa de OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(mapa);
        
        // Icono personalizado para las torres
        var torreIcon = L.divIcon({
            html: '<div class="tower-icon"><i class="fas fa-building fa-2x"></i></div>',
            className: '',
            iconSize: [30, 30],
            iconAnchor: [15, 30]
        });
        
        // Nombres de torres según posición
        var nombresTorres = {
            "CDMX": "Torre Omega: El edificio más alto del mundo",
            "JAL": "Torre Beta: El edificio más alto de América",
            "NL": "Torre Alfa: El edificio más alto de México"
        };
        
        // Añadir marcadores para cada torre
        var listaHTML = '';
        var posicion = 1;
        Object.keys(torresDatos).forEach(function(clave) {
            var torre = torresDatos[clave];
            if (torre.capital) {
                // Obtener el nombre de la torre según la posición
                var nombreTorre = nombresTorres[clave];
                
                // Añadir marcador al mapa
                var marker = L.marker([torre.capital.lat, torre.capital.lng], {icon: torreIcon})
                    .addTo(mapa)
                    .bindPopup('<strong>' + nombreTorre + '</strong><br>' +
                               '<i class="fas fa-map-marker-alt"></i> ' + torre.capital.nombre + '<br>' +
                               '<i class="fas fa-star"></i> Puntaje: ' + torre.puntaje_final.toFixed(2));
                
                // Añadir a la lista
                listaHTML += '<li><strong>' + nombreTorre + '</strong> - ' + torre.capital.nombre + 
                             ' <span class="badge bg-success">' + torre.puntaje_final.toFixed(2) + ' pts</span></li>';
                
                posicion++;
            }
        });
        
        document.getElementById('torres-lista').innerHTML = listaHTML;
    </script>
</body>
</html>
                