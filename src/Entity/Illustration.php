<?php

namespace App\Entity;

use App\Repository\IllustrationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=IllustrationRepository::class)
 */
class Illustration
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="blob")  // ✅ Store image as binary data
     */
    private $imageData;

    /**
     * @ORM\ManyToOne(targetEntity=Trick::class, inversedBy="illustrations")
     * @ORM\JoinColumn(nullable=false)
     */
    private $trick;

    public function getBase64Image(): string
    {
        return base64_encode(stream_get_contents($this->imageData));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getImageData(): ?string
    {
        return $this->imageData ? stream_get_contents($this->imageData) : null;
    }    

    public function setImageData($imageData): self
    {
        $this->imageData = $imageData;
        return $this;
    }

    public function getTrick(): ?Trick
    {
        return $this->trick;
    }

    public function setTrick(?Trick $trick): self
    {
        $this->trick = $trick;
        return $this;
    }
}

