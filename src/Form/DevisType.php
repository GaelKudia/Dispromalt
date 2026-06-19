<?php

namespace App\Form;

use App\Entity\Devis;
use Symfony\Component\Form\AbstractType; // 👈 1. Le bon import est ici
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class DevisType extends AbstractType // 👈 2. On change "AbstractFormType" par "AbstractType"
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder

        ->add('entete')
        ->add('description')
        ->add('montant')
        ->add('devise', ChoiceType::class, [
            'choices'  => [
                'USD ($)' => 'USD',
                'CDF (FC)' => 'CDF',
            ],
            'attr' => ['class' => 'form-select']
        ])
            
            ->add('description', TextareaType::class, [
                'label' => 'Description du devis',
                'attr' => ['rows' => 4, 'placeholder' => 'Détaillez votre demande de travaux ou de matériel...']
            ])
            ->add('montant', NumberType::class, [
                'label' => 'Montant estimé (€)',
                'attr' => ['placeholder' => '0.00']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Devis::class,
        ]);
    }
}