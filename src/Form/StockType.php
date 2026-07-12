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

        // Fonction pour ajouter le champ sous-catégorie
        $formModifier = function (FormInterface $form, Category $category = null) {
            $subCategories = null === $category ? [] : $category->getSubCategories();

            $form->add('subCategory', EntityType::class, [
                'class' => SubCategory::class,
                'choices' => $subCategories,
                'choice_label' => 'nom',
                'placeholder' => 'Choisir une sous-catégorie',
                'required' => true,
            ]);
        };

        // Écouteur quand le formulaire est créé
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($formModifier) {
            $data = $event->getData();
            $formModifier($event->getForm(), $data ? $data->getCategory() : null);
        });

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