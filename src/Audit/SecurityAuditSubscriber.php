<?php

namespace App\Audit;

use App\Entity\AuditLog;
use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/** Journalise les connexions, les échecs de connexion (avec l'identifiant tenté) et les déconnexions. */
final class SecurityAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly AuditRecorder $recorder)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onSuccess',
            LoginFailureEvent::class => 'onFailure',
            LogoutEvent::class       => 'onLogout',
        ];
    }

    public function onSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }
        $this->recorder->event(
            AuditLog::TYPE_SECURITE,
            'connexion',
            'Sécurité',
            sprintf('%s (%s)', $user->getNomComplet(), $event->getFirewallName() === 'admin' ? 'administration' : 'espace licenciés'),
            null,
            ['pare_feu' => $event->getFirewallName()],
            User::class,
            $user->getId(),
            (string) $user->getEmail(),
            null,
            $user,
        );
    }

    public function onFailure(LoginFailureEvent $event): void
    {
        $identifier = (string) ($event->getPassport()?->getBadge(\Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge::class)?->getUserIdentifier() ?? '');
        $this->recorder->event(
            AuditLog::TYPE_SECURITE,
            'echec_connexion',
            'Sécurité',
            sprintf('Identifiant « %s »', $identifier ?: 'inconnu'),
            null,
            ['pare_feu' => $event->getFirewallName(), 'motif' => $event->getException()->getMessageKey()],
            null,
            null,
            null,
            null,
            null,
            $identifier ?: null,
        );
    }

    public function onLogout(LogoutEvent $event): void
    {
        $user = $event->getToken()?->getUser();
        if (!$user instanceof User) {
            return;
        }
        $this->recorder->event(AuditLog::TYPE_SECURITE, 'deconnexion', 'Sécurité', $user->getNomComplet(), null, null, User::class, $user->getId(), (string) $user->getEmail(), null, $user);
    }
}
