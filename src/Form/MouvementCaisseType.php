<?php

namespace App\Form;

use App\Entity\MouvementCaisse;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MouvementCaisseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('montant', NumberType::class, [
                'label' => 'Montant',
                'scale' => 2,
                'attr'  => ['class' => 'form-control', 'step' => '0.01', 'min' => '0.01', 'placeholder' => '0.00'],
            ])
            ->add('devise', ChoiceType::class, [
                'label'   => 'Devise',
                'choices' => [
                    'USD ($)' => MouvementCaisse::DEVISE_USD,
                    'CDF (FC)' => MouvementCaisse::DEVISE_CDF,
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('provenance', TextType::class, [
                'label' => 'Libellé / Provenance',
                'attr'  => ['class' => 'form-control', 'placeholder' => 'Ex : Vente client XYZ, Subvention DG…'],
            ])
            ->add('reference', TextType::class, [
                'label'    => 'Référence (n° facture, reçu…)',
                'required' => false,
                'attr'     => ['class' => 'form-control', 'placeholder' => 'Optionnel'],
            ])
            ->add('categorie', ChoiceType::class, [
                'label'   => 'Catégorie',
                'choices' => [
                    'Vente'         => 'VENTE',
                    'Recouvrement'  => 'RECOUVREMENT',
                    'Subvention'    => 'SUBVENTION',
                    'Autre'         => 'AUTRE',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('dateMouvement', DateType::class, [
                'label'  => 'Date du mouvement',
                'widget' => 'single_text',
                'data'   => new \DateTime(),
                'attr'   => ['class' => 'form-control'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MouvementCaisse::class,
        ]);
    }
}