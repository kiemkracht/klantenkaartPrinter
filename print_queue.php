<?php

require __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// === CONFIGURATION ===
$apiUrl = 'https://www.kiemkracht.org/api.php';; // hierkomt het de naam van de api
$apiKey = 'abc123'; // hier komt de api key voor elke unieke kassa 
$queueName = 'Waasmunster'; // hier komt de naam van de winkel

$printerName = 'star'; // zet hier de exacte naam van de kassa 
$sumatraPath = "C:\\Users\\RenierLeenaers\\AppData\\Local\\SumatraPDF\\SumatraPDF.exe"; // zet hier de exacte pad naar sumatrapdf 
$logFile = __DIR__ . '/printqueue.log';
$logoPath = __DIR__ . '/logo.png';

function logMessage($msg) {
    global $logFile;
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND);
}

// FETCH TICKETS VAN DE API 
$url = "$apiUrl?action=next&queue=$queueName&limit=3&key=$apiKey";
$response = file_get_contents($url);
$tickets = json_decode($response, true);

if (!is_array($tickets) || empty($tickets)) {
    logMessage("ℹ️ No tickets to print for $queueName.");
    exit;
}

if (!file_exists($logoPath)) {
    logMessage("❌ logo.png not found.");
    exit("❌ logo.png not found.\n");
}
$logoData = base64_encode(file_get_contents($logoPath));
$logoSrc = 'data:image/png;base64,' . $logoData;

foreach ($tickets as $ticket) {
    $id = $ticket['id'];
    $barcode = $ticket['barcode'];
    $voornaam = htmlspecialchars($ticket['voornaam'] ?? 'Klant');
    $barcodeUrl = "https://barcodeapi.org/api/128/{$barcode}";
    $pdfPath = __DIR__ . "/foto/ticket_{$id}.pdf";

    //  HTML 
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
        body { margin: 0; }
        .ticket { max-width: 80mm; margin: 0 auto; }
        .logo { height: 130px; display: block; margin: 0 auto 20px auto; }
        h1 { font-size: 20px; text-align: left; margin: 0 10px 15px 10px; }
        p { text-align: left; line-height: 1.6; margin: 0 10px 12px 10px; font-size: 14px; }
        .barcode { max-width: 100%; height: auto; margin: 15px auto 0 auto; display: block; }
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

    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper([0, 0, 226.77, 9999], 'portrait');
    $dompdf->render();
    file_put_contents($pdfPath, $dompdf->output());
    logMessage("📄 PDF created for ticket ID $id");

    $escapedPdfPath = str_replace('/', '\\', realpath($pdfPath));
    $printerArg = escapeshellarg($printerName);
    $command = "\"$sumatraPath\" -print-to $printerArg \"$escapedPdfPath\"";
    pclose(popen("start /min /B cmd /C \"$command\"", "r"));
    logMessage("🖨️ Sent to printer: $escapedPdfPath");

    sleep(2);

    if (file_exists($pdfPath)) {
        unlink($pdfPath);
        logMessage("🗑️ Deleted PDF for ticket ID $id");
    }

    // MARKEER TICKET ALS PRINTED 
    $markUrl = "$apiUrl?action=mark&id=$id&key=$apiKey";
    file_get_contents($markUrl);
    logMessage("✅ Marked ticket ID $id as printed");
}

logMessage("✅ PRINT FINISHED for $queueName");
