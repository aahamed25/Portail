<?php

namespace App\Tests\Controller;

use App\Tests\Support\PortailTestCase;

/**
 * C. Validation des données (tests 9 à 12)
 */
class RegistrationTest extends PortailTestCase
{
    private const EMAIL = 'camille@test.fr';
    private const PASSWORD = 'Lilas!Bureau7-Nuage#42';

    // ---------- Test 9 : champs obligatoires ----------

    public function testNomVideRefuse(): void
    {
        $this->assertInscriptionRefusee(['nom' => '']);
    }

    public function testPrenomVideRefuse(): void
    {
        $this->assertInscriptionRefusee(['prenom' => '']);
    }

    public function testAdresseVideRefusee(): void
    {
        $this->assertInscriptionRefusee(['adresse' => '']);
    }

    public function testCodePostalVideRefuse(): void
    {
        $this->assertInscriptionRefusee(['codePostal' => '']);
    }

    public function testMotDePasseVideRefuse(): void
    {
        $this->assertInscriptionRefusee(['password' => '']);
    }

    // ---------- Test 10 : données invalides ----------

    public function testCodePostalAbcdeRefuse(): void
    {
        $this->assertInscriptionRefusee(['codePostal' => 'ABCDE']);
    }

    public function testEmailInvalideRefuse(): void
    {
        $this->assertInscriptionRefusee(['email' => 'pas-un-email']);
    }

    public function testNomTropLongRefuse(): void
    {
        $this->assertInscriptionRefusee(['nom' => str_repeat('A', 101)]);
    }

    public function testMotDePasseTropCourtRefuse(): void
    {
        $this->assertInscriptionRefusee(['password' => 'Ab1!']);
    }

    public function testMotDePasseCompromisRefuse(): void
    {
        $client = $this->assertInscriptionRefusee(['password' => 'Password123!Password']);

        $this->assertSelectorTextContains('form', 'fuité');
    }

    // ---------- Test 11 : inscription valide ----------

    public function testInscriptionValideAcceptee(): void
    {
        $client = $this->inscrire();

        $this->assertResponseRedirects('/login');
    }

    public function testCompteCreeEnBaseAvecMotDePasseHache(): void
    {
        $this->inscrire();

        $user = $this->findUser(self::EMAIL);
        $this->assertNotSame(self::PASSWORD, $user->getPassword(), 'Le mot de passe ne doit pas être stocké en clair.');
        $this->assertContains('ROLE_USER', $user->getRoles());
        $this->assertFalse($user->isSuspended());
        $this->assertCount(1, $user->getPasswordHistories());
    }

    public function testConnexionPossibleApresInscription(): void
    {
        $this->inscrire();

        $client = $this->loginAttempt(self::EMAIL, self::PASSWORD);

        $this->assertResponseRedirects('/profil');
    }

    // ---------- Test 12 : aucun enregistrement après un refus ----------

    public function testAucunCompteCreeApresRefusPuisCreeUneSeuleFois(): void
    {
        $this->assertInscriptionRefusee(['codePostal' => 'ABCDE']);
        $this->assertNull($this->findUserOrNull(self::EMAIL));

        $this->assertInscriptionRefusee(['adresse' => '']);
        $this->assertNull($this->findUserOrNull(self::EMAIL));

        $this->assertInscriptionRefusee(['password' => 'faible']);
        $this->assertNull($this->findUserOrNull(self::EMAIL));

        $this->inscrire();
        $this->assertResponseRedirects('/login');
        $users = $this->em()->getRepository(\App\Entity\User::class)->findBy(['email' => self::EMAIL]);
        $this->assertCount(1, $users);
    }

    // ---------- Outils ----------

    private function inscrire(array $overrides = [])
    {
        $values = array_merge([
            'email' => self::EMAIL,
            'nom' => 'Lefevre',
            'prenom' => 'Camille',
            'adresse' => '12 rue des Lilas',
            'ville' => 'Lyon',
            'codePostal' => '69001',
            'password' => self::PASSWORD,
        ], $overrides);

        $client = $this->newClient();
        $crawler = $client->request('GET', '/inscription');
        $form = $crawler->selectButton('Créer mon compte')->form([
            'registration[email]' => $values['email'],
            'registration[nom]' => $values['nom'],
            'registration[prenom]' => $values['prenom'],
            'registration[adresse]' => $values['adresse'],
            'registration[ville]' => $values['ville'],
            'registration[codePostal]' => $values['codePostal'],
            'registration[plainPassword][first]' => $values['password'],
            'registration[plainPassword][second]' => $values['password'],
        ]);
        $client->submit($form);

        return $client;
    }

    private function assertInscriptionRefusee(array $overrides)
    {
        $client = $this->inscrire($overrides);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('form ul li');
        $this->assertNull($this->findUserOrNull(self::EMAIL), 'Aucun compte ne doit être créé.');

        return $client;
    }
}