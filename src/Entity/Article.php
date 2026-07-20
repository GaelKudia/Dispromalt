<?php

namespace App\Entity;

use App\Repository\ArticleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column]
    private ?int $quantite = null;

    #[ORM\Column]
    private ?int $seuilAlerte = null;

    #[ORM\ManyToOne(inversedBy: 'articles')]
    private ?Category $category = null;

    #[ORM\ManyToOne]
    private ?SubCategory $subCategory = null;

    #[ORM\ManyToOne]
    private ?Machine $machine = null;

    // La seule et unique collection pour les attributs
    #[ORM\OneToMany(mappedBy: 'article', targetEntity: ArticleAttribut::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $attributs;

    public function __construct()
    {
        $this->attributs = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }

    public function getQuantite(): ?int { return $this->quantite; }
    public function setQuantite(int $quantite): static { $this->quantite = $quantite; return $this; }

    public function getSeuilAlerte(): ?int { return $this->seuilAlerte; }
    public function setSeuilAlerte(int $seuilAlerte): static { $this->seuilAlerte = $seuilAlerte; return $this; }

    public function getCategory(): ?Category { return $this->category; }
    public function setCategory(?Category $category): static { $this->category = $category; return $this; }

    public function getSubCategory(): ?SubCategory { return $this->subCategory; }
    public function setSubCategory(?SubCategory $subCategory): static { $this->subCategory = $subCategory; return $this; }

    public function getMachine(): ?Machine { return $this->machine; }
    public function setMachine(?Machine $machine): static { $this->machine = $machine; return $this; }

    // Gestion de la collection unique
    /** @return Collection<int, ArticleAttribut> */
    public function getAttributs(): Collection
    {
        return $this->attributs;
    }

    public function addAttribut(ArticleAttribut $attribut): static
    {
        if (!$this->attributs->contains($attribut)) {
            $this->attributs->add($attribut);
            $attribut->setArticle($this);
        }
        return $this;
    }

    public function removeAttribut(ArticleAttribut $attribut): static
    {
        if ($this->attributs->removeElement($attribut)) {
            if ($attribut->getArticle() === $this) {
                $attribut->setArticle(null);
            }
        }
        return $this;
    }
}