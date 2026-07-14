<?php

namespace App\Form;

use App\Entity\category;
use App\Entity\Machine;
use App\Entity\SubCategory;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MachineType extends AbstractType
{
    // Dans src/Form/MachineType.php
public function buildForm(FormBuilderInterface $builder, array $options): void
{
    $builder
        ->add('nom', null, [
            'label' => 'Nom de la machine'
        ])
        ->add('subCategory', EntityType::class, [ // Ou 'category' si tu préfères garder ce lien
            'class' => SubCategory::class,       // Assure-toi que c'est la bonne classe
            'choice_label' => 'nom',             // Affiche le nom au lieu de l'ID
            'label' => 'Sous-catégorie associée',
            'placeholder' => 'Choisir une sous-catégorie...',
            'required' => true,
        ]);
}

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Machine::class,
        ]);
    }
}
