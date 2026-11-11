<?php

namespace App\DataFixtures;

use App\Entity\PasswordHistory;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public const PASSWORDS = [
        'admin@test.fr' => 'Admin#Secure2026-Test',
        'alice@test.fr' => 'Alice#Secure2026-Test',
        'bob@test.fr' => 'Bobby#Secure2026-Test',
        'suspendu@test.fr' => 'Suspendu#Secure2026-Test',
    ];

    public function __construct(private UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $users = [
            ['admin@test.fr', 'Admin', 'Test', ['ROLE_ADMIN'], false],
            ['alice@test.fr', 'Alice', 'Martin', ['ROLE_USER'], false],
            ['bob@test.fr', 'Bob', 'Durand', ['ROLE_USER'], false],
            ['suspendu@test.fr', 'Suspendu', 'Test', ['ROLE_USER'], true],
        ];

        foreach ($users as [$email, $prenom, $nom, $roles, $suspended]) {
            $user = (new User())
                ->setEmail($email)
                ->setPrenom($prenom)
                ->setNom($nom)
                ->setAdresse('1 rue du Test')
                ->setVille('Paris')
                ->setCodePostal('75001')
                ->setRoles($roles)
                ->setSuspended($suspended);

            $hash = $this->hasher->hashPassword($user, self::PASSWORDS[$email]);
            $user->setPassword($hash);

            $manager->persist($user);
            $manager->persist((new PasswordHistory())
                ->setUser($user)
                ->setHash($hash)
                ->setCreatedAt(new \DateTimeImmutable()));
        }

        $manager->flush();
    }
}