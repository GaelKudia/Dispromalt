<?php

$bundles = [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Symfony\UX\StimulusBundle\StimulusBundle::class => ['all' => true],
    Symfony\UX\Turbo\TurboBundle::class => ['all' => true],
    Twig\Extra\TwigExtraBundle\TwigExtraBundle::class => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
    Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
    SpomkyLabs\PwaBundle\SpomkyLabsPwaBundle::class => ['all' => true],
];

// Sécurité Blue Team : On ne charge les bundles de dev QUE s'ils sont installés physiquement
if (class_exists(Symfony\Bundle\DebugBundle\DebugBundle::class)) {
    $bundles[Symfony\Bundle\DebugBundle\DebugBundle::class] = ['dev' => true, 'test' => true];
}

if (class_exists(Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class)) {
    $bundles[Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class] = ['dev' => true, 'test' => true];
}

if (class_exists(Symfony\Bundle\MakerBundle\MakerBundle::class)) {
    $bundles[Symfony\Bundle\MakerBundle\MakerBundle::class] = ['dev' => true, 'test' => true];
}

return $bundles;
