<?php

namespace App\Repository;

use App\Entity\Note;
use DateInterval;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Note>
 *
 * @method Note|null find($id, $lockMode = null, $lockVersion = null)
 * @method Note|null findOneBy(array $criteria, array $orderBy = null)
 * @method Note[]    findAll()
 * @method Note[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Note::class);
    }


    public function getOldNotes(): array
    {
        $entityManager = $this->getEntityManager();

        $today = new DateTime('now');

        $aWeekAgo = $today->sub(new DateInterval('P7D'));

        $query = $entityManager->createQuery(
            'SELECT n
            FROM App\Entity\Note n
            WHERE n.date < :date'
        )->setParameter('date', $aWeekAgo);

        return $query->getResult();
    }
}
