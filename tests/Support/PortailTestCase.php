<?php

namespace App\Tests\Support;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Classe de base des tests fonctionnels :
 * - un client HTTP avec une adresse IP aléatoire (évite que le limiteur de
 *   tentatives de connexion fausse les tests) ;
 * - des outils pour se connecter, retrouver un utilisateur en base, etc.
 */
abstract class PortailTestCase extends WebTestCase
{
    protected function newClient(): KernelBrowser
    {
        static::ensureKernelShutdown();
        $ip = sprintf('10.%d.%d.%d', random_int(1, 254), random_int(1, 254), random_int(1, 254));

        return static::createClient([], ['REMOTE_ADDR' => $ip]);
    }

    protected function em(): EntityManagerInterface
    {
        $em = static::getContainer()->get('doctrine')->getManager();
        $em->clear();

        return $em;
    }

    protected function findUser(string $email): User
    {
        $user = $this->findUserOrNull($email);
        $this->assertNotNull($user, "L'utilisateur $email est introuvable en base de test.");

        return $user;
    }

    protected function findUserOrNull(string $email): ?User
    {
        return $this->em()->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    protected function password(string $email): string
    {
        return AppFixtures::PASSWORDS[$email];
    }

    /** Client déjà authentifié (sans passer par le formulaire). */
    protected function actAs(string $email): KernelBrowser
    {
        $client = $this->newClient();
        $client->loginUser($this->findUser($email));

        return $client;
    }

    /** Tentative de connexion réelle via le formulaire /login. */
    protected function loginAttempt(string $email, ?string $password = null): KernelBrowser
    {
        $client = $this->newClient();
        $crawler = $client->request('GET', '/login');
        $form = $crawler->filter('form')->form([
            '_username' => $email,
            '_password' => $password ?? $this->password($email),
        ]);
        $client->submit($form);

        return $client;
    }

    /** Jeton CSRF d'une action (suspend, delete...) lu dans la liste d'administration. */
    protected function csrfTokenFor(KernelBrowser $client, string $action, int $userId): string
    {
        $crawler = $client->request('GET', '/admin/users');
        $node = $crawler->filter(sprintf('form[action$="/%d/%s"] input[name="_token"]', $userId, $action));
        $this->assertCount(1, $node, "Bouton « $action » introuvable pour l'utilisateur $userId.");

        return $node->attr('value');
    }

    /** Données d'un formulaire d'administration (envoi direct d'une requête POST). */
    protected function adminFormData(User $user, array $overrides = []): array
    {
        return ['admin_user_form' => array_merge([
            'email' => $user->getEmail(),
            'nom' => $user->getNom(),
            'prenom' => $user->getPrenom(),
            'adresse' => $user->getAdresse(),
            'ville' => $user->getVille(),
            'codePostal' => $user->getCodePostal(),
            'roles' => $user->getRoles(),
            'plainPassword' => ['first' => '', 'second' => ''],
        ], $overrides)];
    }

    /** Données du formulaire de profil (envoi direct d'une requête POST). */
    protected function profileFormData(User $user, array $overrides = []): array
    {
        return ['profile_form' => array_merge([
            'nom' => $user->getNom(),
            'prenom' => $user->getPrenom(),
            'adresse' => $user->getAdresse(),
            'ville' => $user->getVille(),
            'codePostal' => $user->getCodePostal(),
        ], $overrides)];
    }

    /** Soumet le formulaire de changement de mot de passe. */
    protected function submitPasswordChange(KernelBrowser $client, string $current, string $new): void
    {
        $crawler = $client->request('GET', '/profil/mot-de-passe');
        $form = $crawler->selectButton('Enregistrer')->form([
            'change_password_form[currentPassword]' => $current,
            'change_password_form[newPassword][first]' => $new,
            'change_password_form[newPassword][second]' => $new,
        ]);
        $client->submit($form);
    }
}