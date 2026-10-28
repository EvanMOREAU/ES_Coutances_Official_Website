<?php

namespace App\Tests\Functional;

use App\Entity\BoutiqueSettings;
use App\Repository\BoutiqueSettingsRepository;
use App\Tests\Support\DatabaseTestCase;

/** Les réglages lus à chaque page sont mis en cache, et le cache est vidé dès qu'un réglage est enregistré. */
final class SingletonCacheTest extends DatabaseTestCase
{
    public function testSettingsAreCachedThenRefreshedOnWrite(): void
    {
        $settings = (new BoutiqueSettings())->setEnMaintenance(false);
        $this->em->persist($settings);
        $this->em->flush();
        $repository = static::getContainer()->get(BoutiqueSettingsRepository::class);

        $this->em->clear();
        self::assertFalse($repository->getSingleton()->isEnMaintenance());

        // Modification hors ORM (comme une restauration de sauvegarde) : le cache sert encore l'ancienne valeur…
        $this->em->getConnection()->executeStatement('UPDATE es_boutique_settings SET en_maintenance = 1');
        $this->em->clear();
        self::assertFalse($repository->getSingleton()->isEnMaintenance(), 'Réponse servie par le cache.');

        // … tandis qu'une écriture par l'ORM le vide aussitôt.
        $this->em->clear();
        $fresh = $this->em->find(BoutiqueSettings::class, $settings->getId());
        $fresh->setEnMaintenance(false);
        $this->em->flush();
        $this->em->clear();
        self::assertFalse($repository->getSingleton()->isEnMaintenance());
        $this->em->getConnection()->executeStatement('UPDATE es_boutique_settings SET en_maintenance = 1');
        $this->em->clear();
        $fresh = $this->em->find(BoutiqueSettings::class, $settings->getId());
        $fresh->setSeuilStockBas(9);
        $this->em->flush();
        $this->em->clear();
        self::assertTrue($repository->getSingleton()->isEnMaintenance(), 'Le cache a été vidé par l\'écriture.');
    }
}
