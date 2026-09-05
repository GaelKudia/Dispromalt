<?php

namespace App\Entity;
use App\Entity\SubCategory;

use App\Repository\MachineRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MachineRepository::class)]
class Machine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\ManyToOne(inversedBy: 'machines')]
    #[ORM\JoinColumn(nullable: false)]
    private ?category $category = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getCategory(): ?category
    {
        return $this->category;
    }

    public function setCategory(?category $category): static
    {
        $this->category = $category;

        return $this;
    }


    // ... et ajoute les getters et setters :

  /*  public function getSubCategory(): ?SubCategory
    {
        return $this->subCategory;
    }

    public function setSubCategory(?SubCategory $subCategory): static
    {
        $this->subCategory = $subCategory;

        return $this;
    }
    */
        // Dans src/Entity/Machine.php

// ...

    #[ORM\ManyToOne(inversedBy: 'machines')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SubCategory $subCategory = null;

    // Décommente cette méthode ci-dessous pour que Symfony puisse lire la valeur
    public function getSubCategory(): ?SubCategory
    {
        return $this->subCategory;
    }

    // Garde celle-ci (que tu avais déjà modifiée avec la logique automatique)
    public function setSubCategory(?SubCategory $subCategory): static
    {
        $this->subCategory = $subCategory;

        if ($subCategory !== null) {
            $this->setCategory($subCategory->getCategory());
        }

        return $this;
    }

    public function __toString(): string
{
    // Cela indique à Symfony d'utiliser le nom de la machine 
    // quand il a besoin de convertir l'objet en texte.
    return $this->getNom() ?? 'Machine sans nom';
}
}
