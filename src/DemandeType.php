<?php

namespace App\Form;

use App\Entity\Demande;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DemandeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('materiel', TextType::class, [
                'label' => 'Article / Matériel demandé',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Rames de papier A4, Gilets, etc.']
            ])
            ->add('quantite', IntegerType::class, [
                'label' => 'Quantité demandée',
                'help' => 'Pour les gilets : spécifiez le nombre de gilets individuels (Ex: 25 pour 1 paquet, 50 pour 2 paquets).',
                'attr' => ['class' => 'form-control', 'min' => 1]
            ])
            // 🗺️ Nouveau champ : Liste des communes de Kinshasa pour la destination
            ->add('destination', ChoiceType::class, [
                'label' => 'Commune de destination',
                'placeholder' => '-- Sélectionnez la commune de destination --',
                'choices' => [
                    'Bandalungwa' => 'Bandalungwa',
                    'Barumbu' => 'Barumbu',
                    'Bumbu' => 'Bumbu',
                    'Gombe' => 'Gombe',
                    'Kalamu' => 'Kalamu',
                    'Kasa-Vubu' => 'Kasa-Vubu',
                    'Kimbanseke' => 'Kimbanseke',
                    'Kinshasa' => 'Kinshasa',
                    'Kintambo' => 'Kintambo',
                    'Kisenso' => 'Kisenso',
                    'Lemba' => 'Lemba',
                    'Limete' => 'Limete',
                    'Lingwala' => 'Lingwala',
                    'Makala' => 'Makala',
                    'Maluku' => 'Maluku',
                    'Masina' => 'Masina',
                    'Matete' => 'Matete',
                    'Mont-Ngafula' => 'Mont-Ngafula',
                    'Ndjili' => 'Ndjili',
                    'Ngaba' => 'Ngaba',
                    'Ngaliema' => 'Ngaliema',
                    'Ngiri-Ngiri' => 'Ngiri-Ngiri',
                    'Nsele' => 'Nsele',
                    'Selembao' => 'Selembao',
                ],
                'attr' => ['class' => 'form-select']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Demande::class,
        ]);
    }
}