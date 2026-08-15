<?php

namespace App\Form;

use App\Entity\Visite;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
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
            ])
            // 🚀 AJOUT DU CHAMP DE DÉLÉGATION
            ->add('responsableDelegue', EntityType::class, [
                'class' => User::class,
                'query_builder' => function (EntityRepository $er) {
                    // On filtre pour ne proposer que les rôles concernés (DG, Coordonnateur, etc.)
                    return $er->createQueryBuilder('u')
                        ->where('u.roles LIKE :dg OR u.roles LIKE :coordon')
                        ->setParameter('dg', '%ROLE_DG%')
                        ->setParameter('coordon', '%ROLE_COORDON%');
                },
                'choice_label' => 'email', // Ou un autre champ identifiant si tu as un nom/prénom
                'label' => 'Déléguer la visite à un responsable (DG, Coordon...)',
                'required' => false,
                'placeholder' => 'Sélectionner un responsable (Optionnel)',
                'attr' => ['class' => 'form-select']
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
            'with_extra_fields' => true, // Mis à true pour afficher tous les détails par défaut
        ]);
    }
}