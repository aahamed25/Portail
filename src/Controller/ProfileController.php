<?php

namespace App\Controller;

use App\Entity\PasswordHistory;
use App\Form\ChangePasswordFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('/profil', name: 'app_profile')]
    public function index(): Response
    {
        return $this->render('profile/index.html.twig', ['user' => $this->getUser()]);
    }

    #[Route('/profil/mot-de-passe', name: 'app_change_password')]
    public function changePassword(
        Request $request,
        PasswordHasherFactoryInterface $factory,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();
        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = $form->get('newPassword')->getData();
            $verifier = $factory->getPasswordHasher($user);

            foreach ($user->getPasswordHistories() as $old) {
                if ($verifier->verify($old->getHash(), $plain)) {
                    $form->get('newPassword')->addError(
                        new FormError('Ce mot de passe a déjà été utilisé, choisissez-en un nouveau.')
                    );
                    return $this->render('profile/change_password.html.twig', ['form' => $form]);
                }
            }

            $hash = $hasher->hashPassword($user, $plain);
            $user->setPassword($hash);
            $em->persist((new PasswordHistory())
                ->setUser($user)
                ->setHash($hash)
                ->setCreatedAt(new \DateTimeImmutable()));
            $em->flush();

            $this->addFlash('success', 'Votre mot de passe a bien été modifié.');
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/change_password.html.twig', ['form' => $form]);
    }
}