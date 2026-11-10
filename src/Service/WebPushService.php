<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\PushSubscriptionRepository;
use Psr\Log\LoggerInterface;
use WebPush\Notification;
use WebPush\WebPush;

class WebPushService
{
    public function __construct(
        private readonly WebPush $webPush,
        private readonly PushSubscriptionRepository $repository,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Envoie une notification push à tous les navigateurs d'un utilisateur.
     *
     * @return int Nombre de notifications envoyées avec succès
     */
    public function sendNotification(User $user, string $title, string $body, array $options = []): int
    {
        $subscriptions = $this->repository->findBy(['user' => $user]);

        if (empty($subscriptions)) {
            return 0;
        }

        $notification = Notification::create()
            ->setTitle($title)
            ->setBody($body);

        if (isset($options['icon'])) {
            $notification = $notification->setIcon($options['icon']);
        }
        if (isset($options['badge'])) {
            $notification = $notification->setBadge($options['badge']);
        }
        if (isset($options['data'])) {
            $notification = $notification->setData($options['data']);
        }

        $sentCount = 0;

        foreach ($subscriptions as $entity) {
            try {
                $report = $this->webPush->send($notification, $entity->toWebPushSubscription());

                if ($report->isSuccess()) {
                    $entity->setLastUsedAt(new \DateTimeImmutable());
                    $sentCount++;
                } elseif ($report->isSubscriptionExpired()) {
                    $this->repository->remove($entity);
                    $this->logger->info('Abonnement push expiré supprimé', ['endpoint' => $entity->getEndpoint()]);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Échec envoi push', [
                    'endpoint' => $entity->getEndpoint(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->repository->getEntityManager()->flush();

        return $sentCount;
    }
}
