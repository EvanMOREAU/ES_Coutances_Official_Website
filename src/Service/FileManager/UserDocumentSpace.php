<?php

namespace App\Service\FileManager;

use App\Entity\User;
use App\Security\PermissionChecker;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Espace personnel de chaque compte dans « Documents » : un dossier à son nom, créé à la
 * demande (première ouverture du gestionnaire ou premier envoi dans la messagerie).
 *
 * Chacun ne voit et ne manipule que son dossier, sauf à détenir l'autorisation
 * « fichiers.documents_tous » (les administrateurs non restreints l'ont d'office).
 */
class UserDocumentSpace
{
    public const ROOT = 'documents';

    /** Sous-dossier des fichiers envoyés depuis la messagerie : leur contenu est supprimé à l'échéance. */
    public const TEMP_FOLDER = 'Messagerie temporaire';

    public const SEE_ALL_PERMISSION = 'fichiers.documents_tous';

    public function __construct(
        private readonly FileStorage $storage,
        private readonly PermissionChecker $permissions,
    ) {
    }

    /** Nom du dossier personnel (stable : basé sur l'identifiant du compte). */
    public function folderName(User $user): string
    {
        $label = $user->getNomComplet() ?: (string) strstr((string) $user->getEmail(), '@', true);
        $slug  = strtolower((new AsciiSlugger())->slug($label)->toString());

        return ($slug !== '' ? $slug : 'compte').'-'.$user->getId();
    }

    public function rootPath(User $user): string
    {
        return self::ROOT.'/'.$this->folderName($user);
    }

    public function tempPath(User $user): string
    {
        return $this->rootPath($user).'/'.self::TEMP_FOLDER;
    }

    public function canSeeAll(User $user): bool
    {
        return $this->permissions->can(self::SEE_ALL_PERMISSION, $user);
    }

    /** Crée le dossier personnel s'il n'existe pas encore. */
    public function ensure(User $user): ResolvedPath
    {
        return $this->ensureFolder($this->rootPath($user));
    }

    public function ensureTemp(User $user): ResolvedPath
    {
        $this->ensure($user);

        return $this->ensureFolder($this->tempPath($user));
    }

    /** Un chemin du gestionnaire est-il accessible à cet utilisateur ? (ne concerne que « Documents ») */
    public function allows(User $user, string $virtual): bool
    {
        $segments = explode('/', trim(str_replace('\\', '/', $virtual), '/'));
        if ($segments[0] !== self::ROOT || count($segments) < 2) {
            return true;
        }

        return $this->canSeeAll($user) || $segments[1] === $this->folderName($user);
    }

    /** Ce chemin se trouve-t-il dans le dossier personnel de l'utilisateur (et non dans celui d'un autre) ? */
    public function isOwn(User $user, string $virtual): bool
    {
        $prefix = $this->rootPath($user).'/';

        return str_starts_with(trim(str_replace('\\', '/', $virtual), '/'), $prefix);
    }

    private function ensureFolder(string $virtual): ResolvedPath
    {
        $resolved = $this->storage->resolve($virtual);
        if (!$resolved->exists) {
            $parent = $this->storage->resolve($resolved->parent());
            $name   = substr($virtual, strrpos($virtual, '/') + 1);
            $this->storage->createFolder($parent, $name);
            $resolved = $this->storage->resolve($virtual);
        }

        return $resolved;
    }
}
