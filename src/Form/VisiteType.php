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
                'attr'  => ['placeholder' => 'Ex: Jean Dupont'],
            ])
            ->add('departement', TextType::class, [
                'label' => 'Département visité',
                'attr'  => ['placeholder' => 'Ex: Production'],
            ])
            ->add('motif', TextType::class, [
                'label' => 'Motif de la visite',
                'attr'  => ['placeholder' => 'Raison de la visite'],
            ]);

        // ============================================================
        // CHAMPS SUPPLÉMENTAIRES (téléphone, email, pièce d'identité)
        // ============================================================
        if ($options['with_extra_fields']) {
            $builder
                ->add('telephone', TelType::class, [
                    'label'    => 'Téléphone',
                    'required' => false,
                    'attr'     => ['placeholder' => 'Numéro de téléphone'],
                ])
                ->add('email', EmailType::class, [
                    'label'    => 'Email',
                    'required' => false,
                    'attr'     => ['placeholder' => 'email@exemple.com'],
                ])
                ->add('pieceIdentite', TextType::class, [
                    'label'    => "Pièce d'identité",
                    'required' => false,
                    'attr'     => ['placeholder' => 'CIN, Passeport...'],
                ]);
        }

        // ============================================================
        // CHAMP DÉLÉGATION (uniquement si activé ET rôles autorisés définis)
        // Seuls les comptes ayant les rôles cibles apparaissent dans la liste.
        // ============================================================
        if ($options['with_delegation'] && !empty($options['delegation_target_roles'])) {
            $targetRoles = $options['delegation_target_roles'];

            $builder->add('responsableDelegue', EntityType::class, [
                'class'         => User::class,
                'label'         => 'Déléguer la visite à un responsable',
                'required'      => false,
                'placeholder'   => '— Sélectionner un responsable —',
                'choice_label'  => function (User $user) {
                    return $user->getEmail(); // adaptez si vous avez un getNomComplet()
                },
                'query_builder' => function (EntityRepository $er) use ($targetRoles) {
                    $qb = $er->createQueryBuilder('u');

                    // Filtre : l'utilisateur doit avoir AU MOINS UN des rôles cibles
                    // et doit être actif
                    $orConditions = [];
                    foreach ($targetRoles as $index => $role) {
                        $orConditions[] = "u.roles LIKE :role_$index";
                        $qb->setParameter("role_$index", '%' . $role . '%');
                    }
                    $qb->andWhere(implode(' OR ', $orConditions));

                    // Optionnel : exclure les comptes inactifs
                    // (décommentez si vous avez le champ isActif)
                    // $qb->andWhere('u.isActif = :actif')->setParameter('actif', true);

                    return $qb->orderBy('u.email', 'ASC');
                },
                'attr' => ['class' => 'form-select'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'             => Visite::class,
            'with_extra_fields'      => false,
            'with_delegation'        => false,

            // 👇 Liste des rôles éligibles à recevoir une délégation.
            //    Modifiez cette liste selon VOTRE logique métier.
            'delegation_target_roles' => [
                'ROLE_DG',
                'ROLE_COORDON',
                'ROLE_CHEF_COMPTA',
            ],
        ]);

        $resolver->setAllowedTypes('with_extra_fields', 'bool');
        $resolver->setAllowedTypes('with_delegation', 'bool');
        $resolver->setAllowedTypes('delegation_target_roles', 'array');
    }
}