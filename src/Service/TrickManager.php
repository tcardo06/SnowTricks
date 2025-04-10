<?php
// src/Service/TrickManager.php

namespace App\Service;

use App\Entity\Trick;
use App\Entity\Illustration;
use App\Entity\Video;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

class TrickManager
{
    private $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
         $this->em = $entityManager;
    }

    /**
     * Crée une nouvelle figure, en générant le slug et en traitant les médias.
     *
     * @throws UniqueConstraintViolationException si le nom existe déjà.
     */
    public function createTrick(Trick $trick, array $uploadedImages, array $videoEmbeds): void
    {
         // Vérifier l'unicité du nom est à faire dans le contrôleur ou ici
         $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $trick->getName()), '-'));
         $trick->setSlug($slug);

         // Traitement des images
         foreach ($uploadedImages as $uploadedImage) {
             if ($uploadedImage instanceof UploadedFile) {
                  $imageData = file_get_contents($uploadedImage->getPathname());
                  $illustration = new Illustration();
                  $illustration->setImageData($imageData);
                  $illustration->setTrick($trick);
                  $trick->addIllustration($illustration);
                  $this->em->persist($illustration);
             }
         }

         // Traitement des vidéos
         foreach ($videoEmbeds as $embedCode) {
             if (!empty($embedCode)) {
                  $video = new Video();
                  if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([\w-]+)/', $embedCode, $matches)) {
                        $video->setEmbedCode('https://www.youtube.com/embed/' . $matches[1]);
                  } elseif (preg_match('/dailymotion\.com\/video\/([\w-]+)/', $embedCode, $matches)) {
                        $video->setEmbedCode('https://www.dailymotion.com/embed/video/' . $matches[1]);
                  } else {
                        $video->setEmbedCode($embedCode);
                  }
                  $video->setTrick($trick);
                  $trick->addVideo($video);
                  $this->em->persist($video);
             }
         }

         $this->em->persist($trick);
         $this->em->flush();
    }

    public function deleteTrick(Trick $trick): void
    {
         $this->em->remove($trick);
         $this->em->flush();
    }

    public function editTrick(Trick $trick): void
    {
         $newSlug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $trick->getName()), '-'));
         $trick->setSlug($newSlug);
         $this->em->flush();
    }

    public function getTrickBySlug(string $slug): ?Trick
    {
         return $this->em->getRepository(Trick::class)->findOneBy(['slug' => $slug]);
    }
}
