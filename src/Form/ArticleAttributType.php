<?php

namespace App\Form;

use App\Entity\ArticleAttribut;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class ArticleAttributType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dimension', TextType::class, [
                'required' => false,
                'attr' => ['placeholder' => 'Ex: 100x200 cm']
            ])
            ->add('grammage', TextType::class, [
                'required' => false,
                'attr' => ['placeholder' => 'Ex: 80g']
            ])
            ->add('couleur', TextType::class, [
                'required' => false,
                'attr' => ['placeholder' => 'Ex: Blanc']
            ])
            ->add('longueur', TextType::class, [
                'required' => false,
                'attr' => ['placeholder' => 'Ex: 50m']
            ])
            ->add('kilogramme', TextType::class, [
                'required' => false,
                'attr' => ['placeholder' => 'Ex: 5kg']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ArticleAttribut::class,
        ]);
    }
}