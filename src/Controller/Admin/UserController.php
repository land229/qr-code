<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\QrCodeRepository;
use App\Repository\ScanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users', name: 'admin_user_')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface      $em,
        private UserPasswordHasherInterface $hasher,
        private QrCodeRepository           $qrRepo,
        private ScanRepository             $scanRepo,
    ) {}

    // ── Liste de tous les utilisateurs ────────────────────────────────
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $users = $this->em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->leftJoin('u.qRCodes', 'q')
            ->addSelect('COUNT(q.id) as qrCount')
            ->groupBy('u.id')
            ->orderBy('u.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/user/index.html.twig', [
            'users' => $users,
        ]);
    }

    // ── Créer un utilisateur ──────────────────────────────────────────
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            $user = new User();
            $user->setEmail($data['email'] ?? '');
            $user->setFirstname($data['nom'] ?? '');
            //$user->setStatut('actif');
            $user->setRoles(isset($data['isAdmin']) ? ['ROLE_ADMIN'] : ['ROLE_USER']);
            $user->setPassword(
                $this->hasher->hashPassword($user, $data['password'] ?? 'changeme123')
            );

            $this->em->persist($user);
            $this->em->flush();

            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => true, 'message' => 'Utilisateur créé.']);
            }

            $this->addFlash('success', 'Utilisateur créé avec succès.');
            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/new.html.twig');
    }

    // ── Voir un utilisateur ───────────────────────────────────────────
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(User $user): Response
    {
        $qrCodes   = $this->qrRepo->findByUser($user);
        $scanStats = $this->scanRepo->getGlobalStatsForUser($user);
        $qrStats   = $this->qrRepo->getStatsForUser($user);

        return $this->render('admin/user/show.html.twig', [
            'user'      => $user,
            'qrCodes'   => $qrCodes,
            'scanStats' => $scanStats,
            'qrStats'   => $qrStats,
        ]);
    }

    // ── Toggle actif/suspendu ─────────────────────────────────────────
    #[Route('/{id}/toggle-status', name: 'toggle_status', methods: ['POST'])]
    public function toggleStatus(User $user): JsonResponse
    {
        // Empêcher l'admin de se désactiver lui-même
        if ($user === $this->getUser()) {
            return $this->json(['success' => false, 'message' => 'Impossible de modifier votre propre compte.'], 403);
        }

        /*$newStatut = $user->getStatut() === 'actif' ? 'suspendu' : 'actif';
        $user->setStatut($newStatut);*/
        $this->em->flush();

        return $this->json([
            'success' => true,
           /* 'statut'  => $newStatut,
            'message' => 'Compte ' . ($newStatut === 'actif' ? 'réactivé' : 'suspendu') . '.',*/
        ]);
    }

    // ── Supprimer un utilisateur ──────────────────────────────────────
    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, User $user): JsonResponse
    {
        if ($user === $this->getUser()) {
            return $this->json(['success' => false, 'message' => 'Impossible de supprimer votre propre compte.'], 403);
        }

        if (!$this->isCsrfTokenValid('delete_user_' . $user->getId(), $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Token CSRF invalide.'], 403);
        }

        $this->em->remove($user);
        $this->em->flush();

        return $this->json(['success' => true, 'message' => 'Utilisateur supprimé.']);
    }
}