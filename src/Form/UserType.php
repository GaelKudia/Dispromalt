<?php

namespace App\Form;

use App\Entity\User;
use App\Entity\Service;
use Symfony\Component\Form\AbstractType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
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
                'expanded' => false,
                'label' => 'Attribuer un rôle / Service',
                'attr' => ['class' => 'form-control']
            ])
            // Dans UserType.php, dans la méthode buildForm()
  ->add('password', PasswordType::class, [
    'label' => 'Mot de passe provisoire',
    'required' => true,
    'mapped' => true,
    'attr' => [
        'class' => 'form-control',
        'placeholder' => 'Saisissez le mot de passe à communiquer',
        'autocomplete' => 'new-password',
    ],
])
            ->add('service', EntityType::class, [
                'class' => Service::class,
                'choice_label' => 'nom',
                'label' => 'Service d\'appartenance',
                'placeholder' => 'Sélectionner un service',
                'required' => false,
                'attr' => ['class' => 'form-select']
            ])
            ->add('isChefService', CheckboxType::class, [
                'label' => 'Cet utilisateur est-il Chef de Service ?',
                'required' => false,
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
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false, // 👈 Déclaration de l'option pour éviter l'erreur
        ]);
    }
}