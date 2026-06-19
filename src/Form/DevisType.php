<?php

namespace App\Form;

use App\Entity\Devis;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DevisType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('entete', TextType::class, [
                'label' => 'Objet / En-tête du devis',
                'attr' => ['placeholder' => 'Ex: Devis de travaux d\'impression']
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description du devis',
                'attr' => ['rows' => 4, 'placeholder' => 'Détaillez votre demande de travaux ou de matériel...']
            ])
            ->add('montant', NumberType::class, [
                'label' => 'Montant estimé',
                'attr' => ['placeholder' => '0.00']
            ])
            ->add('devise', ChoiceType::class, [
                'label' => 'Devise',
                'choices'  => [
                    'USD ($)' => 'USD',
                    'CDF (FC)' => 'CDF',
                ],
                'empty_data' => 'USD', // 👈 Sécurité d'intégrité ajoutée ici pour la BDD
                'attr' => ['class' => 'form-select']
            ])
        ;
    }

    #[ORM\Column(length: 3)]
private ?string $devise = 'USD'; // 👈 On initialise la valeur par défaut ici

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Devis::class,
        ]);
    }
}