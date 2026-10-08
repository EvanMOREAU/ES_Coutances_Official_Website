<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\WebauthnCredentialRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Contrôle de configuration d'un site en production : ce qui ne se voit pas à l'écran mais expose
 * le site si c'est oublié (mode debug, clé secrète du dépôt, comptes privilégiés sans double
 * authentification…). Utilisé par `app:securite:verifier` et affiché à la fin d'un déploiement.
 *
 * Chaque contrôle renvoie [libellé, réussi ?, conseil si échec, bloquant ?].
 */
final class ProductionChecklist
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WebauthnCredentialRepository $passkeys,
        private readonly string $projectDir,
        private readonly string $kernelEnvironment,
        private readonly string $secret,
        private readonly bool $debug,
        private readonly bool $privilegedTwoFactorEnforced,
    ) {
    }

    /** @return list<array{label: string, ok: bool, advice: string, blocking: bool}> */
    public function run(): array
    {
        $checks = [
            $this->check('Environnement de production (APP_ENV=prod)', 'prod' === $this->kernelEnvironment, "Définir APP_ENV=prod dans .env.local (ou l'environnement du serveur).", true),
            $this->check('Mode debug désactivé', !$this->debug, 'Définir APP_DEBUG=0.', true),
            $this->check('Clé secrète propre au serveur (APP_SECRET)', !$this->secretIsCommitted(), 'APP_SECRET est celle du dépôt Git : en définir une autre dans .env.local (⚠ les mots de passe SMTP/HelloAsso enregistrés en base devront être ressaisis dans l\'admin).', true),
            $this->check('Doubles authentifications obligatoires (ENFORCE_PRIVILEGED_2FA)', $this->privilegedTwoFactorEnforced, 'Retirer ENFORCE_PRIVILEGED_2FA=0 de la configuration.', true),
            $this->check('Dossier des envois protégé (public/uploads/.htaccess)', is_file($this->projectDir.'/public/uploads/.htaccess'), 'Fichier absent : voir deploy/nginx.conf.example si le serveur est Nginx (aucun script ne doit s\'exécuter dans /uploads).', false),
            $this->check('Variables compilées (.env.local.php)', is_file($this->projectDir.'/.env.local.php'), 'Lancer « composer dump-env prod » : démarrage plus rapide, configuration figée.', false),
        ];

        $unprotected = $this->privilegedWithoutSecondFactor();
        $checks[] = $this->check(
            'Comptes développeur/administrateur avec double authentification',
            [] === $unprotected,
            'Sans protection : '.implode(', ', $unprotected).'.',
            true,
        );

        return $checks;
    }

    /** @param list<array{label: string, ok: bool, advice: string, blocking: bool}> $checks */
    public static function hasBlockingFailure(array $checks): bool
    {
        foreach ($checks as $check) {
            if (!$check['ok'] && $check['blocking']) {
                return true;
            }
        }

        return false;
    }

    /** @return array{label: string, ok: bool, advice: string, blocking: bool} */
    private function check(string $label, bool $ok, string $advice, bool $blocking): array
    {
        return ['label' => $label, 'ok' => $ok, 'advice' => $advice, 'blocking' => $blocking];
    }

    /** La clé du dépôt (.env, .env.dev) est publique : elle ne doit pas servir en production. */
    private function secretIsCommitted(): bool
    {
        foreach (['.env', '.env.dev', '.env.test'] as $file) {
            $path = $this->projectDir.'/'.$file;
            if (is_file($path) && preg_match('/^APP_SECRET=[\'"]?([^\'"\r\n]*)/m', (string) file_get_contents($path), $m) && '' !== $m[1] && hash_equals($m[1], $this->secret)) {
                return true;
            }
        }

        return '' === $this->secret;
    }

    /** @return list<string> */
    private function privilegedWithoutSecondFactor(): array
    {
        $users = $this->em->getRepository(User::class)->createQueryBuilder('u')
            ->where('u.roles LIKE :dev OR u.roles LIKE :admin')
            ->setParameter('dev', '%ROLE_DEV%')
            ->setParameter('admin', '%ROLE_ADMIN%')
            ->getQuery()->getResult();

        $emails = [];
        foreach ($users as $user) {
            if (!$user->hasTwoFactorEnabled() && [] === $this->passkeys->findAllForUser($user)) {
                $emails[] = (string) $user->getEmail();
            }
        }

        return $emails;
    }
}
