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
            ]);

        // ============================================================
        // CHAMP DE DÉLÉGATION (UNIQUEMENT SI L'OPTION EST ACTIVÉE)
        // ============================================================
        if ($options['with_delegation']) {
            $builder->add('responsableDelegue', EntityType::class, [
                'class' => User::class,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('u')
                        ->where('u.roles LIKE :dg OR u.roles LIKE :coordon OR u.roles LIKE :compta')
                        ->setParameter('dg', '%ROLE_DG%')
                        ->setParameter('coordon', '%ROLE_COORDON%')
                        ->setParameter('compta', '%ROLE_COMPTA%');
                },
                'choice_label' => 'email',
                'label' => 'Déléguer la visite à un responsable',
                'required' => false,
                'placeholder' => 'Sélectionner un responsable (Optionnel)',
                'attr' => ['class' => 'form-select', 'id' => 'responsable_delegue']
            ]);
        }

        // ============================================================
        // CHAMPS SUPPLÉMENTAIRES (téléphone, email, pièce d'identité)
        // ============================================================
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
            'with_extra_fields' => false,
            'with_delegation' => false, // ← option ajoutée
        ]);
    }
}