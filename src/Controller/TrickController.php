<?php

namespace App\Controller;

use App\Entity\Trick;
use App\Form\TrickType;
use App\Entity\Illustration;
use App\Entity\Video;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpFoundation\JsonResponse;

class TrickController extends AbstractController
{
    #[Route('/trick/create', name: 'trick_create')]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $trick = new Trick();
        $trick->setCreator($this->getUser());
        $form = $this->createForm(TrickType::class, $trick);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // ✅ Check if trick name already exists
            $existingTrick = $entityManager->getRepository(Trick::class)->findOneBy(['name' => $trick->getName()]);
            if ($existingTrick) {
                $this->addFlash('danger', 'Ce nom de figure existe déjà. Veuillez en choisir un autre.');
                return $this->render('trick/create.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            // ✅ Generate Slug Before Saving
            $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', $trick->getName()), '-'));
            $trick->setSlug($slug);

            // ✅ Persist Images
            $uploadedImages = $form->get('images')->getData();
            foreach ($uploadedImages as $uploadedImage) {
                if ($uploadedImage instanceof UploadedFile) {
                    $imageData = file_get_contents($uploadedImage->getPathname());

                    $illustration = new Illustration();
                    $illustration->setImageData($imageData);
                    $illustration->setTrick($trick);

                    $trick->addIllustration($illustration);
                    $entityManager->persist($illustration);
                }
            }

            // ✅ Persist Videos (Ensure YouTube/Dailymotion Embeds)
            $videoEmbeds = $form->get('videos')->getData();
            foreach ($videoEmbeds as $embedCode) {
                if (!empty($embedCode)) {
                    $video = new Video();

                    // ✅ Convert YouTube/Dailymotion links to embed format
                    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([\w-]+)/', $embedCode, $matches)) {
                        $video->setEmbedCode('https://www.youtube.com/embed/' . $matches[1]);
                    } elseif (preg_match('/dailymotion\.com\/video\/([\w-]+)/', $embedCode, $matches)) {
                        $video->setEmbedCode('https://www.dailymotion.com/embed/video/' . $matches[1]);
                    } else {
                        $video->setEmbedCode($embedCode);
                    }

                    $video->setTrick($trick);
                    $trick->addVideo($video);
                    $entityManager->persist($video);
                }
            }

            try {
                $entityManager->persist($trick);
                $entityManager->flush();

                $this->addFlash('success', 'La figure a été ajoutée avec succès !');
                return $this->redirectToRoute('home');
            } catch (UniqueConstraintViolationException $e) {
                $this->addFlash('danger', 'Ce nom de figure existe déjà. Veuillez en choisir un autre.');
            }
        }

        return $this->render('trick/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/trick/{slug}/delete', name: 'trick_delete', methods: ['GET'])]
    public function delete(string $slug, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // Fetch trick
        $trick = $entityManager->getRepository(Trick::class)->findOneBy(['slug' => $slug]);

        if (!$trick) {
            throw $this->createNotFoundException('Trick not found.');
        }

        // Ensure only the creator can delete the trick
        if ($trick->getCreator() !== $this->getUser()) {
            $this->addFlash('danger', 'Vous ne pouvez pas supprimer cette figure.');
            return $this->redirectToRoute('home');
        }

        // Remove trick and flush
        $entityManager->remove($trick);
        $entityManager->flush();

        $this->addFlash('success', 'La figure a été supprimée avec succès.');
        return $this->redirectToRoute('home');
    }
    
    /**
     * Converts YouTube links into embed URLs.
     */
    private function convertToEmbed(string $url): string
    {
        if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $url, $matches) || 
            preg_match('/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return 'https://www.youtube.com/embed/' . $matches[1];
        }
        return $url; // Return original URL if no match
    }             
    
    #[Route('/trick/{slug}', name: 'trick_details', requirements: ['slug' => '[a-z0-9-]+'])]
    public function details(string $slug, EntityManagerInterface $entityManager): Response
    {
        // Fetch trick by slug
        $trick = $entityManager->getRepository(Trick::class)->findOneBy(['slug' => $slug]);
    
        // If trick is not found, throw a 404
        if (!$trick) {
            throw $this->createNotFoundException('Trick not found.');
        }
    
        return $this->render('trick/details.html.twig', [
            'trick' => $trick,
        ]);
    }
    
    #[Route('/trick/{trick_slug}/delete-image/{image_id}', name: 'trick_delete_image', methods: ['GET'])]
    public function deleteImage(string $trick_slug, int $image_id, EntityManagerInterface $entityManager): Response
    {
        $trick = $entityManager->getRepository(Trick::class)->findOneBy(['slug' => $trick_slug]);
        if (!$trick) {
            throw $this->createNotFoundException('Trick not found.');
        }
    
        $image = $entityManager->getRepository(Illustration::class)->find($image_id);
        if (!$image || $image->getTrick() !== $trick) {
            throw $this->createNotFoundException('Image not found or does not belong to this trick.');
        }
    
        // Remove Image from Database
        $entityManager->remove($image);
        $entityManager->flush();
    
        // Flash Message
        $this->addFlash('success', 'Image supprimée avec succès.');
    
        return $this->redirectToRoute('trick_details', ['slug' => $trick_slug]);
    }

    #[Route('/trick/{trick_slug}/edit-image/{image_id}', name: 'trick_edit_image', methods: ['POST'])]
    public function editImage(string $trick_slug, int $image_id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $trick = $entityManager->getRepository(Trick::class)->findOneBy(['slug' => $trick_slug]);
        if (!$trick) {
            return new JsonResponse(['success' => false, 'message' => 'Trick not found.'], Response::HTTP_NOT_FOUND);
        }
    
        $image = $entityManager->getRepository(Illustration::class)->find($image_id);
        if (!$image) {
            return new JsonResponse(['success' => false, 'message' => 'Image not found.'], Response::HTTP_NOT_FOUND);
        }
    
        $uploadedFile = $request->files->get('image');
        if ($uploadedFile instanceof UploadedFile) {
            $image->setImageData(file_get_contents($uploadedFile->getPathname()));
            $entityManager->flush();
            return new JsonResponse(['success' => true]);
        }
    
        return new JsonResponse(['success' => false, 'message' => 'Invalid file upload.'], Response::HTTP_BAD_REQUEST);
    }

    #[Route('/trick/{trick_slug}/edit-video/{video_id}', name: 'trick_edit_video', methods: ['POST'])]
    public function editVideo(string $trick_slug, int $video_id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $trick = $entityManager->getRepository(Trick::class)->findOneBy(['slug' => $trick_slug]);
        if (!$trick) {
            return new JsonResponse(['success' => false, 'message' => 'Trick not found.'], Response::HTTP_NOT_FOUND);
        }

        $video = $entityManager->getRepository(Video::class)->find($video_id);
        if (!$video || $video->getTrick() !== $trick) {
            return new JsonResponse(['success' => false, 'message' => 'Video not found or does not belong to this trick.'], Response::HTTP_NOT_FOUND);
        }

        // Decode JSON request body
        $data = json_decode($request->getContent(), true);
        if (!isset($data['embedCode']) || empty($data['embedCode'])) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid or missing video URL.'], Response::HTTP_BAD_REQUEST);
        }

        $newEmbedCode = $this->convertToEmbed($data['embedCode']); // Convert to embed format if needed

        // Update the video with the new embed URL
        $video->setEmbedCode($newEmbedCode);
        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/trick/{trick_slug}/delete-video/{video_id}', name: 'trick_delete_video', methods: ['GET'])]
    public function deleteVideo(string $trick_slug, int $video_id, EntityManagerInterface $entityManager): Response
    {
        $trick = $entityManager->getRepository(Trick::class)->findOneBy(['slug' => $trick_slug]);
        if (!$trick) {
            throw $this->createNotFoundException('Trick not found.');
        }
    
        $video = $entityManager->getRepository(Video::class)->find($video_id);
        if (!$video || $video->getTrick() !== $trick) {
            throw $this->createNotFoundException('Video not found or does not belong to this trick.');
        }
    
        // Remove only the video without affecting the trick
        $entityManager->remove($video);
        $entityManager->flush();
    
        $this->addFlash('success', 'Vidéo supprimée avec succès.');
        return $this->redirectToRoute('trick_details', ['slug' => $trick_slug]);
    }

    // Add an image media via AJAX
    #[Route('/trick/{trick_slug}/add-media/image', name: 'trick_add_media_image', methods: ['POST'])]
    public function addMediaImage(string $trick_slug, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $trick = $entityManager->getRepository(Trick::class)->findOneBy(['slug' => $trick_slug]);
        if (!$trick) {
            return new JsonResponse(['success' => false, 'message' => 'Trick not found.'], Response::HTTP_NOT_FOUND);
        }
        // Ensure only the trick creator can add media
        if ($trick->getCreator() !== $this->getUser()) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized.'], Response::HTTP_FORBIDDEN);
        }
    
        $uploadedFile = $request->files->get('image');
        if (!$uploadedFile instanceof UploadedFile) {
            return new JsonResponse(['success' => false, 'message' => 'No image uploaded.'], Response::HTTP_BAD_REQUEST);
        }
    
        $imageData = file_get_contents($uploadedFile->getPathname());
        $illustration = new Illustration();
        $illustration->setImageData($imageData);
        $illustration->setTrick($trick);
    
        $entityManager->persist($illustration);
        $entityManager->flush();
    
        return new JsonResponse(['success' => true]);
    }
    
    // Add a video media via AJAX
    #[Route('/trick/{trick_slug}/add-media/video', name: 'trick_add_media_video', methods: ['POST'])]
    public function addMediaVideo(string $trick_slug, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $trick = $entityManager->getRepository(Trick::class)->findOneBy(['slug' => $trick_slug]);
        if (!$trick) {
            return new JsonResponse(['success' => false, 'message' => 'Trick not found.'], Response::HTTP_NOT_FOUND);
        }
        // Ensure only the trick creator can add media
        if ($trick->getCreator() !== $this->getUser()) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized.'], Response::HTTP_FORBIDDEN);
        }
    
        $data = json_decode($request->getContent(), true);
        if (!isset($data['embedCode']) || empty($data['embedCode'])) {
            return new JsonResponse(['success' => false, 'message' => 'No video embed code provided.'], Response::HTTP_BAD_REQUEST);
        }
    
        $embedCode = $this->convertToEmbed($data['embedCode']);
    
        $video = new Video();
        $video->setEmbedCode($embedCode);
        $video->setTrick($trick);
    
        $entityManager->persist($video);
        $entityManager->flush();
    
        return new JsonResponse(['success' => true]);
    }

    #[Route('/trick/{slug}/edit', name: 'trick_edit', methods: ['GET', 'POST'])]
    public function edit(string $slug, Request $request, EntityManagerInterface $entityManager): Response
    {
        // Fetch the trick by slug
        $trick = $entityManager->getRepository(Trick::class)->findOneBy(['slug' => $slug]);
        if (!$trick) {
            throw $this->createNotFoundException('Trick not found.');
        }

        // Ensure only the creator can edit the trick
        if ($trick->getCreator() !== $this->getUser()) {
            $this->addFlash('danger', 'Vous n’êtes pas autorisé à modifier cette figure.');
            return $this->redirectToRoute('home');
        }

        // Create the form pre-filled with the current trick data
        $form = $this->createForm(TrickType::class, $trick);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Regenerate slug in case the name changed
            $newSlug = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', $trick->getName()), '-'));
            $trick->setSlug($newSlug);

            // Persist changes
            $entityManager->flush();

            $this->addFlash('success', 'La figure a été modifiée avec succès.');
            return $this->redirectToRoute('trick_details', ['slug' => $trick->getSlug()]);
        }

        return $this->render('trick/edit.html.twig', [
            'form'  => $form->createView(),
            'trick' => $trick,
        ]);
    }
}
