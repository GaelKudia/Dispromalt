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
            ->add('nomEmploye', null, [
                'label' => 'Nom de l\'employé concerné',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Jean Dupont']
            ])
            ->add('departement', null, [
                'label' => 'Département de l\'employé',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Impression']
            ])
            ->add('raison', null, [
                'label' => 'Motif / Raison précise de la sortie',
                'attr' => ['class' => 'form-control', 'rows' => 3, 'placeholder' => 'Ex: Maintenance machine externe']
            ])
            ->add('isAllerSansRetour', ChoiceType::class, [
                'label' => 'Type de sortie',
                'choices' => [
                    '🔄 Aller-Retour (Sortie temporaire)' => false,
                    '⚠️ Aller simple (Sortie définitive)' => true,
                ],
                'attr' => ['class' => 'form-select']
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