<?php

namespace App\Tests\Controller;

use App\Tests\Support\PortailTestCase;

/**
 * D. Sécurité des comptes (tests 13 à 15)
 */
class AccountSecurityTest extends PortailTestCase
{
    private const A = 'Alice#Secure2026-Test';        // mot de passe des fixtures
    private const B = 'Horloge&Sable15-Tomate';
    private const C = 'Voilier$Chene77!Pierre';

    // ---------- Test 13 : suspension d'un utilisateur ----------

    public function testCompteSuspenduDesFixturesNePeutPasSeConnecter(): void
    {
        $client = $this->loginAttempt('suspendu@test.fr');

        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-error', 'suspendu');
    }

    public function testSuspensionPuisReactivationParLAdministrateur(): void
    {
        // Alice, compte actif, se connecte
        $this->loginAttempt('alice@test.fr');
        $this->assertResponseRedirects('/profil');

        // L'administrateur suspend Alice
        $this->basculerSuspension('alice@test.fr');
        $this->assertTrue($this->findUser('alice@test.fr')->isSuspended());

        // Alice ne peut plus se connecter
        $client = $this->loginAttempt('alice@test.fr');
        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-error', 'suspendu');

        // L'administrateur la réactive
        $this->basculerSuspension('alice@test.fr');
        $this->assertFalse($this->findUser('alice@test.fr')->isSuspended());

        // Alice peut de nouveau se connecter
        $this->loginAttempt('alice@test.fr');
        $this->assertResponseRedirects('/profil');
    }

    // ---------- Test 14 : réutilisation d'un ancien mot de passe ----------

    public function testAncienMotDePasseNePeutPasEtreReutilise(): void
    {
        // A -> B
        $client = $this->loginAttempt('alice@test.fr', self::A);
        $this->assertResponseRedirects('/profil');
        $this->submitPasswordChange($client, self::A, self::B);
        $this->assertResponseRedirects('/profil');

        // B -> C
        $client = $this->loginAttempt('alice@test.fr', self::B);
        $this->assertResponseRedirects('/profil');
        $this->submitPasswordChange($client, self::B, self::C);
        $this->assertResponseRedirects('/profil');

        // C -> A : refusé
        $client = $this->loginAttempt('alice@test.fr', self::C);
        $this->assertResponseRedirects('/profil');
        $this->submitPasswordChange($client, self::C, self::A);
        $this->assertSelectorTextContains('form', 'déjà été utilisé');

        // C -> B : refusé
        $this->submitPasswordChange($client, self::C, self::B);
        $this->assertSelectorTextContains('form', 'déjà été utilisé');

        // Le mot de passe courant est resté C
        $this->loginAttempt('alice@test.fr', self::C);
        $this->assertResponseRedirects('/profil');
    }

    // ---------- Test 15 : modification autorisée du mot de passe ----------

    public function testModificationAutoriseeDuMotDePasse(): void
    {
        $client = $this->loginAttempt('alice@test.fr', self::A);
        $this->submitPasswordChange($client, self::A, self::B);

        $this->assertResponseRedirects('/profil');
    }

    public function testMotDePasseActuelIncorrectRefuse(): void
    {
        $client = $this->loginAttempt('alice@test.fr', self::A);
        $this->submitPasswordChange($client, 'MauvaisActuel#2026x', self::B);

        $this->assertSelectorTextContains('form', 'incorrect');
        $this->loginAttempt('alice@test.fr', self::A);
        $this->assertResponseRedirects('/profil');   // l'ancien mot de passe fonctionne toujours
    }

    public function testNouveauMotDePasseNonConformeRefuse(): void
    {
        $client = $this->loginAttempt('alice@test.fr', self::A);
        $this->submitPasswordChange($client, self::A, 'court');

        $this->assertResponseStatusCodeSame(422);
        $this->loginAttempt('alice@test.fr', self::A);
        $this->assertResponseRedirects('/profil');
    }

    public function testConnexionAvecLeNouveauMotDePasseEtRefusAvecLAncien(): void
    {
        $client = $this->loginAttempt('alice@test.fr', self::A);
        $this->submitPasswordChange($client, self::A, self::B);

        $this->loginAttempt('alice@test.fr', self::B);
        $this->assertResponseRedirects('/profil');

        $this->loginAttempt('alice@test.fr', self::A);
        $this->assertResponseRedirects('/login');
    }

    // ---------- Outil ----------

    private function basculerSuspension(string $email): void
    {
        $user = $this->findUser($email);
        $client = $this->actAs('admin@test.fr');
        $token = $this->csrfTokenFor($client, 'suspend', $user->getId());
        $client->request('POST', '/admin/users/' . $user->getId() . '/suspend', ['_token' => $token]);
        $this->assertResponseRedirects('/admin/users');
    }
}