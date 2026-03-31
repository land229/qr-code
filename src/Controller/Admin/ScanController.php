<?php

namespace App\Controller\Admin;

use App\Entity\QrCode;
use App\Repository\ScanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ScanController extends AbstractController
{
    public function __construct(
        private ScanRepository         $scanRepo,
        private EntityManagerInterface $em,
    ) {}

    // ── Scan public : /qr/{id} ────────────────────────────────────────
    #[Route('/qr/{id}', name: 'qr_scan', methods: ['GET'])]
    public function scan(QrCode $qrCode, Request $request): Response
    {
        $now = new \DateTime();

        // QR inactif
        if (!$qrCode->isEstActif()) {
            return $this->render('scan/inactive.html.twig', ['qr' => $qrCode]);
        }

        // Pas encore actif (dateDebut dans le futur) — EM-06
        if ($qrCode->getDateDebut() && $now < $qrCode->getDateDebut()) {
            return $this->render('scan/inactive.html.twig', ['qr' => $qrCode]);
        }

        // Expiré (dateFin dépassée) — EM-06 → désactivation automatique
        if ($qrCode->getDateFin() && $now > $qrCode->getDateFin()) {
            $qrCode->setEstActif(false);
            $this->em->flush();
            return $this->render('scan/inactive.html.twig', ['qr' => $qrCode]);
        }

        // Quota dépassé — EM-05 → désactivation automatique
        if ($qrCode->getQuotaMaxScans() !== null
            && $qrCode->getCompteurScans() >= $qrCode->getQuotaMaxScans()) {
            $qrCode->setEstActif(false);
            $this->em->flush();
            return $this->render('scan/quota.html.twig', ['qr' => $qrCode]);
        }

        // Enregistrer le scan
        $ip = $request->getClientIp() ?? '0.0.0.0';
        $ua = $request->headers->get('User-Agent') ?? '';
        $this->scanRepo->record($qrCode, $ip, $ua);

        // Redirection ou affichage selon type
        $contenu = $qrCode->getContenu() ?? '';
        if ($qrCode->getType() === 'url' && !empty($contenu)) {
            return $this->redirect($contenu);
        }

        return $this->render('scan/show.html.twig', ['qr' => $qrCode]);
    }

    // ── Export CSV des scans (EU-08) ──────────────────────────────────
    #[Route('/admin/analytics/qr/{id}/export-csv', name: 'admin_analytics_export_csv', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function exportCsv(QrCode $qrCode, Request $request): StreamedResponse
    {
        $this->denyAccessUnlessGranted('edit', $qrCode);

        // Filtre période optionnel (EU-15)
        $from = $request->query->get('from')
            ? new \DateTime($request->query->get('from'))
            : new \DateTime('-30 days');
        $to = $request->query->get('to')
            ? new \DateTime($request->query->get('to'))
            : new \DateTime();

        $scans = $this->scanRepo->findByQrCodeAndPeriod($qrCode, $from, $to);

        $response = new StreamedResponse(function () use ($scans) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 pour Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['Date & Heure', 'IP', 'Pays', 'Ville', 'Appareil', 'Navigateur'], ';');

            foreach ($scans as $scan) {
                fputcsv($handle, [
                    $scan->getDateHeure()?->format('d/m/Y H:i:s') ?? '',
                    $scan->getIpAdresse()  ?? '',
                    $scan->getPays()       ?? '',
                    $scan->getVille()      ?? '',
                    $scan->getAppareil()   ?? '',
                    $scan->getNavigateur() ?? '',
                ], ';');
            }
            fclose($handle);
        });

        $slug     = preg_replace('/[^a-z0-9]/i', '_', $qrCode->getTitre());
        $filename = "scans_{$slug}_" . date('Ymd') . '.csv';
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}\"");

        return $response;
    }
}