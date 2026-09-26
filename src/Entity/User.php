<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $localisation = null;

    #[ORM\Column(type: 'boolean')]
    private bool $actif = true;

    #[ORM\ManyToOne(inversedBy: 'users')]
    private ?Service $service = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isChefService = false;

    // ============================================================
    // RELATION INVERSE : Devis créés par cet utilisateur
    // ------------------------------------------------------------
    // Note : la suppression de l'utilisateur met user_id à NULL
    // dans les devis (onDelete: 'SET NULL' côté Devis).
    // On conserve donc createdBy pour la traçabilité.
    // ============================================================
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Devis::class)]
    private Collection $devis;

    public function __construct()
    {
        $this->devis = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0" . self::class . "\0password"] = hash('crc32c', $this->password);
        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
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

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): self
    {
        $this->actif = $actif;
        return $this;
    }

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;
        return $this;
    }

    public function isChefService(): bool
    {
        return $this->isChefService;
    }

    public function setIsChefService(bool $isChefService): static
    {
        $this->isChefService = $isChefService;
        return $this;
    }

    // ============================================================
    // COLLECTION DEVIS
    // ============================================================
    /**
     * @return Collection<int, Devis>
     */
    public function getDevis(): Collection
    {
        return $this->devis;
    }

    public function addDevis(Devis $devi): static
    {
        if (!$this->devis->contains($devi)) {
            $this->devis->add($devi);
            $devi->setUser($this);
        }
        return $this;
    }

    public function removeDevis(Devis $devi): static
    {
        if ($this->devis->removeElement($devi)) {
            if ($devi->getUser() === $this) {
                $devi->setUser(null);
            }
        }
        return $this;
    }

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
private bool $isChefComptable = false;

public function isChefComptable(): bool { return $this->isChefComptable; }

public function setIsChefComptable(bool $isChefComptable): static {
    $this->isChefComptable = $isChefComptable;
    return $this;
}
}