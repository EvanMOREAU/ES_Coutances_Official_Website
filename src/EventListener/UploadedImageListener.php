<?php

namespace App\EventListener;

use App\Service\Upload\ImageCleaner;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Vich\UploaderBundle\Event\Event;
use Vich\UploaderBundle\Event\Events;
use Vich\UploaderBundle\Storage\StorageInterface;

/** Toute image enregistrée par VichUploader est nettoyée (EXIF, GPS…) juste après son envoi. */
#[AsEventListener(event: Events::POST_UPLOAD)]
final class UploadedImageListener
{
    public function __construct(
        private readonly ImageCleaner $cleaner,
        private readonly StorageInterface $storage,
    ) {
    }

    public function __invoke(Event $event): void
    {
        $path = $this->storage->resolvePath($event->getObject(), $event->getMapping()->getFilePropertyName());
        if (null !== $path) {
            $this->cleaner->clean($path);
        }
    }
}
