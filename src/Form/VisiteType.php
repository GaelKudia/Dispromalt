<?php

namespace App\Form;

use App\Entity\Visite;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VisiteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Champs de base (toujours présents)
        $builder
            ->add('nomVisiteur', TextType::class, [
                'label' => 'Nom du visiteur',
                'attr' => ['placeholder' => 'Ex: Jean Dupont']
            ])
            ->add('departement', TextType::class, [
                'label' => 'Département visité',
                'attr' => ['placeholder' => 'Ex: Production']
            ])
            ->add('motif', TextType::class, [
                'label' => 'Motif de la visite',
                'attr' => ['placeholder' => 'Raison de la visite']
            ]);

        // Champs supplémentaires (si l'option est activée)
        if ($options['with_extra_fields']) {
            $builder
                ->add('telephone', TelType::class, [
                    'label' => 'Téléphone',
                    'required' => false,
                    'attr' => ['placeholder' => 'Numéro de téléphone']
                ])
                ->add('email', EmailType::class, [
                    'label' => 'Email',
                    'required' => false,
                    'attr' => ['placeholder' => 'email@exemple.com']
                ])
                ->add('pieceIdentite', TextType::class, [
                    'label' => "Pièce d'identité",
                    'required' => false,
                    'attr' => ['placeholder' => 'CIN, Passeport...']
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Visite::class,
            'with_extra_fields' => false, // valeur par défaut : champs simples
        ]);
    }
}


//test-pratique //