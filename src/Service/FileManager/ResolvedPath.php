<?php

namespace App\Service\FileManager;

/** Un chemin du gestionnaire de fichiers, une fois validé et traduit en chemin réel. */
final class ResolvedPath
{
    public function __construct(
        /** Chemin virtuel normalisé (« images/logos »). Vide = racine du gestionnaire. */
        public readonly string $virtual,
        /** Chemin réel sur le disque (null pour la racine virtuelle ou un montage encore absent). */
        public readonly ?string $real,
        public readonly ?string $rootKey,
        public readonly bool $isVirtualRoot,
        public readonly bool $readOnly,
        /** Dossier déclaré (racine, montage, dossier d'envoi du site) : ni supprimable ni renommable. */
        public readonly bool $isDeclared,
        public readonly bool $isPrivate,
        public readonly bool $exists = true,
    ) {
    }

    public function parent(): string
    {
        $position = strrpos($this->virtual, '/');

        return $position === false ? '' : substr($this->virtual, 0, $position);
    }
}
