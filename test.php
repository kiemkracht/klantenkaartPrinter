<?php

require __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$config = require __DIR__ . '/config.php';

function logMessage($msg) {
    global $config;
    file_put_contents($config['log_file'], "[" . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND);
}

logMessage("🟡 PRINT TEST (no DB update) started for store: {$config['current_store']}");

$mysqli = new mysqli(
    $config['db_host'],
    $config['db_user'],
    $config['db_pass'],
    $config['db_name'],
    $config['db_port']
);

if ($mysqli->connect_error) {
    logMessage("❌ Connection failed: " . $mysqli->connect_error);
    exit(1);
}

// Load logo and encode as base64
$logoPath = __DIR__ . '/logo.png';
if (!file_exists($logoPath)) {
    logMessage("❌ logo.png not found in script folder.");
    exit("❌ logo.png not found.\n");
}
$logoData = base64_encode(file_get_contents($logoPath));
$logoSrc = 'data:image/png;base64,' . $logoData;

// Get all unprinted tickets
$query = $mysqli->prepare("SELECT id, barcode, user_id FROM printqueues WHERE printed_at IS NULL AND queue = ?");
$query->bind_param("s", $config['current_store']);
$query->execute();
$result = $query->get_result();

logMessage("🔍 Found " . $result->num_rows . " ticket(s) to PRINT (no DB update).");

while ($row = $result->fetch_assoc()) {
    try {
        $userResult = $mysqli->query("SELECT voornaam FROM users WHERE id = " . intval($row['user_id']) . " LIMIT 1");
        $user = $userResult->fetch_assoc();
        $voornaam = htmlspecialchars($user['voornaam'] ?? 'Klant');

        $barcode = $row['barcode'];
        $barcodeUrl = "https://barcodeapi.org/api/128/{$barcode}";
        $pdfPath = __DIR__ . "/foto/ticket_{$row['id']}.pdf";

        // HTML with embedded base64 logo
        $html = <<<HTML
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-size: 14px;
            font-family: Arial, sans-serif;
        }
        body {
            margin: 0;
        }
        .ticket {
            max-width: 80mm;
            margin: 0 auto;
        }
        .logo {
            height: 130px;
            display: block;
            margin: 0 auto 20px auto;
        }
        h1 {
            font-size: 20px;
            text-align: left;
            margin: 0 10px 15px 10px;
        }
        p {
            text-align: left;
            line-height: 1.6;
            margin: 0 10px 12px 10px;
            font-size: 14px;
        }
        .barcode {
            max-width: 100%;
            height: auto;
            margin: 15px auto 0 auto;
            display: block;
        }
    </style>
    <title>Klantenkaart</title>
</head>
<body>
    <div class="ticket">
        <img class="logo" src="{$logoSrc}" alt="logo">
        <h1>Hej {$voornaam}</h1>
        <p>
            Dit is jouw tijdelijke KiemKracht klantenkaart. Vanaf nu kan je sparen voor extra tegoed bij elke aankoop.<br><br>
            Je kunt je gegevens altijd raadplegen en aanpassen via <b>mijn.kiemkracht.org</b> en jouw aankoopgeschiedenis bekijken.<br><br>
            Jouw klantenkaart vergeten scannen bij het afrekenen? Log in op <b>mijn.kiemkracht.org</b> en voer het ticketnummer in om jouw tegoed te claimen.<br><br>
            Indien je een fysieke klantenkaart hebt aangevraagd, zal deze per post naar het opgegeven adres worden gestuurd.
        </p>
        <img class="barcode" src="{$barcodeUrl}" alt="barcode">
    </div>
</body>
</html>
HTML;

        // Generate PDF
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper([0, 0, 226.77, 9999], 'portrait');
        $dompdf->render();
        file_put_contents($pdfPath, $dompdf->output());
        logMessage("📄 PDF created: $pdfPath");

        // Print via SumatraPDF
        $sumatraPath = "C:\\Users\\RenierLeenaers\\AppData\\Local\\SumatraPDF\\SumatraPDF.exe";
        $escapedPdfPath = str_replace('/', '\\', realpath($pdfPath));
        $printerName = escapeshellarg($config['printer_name']);
        $command = "\"$sumatraPath\" -print-to $printerName \"$escapedPdfPath\"";
        pclose(popen("start /min /B cmd /C \"$command\"", "r"));
        logMessage("🖨️ Sent to printer: $escapedPdfPath");

        logMessage("⏭️ Skipped DB update for ID {$row['id']}");

    } catch (Exception $e) {
        logMessage("❌ Error on ID {$row['id']}: " . $e->getMessage());
    }
}

$query->close();
$mysqli->close();
logMessage("✅ TEST PRINT finished for store: {$config['current_store']}");
