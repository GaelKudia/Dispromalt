<?php
namespace App\Form;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\SubCategory;
use App\Entity\Machine;
use App\Form\ArticleAttributType;

use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // ============================================================
            // 1. CATÉGORIE
            // ============================================================
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'nom',
                'placeholder' => 'Choisir une catégorie',
                'mapped' => true, // Modifié à true car c'est une relation dans l'entité
                'required' => true,
                'attr' => ['class' => 'category-select']
            ])

            // ============================================================
            // 2. SOUS-CATÉGORIE (avec group_by pour l'affichage hiérarchique)
            // ============================================================
            ->add('subCategory', EntityType::class, [
                'class' => SubCategory::class,
                'choice_label' => 'nom',
                'group_by' => 'category.nom', // Groupe par catégorie
                'placeholder' => 'Choisir une sous-catégorie',
                'required' => true,
                'attr' => ['class' => 'sub-category-select']
            ])

            // ============================================================
            // 3. CHAMPS SPÉCIFIQUES (champs dynamiques selon sous-catégorie)
            // ============================================================
          /* ->add('couleur', TextType::class, [  
                'required' => false,
                'label' => 'Couleur',
                'attr' => ['class' => 'media-field', 'placeholder' => 'Ex: Rouge, Bleu...']
            ])
            ->add('longueur', TextType::class, [
                'required' => false,
                'label' => 'Longueur',
                'attr' => ['class' => 'media-field', 'placeholder' => 'Ex: 100cm, 200cm...']
            ])
            ->add('grammage', TextType::class, [
                'required' => false,
                'label' => 'Grammage',
                'attr' => ['class' => 'media-field', 'placeholder' => 'Ex: 80g/m², 120g/m²...']
            ])
            ->add('kilogramme', TextType::class, [
                'required' => false,
                'label' => 'Kilogramme',
                'attr' => ['class' => 'media-field', 'placeholder' => 'Ex: 5kg, 10kg...']
            ]) 

            */
            // ============================================================
            // 4. CHAMPS DE BASE
            // ============================================================
            ->add('nom', TextType::class, [
                'label' => 'Nom de l\'article',
                'attr' => ['placeholder' => 'Ex: Papier A4, Encre noire...']
            ])
            ->add('quantite', NumberType::class, [
                'label' => 'Quantité',
                'attr' => ['min' => 0]
            ])
            ->add('seuilAlerte', NumberType::class, [
                'label' => 'Seuil d\'alerte',
                'attr' => ['min' => 0]
            ])
            ->add('machine', EntityType::class, [
                'class' => Machine::class,
                'choice_label' => 'nom',
                'placeholder' => 'Choisir une machine (optionnel)',
                'required' => false,
                'label' => 'Machine associée'
            ])

            // ============================================================
            // 5. ATTRIBUTS DYNAMIQUES (collection)
            // ============================================================
            ->add('attributs', CollectionType::class, [
                'entry_type' => ArticleAttributType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'label' => 'Attributs personnalisés'
            ]);

        // ============================================================
        // 6. ÉCOUTEUR POUR LE CHANGEMENT DE CATÉGORIE
        // ============================================================
        $builder->get('category')->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                $category = $event->getForm()->getData();
                $this->updateSubCategories($event->getForm()->getParent(), $category);
            }
        );
    }

    /**
     * Met à jour la liste des sous-catégories en fonction de la catégorie sélectionnée
     */
    private function updateSubCategories(FormInterface $form, ?Category $category): void
    {
        $form->add('subCategory', EntityType::class, [
            'class' => SubCategory::class,
            'choice_label' => 'nom',
            'group_by' => 'category.nom',
            'placeholder' => 'Choisir une sous-catégorie',
            'required' => true,
            'choices' => $category ? $category->getSubCategories() : [],
            'attr' => ['class' => 'sub-category-select']
        ]);
    }

    /**
     * Met à jour les champs spécifiques en fonction de la sous-catégorie
     * (à utiliser avec un écouteur supplémentaire si besoin)
     */
    private function updateMediaFields(FormInterface $form, ?SubCategory $subCategory): void
    {
        $mediaFields = ['couleur', 'longueur', 'grammage', 'kilogramme'];
        $showFields = $subCategory && in_array($subCategory->getNom(), ['Papier', 'Film', 'Plaque']);
        
        foreach ($mediaFields as $field) {
            $form->add($field, TextType::class, [
                'required' => $showFields,
                'label' => ucfirst($field),
                'attr' => [
                    'class' => 'media-field',
                    'style' => $showFields ? 'display:block;' : 'display:none;',
                    'placeholder' => 'Ex: ...'
                ]
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Article::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'stock_form'
        ]);
    }
}