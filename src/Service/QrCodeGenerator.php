<?php

namespace App\Service;

use App\Entity\QrCode;
use Endroid\QrCode\QrCode as EndroidQrCode;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class QrCodeGenerator
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads/qrcodes')]
        private string $uploadDir,
    ) {}

    // ── Construire le contenu selon le type ──────────────────────────
    public function buildContent(string $type, array $data): string
    {
        return match($type) {
            'url'   => $data['url'] ?? '',
            'text'  => $data['text'] ?? '',
            'email' => sprintf('mailto:%s?subject=%s&body=%s',
                            $data['to'] ?? '',
                            urlencode($data['subject'] ?? ''),
                            urlencode($data['body'] ?? '')),
            'tel'   => 'tel:' . ($data['tel'] ?? ''),
            'sms'   => sprintf('smsto:%s:%s', $data['tel'] ?? '', $data['message'] ?? ''),
            'geo'   => sprintf('geo:%s,%s', $data['lat'] ?? '0', $data['lng'] ?? '0'),
            'wifi'  => sprintf('WIFI:T:%s;S:%s;P:%s;;',
                            $data['security'] ?? 'WPA',
                            $data['ssid'] ?? '',
                            $data['password'] ?? ''),
            'vcard' => implode("\n", [
                            'BEGIN:VCARD',
                            'VERSION:3.0',
                            'N:'     . ($data['lastname']  ?? '') . ';' . ($data['firstname'] ?? ''),
                            'FN:'    . ($data['firstname'] ?? '') . ' ' . ($data['lastname']  ?? ''),
                            'TEL:'   . ($data['phone']     ?? ''),
                            'EMAIL:' . ($data['email']     ?? ''),
                            'ORG:'   . ($data['org']       ?? ''),
                            'URL:'   . ($data['website']   ?? ''),
                            'END:VCARD',
                        ]),
            default => $data['url'] ?? '',
        };
    }

    // ── Générer et sauvegarder en PNG ────────────────────────────────
    public function generate(QrCode $qrCode): string
    {
        $design = $qrCode->getDesign();

        [$fgR, $fgG, $fgB] = $this->hexToRgb($design?->getCouleurPoints() ?? '#000000');
        [$bgR, $bgG, $bgB] = $this->hexToRgb($design?->getCouleurFond()   ?? '#ffffff');

        // ── API v6 : new EndroidQrCode() + setters (pas de ::create()) ──
         $qr = new EndroidQrCode(
            data: $qrCode->getContenu(),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $design?->getTaille() ?? 300,
            margin: $design?->getMarge() ?? 10,
            foregroundColor: new Color($fgR, $fgG, $fgB),
            backgroundColor: new Color($bgR, $bgG, $bgB),
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );

        // Logo optionnel
        $logo = null;
        if ($design?->getLogoPath()) {
            $logoFullPath = $this->uploadDir . '/../logos/' . $design->getLogoPath();
            if (file_exists($logoFullPath)) {
                // API v6 : new Logo($path, $resizeToWidth, $resizeToHeight, $punchoutBackground)
                $logo = new Logo($logoFullPath, 60, null, true);
            }
        }

        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }

        $writer   = new PngWriter();
        $result   = $writer->write($qr, $logo);
        $filename = 'qr_' . uniqid() . '.png';
        $filepath = $this->uploadDir . '/' . $filename;
        $result->saveToFile($filepath);

        return 'uploads/qrcodes/' . $filename;
    }

    // ── Générer en SVG ───────────────────────────────────────────────
    public function generateSvg(QrCode $qrCode): string
    {
        $design = $qrCode->getDesign();

        [$fgR, $fgG, $fgB] = $this->hexToRgb($design?->getCouleurPoints() ?? '#000000');
        [$bgR, $bgG, $bgB] = $this->hexToRgb($design?->getCouleurFond()   ?? '#ffffff');

        $qr = new EndroidQrCode(
            data: $qrCode->getContenu(),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $design?->getTaille() ?? 300,
            margin: $design?->getMarge() ?? 10,
            foregroundColor: new Color($fgR, $fgG, $fgB),
            backgroundColor: new Color($bgR, $bgG, $bgB),
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );

        $writer = new SvgWriter();
        $result = $writer->write($qr);

        return $result->getString();
    }

    // ── Helper : hex → [R, G, B] ─────────────────────────────────────
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
