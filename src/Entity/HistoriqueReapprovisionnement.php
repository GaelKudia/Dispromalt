<?php

namespace App\Entity;

use App\Repository\HistoriqueReapprovisionnementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HistoriqueReapprovisionnementRepository::class)]
class HistoriqueReapprovisionnement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Article $article = null;

    #[ORM\Column(length: 50)]
    private ?string $typeAction = null; // ALERTE_DECLENCHEE, DATE_MODIFIEE, REAPPRO_LANCE, REAPPRO_TERMINE, ALERTE_LEVEE

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateAction = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $ancienneDateReappro = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $nouvelleDateReappro = null;

    #[ORM\Column(nullable: true)]
    private ?int $quantiteAuMoment = null;

    #[ORM\Column(nullable: true)]
    private ?int $seuilAuMoment = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $effectuePar = null;

    // Getters / Setters ...
    public function getId(): ?int { return $this->id; }
    public function getArticle(): ?Article { return $this->article; }
    public function setArticle(?Article $a): self { $this->article = $a; return $this; }
    public function getTypeAction(): ?string { return $this->typeAction; }
    public function setTypeAction(string $t): self { $this->typeAction = $t; return $this; }
    public function getCommentaire(): ?string { return $this->commentaire; }
    public function setCommentaire(?string $c): self { $this->commentaire = $c; return $this; }
    public function getDateAction(): ?\DateTimeInterface { return $this->dateAction; }
    public function setDateAction(\DateTimeInterface $d): self { $this->dateAction = $d; return $this; }
    public function getAncienneDateReappro(): ?\DateTimeInterface { return $this->ancienneDateReappro; }
    public function setAncienneDateReappro(?\DateTimeInterface $d): self { $this->ancienneDateReappro = $d; return $this; }
    public function getNouvelleDateReappro(): ?\DateTimeInterface { return $this->nouvelleDateReappro; }
    public function setNouvelleDateReappro(?\DateTimeInterface $d): self { $this->nouvelleDateReappro = $d; return $this; }
    public function getQuantiteAuMoment(): ?int { return $this->quantiteAuMoment; }
    public function setQuantiteAuMoment(?int $q): self { $this->quantiteAuMoment = $q; return $this; }
    public function getSeuilAuMoment(): ?int { return $this->seuilAuMoment; }
    public function setSeuilAuMoment(?int $s): self { $this->seuilAuMoment = $s; return $this; }
    public function getEffectuePar(): ?User { return $this->effectuePar; }
    public function setEffectuePar(?User $u): self { $this->effectuePar = $u; return $this; }
}