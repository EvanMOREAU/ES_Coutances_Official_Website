<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Répond à « cet utilisateur a-t-il cette autorisation ? ».
 *
 * - Développeur : toutes les autorisations, toujours.
 * - Administrateur sans restriction (cas des comptes existants) : toutes, y compris les futures.
 * - Gestionnaire boutique : en plus de son profil, toute la rubrique Boutique, ses réglages et le support client.
 * - Sinon : celles de son profil, plus celles qu'on lui ajoute, moins celles qu'on lui retire.
 */
class PermissionChecker
{
    /** @var array<int, array<string, true>> autorisations effectives par utilisateur (cache de la requête) */
    private array $cache = [];

    public function __construct(private readonly Security $security)
    {
    }

    /** L'utilisateur connecté a-t-il l'autorisation (ou l'une d'elles si « a,b,c ») ? */
    public function can(string $codes, ?User $user = null): bool
    {
        $user ??= $this->security->getUser() instanceof User ? $this->security->getUser() : null;
        if (!$user) {
            return false;
        }

        $granted = $this->effective($user);
        foreach (explode(',', $codes) as $code) {
            if (isset($granted['*']) || isset($granted[trim($code)])) {
                return true;
            }
        }

        return false;
    }

    /** Accès total (développeur ou administrateur non restreint) ? */
    public function hasFullAccess(User $user): bool
    {
        return isset($this->effective($user)['*']);
    }

    /** @return list<string> codes accordés (tous les codes du catalogue pour un accès total) */
    public function grantedCodes(User $user): array
    {
        $effective = $this->effective($user);

        return isset($effective['*']) ? PermissionCatalog::all() : array_keys($effective);
    }

    /** @return list<string> */
    private function boutiqueCodes(): array
    {
        $codes = ['boutique_maintenance.voir', 'boutique_maintenance.modifier', 'messagerie.utiliser', 'messagerie.support'];
        foreach (PermissionCatalog::groups()['Boutique'] as $resource => $definition) {
            foreach (array_keys($definition['actions']) as $action) {
                $codes[] = $resource.'.'.$action;
            }
        }

        return $codes;
    }

    /** @return array<string, true> */
    private function effective(User $user): array
    {
        $key = (int) $user->getId();
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $roles = $user->getRoles();
        if (in_array('ROLE_DEV', $roles, true) || (in_array('ROLE_ADMIN', $roles, true) && !$user->isAccesRestreint())) {
            return $this->cache[$key] = ['*' => true];
        }

        $codes = $user->getProfil()?->getPermissions() ?? [];
        if (in_array('ROLE_BOUTIQUE', $roles, true)) {
            // Le gestionnaire boutique a toujours la rubrique Boutique, ses réglages et le support client.
            $codes = array_merge($codes, $this->boutiqueCodes());
        }
        $codes = array_merge($codes, $user->getPermissionsAjoutees());
        $codes = array_diff($codes, $user->getPermissionsRetirees());

        return $this->cache[$key] = array_fill_keys(PermissionCatalog::sanitize($codes), true);
    }
}
