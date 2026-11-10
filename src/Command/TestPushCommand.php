<?php

namespace App\Command;

use App\Repository\PushSubscriptionRepository;
use App\Service\WebPushService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:test-push', description: 'Envoie une notification de test à tous les abonnés')]
class TestPushCommand extends Command
{
    public function __construct(
        private readonly PushSubscriptionRepository $repository,
        private readonly WebPushService $webPushService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $subscriptions = $this->repository->findAll();

        if (empty($subscriptions)) {
            $output->writeln('<error>Aucun abonnement trouvé en base de données.</error>');
            return Command::FAILURE;
        }

        $count = 0;
        foreach ($subscriptions as $sub) {
            $user = $sub->getUser();
            if ($user) {
                // On utilise le service qu'on a créé plus tôt
                $sent = $this->webPushService->sendNotification(
                    $user,
                    '🚀 Test de Notification',
                    'Si vous voyez ceci, le système fonctionne parfaitement !',
                    ['url' => '/dashboard']
                );
                $count += $sent;
            }
        }

        $output->writeln("<info>✅ $count notification(s) envoyée(s) avec succès !</info>");
        return Command::SUCCESS;
    }
}
