<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'attr' => ['class' => 'form-control', 'placeholder' => 'Adresse email']
            ])
            ->add('roles', ChoiceType::class, [
                'choices' => [
                    'Direction Générale (DG / Directeur)' => 'ROLE_DG',
                    'Coordonnateur' => 'ROLE_COORDON',
                    'Guérite / Sécurité' => 'ROLE_GUERITE',
                    'Comptabilité' => 'ROLE_COMPTA',
                    'Gérant de Stock' => 'ROLE_STOCK',
                    'Utilisateur Standard' => 'ROLE_USER',
                    'Administrateur' => 'ROLE_ADMIN',
                ],
                'multiple' => true,
                'expanded' => false, // Garde un menu déroulant, mais comme multiple = true, l'admin pourra maintenir Ctrl pour sélectionner
                'label' => 'Attribuer un rôle / Service', // 👈 LA VIRGULE CORRIGÉE ICI !
                'attr' => ['class' => 'form-control']
            ])
            ->add('password', PasswordType::class, [
                'attr' => ['class' => 'form-control', 'placeholder' => 'Mot de passe temporaire']
            ])
        ->add('localisation', ChoiceType::class, [
    'choices'  => [
        'Agent de la Direction (Par défaut)' => null,
        'Site Sedwe' => 'sedwe',
        'Site Bonagère' => 'bonagere',
    ],
    'required' => false,
    'attr' => ['class' => 'form-select']
]);
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}