<?php

namespace App\Form;

use App\Entity\BonSortie;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BonSortieType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // ✅ Nom de l'agent qui emporte physiquement le matériel
            //    (généralement différent de l'agent d'accueil qui crée le bon)
            ->add('nomEmploye', null, [
                'label'    => 'Nom de l\'agent qui emporte le matériel',
                'required' => true,
                'attr'     => [
                    'class'        => 'form-control',
                    'placeholder'  => 'Nom et prénom de l\'agent porteur',
                    'autocomplete' => 'off',
                ],
            ])

            // ✅ Département réel de l'agent porteur
            ->add('departement', null, [
                'label'    => 'Département de l\'agent',
                'required' => true,
                'attr'     => [
                    'class'        => 'form-control',
                    'placeholder'  => 'Ex : Production, Impression, Logistique…',
                    'autocomplete' => 'off',
                ],
            ])

            // Motif de la sortie
            ->add('raison', null, [
                'label'    => 'Motif / Raison précise de la sortie',
                'required' => false,
                'attr'     => [
                    'class'       => 'form-control',
                    'rows'        => 3,
                    'placeholder' => 'Ex : Maintenance machine externe',
                ],
            ])

            // Type de sortie
            ->add('isAllerSansRetour', ChoiceType::class, [
                'label'   => 'Type de sortie',
                'choices' => [
                    '🔄 Aller-Retour (Sortie temporaire)' => false,
                    '⚠️ Aller simple (Sortie définitive)' => true,
                ],
                'attr' => ['class' => 'form-select'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BonSortie::class,
        ]);
    }
}