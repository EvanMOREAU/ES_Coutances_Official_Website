<?php

namespace App\Service\Chat;

use App\Entity\ChatAttachment;
use App\Entity\User;
use App\Repository\ChatAttachmentRepository;
use App\Service\FileManager\FileManagerException;
use App\Service\FileManager\FileStorage;
use App\Service\FileManager\UserDocumentSpace;
use Doctrine\ORM\EntityManagerInterface;

/** Documents joints à la messagerie : disponibilité du fichier, présentation, documents partageables, purge à l'échéance. */
class ChatDocuments
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ChatAttachmentRepository $attachmentRepository,
        private readonly FileStorage $storage,
        private readonly UserDocumentSpace $space,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function serialize(?ChatAttachment $attachment): ?array
    {
        if ($attachment === null) {
            return null;
        }
        $ext = strtolower(pathinfo($attachment->getName(), \PATHINFO_EXTENSION));

        return [
            'id'        => $attachment->getId(),
            'name'      => $attachment->getName(),
            'size'      => $attachment->getSize(),
            'kind'      => $this->storage->kindOf($ext),
            'expiresAt' => $attachment->getExpiresAt()->format(\DATE_ATOM),
            'purgedAt'  => $attachment->getPurgedAt()?->format(\DATE_ATOM),
            'available' => $this->fileOf($attachment) !== null,
        ];
    }

    /** Chemin réel du fichier si le document est encore disponible (non périmé, toujours présent), sinon null. */
    public function fileOf(ChatAttachment $attachment): ?string
    {
        if ($attachment->isExpired()) {
            return null;
        }
        try {
            $resolved = $this->storage->resolve($attachment->getPath());
        } catch (FileManagerException) {
            return null;
        }

        return $resolved->exists && $resolved->real !== null && is_file($resolved->real) ? $resolved->real : null;
    }

    /**
     * Documents de l'espace personnel que l'utilisateur peut partager (hors fichiers temporaires de la messagerie).
     *
     * @return list<array{name: string, path: string, size: int, mtime: int}>
     */
    public function shareableDocuments(User $me): array
    {
        $root  = $this->space->ensure($me);
        $files = [];
        foreach ($this->storage->filesUnder($root) as $entry) {
            if (str_starts_with($entry['path'], $this->space->tempPath($me).'/')) {
                continue;
            }
            $files[] = ['name' => $entry['name'], 'path' => $entry['path'], 'size' => (int) $entry['size'], 'mtime' => (int) $entry['mtime']];
        }
        usort($files, static fn (array $a, array $b) => $b['mtime'] <=> $a['mtime']);

        return array_slice($files, 0, 200);
    }

    /**
     * Supprime du disque les documents temporaires arrivés à échéance et ferme l'accès aux documents partagés.
     * Les lignes sont conservées : l'historique garde la trace de l'échange.
     */
    public function purgeExpired(): int
    {
        $count = 0;
        foreach ($this->attachmentRepository->expired() as $attachment) {
            if ($attachment->isTemporary()) {
                try {
                    $resolved = $this->storage->resolve($attachment->getPath());
                    if ($resolved->exists && $resolved->real !== null && is_file($resolved->real)) {
                        @unlink($resolved->real);
                    }
                } catch (FileManagerException) {
                    // déjà supprimé à la main : rien à faire
                }
            }
            $attachment->markPurged();
            ++$count;
        }
        if ($count > 0) {
            $this->em->flush();
        }

        return $count;
    }

}
