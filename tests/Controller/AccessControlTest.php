<?php

namespace App\Tests\Controller;

use App\Tests\Support\PortailTestCase;

/**
 * B. Rôles et habilitations (tests 5 à 8)
 */
class AccessControlTest extends PortailTestCase
{
    // ---------- Test 5 : accès à l'administration ----------

    public function testAdministrateurAccedeAAdmin(): void
    {
        $client = $this->actAs('admin@test.fr');
        $client->request('GET', '/admin');

        $this->assertResponseRedirects('/admin/users');
    }

    public function testAliceUtilisatriceRefuseeSurAdmin(): void
    {
        $client = $this->actAs('alice@test.fr');

        $client->request('GET', '/admin');
        $this->assertResponseStatusCodeSame(403);

        // Saisie directe de l'URL d'administration
        $client->request('GET', '/admin/users');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testVisiteurNonConnecteRedirigeDepuisAdmin(): void
    {
        $client = $this->newClient();
        $client->request('GET', '/admin');

        $this->assertResponseRedirects('/login');
    }

    // ---------- Test 6 : fonctionnalités d'administration ----------

    public function testAdministrateurConsulteLaListeDesUtilisateurs(): void
    {
        $client = $this->actAs('admin@test.fr');
        $client->request('GET', '/admin/users');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('table.users', 'bob@test.fr');
    }

    public function testAdministrateurModifieLeCompteDeBob(): void
    {
        $bob = $this->findUser('bob@test.fr');
        $client = $this->actAs('admin@test.fr');

        $client->request('POST', '/admin/users/' . $bob->getId() . '/edit',
            $this->adminFormData($bob, ['prenom' => 'Robert']));

        $this->assertResponseRedirects('/admin/users');
        $this->assertSame('Robert', $this->findUser('bob@test.fr')->getPrenom());
    }

    public function testAdministrateurSuspendLeCompteDeBob(): void
    {
        $bob = $this->findUser('bob@test.fr');
        $client = $this->actAs('admin@test.fr');
        $token = $this->csrfTokenFor($client, 'suspend', $bob->getId());

        $client->request('POST', '/admin/users/' . $bob->getId() . '/suspend', ['_token' => $token]);

        $this->assertResponseRedirects('/admin/users');
        $this->assertTrue($this->findUser('bob@test.fr')->isSuspended());
    }

    public function testAdministrateurMetBobALaCorbeille(): void
    {
        $bob = $this->findUser('bob@test.fr');
        $client = $this->actAs('admin@test.fr');
        $token = $this->csrfTokenFor($client, 'delete', $bob->getId());

        $client->request('POST', '/admin/users/' . $bob->getId() . '/delete', ['_token' => $token]);

        $this->assertResponseRedirects('/admin/users');
        $this->assertTrue($this->findUser('bob@test.fr')->isDeleted());
    }

    public function testAliceNePeutPasSuspendreBob(): void
    {
        $bob = $this->findUser('bob@test.fr');
        $client = $this->actAs('alice@test.fr');

        $client->request('POST', '/admin/users/' . $bob->getId() . '/suspend', ['_token' => 'invalide']);

        $this->assertResponseStatusCodeSame(403);
        $this->assertFalse($this->findUser('bob@test.fr')->isSuspended());
    }

    public function testAliceNePeutPasSupprimerUnCompte(): void
    {
        $bob = $this->findUser('bob@test.fr');
        $client = $this->actAs('alice@test.fr');

        $client->request('POST', '/admin/users/' . $bob->getId() . '/delete', ['_token' => 'invalide']);

        $this->assertResponseStatusCodeSame(403);
        $this->assertFalse($this->findUser('bob@test.fr')->isDeleted());
    }

    // ---------- Test 7 : modification du compte d'un autre (IDOR / BOLA) ----------

    public function testAliceModifieSonPropreProfil(): void
    {
        $alice = $this->findUser('alice@test.fr');
        $client = $this->actAs('alice@test.fr');

        $crawler = $client->request('GET', '/profil/' . $alice->getId() . '/modifier');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Enregistrer')->form(['profile_form[prenom]' => 'Alicia']);
        $client->submit($form);

        $this->assertResponseRedirects('/profil');
        $this->assertSame('Alicia', $this->findUser('alice@test.fr')->getPrenom());
    }

    public function testAliceNePeutPasModifierLeProfilDeBob(): void
    {
        $bob = $this->findUser('bob@test.fr');
        $client = $this->actAs('alice@test.fr');

        $client->request('GET', '/profil/' . $bob->getId() . '/modifier');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testAliceRemplaceSonIdentifiantParCeluiDeBobDansLUrl(): void
    {
        $alice = $this->findUser('alice@test.fr');
        $bob = $this->findUser('bob@test.fr');
        $client = $this->actAs('alice@test.fr');

        $crawler = $client->request('GET', '/profil/' . $alice->getId() . '/modifier');
        $form = $crawler->selectButton('Enregistrer')->form(['profile_form[prenom]' => 'Pirate']);
        $url = str_replace('/profil/' . $alice->getId() . '/', '/profil/' . $bob->getId() . '/', $form->getUri());

        $client->request('POST', $url, $form->getPhpValues());

        $this->assertResponseStatusCodeSame(403);
        $this->assertSame('Bob', $this->findUser('bob@test.fr')->getPrenom());
    }

    public function testAliceEnvoieDirectementUneRequeteHttpPourModifierBob(): void
    {
        $bob = $this->findUser('bob@test.fr');
        $client = $this->actAs('alice@test.fr');

        $client->request('POST', '/profil/' . $bob->getId() . '/modifier',
            $this->profileFormData($bob, ['nom' => 'Hack', 'prenom' => 'Hack']));

        $this->assertResponseStatusCodeSame(403);
        $after = $this->findUser('bob@test.fr');
        $this->assertSame('Bob', $after->getPrenom());
        $this->assertSame('Durand', $after->getNom());
    }

    public function testAdministrateurModifieLeProfilDeBob(): void
    {
        $bob = $this->findUser('bob@test.fr');
        $client = $this->actAs('admin@test.fr');

        $crawler = $client->request('GET', '/profil/' . $bob->getId() . '/modifier');
        $this->assertResponseIsSuccessful();
        $client->submit($crawler->selectButton('Enregistrer')->form(['profile_form[prenom]' => 'Robert']));

        $this->assertResponseRedirects('/admin/users');
        $this->assertSame('Robert', $this->findUser('bob@test.fr')->getPrenom());
    }

    // ---------- Test 8 : modification des rôles et habilitations ----------

    public function testAliceModifieSonPrenom(): void
    {
        $alice = $this->findUser('alice@test.fr');
        $client = $this->actAs('alice@test.fr');

        $client->request('POST', '/profil/' . $alice->getId() . '/modifier',
            $this->profileFormData($alice, ['prenom' => 'Alicia']));

        $this->assertResponseRedirects('/profil');
        $this->assertSame('Alicia', $this->findUser('alice@test.fr')->getPrenom());
    }

    public function testAlicePeutPasSAttribuerLeRoleAdmin(): void
    {
        $alice = $this->findUser('alice@test.fr');
        $client = $this->actAs('alice@test.fr');

        // 1) via le formulaire de profil : le champ « roles » n'existe pas dans ce formulaire
        $client->request('POST', '/profil/' . $alice->getId() . '/modifier',
            $this->profileFormData($alice, ['roles' => ['ROLE_ADMIN']]));
        $this->assertNotContains('ROLE_ADMIN', $this->findUser('alice@test.fr')->getRoles());

        // 2) via le formulaire d'administration : accès interdit
        $client->request('POST', '/admin/users/' . $alice->getId() . '/edit',
            $this->adminFormData($alice, ['roles' => ['ROLE_USER', 'ROLE_ADMIN']]));
        $this->assertResponseStatusCodeSame(403);
        $this->assertNotContains('ROLE_ADMIN', $this->findUser('alice@test.fr')->getRoles());
    }

    public function testParametreRolesAjouteALaRequeteDeProfilEstIgnore(): void
    {
        $alice = $this->findUser('alice@test.fr');
        $client = $this->actAs('alice@test.fr');

        $data = $this->profileFormData($alice, ['prenom' => 'Alicia']);
        $data['roles'] = ['ROLE_ADMIN'];   // paramètre supplémentaire hors du formulaire
        $client->request('POST', '/profil/' . $alice->getId() . '/modifier', $data);

        $after = $this->findUser('alice@test.fr');
        $this->assertContains('ROLE_USER', $after->getRoles());
        $this->assertNotContains('ROLE_ADMIN', $after->getRoles());
    }

    public function testAliceNePeutPasModifierLesRolesDeBob(): void
    {
        $bob = $this->findUser('bob@test.fr');
        $client = $this->actAs('alice@test.fr');

        $client->request('POST', '/admin/users/' . $bob->getId() . '/edit',
            $this->adminFormData($bob, ['roles' => ['ROLE_USER', 'ROLE_ADMIN']]));

        $this->assertResponseStatusCodeSame(403);
        $this->assertNotContains('ROLE_ADMIN', $this->findUser('bob@test.fr')->getRoles());
    }

    public function testAdministrateurAttribueUnRoleABob(): void
    {
        $bob = $this->findUser('bob@test.fr');
        $client = $this->actAs('admin@test.fr');

        $client->request('POST', '/admin/users/' . $bob->getId() . '/edit',
            $this->adminFormData($bob, ['roles' => ['ROLE_USER', 'ROLE_ADMIN']]));

        $this->assertResponseRedirects('/admin/users');
        $this->assertContains('ROLE_ADMIN', $this->findUser('bob@test.fr')->getRoles());
    }
}