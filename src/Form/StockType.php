<?php
namespace App\Form;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\SubCategory;
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
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'nom',
                'placeholder' => 'Choisir une catégorie',
                'mapped' => false, // On gère manuellement
            ]);

            $builder->add('subCategory', EntityType::class, [
    'class' => SubCategory::class,
    'choice_label' => 'nom', // Le nom de la sous-catégorie
    'group_by' => 'category.nom', // C'est ici que la magie opère !
    'placeholder' => 'Choisir une sous-catégorie',
    'required' => true,
]);
        $formModifier = function (FormInterface $form, Category $category = null) {
    // AU LIEU DE FILTRER, on affiche TOUT pour tester
    $subCategories = \App\Entity\SubCategory::class; // Tu peux aussi utiliser $em->getRepository(...)->findAll() si besoin

    $form->add('subCategory', EntityType::class, [
        'class' => \App\Entity\SubCategory::class,
        'choice_label' => 'nom',
        'placeholder' => 'Choisir une sous-catégorie',
        'required' => true,
    ]);
};

        // Écouteur quand l'utilisateur change la catégorie
        $builder->get('category')->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($formModifier) {
            $category = $event->getForm()->getData();
            $formModifier($event->getForm()->getParent(), $category);
        });

        // Ajoute les autres champs (Nom, Quantité, Seuil, Machine)
        $builder->add('nom')->add('quantite')->add('seuilAlerte')->add('machine');
    }

   

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Article::class]);
    }
}