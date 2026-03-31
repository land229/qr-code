<?php
// src/Controller/Admin/PlanTarifaireController.php

namespace App\Controller\Admin;

use App\Entity\PlanTarifaire;
use App\Form\PlanTarifaireType;
use App\Repository\PlanTarifaireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/plans', name: 'admin_plan_')]
#[IsGranted('ROLE_ADMIN')]
class PlanTarifaireController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private PlanTarifaireRepository $repo
    ) {}

    // ── Liste ──────────────────────────────────────────────
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/plan_tarifaire/index.html.twig', [
            'plans' => $this->repo->findAll(),
        ]);
    }

    // ── Créer ──────────────────────────────────────────────
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): JsonResponse|Response
    {
        $plan = new PlanTarifaire();
        $form = $this->createForm(PlanTarifaireType::class, $plan);
        $form->handleRequest($request);

        if ($request->isXmlHttpRequest()) {
            if ($form->isSubmitted() && $form->isValid()) {
                $this->em->persist($plan);
                $this->em->flush();
                return $this->json([
                    'success' => true,
                    'message' => 'Plan créé avec succès.',
                    'plan'    => $this->serialize($plan),
                ]);
            }
            if ($form->isSubmitted() && !$form->isValid()) {
                return $this->json([
                    'success' => false,
                    'message' => 'Formulaire invalide.',
                    'errors'  => $this->getFormErrors($form),
                ], 422);
            }
            // Renvoie le HTML du formulaire (ouverture modale)
            return $this->json([
                'html' => $this->renderView('admin/plan_tarifaire/_form_modal.html.twig', [
                    'form'  => $form->createView(),
                    'plan'  => $plan,
                    'title' => 'Nouveau plan tarifaire',
                    'route' => $this->generateUrl('admin_plan_new'),
                ]),
            ]);
        }

        return $this->render('admin/plan_tarifaire/new.html.twig', [
            'form' => $form->createView(),
            'plan' => $plan,
        ]);
    }

    // ── Modifier ───────────────────────────────────────────
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, PlanTarifaire $plan): JsonResponse|Response
    {
        $form = $this->createForm(PlanTarifaireType::class, $plan);
        $form->handleRequest($request);

        if ($request->isXmlHttpRequest()) {
            if ($form->isSubmitted() && $form->isValid()) {
                $this->em->flush();
                return $this->json([
                    'success' => true,
                    'message' => 'Plan mis à jour.',
                    'plan'    => $this->serialize($plan),
                ]);
            }
            if ($form->isSubmitted() && !$form->isValid()) {
                return $this->json([
                    'success' => false,
                    'errors'  => $this->getFormErrors($form),
                ], 422);
            }
            return $this->json([
                'html' => $this->renderView('admin/plan_tarifaire/_form_modal.html.twig', [
                    'form'  => $form->createView(),
                    'plan'  => $plan,
                    'title' => 'Modifier le plan — ' . $plan->getNom(),
                    'route' => $this->generateUrl('admin_plan_edit', ['id' => $plan->getId()]),
                ]),
            ]);
        }

        return $this->render('admin/plan_tarifaire/edit.html.twig', [
            'form' => $form->createView(),
            'plan' => $plan,
        ]);
    }

    // ── Supprimer ──────────────────────────────────────────
    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, PlanTarifaire $plan): JsonResponse
    {
        if (!$this->isCsrfTokenValid('delete_plan_' . $plan->getId(), $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Token CSRF invalide.'], 403);
        }

        // Sécurité : empêcher la suppression si des utilisateurs y sont liés
        if ($plan->getUsers()->count() > 0) {
            return $this->json([
                'success' => false,
                'message' => 'Impossible de supprimer : ' . $plan->getUsers()->count() . ' utilisateur(s) sont sur ce plan.',
            ], 409);
        }

        $this->em->remove($plan);
        $this->em->flush();

        return $this->json(['success' => true, 'message' => 'Plan supprimé avec succès.']);
    }

    // ── Toggle accès avancé ────────────────────────────────
    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(PlanTarifaire $plan): JsonResponse
    {
        $plan->setAccesAvance(!$plan->isAccesAvance());
        $this->em->flush();

        return $this->json([
            'success'      => true,
            'accesAvance'  => $plan->isAccesAvance(),
            'message'      => 'Accès avancé ' . ($plan->isAccesAvance() ? 'activé' : 'désactivé') . '.',
        ]);
    }

    // ── Helpers privés ─────────────────────────────────────
    private function serialize(PlanTarifaire $p): array
    {
        return [
            'id'          => $p->getId(),
            'nom'         => $p->getNom(),
            'prix'        => $p->getPrix(),
            'limiteQR'    => $p->getLimiteQR(),
            'accesAvance' => $p->isAccesAvance(),
            'nbUsers'     => $p->getUsers()->count(),
        ];
    }

    private function getFormErrors(\Symfony\Component\Form\FormInterface $form): array
    {
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }
        return $errors;
    }
}