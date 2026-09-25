<?php

namespace App\Entity;

use App\Repository\DemandeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DemandeRepository::class)]
class Demande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $departement = null;

    #[ORM\Column(length: 255)]
    private ?string $materiel = null;

    #[ORM\Column]
    private ?int $quantite = null;

    #[ORM\Column(length: 50)]
    private ?string $statut = null;

    // 🗺️ Nouvelle colonne pour stocker la commune de destination à Kinshasa
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $destination = null;

    // 📅 Nouvelle colonne pour enregistrer la date exacte (Crucial pour tes filtres Jour J et Rapports X à Y)
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $localisation = null;

    // ⚙️ Le constructeur initialise automatiquement la date au moment de la demande
    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDepartement(): ?string
    {
        return $this->departement;
    }

    public function setDepartement(string $departement): static
    {
        $this->departement = $departement;

        return $this;
    }

    public function getMateriel(): ?string
    {
        return $this->materiel;
    }

    public function setMateriel(string $materiel): static
    {
        $this->materiel = $materiel;

        return $this;
    }

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): static
    {
        $this->quantite = $quantite;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    // 🟢 Getter et Setter pour la Destination (Commune)
    public function getDestination(): ?string
    {
        return $this->destination;
    }

    public function setDestination(?string $destination): static
    {
        $this->destination = $destination;

        return $this;
    }

    // 🟢 Getter et Setter pour la Date de création
    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getLocalisation(): ?string
    {
        return $this->localisation;
    }

    public function setLocalisation(?string $localisation): static
    {
        $this->localisation = $localisation;

        return $this;
    }

    // Dans ton Demande.php
#[ORM\ManyToOne(targetEntity: Article::class)]
private ?Article $article = null;

#[ORM\ManyToOne(targetEntity: User::class)]
private ?User $demandeur = null; // C'est ici que tu récupères automatiquement le site

#[ORM\ManyToOne(targetEntity: ArticleAttribut::class)]
private ?ArticleAttribut $attribut = null;

// N'oublie pas les getters et setters associés :
public function getAttribut(): ?ArticleAttribut { return $this->attribut; }
public function setAttribut(?ArticleAttribut $attribut): self { 
    $this->attribut = $attribut; 
    // Si tu as un champ 'materiel' textuel dans Demande, tu peux aussi le remplir automatiquement ici :
    if ($attribut && $attribut->getArticle()) {
        $this->materiel = $attribut->getArticle()->getNom();
    }
    return $this; 
}

    // ============================================================
    // GETTER / SETTER pour la relation Demandeur
    // ============================================================
    public function getDemandeur(): ?User
    {
        return $this->demandeur;
    }

    public function setDemandeur(?User $demandeur): static
    {
        $this->demandeur = $demandeur;
        return $this;
    }

    // ============================================================
    // GETTER / SETTER pour la relation Article
    // ============================================================
    public function getArticle(): ?Article
    {
        return $this->article;
    }

    public function setArticle(?Article $article): static
    {
        $this->article = $article;
        return $this;
    }

}