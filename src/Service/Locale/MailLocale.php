<?php

namespace App\Service\Locale;

use App\Entity\User;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * Rédige un e-mail dans la langue de son destinataire plutôt que dans celle de la personne qui déclenche l'envoi
 * (un administrateur qui marque une commande « prête » écrit au client, pas à lui-même).
 */
final class MailLocale
{
    public function __construct(private readonly LocaleSwitcher $switcher)
    {
    }

    /**
     * Exécute le rendu et l'envoi avec la langue du destinataire (sans changement si on ne connaît pas son compte).
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function runFor(?User $recipient, callable $callback): mixed
    {
        if (null === $recipient || !Locales::isSupported($recipient->getLocale())) {
            return $callback();
        }

        return $this->switcher->runWithLocale($recipient->getLocale(), $callback);
    }
}
