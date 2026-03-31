<?php

namespace App\Controller\Admin;

use App\Entity\Design;
use App\Entity\QrCode;
use App\Form\QrCodeType;
use App\Repository\QrCodeRepository;
use App\Service\QrCodeGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/qrcodes', name: 'admin_qr_')]
#[IsGranted('ROLE_USER')]
class QrCodeController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private QrCodeRepository       $repo,
        private QrCodeGenerator        $generator,
        private Security               $security,
    ) {}

    // ── Liste ─────────────────────────────────────────────────────────
    #[Route('', name: 'list', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->security->getUser();
        return $this->render('admin/qr_code/index.html.twig', [
            'qr_codes' => $this->repo->findByUser($user),
            'stats'    => $this->repo->getStatsForUser($user),
        ]);
    }

    // ── Générer ───────────────────────────────────────────────────────
    #[Route('/generate', name: 'generate', methods: ['GET', 'POST'])]
    public function generate(Request $request): JsonResponse|Response
    {
        $qrCode = new QrCode();
        $design = new Design();
        $qrCode->setDesign($design);
        $qrCode->setUser($this->security->getUser());
        $qrCode->setEstActif(true);
        $qrCode->setCompteurScans(0);
        $qrCode->setDateDebut(new \DateTime());

        $form = $this->createForm(QrCodeType::class, $qrCode);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $formData = $request->request->all();

            // ── 1. Type (champ hidden Symfony dans qr_code[]) ──────────
            $type = $formData['qr_code']['type'] ?? 'url';
            if (empty($type)) {
                $type = 'url';
            }

            // ── 2. Payload : les champs _xxx arrivent à la RACINE ──────
            //    car name="_url", "_text"... ne sont PAS prefixés qr_code[]
            $payload = match($type) {
                'url'   => ['url'     => $formData['_url']      ?? ''],
                'text'  => ['text'    => $formData['_text']     ?? ''],
                'email' => [
                    'to'      => $formData['_to']      ?? '',
                    'subject' => $formData['_subject'] ?? '',
                    'body'    => $formData['_body']    ?? '',
                ],
                'tel'   => ['tel'     => $formData['_tel']      ?? ''],
                'sms'   => [
                    'tel'     => $formData['_sms_tel'] ?? '',
                    'message' => $formData['_sms_msg'] ?? '',
                ],
                'geo'   => [
                    'lat'     => $formData['_lat']     ?? '0',
                    'lng'     => $formData['_lng']     ?? '0',
                ],
                'wifi'  => [
                    'ssid'     => $formData['_ssid']     ?? '',
                    'password' => $formData['_password'] ?? '',
                    'security' => $formData['_security'] ?? 'WPA',
                ],
                'vcard' => [
                    'firstname' => $formData['_firstname'] ?? '',
                    'lastname'  => $formData['_lastname']  ?? '',
                    'phone'     => $formData['_phone']     ?? '',
                    'email'     => $formData['_email']     ?? '',
                    'org'       => $formData['_org']       ?? '',
                    'website'   => $formData['_website']   ?? '',
                ],
                default => ['url' => $formData['_url'] ?? ''],
            };

            // ── 3. Construire le contenu encodé ─────────────────────────
            $contenu = $this->generator->buildContent($type, $payload);

            if (empty($contenu)) {
                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'success' => false,
                        'message' => 'Le contenu est vide. Veuillez remplir les champs.',
                    ], 422);
                }
                $this->addFlash('error', 'Le contenu est vide.');
                return $this->redirectToRoute('admin_qr_generate');
            }

            $qrCode->setContenu($contenu);
            $qrCode->setType($type);

            // ── 4. Design (champs hidden Symfony dans qr_code[]) ────────
            $design->setCouleurPoints($formData['qr_code']['couleurPoints'] ?? '#000000');
            $design->setCouleurFond($formData['qr_code']['couleurFond']     ?? '#ffffff');
            $design->setTaille((int)($formData['qr_code']['taille']         ?? 300));
            $design->setMarge(10);

            // ── 5. Upload logo optionnel ─────────────────────────────────
            $logoFile = $form->get('logoFile')->getData();
            if ($logoFile) {
                $logoDir = $this->getParameter('kernel.project_dir') . '/public/uploads/logos';
                if (!is_dir($logoDir)) {
                    mkdir($logoDir, 0755, true);
                }
                $logoName = uniqid('logo_') . '.' . $logoFile->guessExtension();
                $logoFile->move($logoDir, $logoName);
                $design->setLogoPath($logoName);
            }

            // ── 6. Persister ────────────────────────────────────────────
            $this->em->persist($design);
            $this->em->persist($qrCode);
            $this->em->flush();

            // ── 7. Générer l'image PNG ───────────────────────────────────
            $imagePath = $this->generator->generate($qrCode);
            $qrCode->setImagePath($imagePath);
            $this->em->flush();

            // ── 8. Réponse ───────────────────────────────────────────────
            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'success'      => true,
                    'message'      => 'QR Code généré avec succès !',
                    'image_url'    => '/' . $imagePath,
                    'download_png' => $this->generateUrl('admin_qr_download', [
                        'id'     => $qrCode->getId(),
                        'format' => 'png',
                    ]),
                    'download_svg' => $this->generateUrl('admin_qr_download', [
                        'id'     => $qrCode->getId(),
                        'format' => 'svg',
                    ]),
                ]);
            }

            $this->addFlash('success', 'QR Code généré avec succès !');
            return $this->redirectToRoute('admin_qr_list');
        }

        // Formulaire soumis invalide en AJAX
        if ($form->isSubmitted() && !$form->isValid() && $request->isXmlHttpRequest()) {
            return $this->json([
                'success' => false,
                'message' => 'Formulaire invalide.',
                'errors'  => $this->getFormErrors($form),
            ], 422);
        }

        return $this->render('admin/qr_code/generate.html.twig', [
            'form'  => $form->createView(),
            'stats' => $this->repo->getStatsForUser($this->security->getUser()),
        ]);
    }

    // ── Modifier ─────────────────────────────────────────────────────
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, QrCode $qrCode): JsonResponse|Response
    {
        $this->denyAccessUnlessGranted('edit', $qrCode);

        $form = $this->createForm(QrCodeType::class, $qrCode);
        $form->handleRequest($request);

        if ($request->isXmlHttpRequest()) {
            if ($form->isSubmitted() && $form->isValid()) {

                $formData = $request->request->all();
                $type     = $formData['qr_code']['type'] ?? $qrCode->getType();
                if (empty($type)) $type = $qrCode->getType();

                $payload = match($type) {
                    'url'   => ['url'     => $formData['_url']      ?? ''],
                    'text'  => ['text'    => $formData['_text']     ?? ''],
                    'email' => ['to' => $formData['_to'] ?? '', 'subject' => $formData['_subject'] ?? '', 'body' => $formData['_body'] ?? ''],
                    'tel'   => ['tel'     => $formData['_tel']      ?? ''],
                    'sms'   => ['tel' => $formData['_sms_tel'] ?? '', 'message' => $formData['_sms_msg'] ?? ''],
                    'geo'   => ['lat' => $formData['_lat'] ?? '0', 'lng' => $formData['_lng'] ?? '0'],
                    'wifi'  => ['ssid' => $formData['_ssid'] ?? '', 'password' => $formData['_password'] ?? '', 'security' => $formData['_security'] ?? 'WPA'],
                    'vcard' => ['firstname' => $formData['_firstname'] ?? '', 'lastname' => $formData['_lastname'] ?? '', 'phone' => $formData['_phone'] ?? '', 'email' => $formData['_email'] ?? '', 'org' => $formData['_org'] ?? '', 'website' => $formData['_website'] ?? ''],
                    default => ['url' => $formData['_url'] ?? ''],
                };

                $contenu = $this->generator->buildContent($type, $payload);
                $qrCode->setContenu($contenu);
                $qrCode->setType($type);

                $design = $qrCode->getDesign() ?? new Design();
                $design->setCouleurPoints($formData['qr_code']['couleurPoints'] ?? '#000000');
                $design->setCouleurFond($formData['qr_code']['couleurFond']     ?? '#ffffff');
                $design->setTaille((int)($formData['qr_code']['taille']         ?? 300));
                $qrCode->setDesign($design);

                $imagePath = $this->generator->generate($qrCode);
                $qrCode->setImagePath($imagePath);
                $this->em->flush();

                return $this->json([
                    'success'   => true,
                    'message'   => 'QR Code mis à jour.',
                    'image_url' => '/' . $imagePath,
                ]);
            }

            return $this->json([
                'html' => $this->renderView('admin/qr_code/_form_modal.html.twig', [
                    'form'  => $form->createView(),
                    'qr'    => $qrCode,
                    'title' => 'Modifier — ' . $qrCode->getTitre(),
                ]),
            ]);
        }

        return $this->render('admin/qr_code/edit.html.twig', [
            'form' => $form->createView(),
            'qr'   => $qrCode,
        ]);
    }

    // ── Toggle actif/inactif ─────────────────────────────────────────
    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(QrCode $qrCode): JsonResponse
    {
        $this->denyAccessUnlessGranted('edit', $qrCode);
        $qrCode->setEstActif(!$qrCode->isEstActif());
        $this->em->flush();

        return $this->json([
            'success'  => true,
            'estActif' => $qrCode->isEstActif(),
            'message'  => 'QR Code ' . ($qrCode->isEstActif() ? 'activé' : 'désactivé') . '.',
        ]);
    }

    // ── Dupliquer ────────────────────────────────────────────────────
    #[Route('/{id}/duplicate', name: 'duplicate', methods: ['POST'])]
    public function duplicate(QrCode $qrCode): JsonResponse
    {
        $this->denyAccessUnlessGranted('edit', $qrCode);

        $clone = new QrCode();
        $clone->setTitre($qrCode->getTitre() . ' (copie)');
        $clone->setType($qrCode->getType());
        $clone->setContenu($qrCode->getContenu());
        $clone->setEstActif(true);
        $clone->setCompteurScans(0);
        $clone->setDateDebut(new \DateTime());
        $clone->setUser($qrCode->getUser());
        $clone->setQuotaMaxScans($qrCode->getQuotaMaxScans());

        $srcDesign = $qrCode->getDesign();
        if ($srcDesign) {
            $newDesign = new Design();
            $newDesign->setCouleurPoints($srcDesign->getCouleurPoints());
            $newDesign->setCouleurFond($srcDesign->getCouleurFond());
            $newDesign->setTaille($srcDesign->getTaille());
            $newDesign->setMarge($srcDesign->getMarge());
            $newDesign->setFormeYeux($srcDesign->getFormeYeux());
            $this->em->persist($newDesign);
            $clone->setDesign($newDesign);
        }

        $this->em->persist($clone);
        $this->em->flush();

        $imagePath = $this->generator->generate($clone);
        $clone->setImagePath($imagePath);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => 'QR Code « ' . $clone->getTitre() . ' » dupliqué.',
        ]);
    }

    // ── Supprimer ────────────────────────────────────────────────────
    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, QrCode $qrCode): JsonResponse
    {
        $this->denyAccessUnlessGranted('edit', $qrCode);

        if (!$this->isCsrfTokenValid('delete_qr_' . $qrCode->getId(), $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Token CSRF invalide.'], 403);
        }

        if ($qrCode->getImagePath()) {
            $imgPath = $this->getParameter('kernel.project_dir') . '/public/' . $qrCode->getImagePath();
            if (file_exists($imgPath)) {
                unlink($imgPath);
            }
        }

        $this->em->remove($qrCode);
        $this->em->flush();

        return $this->json(['success' => true, 'message' => 'QR Code supprimé.']);
    }

    // ── Télécharger ──────────────────────────────────────────────────
    #[Route('/{id}/download/{format}', name: 'download', methods: ['GET'],
            requirements: ['format' => 'png|svg'])]
    public function download(QrCode $qrCode, string $format): Response
    {
        $this->denyAccessUnlessGranted('edit', $qrCode);

        if ($format === 'svg') {
            $svg = $this->generator->generateSvg($qrCode);
            return new Response($svg, 200, [
                'Content-Type'        => 'image/svg+xml',
                'Content-Disposition' => 'attachment; filename="qrcode-' . $qrCode->getId() . '.svg"',
            ]);
        }

        $path = $this->getParameter('kernel.project_dir') . '/public/' . $qrCode->getImagePath();
        if (!file_exists($path)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        return new Response(file_get_contents($path), 200, [
            'Content-Type'        => 'image/png',
            'Content-Disposition' => 'attachment; filename="qrcode-' . $qrCode->getId() . '.png"',
        ]);
    }

    // ── Helper erreurs formulaire ─────────────────────────────────────
    private function getFormErrors(\Symfony\Component\Form\FormInterface $form): array
    {
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }
        return $errors;
    }
}
