<?php

namespace App\Form;

use App\Entity\Visite;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VisiteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nomVisiteur', TextType::class, [
                'label' => 'Nom du visiteur',
                'attr' => ['placeholder' => 'Nom complet']
            ])
            ->add('departement', TextType::class, [
                'label' => 'Département visité',
                'attr' => ['placeholder' => 'Ex: Production, Compta...']
            ])
            ->add('motif', TextType::class, [
                'label' => 'Motif de la visite',
                'attr' => ['placeholder' => 'Raison de la visite']
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => ['placeholder' => 'Numéro de téléphone']
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'attr' => ['placeholder' => 'email@example.com']
            ])
            ->add('pieceIdentite', TextType::class, [
                'label' => 'Pièce d\'identité',
                'required' => false,
                'attr' => ['placeholder' => 'CIN, Passeport...']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Visite::class,
            'csrf_protection' => true,
        ]);
    }
}