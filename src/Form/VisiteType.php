<?php

namespace App\Form;

use App\Entity\Visite;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VisiteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nomVisiteur', TextType::class, [
                'attr' => ['class' => 'form-control']
            ])
            ->add('departement', TextType::class, [
                'label' => 'Département à visiter',
                'attr' => ['class' => 'form-control']
            ])
            ->add('motif', TextType::class, [
                'label' => 'Motif de la visite',
                'attr' => ['class' => 'form-control']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // RE-LIÉ : On associe enfin le formulaire à la vraie entité Visite
            'data_class' => Visite::class, 
        ]);
    }
}