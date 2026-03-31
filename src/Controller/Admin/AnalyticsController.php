<?php

namespace App\Controller\Admin;

use App\Entity\QrCode;
use App\Repository\QrCodeRepository;
use App\Repository\ScanRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin', name: 'admin_analytics_')]
#[IsGranted('ROLE_USER')]
class AnalyticsController extends AbstractController
{
    public function __construct(
        private ScanRepository  $scanRepo,
        private QrCodeRepository $qrRepo,
        private Security        $security,
    ) {}

    // ── Analytics global (tous les QR codes de l'user) ───────────────
    #[Route('/analytics', name: 'global', methods: ['GET'])]
    public function global(): Response
    {
        $user = $this->security->getUser();

        $globalStats  = $this->scanRepo->getGlobalStatsForUser($user);
        $scansPerDay  = $this->scanRepo->getGlobalScansPerDay($user, 30);
        $topQrCodes   = $this->scanRepo->getTopQrCodes($user, 5);
        $qrStats      = $this->qrRepo->getStatsForUser($user);

        return $this->render('admin/analytics/global.html.twig', [
            'globalStats'  => $globalStats,
            'scansPerDay'  => $scansPerDay,
            'topQrCodes'   => $topQrCodes,
            'qrStats'      => $qrStats,
            'labels'       => array_keys($scansPerDay),
            'values'       => array_values($scansPerDay),
        ]);
    }

    // ── Analytics d'un QR Code spécifique ────────────────────────────
    #[Route('/analytics/qr/{id}', name: 'qr', methods: ['GET'])]
    public function qrAnalytics(QrCode $qrCode): Response
    {
        $this->denyAccessUnlessGranted('edit', $qrCode);

        $scansPerDay   = $this->scanRepo->getScansPerDay($qrCode, 30);
        $deviceStats   = $this->scanRepo->getDeviceStats($qrCode);
        $browserStats  = $this->scanRepo->getBrowserStats($qrCode);
        $recentScans   = $this->scanRepo->findByQrCode($qrCode, 50);

        return $this->render('admin/analytics/qr.html.twig', [
            'qr'           => $qrCode,
            'scansPerDay'  => $scansPerDay,
            'deviceStats'  => $deviceStats,
            'browserStats' => $browserStats,
            'recentScans'  => $recentScans,
            'labels'       => array_keys($scansPerDay),
            'values'       => array_values($scansPerDay),
        ]);
    }

    // ── API JSON : données fraîches (refresh AJAX) ────────────────────
    #[Route('/analytics/qr/{id}/data', name: 'qr_data', methods: ['GET'])]
    public function qrData(QrCode $qrCode): JsonResponse
    {
        $this->denyAccessUnlessGranted('edit', $qrCode);

        return $this->json([
            'scansPerDay'  => $this->scanRepo->getScansPerDay($qrCode, 30),
            'deviceStats'  => $this->scanRepo->getDeviceStats($qrCode),
            'browserStats' => $this->scanRepo->getBrowserStats($qrCode),
            'total'        => $qrCode->getCompteurScans(),
        ]);
    }
}