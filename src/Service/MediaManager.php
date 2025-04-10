<?php
// src/Service/MediaManager.php

namespace App\Service;

use App\Entity\Illustration;
use App\Entity\Video;
use App\Entity\Trick;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MediaManager
{
    private $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
         $this->em = $entityManager;
    }

    public function addImage(Trick $trick, UploadedFile $image): void
    {
         $imageData = file_get_contents($image->getPathname());
         $illustration = new Illustration();
         $illustration->setImageData($imageData);
         $illustration->setTrick($trick);
         $this->em->persist($illustration);
         $this->em->flush();
    }

    public function addVideo(Trick $trick, string $embedUrl): void
    {
         $video = new Video();
         $video->setEmbedCode($embedUrl);
         $video->setTrick($trick);
         $this->em->persist($video);
         $this->em->flush();
    }

    public function deleteMedia($media): void
    {
         $this->em->remove($media);
         $this->em->flush();
    }

    public function updateImage(Illustration $image, UploadedFile $uploadedFile): void
    {
         $image->setImageData(file_get_contents($uploadedFile->getPathname()));
         $this->em->flush();
    }

    public function updateVideo(Video $video, string $embedUrl): void
    {
         $video->setEmbedCode($embedUrl);
         $this->em->flush();
    }
}
