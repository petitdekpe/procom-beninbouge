<?php

namespace App\Repository;

use App\Entity\HomeSection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HomeSection>
 */
class HomeSectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HomeSection::class);
    }

    /**
     * @return list<HomeSection>
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['position' => 'ASC']);
    }

    /**
     * The sections actually rendered on the public homepage, in the order
     * the admin configured — see App\Controller\HomeController.
     *
     * @return list<HomeSection>
     */
    public function findEnabledOrdered(): array
    {
        return $this->findBy(['enabled' => true], ['position' => 'ASC']);
    }
}
