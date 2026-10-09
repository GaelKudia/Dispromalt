<?php

namespace App\Form;

use App\Entity\MouvementCaisse;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MouvementCaisseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('montant', NumberType::class, [
                'label' => 'Montant',
                'scale' => 2,
            ])
            ->add('devise', ChoiceType::class, [
                'label' => 'Devise',
                'choices' => [
                    'USD' => 'USD',
                    'CDF' => 'CDF',
                ],
            ])
            ->add('categorie', TextType::class, [
                'label' => 'Catégorie',
                'required' => false,
            ])
            ->add('provenance', TextType::class, [
                'label' => 'Provenance',
            ])
            ->add('dateMouvement', DateType::class, [
                'label' => 'Date',
                'widget' => 'single_text',
            ])

            ->add('reference', TextType::class, [
    'label' => 'Référence',
    'required' => false,
])
->add('categorie', TextType::class, [
    'label' => 'Catégorie',
    'required' => false,
])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MouvementCaisse::class,
            'is_entree'  => false,
        ]);
    }
}