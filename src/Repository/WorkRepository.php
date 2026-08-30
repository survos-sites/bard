<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Work;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Work> */
final class WorkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Work::class);
    }

    /** @return list<Work> */
    public function findForIndex(): array
    {
        return $this->createQueryBuilder('work')
            ->orderBy('work.year', 'ASC')
            ->addOrderBy('work.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findWithText(string $id): ?Work
    {
        return $this->createQueryBuilder('work')
            ->addSelect('chapter', 'paragraph', 'character')
            ->leftJoin('work.chapters', 'chapter')
            ->leftJoin('chapter.paragraphs', 'paragraph')
            ->leftJoin('paragraph.character', 'character')
            ->andWhere('work.id = :id')
            ->setParameter('id', $id)
            ->orderBy('chapter.section', 'ASC')
            ->addOrderBy('chapter.scene', 'ASC')
            ->addOrderBy('paragraph.ordinal', 'ASC')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
