<?php

namespace App\Repository;

use App\Entity\Document;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Document>
 */
class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    /**
     * Incrémente le compteur en une seule requête UPDATE, sans charger ni
     * flusher l'entité (appelé à chaque téléchargement public).
     */
    public function incrementDownloadCount(Document $document): void
    {
        $this->createQueryBuilder('d')
            ->update()
            ->set('d.downloadCount', 'd.downloadCount + 1')
            ->where('d.id = :id')
            ->setParameter('id', $document->getId())
            ->getQuery()
            ->execute();
    }
}
