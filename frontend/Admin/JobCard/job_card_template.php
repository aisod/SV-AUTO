<?php
function renderJobCardHeader($business, $logoUrl, $cardNumber) {
    $logo = file_exists(__DIR__ . '/' . $logoUrl) ? "<img src='" . htmlspecialchars($logoUrl) . "' class='jc-logo' alt='Logo'>" : '';
    return $logo . "<div class='jc-header'><h1>" . htmlspecialchars($business['name'] ?? 'SV Auto Services') . "</h1><div class='slogan'>" . htmlspecialchars($business['slogan'] ?? 'Luxury • Precision • Trust') . "</div><div class='card-no'>" . htmlspecialchars($cardNumber) . "</div></div>";
}

function renderSection($title, $content) {
    return "<div class='section'><h2>$title</h2>$content</div>";
}

function renderTable($rows) {
    return "<table class='jc-table'>" . implode('', $rows) . "</table>";
}

function renderRow($label, $value, $highlight = false) {
    $class = $highlight ? 'value highlight' : 'value';
    return "<tr><td class='label'>$label</td><td class='$class'>" . htmlspecialchars($value ?: '—') . "</td></tr>";
}

function renderDescBox($content, $default = '') {
    return "<div class='desc-box'>" . nl2br(htmlspecialchars($content ?: $default)) . "</div>";
}

function renderFooter($business, $qrCode, $techName) {
    return "
    <div style='text-align:center;margin:20px 0 15px;'>
        <img src='" . htmlspecialchars($qrCode) . "' alt='QR Code' style='width:100px;height:100px;border:2px solid white;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.1);'>
        <p style='font-size:10px;color:var(--s);margin-top:4px;'>Scan to access</p>
    </div>
    <div style='display:grid;grid-template-columns:1fr 1fr;gap:20px;margin:15px 0;'>
        <div class='sign-box'>
            <i class='fas fa-pen' style='font-size:32px;color:#ccc;margin-bottom:10px;'></i>
            <div class='sign-label-bottom'>" . htmlspecialchars($techName ?? 'Technician') . "</div>
        </div>
        <div class='sign-box'>
            <i class='fas fa-handshake' style='font-size:32px;color:#ccc;margin-bottom:10px;'></i>
            <div class='sign-label-bottom'>Client Signature</div>
        </div>
    </div>
    <div class='jc-footer'>
        <strong>" . htmlspecialchars($business['name'] ?? 'SV Auto Services') . "</strong><br>
        " . htmlspecialchars($business['address'] ?? '') . " • " . htmlspecialchars($business['phone'] ?? '') . " • " . htmlspecialchars($business['email'] ?? '') . "
    </div>";
}
?>

