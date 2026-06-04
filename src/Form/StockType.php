<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('article', TextType::class, [
                'label' => 'Nom de l\'article / Consommable',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Papier RAM 80g, Encre Noire...'
                ]
            ])
            ->add('quantite', IntegerType::class, [
                'label' => 'Quantité à ajouter',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // On laisse à null ou on liera à ton entité Stock/Article si tu en as une
            'data_class' => null, 
        ]);
    }
}