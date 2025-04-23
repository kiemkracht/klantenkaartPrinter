<?php

require __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// 📁 Configuratie
$apiUrl = 'https://auth.kiemkracht.org/api/tickets';
$apiKey = 'G!1wU1a8uUhPjwf8vCrWkiJk16JveVu!RA!B05/ZpDLG5DgtD?sRG/gJ4o05EsbjDGp5haGX9iYSmVR0JOB4FMTPMPK?=70Gr7zEemz-jXrMAVz?n0u4M6DizT!Zci25-7F=i4OaXt8JTNInE!h9BdMoE!Oyd!mZ26=zedY!Z?jPy9rl=JakMFBKx=lyQIx9RfD4VA7Cx3suOik5EFoa!vFkgKwLa1osA=1A9J6lZj=08pNo!WCIk/1PNs8WqFgK'; // API sleutel
$queueName = 'Waasmunster';
$printerName = 'star';
$sumatraPath = "C:\\Users\\RenierLeenaers\\AppData\\Local\\SumatraPDF\\SumatraPDF.exe";
$logFile = __DIR__ . '/printqueue.log';
$logoPath = __DIR__ . '/logo.png';
//vergeet niet de locatie van the run_php.bat te veranderen naar het juist pad 
//vergeet niet de locatie van the invisible_runner.vbs te veranderen naar het juist pad 

// 📝 Logfunctie
function logMessage($msg) {
    global $logFile;
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND);
}

// 🟡 Start printproces
logMessage("🟡 PRINT STARTED voor winkel: $queueName");

// ✅ Check of logo beschikbaar is
if (!file_exists($logoPath)) {
    logMessage("❌ logo.png niet gevonden.");
    exit("❌ logo.png niet gevonden.\n");
}
$logoData = base64_encode(file_get_contents($logoPath));
$logoSrc = 'data:image/png;base64,' . $logoData;

// 📥 Haal tickets op van de API
$url = $apiUrl . "/next?queue=" . urlencode($queueName) . "&limit=3&key=" . urlencode($apiKey);
$response = @file_get_contents($url);

if ($response === false) {
    logMessage("❌ Kan geen verbinding maken met API: $url");
    exit;
}

$tickets = json_decode($response, true);

if (!is_array($tickets) || empty($tickets)) {
    logMessage("ℹ️ Geen tickets te printen voor $queueName.");
    exit;
}

logMessage("📥 API gaf " . count($tickets) . " ticket(s) terug.");

// 🔁 Print elk ticket
foreach ($tickets as $ticket) {
    $id = $ticket['id'];
    $barcode = $ticket['barcode'];
    $voornaam = htmlspecialchars($ticket['voornaam'] ?? 'Klant');
    $barcodeUrl = "https://barcodeapi.org/api/128/{$barcode}";
    $pdfPath = __DIR__ . "/foto/ticket_{$id}.pdf";

    // 📄 HTML voor PDF
    $html = <<<HTML
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-size: 14px; font-family: Arial, sans-serif; }
        .ticket { max-width: 80mm; margin: 0 auto; }
        .logo { height: 130px; display: block; margin: 0 auto 20px auto; }
        h1 { font-size: 20px; margin: 0 10px 15px 10px; }
        p { margin: 0 10px 12px 10px; line-height: 1.6; }
        .barcode { max-width: 100%; height: auto; margin: 15px auto 0 auto; display: block; }
    </style>
</head>
<body>
    <div class="ticket">
        <img class="logo" src="{$logoSrc}" alt="logo">
        <h1>Hej {$voornaam}</h1>
        <p>
            Dit is jouw tijdelijke KiemKracht klantenkaart.<br><br>
            Raadpleeg je gegevens via <b>mijn.kiemkracht.org</b>.<br><br>
            Vergeten te scannen? Gebruik het ticketnummer.<br><br>
            Je fysieke kaart wordt per post verstuurd.
        </p>
        <img class="barcode" src="{$barcodeUrl}" alt="barcode">
    </div>
</body>
</html>
HTML;

    // 🧾 Maak PDF aan
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper([0, 0, 226.77, 9999], 'portrait');
    $dompdf->render();
    file_put_contents($pdfPath, $dompdf->output());

    logMessage("📄 PDF aangemaakt voor ticket ID $id");

    // 🖨️ Print PDF met Sumatra
    $escapedPdfPath = str_replace('/', '\\', realpath($pdfPath));
    $printerArg = escapeshellarg($printerName);
    $command = "\"$sumatraPath\" -print-to $printerArg \"$escapedPdfPath\"";
    pclose(popen("start /min /B cmd /C \"$command\"", "r"));

    logMessage("🖨️ Ticket ID $id verzonden naar printer");

    sleep(2); // even wachten zodat print klaar is

    // 🗑️ Verwijder PDF
    if (file_exists($pdfPath)) {
        unlink($pdfPath);
        logMessage("🗑️ PDF verwijderd voor ticket ID $id");
    }

    // ✅ Markeer ticket als geprint via API
    $markUrl = $apiUrl . "/mark/{$id}?key=" . urlencode($apiKey);
    $markResponse = @file_get_contents($markUrl);
    if ($markResponse === false) {
        logMessage("❌ Fout bij markeren van ticket ID $id");
    } else {
        logMessage("✅ Ticket ID $id gemarkeerd als geprint");
    }
}

// 🟢 Klaar
logMessage("🟢 PRINT KLAAR voor winkel: $queueName");
