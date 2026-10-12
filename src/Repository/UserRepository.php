<?php

// src/Repository/UserRepository.php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    // Ajoutez votre méthode ici :
    public function findActive(): array
    {
        return $this->createQueryBuilder('u')
            // Adaptez cette condition selon votre base de données 
            // (ex: 'u.deletedAt IS NULL' pour la corbeille, ou 'u.isVerified = true', etc.)
            ->andWhere('u.deletedAt IS NULL') 
            ->orderBy('u.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
    public function findDeleted(): array
    {
        return $this->createQueryBuilder('u')
            // Adaptez selon votre champ de corbeille (ex: 'u.deletedAt IS NOT NULL')
            ->andWhere('u.deletedAt IS NOT NULL') 
            ->orderBy('u.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}