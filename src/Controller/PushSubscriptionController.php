<?php

namespace App\Controller;

use App\Entity\PushSubscription;
use App\Repository\PushSubscriptionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/push')]
class PushSubscriptionController extends AbstractController
{
    public function __construct(
        private readonly PushSubscriptionRepository $repository,
        #[Autowire('%env(WEBPUSH_PUBLIC_KEY)%')]
        private readonly string $vapidPublicKey
    ) {}

    #[Route('/public-key', name: 'push_public_key', methods: ['GET'])]
    public function getPublicKey(): JsonResponse
    {
        return new JsonResponse([
            'publicKey' => $this->vapidPublicKey,
        ]);
    }

    #[Route('/subscribe', name: 'push_subscribe', methods: ['POST'])]
    public function subscribe(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        if (!isset($data['endpoint'])) {
            return new JsonResponse(['error' => 'Invalid subscription'], Response::HTTP_BAD_REQUEST);
        }

        $existing = $this->repository->findByEndpoint($data['endpoint']);
        if ($existing) {
            $existing->setUser($user);
            $this->repository->save($existing, true);
            return new JsonResponse(['message' => 'Already subscribed'], Response::HTTP_OK);
        }

        $subscription = new PushSubscription();
        $subscription->setUser($user);
        $subscription->setEndpoint($data['endpoint']);
        $subscription->setPublicKey($data['keys']['p256dh'] ?? null);
        $subscription->setAuthToken($data['keys']['auth'] ?? null);
        $subscription->setContentEncoding($data['contentEncoding'] ?? 'aes128gcm');

        $this->repository->save($subscription, true);

        return new JsonResponse(['message' => 'Subscription saved'], Response::HTTP_CREATED);
    }
}