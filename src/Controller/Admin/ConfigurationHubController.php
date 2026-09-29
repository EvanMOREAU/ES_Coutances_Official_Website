<?php

namespace App\Controller\Admin;

use App\Repository\CategorieRepository;
use App\Repository\HelloAssoSettingsRepository;
use App\Repository\MailSettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page « Configuration » de l'administration : une carte par réglage technique du site
 * (e-mail, HelloAsso, catégories d'encadrement…).
 */
#[Route('/admin/configuration', name: 'admin_configuration_hub', methods: ['GET'])]
class ConfigurationHubController extends AbstractController
{
    public function __invoke(MailSettingsRepository $mailSettings, HelloAssoSettingsRepository $helloAssoSettings, CategorieRepository $categories): Response
    {
        $mail      = $mailSettings->getSingleton();
        $helloAsso = $helloAssoSettings->getSingleton();
        $nbCategories = $categories->count([]);

        return $this->render('admin/configuration_hub.html.twig', [
            'open'     => '',
            'openView' => 'list',
            'sections' => [
                [
                    'key' => 'mail', 'group' => 'Technique', 'icon' => 'fa-envelope-circle-check', 'title' => 'Paramètres e-mail',
                    'url' => $this->generateUrl('admin_mail_settings'),
                    'new' => null, 'value' => null, 'label' => null, 'sub' => null,
                    'visual' => [
                        'type'     => 'status',
                        'on'       => $mail->isUtilisable(),
                        'onLabel'  => 'Serveur personnalisé actif',
                        'offLabel' => 'Serveur par défaut',
                        'detail'   => 'Le serveur SMTP qui envoie les e-mails du site (mots de passe, confirmations de commande…).',
                    ],
                ],
                [
                    'key' => 'helloasso', 'group' => 'Technique', 'icon' => 'fa-credit-card', 'title' => 'Paramètres HelloAsso',
                    'url' => $this->generateUrl('admin_helloasso_settings'),
                    'new' => null, 'value' => null, 'label' => null, 'sub' => null,
                    'visual' => [
                        'type'     => 'status',
                        'on'       => $helloAsso->isUtilisable(),
                        'onLabel'  => 'HelloAsso actif' . ($helloAsso->isUtilisable() ? ' (' . ($helloAsso->isSandbox() ? 'sandbox' : 'production') . ')' : ''),
                        'offLabel' => 'Non configuré',
                        'detail'   => 'Identifiants d\'API pour le paiement en ligne par carte de la boutique.',
                    ],
                ],
                [
                    'key' => 'categorie', 'group' => 'Technique', 'icon' => 'fa-tags', 'title' => 'Catégories encadrement',
                    'url' => $this->generateUrl('admin_categorie_index'),
                    'new' => $this->generateUrl('admin_categorie_new'),
                    'value' => null, 'label' => null, 'sub' => null,
                    'visual' => [
                        'type'     => 'status',
                        'on'       => $nbCategories > 0,
                        'onLabel'  => $nbCategories . ' catégorie' . ($nbCategories > 1 ? 's' : ''),
                        'offLabel' => 'Aucune catégorie',
                        'detail'   => 'Catégories utilisées pour classer les membres de l\'encadrement (éducateurs, dirigeants…).',
                    ],
                ],
            ],
        ]);
    }
}
