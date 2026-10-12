<?php

namespace App\Controller\Admin;

use App\Entity\PasswordHistory;
use App\Entity\User;
use App\Form\AdminUserFormType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserAdminController extends AbstractController
{
    #[Route('', name: 'admin_user_index')]
    public function index(UserRepository $repo): Response
    {
        return $this->render('admin/user/index.html.twig', [
            'users' => $repo->findActive(),
            'trashCount' => count($repo->findDeleted()),
        ]);
    }

    #[Route('/trash', name: 'admin_user_trash')]
    public function trash(UserRepository $repo): Response
    {
        return $this->render('admin/user/trash.html.twig', [
            'users' => $repo->findDeleted(),
        ]);
    }

    #[Route('/new', name: 'admin_user_new')]
    public function new(Request $request, UserPasswordHasherInterface $hasher, EntityManagerInterface $em): Response
    {
        $user = new User();
        $form = $this->createForm(AdminUserFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $hash = $hasher->hashPassword($user, $form->get('plainPassword')->getData());
            $user->setPassword($hash);

            $em->persist($user);
            $em->persist((new PasswordHistory())
                ->setUser($user)
                ->setHash($hash)
                ->setCreatedAt(new \DateTimeImmutable()));
            $em->flush();

            $this->addFlash('success', 'Utilisateur créé.');
            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/form.html.twig', [
            'form' => $form,
            'title' => 'Ajouter un utilisateur',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_user_edit')]
    public function edit(User $user, Request $request, UserPasswordHasherInterface $hasher, EntityManagerInterface $em): Response
    {
        if ($user->isDeleted()) {
            $this->addFlash('error', 'Ce compte est dans la corbeille : restaurez-le avant de le modifier.');
            return $this->redirectToRoute('admin_user_trash');
        }

        $form = $this->createForm(AdminUserFormType::class, $user, ['edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = $form->get('plainPassword')->getData();
            if ($plain) {
                $hash = $hasher->hashPassword($user, $plain);
                $user->setPassword($hash);
                $em->persist((new PasswordHistory())
                    ->setUser($user)
                    ->setHash($hash)
                    ->setCreatedAt(new \DateTimeImmutable()));
            }

            if ($user === $this->getUser()) {
                $user->setSuspended(false);
            }

            $em->flush();

            $this->addFlash('success', 'Utilisateur modifié.');
            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier ' . $user->getPrenom() . ' ' . $user->getNom(),
        ]);
    }

    #[Route('/{id}/suspend', name: 'admin_user_suspend', methods: ['POST'])]
    public function suspend(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('suspend' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
        } elseif ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas suspendre votre propre compte.');
        } else {
            $user->setSuspended(!$user->isSuspended());
            $em->flush();
            $this->addFlash('success', $user->isSuspended() ? 'Compte suspendu.' : 'Compte réactivé.');
        }

        return $this->redirectToRoute('admin_user_index');
    }

    #[Route('/{id}/delete', name: 'admin_user_delete', methods: ['POST'])]
    public function delete(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
        } elseif ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
        } else {
            $user->setDeletedAt(new \DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', 'Utilisateur placé dans la corbeille.');
        }

        return $this->redirectToRoute('admin_user_index');
    }
    #[Route('/{id}/restore', name: 'admin_user_restore', methods: ['POST'])]
    public function restore(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('restore' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
        } else {
            $user->setDeletedAt(null);
            $em->flush();
            $this->addFlash('success', 'Utilisateur restauré.');
        }

        return $this->redirectToRoute('admin_user_trash');
    }

    #[Route('/{id}/purge', name: 'admin_user_purge', methods: ['POST'])]
    public function purge(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('purge' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
        } elseif (!$user->isDeleted()) {
            $this->addFlash('error', 'Seuls les comptes de la corbeille peuvent être supprimés définitivement.');
        } else {
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', 'Utilisateur supprimé définitivement.');
        }

        return $this->redirectToRoute('admin_user_trash');
    }
}