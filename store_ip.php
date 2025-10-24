<?php

require_once 'db_config.php'; // Include database configuration
require_once 'vendor/mobiledetect/mobiledetectlib/src/MobileDetect.php';

use Detection\MobileDetect;

// Error handling: log errors to a file
function logError($message) {
    error_log($message . PHP_EOL, 3, 'error_log.txt');
}

// Debug mode toggle
$debug = true;

// Function to get visitor's real IP address
function getRealIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim(end($ipList)); // Use the last IP in the list
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

// Function to extract browser information from user agent
function getBrowser($user_agent) {
    if (strpos($user_agent, 'Firefox') !== false) {
        return 'Firefox';
    } elseif (strpos($user_agent, 'Chrome') !== false && strpos($user_agent, 'Edg') === false) {
        return 'Chrome';
    } elseif (strpos($user_agent, 'Safari') !== false && strpos($user_agent, 'Chrome') === false) {
        return 'Safari';
    } elseif (strpos($user_agent, 'Edg') !== false) {
        return 'Edge';
    } elseif (strpos($user_agent, 'OPR') !== false || strpos($user_agent, 'Opera') !== false) {
        return 'Opera';
    } elseif (strpos($user_agent, 'Apache-HttpClient') !== false) {
        return 'Apache HTTP Client (Tool)';
    } elseif (strpos($user_agent, 'Postman') !== false) {
        return 'Postman (Tool)';
    } else {
        return 'Unknown';
    }
}

try {
    $ip_address = getRealIP();
    $ipinfo_url = "http://ip-api.com/json/{$ip_address}";
    $response = file_get_contents($ipinfo_url);
    if ($response === false) {
        logError("Failed to fetch IP data for IP: {$ip_address}");
        $region = $city = $asn = $isp = 'Unknown';
        $latitude = $longitude = 0.0;
    } else {
        $responseData = json_decode($response, true);
        $region = $responseData['regionName'] ?? 'Unknown';
        $city = $responseData['city'] ?? 'Unknown';
        $asn = $responseData['as'] ?? 'Unknown';
        $isp = $responseData['isp'] ?? 'Unknown';

        if ($asn !== 'Unknown') {
            if (preg_match('/^AS(\d+)\s+(.*)$/', $asn, $matches)) {
                $asn = $matches[1];
                if (empty($isp) || $isp === 'Unknown') {
                    $isp = $matches[2];
                }
            }
        }

        $latitude = $responseData['lat'] ?? 0.0;
        $longitude = $responseData['lon'] ?? 0.0;
    }

    $destination_port = $_SERVER['SERVER_PORT'] ?? 80;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $browser = getBrowser($user_agent);

    $detect = new MobileDetect;
    if ($detect->isMobile()) {
        $device_type = 'Mobile';
    } elseif ($detect->isTablet()) {
        $device_type = 'Tablet';
    } elseif ($detect->isWatch()) {
        $device_type = 'Watch';
    } elseif (strpos($user_agent, 'Apache-HttpClient') !== false) {
        $device_type = 'Automated Tool';
    } else {
        $device_type = 'Desktop';
    }

    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $session_id = session_id();

    $stmt = $conn->prepare(
        "INSERT INTO visitors (ip_address, region, destination_port, session_id, user_agent, device_type, browser, city, asn, isp, latitude, longitude) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $stmt->bind_param(
        "ssisssssssdd", 
        $ip_address, $region, $destination_port, $session_id, 
        $user_agent, $device_type, $browser, 
        $city, $asn, $isp, $latitude, $longitude
    );

    if (!$stmt->execute()) {
        throw new Exception("Database insertion error: " . $stmt->error);
    }

    $data = [
        "IP Address" => $ip_address,
        "Region" => $region,
        "City" => $city,
        "ASN" => $asn,
        "ISP" => $isp,
        "Latitude" => $latitude,
        "Longitude" => $longitude,
        "Port" => $destination_port,
        "Session ID" => $session_id,
        "User Agent" => $user_agent,
        "Device Type" => $device_type,
        "Browser" => $browser,
    ];

    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Visitor Information</title>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #f8f9fa;
                margin: 0;
                padding: 0;
            }
            .container {
                max-width: 800px;
                margin: 50px auto;
                padding: 20px;
                background: #ffffff;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                border-radius: 8px;
            }
            h1 {
                text-align: center;
                color: #333;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            table, th, td {
                border: 1px solid #ddd;
            }
            th, td {
                padding: 10px;
                text-align: left;
            }
            th {
                background-color: #f2f2f2;
            }
            #map {
                height: 400px;
                margin-top: 20px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Visitor Details</h1>
            <table>
                <thead>
                    <tr><th>Field</th><th>Value</th></tr>
                </thead>
                <tbody>';
    foreach ($data as $key => $value) {
        echo "<tr><td>{$key}</td><td>{$value}</td></tr>";
    }
    echo '      </tbody>
            </table>
            <div id="map"></div>
        </div>
        <script>
            var map = L.map("map").setView([' . $latitude . ', ' . $longitude . '], 13);
            L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                maxZoom: 19,
                attribution: "© OpenStreetMap contributors"
            }).addTo(map);
            L.marker([' . $latitude . ', ' . $longitude . ']).addTo(map)
                .bindPopup("Location: ' . htmlspecialchars($city) . ', ' . htmlspecialchars($region) . '")
                .openPopup();
        </script>
    </body>
    </html>';

    $stmt->close();
} catch (Exception $e) {
    logError("Error: " . $e->getMessage());
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
} finally {
    $conn->close();
}
