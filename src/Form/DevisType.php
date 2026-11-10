<?php

namespace App\Form;

use App\Entity\Devis;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Range;

class DevisType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('entete', TextType::class, [
                'label' => 'Objet / En-tête du devis',
                'attr'  => ['placeholder' => 'Ex: Devis de travaux d\'impression'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description du devis',
                'attr'  => ['rows' => 4, 'placeholder' => 'Détaillez votre demande de travaux ou de matériel...'],
            ])
            ->add('montant', NumberType::class, [
                'label' => 'Montant estimé',
                'attr'  => ['placeholder' => '0.00'],
            ])
            // ✅ NOUVEAU : délai en JOURS (non mappé — stocké dans description)
            ->add('delaiJours', IntegerType::class, [
                'label'    => 'Délai de travaux (en jours)',
                'required' => true,
                'mapped'   => false,
                'attr'     => [
                    'placeholder' => 'Ex: 30',
                    'min'         => 1,
                    'max'         => 365,
                    'class'       => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le délai de travaux est obligatoire.']),
                    new Positive(['message' => 'Le délai doit être un nombre positif.']),
                    new Range([
                        'min' => 1,
                        'max' => 365,
                        'notInRangeMessage' => 'Le délai doit être entre {{ min }} et {{ max }} jours.',
                    ]),
                ],
            ])
            ->add('devise', ChoiceType::class, [
                'label'      => 'Devise',
                'choices'    => [
                    'USD ($)' => 'USD',
                    'CDF (FC)' => 'CDF',
                ],
                'empty_data' => 'USD',
                'attr'       => ['class' => 'form-select'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Devis::class,
        ]);
    }
}