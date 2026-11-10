<?php

namespace App\Entity;

use App\Repository\ArticleAttributRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArticleAttributRepository::class)]
class ArticleAttribut
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dimension = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $grammage = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $couleur = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $longueur = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $kilogramme = null;

    #[ORM\Column(nullable: true)]
    private ?int $quantite = null;

    #[ORM\ManyToOne(inversedBy: 'attributs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Article $article = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDimension(): ?string
    {
        return $this->dimension;
    }

    public function setDimension(?string $dimension): static
    {
        $this->dimension = $dimension;
        return $this;
    }

    public function getGrammage(): ?string
    {
        return $this->grammage;
    }

    public function setGrammage(?string $grammage): static
    {
        $this->grammage = $grammage;
        return $this;
    }

    public function getCouleur(): ?string
    {
        return $this->couleur;
    }

    public function setCouleur(?string $couleur): static
    {
        $this->couleur = $couleur;
        return $this;
    }

    public function getLongueur(): ?string
    {
        return $this->longueur;
    }

    public function setLongueur(?string $longueur): static
    {
        $this->longueur = $longueur;
        return $this;
    }

    public function getKilogramme(): ?string
    {
        return $this->kilogramme;
    }

    public function setKilogramme(?string $kilogramme): static
    {
        $this->kilogramme = $kilogramme;
        return $this;
    }

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(?int $quantite): static
    {
        $this->quantite = $quantite;
        return $this;
    }

    public function getArticle(): ?Article
    {
        return $this->article;
    }

    public function setArticle(?Article $article): static
    {
        $this->article = $article;
        return $this;
    }

    // ============================================================
    // ✅ MÉTHODES DE REPRÉSENTATION (bonne logique : centralisées)
    // ============================================================

    /**
     * Libellé lisible de la variante, construit dynamiquement.
     * Ex : "Couleur: Bleu - Dimension: 120cm - Grammage: 80g"
     * Retourne "Standard" si tous les champs sont vides.
     */
    public function getValeur(): string
    {
        $details = [];

        if ($this->couleur    !== null && $this->couleur    !== '') $details[] = 'Couleur: '   . $this->couleur;
        if ($this->grammage   !== null && $this->grammage   !== '') $details[] = 'Grammage: '  . $this->grammage;
        if ($this->dimension  !== null && $this->dimension  !== '') $details[] = 'Dimension: ' . $this->dimension;
        if ($this->longueur   !== null && $this->longueur   !== '') $details[] = 'Longueur: '  . $this->longueur;
        if ($this->kilogramme !== null && $this->kilogramme !== '') $details[] = 'Kg: '        . $this->kilogramme;

        return implode(' - ', $details) ?: 'Standard';
    }

    /**
     * Représentation textuelle pour Symfony.
     * - Utilisée automatiquement par EntityType (choice_label par défaut)
     * - Utilisée par les logs / dump()
     * - Utilisée dans les templates Twig ({{ attribut }})
     */
    public function __toString(): string
    {
        return $this->getValeur();
    }
}