<?php

namespace App\Controller\Admin;

use App\Form\HelloAssoSettingsType;
use App\Mail\SecretBox;
use App\Repository\HelloAssoSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Identifiants d'accès à l'API HelloAsso, utilisés pour le paiement en ligne de la boutique
 * (création d'une intention de paiement au moment de la commande). Voir CommandeService.
 */
#[Route('/admin/parametres-helloasso')]
class HelloAssoSettingsController extends AbstractController
{
    public function __construct(
        private readonly HelloAssoSettingsRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly SecretBox $secretBox,
    ) {
    }

    #[Route('', name: 'admin_helloasso_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request): Response
    {
        $settings = $this->repository->getSingleton();
        $form     = $this->createForm(HelloAssoSettingsType::class, $settings, ['a_un_secret' => $settings->hasClientSecret()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($settings->isActif() && (null === $settings->getClientId() || null === $settings->getOrganisationSlug())) {
                $form->get('organisationSlug')->addError(new \Symfony\Component\Form\FormError('Indiquez le client ID et le slug de l\'organisation pour activer HelloAsso.'));
            } else {
                $secret = (string) $form->get('clientSecret')->getData();
                if ($form->get('retirerClientSecret')->getData()) {
                    $settings->setClientSecretChiffre(null);
                } elseif ('' !== $secret) {
                    $settings->setClientSecretChiffre($this->secretBox->encrypt($secret));
                }
                $settings->touch();
                $this->em->persist($settings);
                $this->em->flush();
                $this->addFlash('success', 'Paramètres HelloAsso enregistrés.');

                return $this->redirectToRoute('admin_helloasso_settings');
            }
        }

        return $this->render('admin/helloasso/settings.html.twig', [
            'form'     => $form,
            'settings' => $settings,
        ]);
    }
}
