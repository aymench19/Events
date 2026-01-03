<?php

namespace App\Service;

class QrCodeService
{
    /**
     * Generate QR code image as base64 PNG
     */
    public function generateTicketQrCode(string $ticketKey, string $eventName): string
    {
        // Data to encode
        $data = urlencode(json_encode([
            'ticket_key' => $ticketKey,
            'event' => $eventName,
            'date' => date('Y-m-d')
        ]));

        // Generate QR code using free API
        $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . $data;

        try {
            $qrContent = @file_get_contents($qrUrl);
            if ($qrContent !== false && !empty($qrContent)) {
                return base64_encode($qrContent);
            }
        } catch (\Exception $e) {
            // Fallback to SVG
        }

        return $this->generateSvgQrCode($ticketKey);
    }

    /**
     * Generate simple SVG QR code placeholder
     */
    private function generateSvgQrCode(string $ticketKey): string
    {
        $ticketDisplay = htmlspecialchars($ticketKey);
        $svg = '<svg width="300" height="300" xmlns="http://www.w3.org/2000/svg">' .
            '<rect width="300" height="300" fill="white" stroke="black" stroke-width="2"/>' .
            '<text x="150" y="150" font-size="14" text-anchor="middle" fill="black" font-family="monospace" font-weight="bold">' .
            $ticketDisplay .
            '</text></svg>';

        return base64_encode($svg);
    }

    /**
     * Generate ticket HTML that can be converted to PDF
     */
    public function generateTicketHtml(
        string $ticketKey,
        string $eventName,
        string $ticketType,
        string $price,
        string $userName,
        string $issuedAt,
        ?string $expiresAt = null
    ): string {
        $qrCodeBase64 = $this->generateTicketQrCode($ticketKey, $eventName);
        $expiresText = $expiresAt ? $expiresAt : 'Never';

        // Sanitize data for HTML
        $eventNameSafe = htmlspecialchars($eventName, ENT_QUOTES, 'UTF-8');
        $ticketKeySafe = htmlspecialchars($ticketKey, ENT_QUOTES, 'UTF-8');
        $userNameSafe = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
        $priceSafe = htmlspecialchars($price, ENT_QUOTES, 'UTF-8');
        $ticketTypeSafe = htmlspecialchars($ticketType, ENT_QUOTES, 'UTF-8');

        return '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticket - ' . $ticketKeySafe . '</title>
<style>
@page { margin: 0; size: A4; }
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: Arial, sans-serif; color: #333; background: #f9f9f9; padding: 20px; }
.ticket-container {
    background: white;
    max-width: 595px;
    margin: 0 auto;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
    page-break-after: always;
}
.header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 40px 30px;
    text-align: center;
}
.header h1 { font-size: 32px; margin: 0 0 5px 0; font-weight: bold; }
.header p { font-size: 14px; margin: 0; opacity: 0.9; }
.content { padding: 40px 30px; }
.section { margin-bottom: 30px; }
.section-title {
    font-size: 12px;
    font-weight: bold;
    color: #667eea;
    text-transform: uppercase;
    margin-bottom: 15px;
    letter-spacing: 1px;
}
.info-row { margin-bottom: 12px; display: flex; align-items: center; }
.label {
    font-weight: bold;
    color: #555;
    min-width: 130px;
    font-size: 14px;
}
.value { color: #333; font-size: 14px; }
.divider { border-top: 2px dashed #ddd; margin: 20px 0; }
.ticket-key-box {
    text-align: center;
    margin: 20px 0;
    padding: 20px;
    background: #f5f5f5;
    border: 2px solid #667eea;
    border-radius: 5px;
}
.ticket-key {
    font-size: 18px;
    font-weight: bold;
    color: #667eea;
    font-family: "Courier New", monospace;
    letter-spacing: 2px;
}
.price-box {
    text-align: center;
    font-size: 24px;
    font-weight: bold;
    color: #764ba2;
    margin: 10px 0;
}
.badge {
    display: inline-block;
    background: #667eea;
    color: white;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}
.qr-section {
    text-align: center;
    margin: 30px 0;
    padding: 20px;
    background: #fafafa;
    border-radius: 5px;
}
.qr-code {
    max-width: 200px;
    height: auto;
    margin: 15px auto;
    border: 1px solid #ddd;
    padding: 10px;
    background: white;
}
.footer {
    background: #f0f0f0;
    padding: 20px;
    text-align: center;
    font-size: 12px;
    color: #666;
    border-top: 1px solid #ddd;
}
.footer p { margin: 5px 0; }
@media print {
    body { background: white; padding: 0; }
    .ticket-container { box-shadow: none; margin: 0; max-width: 100%; }
}
</style>
</head>
<body>
<div class="ticket-container">
    <div class="header">
        <h1>🎫 Event Ticket</h1>
        <p>Official Ticket with QR Code</p>
    </div>

    <div class="content">
        <div class="section">
            <div class="section-title">Event Information</div>
            <div class="info-row">
                <span class="label">Event:</span>
                <span class="value">' . $eventNameSafe . '</span>
            </div>
            <div class="info-row">
                <span class="label">Type:</span>
                <span class="badge">' . $ticketTypeSafe . '</span>
            </div>
        </div>

        <div class="divider"></div>

        <div class="section">
            <div class="section-title">Ticket Details</div>
            <div class="ticket-key-box">
                <div class="ticket-key">' . $ticketKeySafe . '</div>
            </div>
            <div class="price-box">$' . $priceSafe . '</div>
        </div>

        <div class="divider"></div>

        <div class="section">
            <div class="section-title">Holder Information</div>
            <div class="info-row">
                <span class="label">Name:</span>
                <span class="value">' . $userNameSafe . '</span>
            </div>
            <div class="info-row">
                <span class="label">Issued:</span>
                <span class="value">' . $issuedAt . '</span>
            </div>
            <div class="info-row">
                <span class="label">Expires:</span>
                <span class="value">' . $expiresText . '</span>
            </div>
        </div>

        <div class="divider"></div>

        <div class="qr-section">
            <div class="section-title">Verification QR Code</div>
            <img src="data:image/png;base64,' . $qrCodeBase64 . '" class="qr-code" alt="QR Code">
            <p style="font-size: 11px; color: #999; margin-top: 10px;">Scan QR code for verification</p>
        </div>
    </div>

    <div class="footer">
        <p><strong>Official event ticket - Please present at entrance</strong></p>
        <p>Valid: ' . $issuedAt . ' to ' . $expiresText . '</p>
    </div>
</div>
</body>
</html>';
    }

    /**
     * Generate PDF ticket with QR code
     * Changed to return HTML that browser can print/convert to PDF
     */
    public function generateTicketPdf(
        string $ticketKey,
        string $eventName,
        string $ticketType,
        string $price,
        string $userName,
        string $issuedAt,
        ?string $expiresAt = null
    ): string {
        // Generate HTML
        $html = $this->generateTicketHtml(
            $ticketKey,
            $eventName,
            $ticketType,
            $price,
            $userName,
            $issuedAt,
            $expiresAt
        );

        // Return HTML as the response
        // The browser will handle printing/saving as PDF
        return $html;
    }
}
