<?php

namespace App\EventListener;

use App\Entity\LoginHistory;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[AsEventListener(event: LoginSuccessEvent::class)]
class LoginSuccessListener
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $log = (new LoginHistory())
            ->setUser($user)
            ->setConnectedAt(new \DateTimeImmutable())
            ->setIp($event->getRequest()->getClientIp());

        $this->em->persist($log);
        $this->em->flush();
    }
}