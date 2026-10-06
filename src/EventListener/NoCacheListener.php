<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class NoCacheListener implements EventSubscriberInterface
{
    public function __construct(private TokenStorageInterface $tokenStorage) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $user = $this->tokenStorage->getToken()?->getUser();

        $route = $event->getRequest()->attributes->get('_route');
        $authRoutes = ['app_login', 'app_logout', 'app_home'];

        // Si l'utilisateur est connecté OU si c'est une route d'auth → interdire le cache
        if ($user instanceof UserInterface || in_array($route, $authRoutes)) {
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
            $response->headers->set('Surrogate-Control', 'no-store');
        }
    }
}
