<?php

namespace App\Form;

use App\Entity\Service;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];

        $builder
            ->add('email', EmailType::class, [
                'label' => 'Adresse Email'
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Rôle Assigné',
                'choices' => [
                    'Opérationnel'             => 'ROLE_USER',
                    'Magasinier'               => 'ROLE_STOCK',
                    'Coordonnateur'            => 'ROLE_COORDON',
                    'Comptabilité'             => 'ROLE_COMPTA',
                    'Chef Comptable'           => 'ROLE_CHEF_COMPTA',           // ✅ ajouté
                    'Adjoint Chef Comptable'   => 'ROLE_ADJOINT_CHEF_COMPTA',   // ✅ ajouté
                    'Réception'                => 'ROLE_RECEPTION',
                    'Agent Guérite'            => 'ROLE_GUERITE',
                    'Caissier'                 => 'ROLE_CAISSIER',
                    'Directeur Général'        => 'ROLE_DG',
                    'Administrateur'           => 'ROLE_ADMIN',
                ],
                'multiple' => false,
                'expanded' => false,
                'mapped'   => false,
            ])
            ->add('service', EntityType::class, [
                'class'        => Service::class,
                'choice_label' => 'nom',
                'placeholder'  => '-- Aucun service --',
                'required'     => false,
            ])
            ->add('localisation', ChoiceType::class, [
                'label'       => 'Localisation géographique',
                'choices'     => [
                    'Direction (Siège)' => 'direction',
                    'Sedwe'             => 'sedwe',
                    'Bonagère'          => 'bonagere',
                ],
                'required'    => false,
                'placeholder' => 'Direction (par défaut)',
                'mapped'      => true,
            ])
            ->add('isChefService', CheckboxType::class, [
                'label'    => 'Nommer comme Chef de service',
                'required' => false,
            ])
            ->add('password', PasswordType::class, [
                'label'    => 'Changer le mot de passe',
                'required' => !$isEdit,
                'mapped'   => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit'    => false,
        ]);
    }
}