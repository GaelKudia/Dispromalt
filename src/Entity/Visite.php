<?php

namespace App\Entity;

use App\Repository\VisiteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VisiteRepository::class)]
class Visite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nomVisiteur = null;

    #[ORM\Column(length: 255)]
    private ?string $departement = null;

    #[ORM\Column(length: 255)]
    private ?string $motif = null;

    #[ORM\Column(length: 50)]
    private ?string $statut = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateArrivee = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pieceIdentite = null;

    // ============================================================
    // RELATION : Responsable à qui la visite / RDV est délégué(e)
    // ============================================================
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $responsableDelegue = null;

    // ============================================================
    // ✅ CHAMPS RDV DG / RÉUNION
    // ============================================================

    /**
     * Type d'enregistrement :
     *  - VISITE   : visite classique
     *  - RDV_DG   : rendez-vous pour le DG
     *  - REUNION  : réunion DG ↔ chef de service
     */
    #[ORM\Column(length: 20, options: ['default' => 'VISITE'])]
    private string $type = 'VISITE';

    /** Le RDV est-il destiné au DG lui-même ? */
    #[ORM\Column(options: ['default' => false])]
    private bool $avecDG = false;

    /** Motif de la délégation (si le RDV est délégué) */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $motifDelegation = null;

    // ============================================================
    // GETTERS / SETTERS
    // ============================================================

    public function getId(): ?int { return $this->id; }

    public function getNomVisiteur(): ?string { return $this->nomVisiteur; }
    public function setNomVisiteur(string $nomVisiteur): static { $this->nomVisiteur = $nomVisiteur; return $this; }

    public function getDepartement(): ?string { return $this->departement; }
    public function setDepartement(string $departement): static { $this->departement = $departement; return $this; }

    public function getMotif(): ?string { return $this->motif; }
    public function setMotif(string $motif): static { $this->motif = $motif; return $this; }

    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }

    public function getDateArrivee(): ?\DateTimeInterface { return $this->dateArrivee; }
    public function setDateArrivee(?\DateTimeInterface $dateArrivee): static { $this->dateArrivee = $dateArrivee; return $this; }

    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(?string $telephone): static { $this->telephone = $telephone; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): static { $this->email = $email; return $this; }

    public function getPieceIdentite(): ?string { return $this->pieceIdentite; }
    public function setPieceIdentite(?string $pieceIdentite): static { $this->pieceIdentite = $pieceIdentite; return $this; }

    public function getResponsableDelegue(): ?User { return $this->responsableDelegue; }
    public function setResponsableDelegue(?User $responsableDelegue): static { $this->responsableDelegue = $responsableDelegue; return $this; }

    // ───── Nouveaux champs RDV DG ─────

    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }

    public function isAvecDG(): bool { return $this->avecDG; }
    public function setAvecDG(bool $avecDG): self { $this->avecDG = $avecDG; return $this; }

    public function getMotifDelegation(): ?string { return $this->motifDelegation; }
    public function setMotifDelegation(?string $motifDelegation): self { $this->motifDelegation = $motifDelegation; return $this; }
}