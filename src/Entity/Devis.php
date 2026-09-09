<?php

namespace App\Entity;

use App\Repository\DevisRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DevisRepository::class)]
class Devis
{
    // Définition des étapes du workflow
    public const STATUT_ATTENTE_CHEF = 'ATTENTE_CHEF_COMPTA';
    public const STATUT_ATTENTE_DIR  = 'ATTENTE_DIRECTEUR';
    public const STATUT_ATTENTE_CAISSE = 'ATTENTE_CAISSE';
    public const STATUT_DECAISSE     = 'DECAISSE';
    public const STATUT_REJETE       = 'REJETE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $description = null;

    #[ORM\Column]
    private ?float $montant = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $montantInitial = null;

    #[ORM\Column]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(length: 255)]
    private ?string $createdBy = null;

    #[ORM\Column(length: 255)]
    private ?string $entete = null;

    #[ORM\Column(length: 50)]
    private ?string $statut = null;

    #[ORM\Column(length: 3)]
    private ?string $devise = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $dateDecaissement = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateLimite = null;

    // ============================================================
    // RELATION VERS L'UTILISATEUR (UNIQUE DÉCLARATION)
    // ============================================================
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    // ============================================================
    // CONSTRUCTEUR
    // ============================================================
    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->statut = self::STATUT_ATTENTE_CHEF;
    }

    // ============================================================
    // GETTERS / SETTERS
    // ============================================================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getMontant(): ?float
    {
        return $this->montant;
    }

    public function setMontant(float $montant): static
    {
        $this->montant = $montant;
        return $this;
    }

    public function getMontantInitial(): ?string
    {
        return $this->montantInitial;
    }

    public function setMontantInitial(?string $montantInitial): self
    {
        $this->montantInitial = $montantInitial;
        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(string $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getEntete(): ?string
    {
        return $this->entete;
    }

    public function setEntete(string $entete): static
    {
        $this->entete = $entete;
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

    public function getDevise(): ?string
    {
        return $this->devise;
    }

    public function setDevise(string $devise): static
    {
        $this->devise = $devise;
        return $this;
    }

    public function getDateDecaissement(): ?\DateTime
    {
        return $this->dateDecaissement;
    }

    public function setDateDecaissement(?\DateTime $dateDecaissement): static
    {
        $this->dateDecaissement = $dateDecaissement;
        return $this;
    }

    public function getDateLimite(): ?\DateTimeInterface
    {
        return $this->dateLimite;
    }

    public function setDateLimite(?\DateTimeInterface $dateLimite): static
    {
        $this->dateLimite = $dateLimite;
        return $this;
    }

    // ============================================================
    // GETTER / SETTER POUR LA RELATION USER (UNIQUE)
    // ============================================================
    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }
}