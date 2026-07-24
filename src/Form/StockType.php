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

    use Symfony\Component\Form\Extension\Core\Type\CollectionType; // Importation correcte
use App\Form\ArticleAttributType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class StockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
{
    $builder
        ->add('category', EntityType::class, [
            'class' => Category::class,
            'choice_label' => 'nom',
            'placeholder' => 'Choisir une catégorie',
            'mapped' => false, // Important car category n'est pas dans Article
            'required' => false,
        ])

        ->add('grammage', TextType::class, ['required' => false])
    ->add('longueur', TextType::class, ['required' => false])
    ->add('couleur', TextType::class, ['required' => false])
    ->add('kilogramme', TextType::class, ['required' => false])

        ->add('nom')
        ->add('quantite')
        ->add('seuilAlerte')
        ->add('machine')
        ->add('grammage', TextType::class, ['required' => false])
    ->add('longueur', TextType::class, ['required' => false])
    ->add('couleur', TextType::class, ['required' => false])
    ->add('kilogramme', TextType::class, ['required' => false])

    
        ->add('attributs', CollectionType::class, [
            'entry_type' => ArticleAttributType::class,
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'label' => 'Caractéristiques techniques'
        ]);

    // La logique pour gérer le changement de catégorie
    $formModifier = function (FormInterface $form, Category $category = null) {
        $form->add('subCategory', EntityType::class, [
            'class' => SubCategory::class,
            'choice_label' => 'nom',
            'placeholder' => 'Choisir une sous-catégorie',
            'required' => true,
        ]);
    };

    $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($formModifier) {
        $formModifier($event->getForm(), null);
    });

    $builder->get('category')->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($formModifier) {
        $formModifier($event->getForm()->getParent(), $event->getForm()->getData());
    });
}

   

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Article::class]);
    }
}