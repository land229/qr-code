<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\QrCodeRepository;
use App\Repository\ScanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DashboardController extends AbstractController
{
    public function __construct(
        private QrCodeRepository            $qrRepo,
        private ScanRepository              $scanRepo,
        private EntityManagerInterface      $em,
        private Security                    $security,
        private UserPasswordHasherInterface $hasher,
    ) {}

    // ── /admin → dashboard global ─────────────────────────────────────
    #[Route('/admin', name: 'app_admin')]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        return $this->redirectToRoute('admin_dashboard_global');
    }

    // ── Dashboard global admin (EU-12) ────────────────────────────────
    #[Route('/admin/dashboard/global', name: 'admin_dashboard_global')]
    #[IsGranted('ROLE_ADMIN')]
    public function globalDashboard(): Response
    {
        $userRepo = $this->em->getRepository(User::class);
        $qrRepo   = $this->em->getRepository(\App\Entity\QrCode::class);

        // Compteurs
        $totalUsers  = (int)$userRepo->count([]);
        $activeUsers = (int)$userRepo->count(['statut' => 'actif']);
        $totalQr     = (int)$qrRepo->count([]);
        $activeQr    = (int)$qrRepo->count(['estActif' => true]);

        // Scans total
        $totalScans = (int)$this->em->createQuery(
            'SELECT COUNT(s.id) FROM App\Entity\Scan s'
        )->getSingleScalarResult();

        // Scans aujourd'hui (comparaison par plage datetime, pas DATE())
        $todayStart = new \DateTime('today');
        $todayEnd   = new \DateTime('tomorrow');
        $todayScans = (int)$this->em->createQuery(
            'SELECT COUNT(s.id) FROM App\Entity\Scan s
             WHERE s.dateHeure >= :start AND s.dateHeure < :end'
        )->setParameter('start', $todayStart)
         ->setParameter('end',   $todayEnd)
         ->getSingleScalarResult();

        // Courbe 30j — DATE_FORMAT compatible MySQL/MariaDB
        $rows = $this->em->createQuery(
            "SELECT FUNCTION('DATE_FORMAT', s.dateHeure, '%Y-%m-%d') as jour,
                    COUNT(s.id) as total
             FROM App\Entity\Scan s
             WHERE s.dateHeure >= :from
             GROUP BY jour
             ORDER BY jour ASC"
        )->setParameter('from', new \DateTime('-30 days'))
         ->getScalarResult();

        $chartData = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = (new \DateTime("-{$i} days"))->format('Y-m-d');
            $chartData[$date] = 0;
        }
        foreach ($rows as $row) {
            $chartData[$row['jour']] = (int)$row['total'];
        }

        // Derniers utilisateurs inscrits
        $lastUsers = $userRepo->createQueryBuilder('u')
            ->orderBy('u.id', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        // ⚠️ Rend admin/dashboard/index.html.twig (comme dans ton projet)
        return $this->render('admin/dashboard/index.html.twig', [
            'totalUsers'  => $totalUsers,
            'activeUsers' => $activeUsers,
            'totalQr'     => $totalQr,
            'activeQr'    => $activeQr,
            'totalScans'  => $totalScans,
            'todayScans'  => $todayScans,
            'labels'      => array_keys($chartData),
            'values'      => array_values($chartData),
            'lastUsers'   => $lastUsers,   // ← variable manquante corrigée
        ]);
    }

    // ── Profil utilisateur (EU-02) ────────────────────────────────────
    #[Route('/admin/profile', name: 'app_profile')]
    #[IsGranted('ROLE_USER')]
    public function profile(Request $request): Response
    {
        /** @var User $user */
        $user = $this->security->getUser();

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            // Mise à jour nom
            if (!empty($data['nom'])) {
                $user->setFirstname($data['nom']);
            }

            // Mise à jour email avec vérification doublon
            if (!empty($data['email']) && $data['email'] !== $user->getEmail()) {
                $existing = $this->em->getRepository(User::class)
                    ->findOneBy(['email' => $data['email']]);
                if ($existing && $existing !== $user) {
                    $this->addFlash('error', 'Cette adresse email est déjà utilisée.');
                } else {
                    $user->setEmail($data['email']);
                }
            }

            // Changement de mot de passe
            if (!empty($data['new_password']) && !empty($data['current_password'])) {
                if ($this->hasher->isPasswordValid($user, $data['current_password'])) {
                    if ($data['new_password'] === ($data['confirm_password'] ?? '')) {
                        $user->setPassword(
                            $this->hasher->hashPassword($user, $data['new_password'])
                        );
                        $this->addFlash('success', 'Mot de passe mis à jour.');
                    } else {
                        $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                    }
                } else {
                    $this->addFlash('error', 'Mot de passe actuel incorrect.');
                }
            }

            $this->em->flush();
            $this->addFlash('success', 'Profil mis à jour.');
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('admin/profile/index.html.twig', [
            'user'      => $user,
            'qrStats'   => $this->qrRepo->getStatsForUser($user),
            'scanStats' => $this->scanRepo->getGlobalStatsForUser($user),
        ]);
    }
}