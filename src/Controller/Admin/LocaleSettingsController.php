<?php

namespace App\Controller\Admin;

use App\Entity\LocaleSetting;
use App\Repository\LocaleSettingRepository;
use App\Service\Locale\LocaleRegistry;
use App\Service\Locale\Locales;
use App\Translation\ContentTranslator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Langues proposées sur le site : chaque langue du catalogue (App\Service\Locale\Locales) peut être activée ou
 * désactivée, sauf le français qui reste la langue par défaut. Seules les langues actives apparaissent dans le
 * sélecteur de la barre de navigation et sont acceptées par LocaleController.
 */
#[Route('/admin/parametres-langues')]
class LocaleSettingsController extends AbstractController
{
    public function __construct(
        private readonly LocaleSettingRepository $repository,
        private readonly LocaleRegistry $registry,
        private readonly EntityManagerInterface $em,
        private readonly ContentTranslator $translator,
    ) {
    }

    #[Route('', name: 'admin_langues_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('langues_settings', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $wanted = array_map('strval', (array) $request->request->all('enabled'));
            $rows   = [];
            foreach ($this->repository->findAll() as $row) {
                $rows[$row->getCode()] = $row;
            }
            $newlyEnabled = [];
            foreach (array_keys(Locales::AVAILABLE) as $code) {
                $enabled = Locales::DEFAULT === $code || in_array($code, $wanted, true);
                if ($enabled && Locales::DEFAULT !== $code && !$this->registry->isEnabled($code)) {
                    $newlyEnabled[] = $code;
                }
                if (isset($rows[$code])) {
                    $rows[$code]->setEnabled($enabled);
                } else {
                    $this->em->persist(new LocaleSetting($code, $enabled));
                }
            }
            $this->em->flush();
            $this->addFlash('success', 'Langues du site enregistrées.');

            // Une langue qu'on vient d'activer : le contenu déjà saisi (offres, pages, boutique…) est traduit une fois pour toutes.
            if ([] !== $newlyEnabled && $this->translator->isAvailable()) {
                @set_time_limit(300);
                $count = $this->translator->translateAllContent($newlyEnabled);
                $this->addFlash('success', sprintf('%d texte(s) du site traduit(s) automatiquement et enregistré(s). Pour traduire aussi les textes fixes du site : php bin/console app:translations:sync', $count));
            }

            return $this->redirectToRoute('admin_langues_settings');
        }

        $languages = [];
        foreach (Locales::AVAILABLE as $code => $lang) {
            $languages[$code] = $lang + ['enabled' => $this->registry->isEnabled($code), 'locked' => Locales::DEFAULT === $code];
        }

        return $this->render('admin/langues/settings.html.twig', ['languages' => $languages]);
    }
}
