<?php

namespace App\Entity;

use App\Repository\MouvementCaisseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MouvementCaisseRepository::class)]
#[ORM\HasLifecycleCallbacks]
class MouvementCaisse
{
    public const TYPE_ENTREE = 'ENTREE';
    public const TYPE_SORTIE = 'SORTIE';

    public const DEVISE_USD = 'USD';
    public const DEVISE_CDF = 'CDF';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10)]
    private ?string $type = null; // ENTREE ou SORTIE

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private ?string $montant = null;

    #[ORM\Column(length: 3)]
    private ?string $devise = self::DEVISE_USD;

    /** Provenance / Motif — ex: "Vente client XYZ", "Subvention DG", "Paiement salaire" */
    #[ORM\Column(length: 255)]
    private ?string $provenance = null;

    /** Référence externe — n° de facture, n° de reçu, etc. */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $reference = null;

    /** Catégorie : VENTE, RECOUVREMENT, SUBVENTION, SALAIRE, DEVIS, FRAIS, AUTRE */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $categorie = null;

    /** Lien optionnel vers un devis (uniquement pour les sorties liées à un devis) */
    #[ORM\ManyToOne(targetEntity: Devis::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Devis $devis = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $effectuePar = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $dateMouvement = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        if (!$this->createdAt)     $this->createdAt = new \DateTime();
        if (!$this->dateMouvement) $this->dateMouvement = new \DateTime();
    }

    // ----- GETTERS / SETTERS -----
    public function getId(): ?int { return $this->id; }

    public function getType(): ?string { return $this->type; }
    public function setType(string $t): static { $this->type = $t; return $this; }

    public function getMontant(): ?string { return $this->montant; }
    public function setMontant(string $m): static { $this->montant = $m; return $this; }

    public function getDevise(): ?string { return $this->devise; }
    public function setDevise(string $d): static { $this->devise = $d; return $this; }

    public function getProvenance(): ?string { return $this->provenance; }
    public function setProvenance(string $p): static { $this->provenance = $p; return $this; }

    public function getReference(): ?string { return $this->reference; }
    public function setReference(?string $r): static { $this->reference = $r; return $this; }

    public function getCategorie(): ?string { return $this->categorie; }
    public function setCategorie(?string $c): static { $this->categorie = $c; return $this; }

    public function getDevis(): ?Devis { return $this->devis; }
    public function setDevis(?Devis $d): static { $this->devis = $d; return $this; }

    public function getEffectuePar(): ?User { return $this->effectuePar; }
    public function setEffectuePar(?User $u): static { $this->effectuePar = $u; return $this; }

    public function getDateMouvement(): ?\DateTimeInterface { return $this->dateMouvement; }

    // ✅ CORRECTION : le paramètre est désormais nullable
    public function setDateMouvement(?\DateTimeInterface $d): static
    {
        $this->dateMouvement = $d;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }

    // ✅ CORRECTION : le paramètre est désormais nullable
    public function setCreatedAt(?\DateTimeInterface $d): static
    {
        $this->createdAt = $d;
        return $this;
    }
}