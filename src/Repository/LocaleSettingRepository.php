<?php

namespace App\Repository;

use App\Entity\LocaleSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LocaleSetting>
 */
class LocaleSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LocaleSetting::class);
    }

    /** @return array<string, bool> code => activée */
    public function enabledByCode(): array
    {
        $map = [];
        foreach ($this->findAll() as $setting) {
            $map[$setting->getCode()] = $setting->isEnabled();
        }

        return $map;
    }
}
