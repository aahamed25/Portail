<?php

namespace App\Tests\Controller;

use App\Tests\Support\PortailTestCase;
use Symfony\Component\BrowserKit\Cookie;

/**
 * A. Authentification (tests 1 à 4)
 */
class AuthenticationTest extends PortailTestCase
{
    // ---------- Test 1 : accès à une page protégée ----------

    public function testAliceConnecteeAccedeAMonEspace(): void
    {
        $client = $this->actAs('alice@test.fr');
        $client->request('GET', '/profil');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Alice');
    }

    public function testVisiteurNonConnecteRedirigeVersLaConnexion(): void
    {
        $client = $this->newClient();
        $client->request('GET', '/profil');

        $this->assertResponseRedirects('/login');
    }

    public function testUrlProtegeeSaisieDirectementSansConnexion(): void
    {
        $aliceId = $this->findUser('alice@test.fr')->getId();
        $client = $this->newClient();

        foreach (['/profil', '/profil/mot-de-passe', "/profil/$aliceId/modifier", '/admin/users'] as $url) {
            $client->request('GET', $url);
            $this->assertResponseRedirects('/login', null, "L'URL $url devrait rediriger vers la connexion.");
        }
    }

    // ---------- Test 2 : connexion avec des identifiants valides ----------

    public function testConnexionAvecIdentifiantsValides(): void
    {
        $client = $this->loginAttempt('alice@test.fr');

        $this->assertResponseRedirects('/profil');
    }

    public function testApresConnexionAliceAccedeAMonEspace(): void
    {
        $client = $this->loginAttempt('alice@test.fr');
        $client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Alice');
    }

    public function testAliceResteAuthentifieeSurUneAutrePageProtegee(): void
    {
        $client = $this->loginAttempt('alice@test.fr');
        $client->request('GET', '/profil/mot-de-passe');
        $this->assertResponseIsSuccessful();

        $client->request('GET', '/profil');
        $this->assertResponseIsSuccessful();
    }

    // ---------- Test 3 : connexion avec un mot de passe incorrect ----------

    public function testMotDePasseIncorrectRefuse(): void
    {
        $client = $this->loginAttempt('alice@test.fr', 'MauvaisMotDePasse#1');

        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        $this->assertSelectorExists('.alert-error');
        $this->assertNonAuthentifie($client);
    }

    public function testMotDePasseDeBobAvecIdentifiantDAliceRefuse(): void
    {
        $client = $this->loginAttempt('alice@test.fr', $this->password('bob@test.fr'));

        $this->assertResponseRedirects('/login');
        $this->assertNonAuthentifie($client);
    }

    public function testIdentifiantInexistantRefuse(): void
    {
        $client = $this->loginAttempt('inconnu@test.fr', 'MotDePasseQuelconque#1');

        $this->assertResponseRedirects('/login');
        $this->assertNonAuthentifie($client);
    }

    // ---------- Test 4 : déconnexion et invalidation de session ----------

    public function testAliceDeconnecteeNePeutPlusConsulterSonEspace(): void
    {
        $client = $this->loginAttempt('alice@test.fr');
        $client->request('GET', '/profil');
        $this->assertResponseIsSuccessful();

        $client->request('GET', '/logout');
        $client->request('GET', '/profil');

        $this->assertResponseRedirects('/login');
    }

    public function testAncienCookieDeSessionRefuseApresDeconnexion(): void
    {
        $client = $this->loginAttempt('alice@test.fr');
        $cookie = $client->getCookieJar()->get('MOCKSESSID') ?? ($client->getCookieJar()->all()[0] ?? null);
        $this->assertNotNull($cookie, 'Cookie de session introuvable.');
        $name = $cookie->getName();
        $value = $cookie->getValue();

        $client->request('GET', '/profil');
        $this->assertResponseIsSuccessful();
        $client->request('GET', '/logout');

        // Nouvelle requête HTTP rejouant l'ancien cookie de session
        $replay = $this->newClient();
        $replay->getCookieJar()->set(new Cookie($name, $value, null, '/'));
        $replay->request('GET', '/profil');

        $this->assertResponseRedirects('/login');
    }

    private function assertNonAuthentifie($client): void
    {
        $client->request('GET', '/profil');
        $this->assertResponseRedirects('/login');
    }
}