<?php

namespace App\Entity;

use App\Repository\SubCategoryRepository;
use Doctrine\ORM\Mapping as ORM;


use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: SubCategoryRepository::class)]
class SubCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\ManyToOne(inversedBy: 'subCategories')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Category $category = null;

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

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    #[ORM\OneToMany(mappedBy: 'subCategory', targetEntity: Machine::class)]
private Collection $machines;

public function __construct()
{
    $this->machines = new ArrayCollection();
}

/**
 * @return Collection<int, Machine>
 */
public function getMachines(): Collection
{
    return $this->machines;
}

public function addMachine(Machine $machine): static
{
    if (!$this->machines->contains($machine)) {
        $this->machines->add($machine);
        $machine->setSubCategory($this);
    }
    return $this;
}

public function removeMachine(Machine $machine): static
{
    if ($this->machines->removeElement($machine)) {
        // set the owning side to null (unless already changed)
        if ($machine->getSubCategory() === $this) {
            $machine->setSubCategory(null);
        }
    }
    return $this;
}
}
