<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=UserRepository::class)
 * @ORM\HasLifecycleCallbacks
 */
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=50, unique=true)
     */
    private $username;

    /**
     * @ORM\Column(type="string", length=100, unique=true)
     */
    private $email;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $password;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $fullName;

    /**
     * @ORM\Column(type="blob", nullable=true)
     */
    private $photo;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $photoMime;

    /**
     * @ORM\Column(type="boolean")
     */
    private $isActive = false;

    /**
     * @ORM\Column(type="datetime_immutable")
     */
    private $createdAt;

    /**
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    private $updatedAt;

    /**
     * @ORM\OneToMany(targetEntity=Message::class, mappedBy="user", cascade={"remove"})
     */
    private Collection $messages;

    /**
     * @ORM\OneToMany(targetEntity=Token::class, mappedBy="user", cascade={"remove"})
     */
    private Collection $tokens;

    public function getUserIdentifier(): string
    {
        return $this->email; // Or use $this->username if you prefer
    }

    public function getRoles(): array
    {
        return ['ROLE_USER']; // Ensure all users have at least this role
    }

    public function eraseCredentials(): void
    {
        // Implement this if you store temporary sensitive data on the user object
    }

    public function getSalt(): ?string
    {
        // Modern password hashing algorithms include the salt internally
        return null;
    }

    public function __construct()
    {
        $this->messages = new ArrayCollection();
        $this->tokens = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(?string $fullName): self
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getPhotoMime(): ?string
    {
        return $this->photoMime;
    }

    public function setPhotoMime(?string $photoMime): self
    {
        $this->photoMime = $photoMime;
        return $this;
    }


    public function getPhoto(): ?string
    {
        if ($this->photo === null) {
            return null;
        }
        // Check if photo is a resource and its type is "stream"
        if (is_resource($this->photo) && get_resource_type($this->photo) === 'stream') {
            return stream_get_contents($this->photo);
        }
        // Otherwise, assume it's already a string (even if stored as blob)
        return (string) $this->photo;
    }    
     
    public function getPhotoAsString(): ?string
{
    if ($this->photo === null) {
        return null;
    }
    if (is_resource($this->photo)) {
        rewind($this->photo);
        $contents = stream_get_contents($this->photo);
        // Save the converted string so subsequent calls are safe
        $this->photo = $contents;
        return $contents;
    }
    // Force any non-null value to a string
    return (string) $this->photo;
}


    public function setPhoto(?string $photo): self
    {
        $this->photo = $photo;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @ORM\PrePersist
     */
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @ORM\PreUpdate
     */
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Message $message): self
    {
        if (!$this->messages->contains($message)) {
            $this->messages[] = $message;
            $message->setUser($this);
        }

        return $this;
    }

    public function removeMessage(Message $message): self
    {
        if ($this->messages->removeElement($message)) {
            if ($message->getUser() === $this) {
                $message->setUser(null);
            }
        }

        return $this;
    }

    public function getTokens(): Collection
    {
        return $this->tokens;
    }

    public function addToken(Token $token): self
    {
        if (!$this->tokens->contains($token)) {
            $this->tokens[] = $token;
            $token->setUser($this);
        }

        return $this;
    }

    public function removeToken(Token $token): self
    {
        if ($this->tokens->removeElement($token)) {
            if ($token->getUser() === $this) {
                $token->setUser(null);
            }
        }

        return $this;
    }
}
