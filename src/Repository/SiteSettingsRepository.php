<?php

namespace App\Repository;

use App\Entity\SiteSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SiteSettings>
 */
class SiteSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteSettings::class);
    }

    /**
     * The single settings row, created on first access — normally seeded
     * by the migration, but this keeps the app working even against a
     * database where that row was somehow deleted.
     */
    public function getSettings(): SiteSettings
    {
        $settings = $this->findOneBy([]);

        if (null !== $settings) {
            return $settings;
        }

        $settings = new SiteSettings();
        $em = $this->getEntityManager();
        $em->persist($settings);
        $em->flush();

        return $settings;
    }
}
