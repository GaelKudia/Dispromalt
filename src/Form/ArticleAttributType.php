<?php

namespace App\Form;

use App\Entity\ArticleAttribut;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
// N'oublie surtout pas ces deux lignes pour utiliser les champs TextType :
use Symfony\Component\Form\Extension\Core\Type\TextType;

class ArticleAttributType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dimension', TextType::class)
            ->add('grammage', TextType::class)
            ->add('couleur', TextType::class, ['required' => false])
            ->add('longueur', TextType::class, ['required' => false])
            ->add('kilogramme', TextType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ArticleAttribut::class,
        ]);
    }
}