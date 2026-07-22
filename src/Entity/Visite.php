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

    // ============================================================
    // NOUVEAU CHAMP : DATE D'ARRIVÉE
    // ============================================================
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateArrivee = null;

    // ============================================================
    // CHAMPS OPTIONNELS (pour compléter le formulaire)
    // ============================================================
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pieceIdentite = null;

    // ============================================================
    // GETTERS ET SETTERS
    // ============================================================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomVisiteur(): ?string
    {
        return $this->nomVisiteur;
    }

    public function setNomVisiteur(string $nomVisiteur): static
    {
        $this->nomVisiteur = $nomVisiteur;
        return $this;
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

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    public function setMotif(string $motif): static
    {
        $this->motif = $motif;
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

    // ============================================================
    // GETTERS ET SETTERS POUR dateArrivee
    // ============================================================
    public function getDateArrivee(): ?\DateTimeInterface
    {
        return $this->dateArrivee;
    }

    public function setDateArrivee(?\DateTimeInterface $dateArrivee): static
    {
        $this->dateArrivee = $dateArrivee;
        return $this;
    }

    // ============================================================
    // GETTERS ET SETTERS POUR telephone
    // ============================================================
    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;
        return $this;
    }

    // ============================================================
    // GETTERS ET SETTERS POUR email
    // ============================================================
    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
    }

    // ============================================================
    // GETTERS ET SETTERS POUR pieceIdentite
    // ============================================================
    public function getPieceIdentite(): ?string
    {
        return $this->pieceIdentite;
    }

    public function setPieceIdentite(?string $pieceIdentite): static
    {
        $this->pieceIdentite = $pieceIdentite;
        return $this;
    }
}