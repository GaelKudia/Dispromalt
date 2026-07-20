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

    #[ORM\Column(length: 255)]
    private ?string $dimension = null;

    #[ORM\Column(length: 255)]
    private ?string $grammage = null;

    // Correspond au 'attributs' dans l'entité Article
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

    public function setDimension(string $dimension): static
    {
        $this->dimension = $dimension;
        return $this;
    }

    public function getGrammage(): ?string
    {
        return $this->grammage;
    }

    public function setGrammage(string $grammage): static
    {
        $this->grammage = $grammage;
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

    #[ORM\Column(length: 255, nullable: true)]
private ?string $couleur = null;

#[ORM\Column(length: 255, nullable: true)]
private ?string $longueur = null;

#[ORM\Column(length: 255, nullable: true)]
private ?string $kilogramme = null;


}