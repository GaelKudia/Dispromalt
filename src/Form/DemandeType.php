<?php

namespace App\Form;

use App\Entity\Demande;
use App\Entity\ArticleAttribut;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
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
            // On remplace le texte libre par la sélection directe de l'attribut/article
           ->add('materiel', TextType::class, [
                'label' => 'Article / Matériel demandé',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Polyester, Rames de papier...']
            ])
            // Champ pour la variante (qui s'affichera dynamiquement s'il y a des choix)
            ->add('attribut', EntityType::class, [
                'class' => ArticleAttribut::class,
                'required' => false,
                'placeholder' => '-- Choisissez la variante / couleur --',
                'choices' => [], // Sera rempli ou géré dynamiquement par le JS
                'attr' => ['class' => 'form-select d-none', 'id' => 'select-attribut-container'] 
            ])
            ->add('quantite', IntegerType::class, [
                'label' => 'Quantité demandée',
                'attr' => ['class' => 'form-control', 'min' => 1]
            ])
            ->add('destination', ChoiceType::class, [
                'label' => 'Commune de destination',
                'placeholder' => '-- Sélectionnez la commune de destination --',
                'choices' => [
                    'Bandalungwa' => 'Bandalungwa', 'Barumbu' => 'Barumbu', 'Bumbu' => 'Bumbu',
                    'Gombe' => 'Gombe', 'Kalamu' => 'Kalamu', 'Kasa-Vubu' => 'Kasa-Vubu',
                    'Kimbanseke' => 'Kimbanseke', 'Kinshasa' => 'Kinshasa', 'Kintambo' => 'Kintambo',
                    'Kisenso' => 'Kisenso', 'Lemba' => 'Lemba', 'Limete' => 'Limete',
                    'Lingwala' => 'Lingwala', 'Makala' => 'Makala', 'Maluku' => 'Maluku',
                    'Masina' => 'Masina', 'Matete' => 'Matete', 'Mont-Ngafula' => 'Mont-Ngafula',
                    'Ndjili' => 'Ndjili', 'Ngaba' => 'Ngaba', 'Ngaliema' => 'Ngaliema',
                    'Ngiri-Ngiri' => 'Ngiri-Ngiri', 'Nsele' => 'Nsele', 'Selembao' => 'Selembao',
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