<?php

namespace App\Controller;

use App\Entity\Trick;
use App\Entity\Illustration;
use App\Entity\Video;
use App\Entity\Message;
use App\Form\TrickType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Annotation\Route;

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
            // Check if trick name already exists
            if ($entityManager->getRepository(Trick::class)->findOneBy(['name' => $trick->getName()])) {
                $this->addFlash('danger', 'Ce nom de figure existe déjà. Veuillez en choisir un autre.');
                return $this->render('trick/create.html.twig', ['form' => $form->createView()]);
            }

            // Generate slug and set it
            $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $trick->getName()), '-'));
            $trick->setSlug($slug);

            // Process media uploads
            $this->processUploadedImages($trick, $form->get('images')->getData(), $entityManager);
            $this->processVideoEmbeds($trick, $form->get('videos')->getData(), $entityManager);

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
        $trick = $this->getTrickOr404($slug, $entityManager);
        $this->assertUserIsCreator($trick);
        
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
        return $url;
    }
    
    #[Route('/trick/{slug}', name: 'trick_details', requirements: ['slug' => '[a-z0-9-]+'])]
    public function details(string $slug, Request $request, EntityManagerInterface $entityManager): Response
    {
        $trick = $this->getTrickOr404($slug, $entityManager);
        $currentPage = $request->query->getInt('page', 1);
        $limit = 10;
        $offset = ($currentPage - 1) * $limit;
        $messageRepo = $entityManager->getRepository(Message::class);
        $messages = $messageRepo->findBy(['trick' => $trick], ['createdAt' => 'DESC'], $limit, $offset);
        $totalMessages = $messageRepo->count(['trick' => $trick]);
        $totalPages = ceil($totalMessages / $limit);
        return $this->render('trick/details.html.twig', [
            'trick'       => $trick,
            'messages'    => $messages,
            'currentPage' => $currentPage,
            'totalPages'  => $totalPages,
        ]);
    }
    
    #[Route('/trick/{trick_slug}/delete-image/{image_id}', name: 'trick_delete_image', methods: ['GET'])]
    public function deleteImage(string $trick_slug, int $image_id, EntityManagerInterface $entityManager): Response
    {
        $trick = $this->getTrickOr404($trick_slug, $entityManager);
        $image = $this->getMediaOr404($entityManager, $trick, $image_id, Illustration::class);
        $entityManager->remove($image);
        $entityManager->flush();
        $this->addFlash('success', 'Image supprimée avec succès.');
        return $this->redirectToRoute('trick_details', ['slug' => $trick_slug]);
    }
 
    #[Route('/trick/{trick_slug}/edit-image/{image_id}', name: 'trick_edit_image', methods: ['POST'])]
    public function editImage(string $trick_slug, int $image_id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $trick = $this->getTrickOr404($trick_slug, $entityManager);
        $image = $this->getMediaOr404($entityManager, $trick, $image_id, Illustration::class);
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
        $trick = $this->getTrickOr404($trick_slug, $entityManager);
        $video = $this->getMediaOr404($entityManager, $trick, $video_id, Video::class);
        $data = json_decode($request->getContent(), true);
        if (!isset($data['embedCode']) || empty($data['embedCode'])) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid or missing video URL.'], Response::HTTP_BAD_REQUEST);
        }
        $video->setEmbedCode($this->convertToEmbed($data['embedCode']));
        $entityManager->flush();
        return new JsonResponse(['success' => true]);
    }
 
    #[Route('/trick/{trick_slug}/delete-video/{video_id}', name: 'trick_delete_video', methods: ['GET'])]
    public function deleteVideo(string $trick_slug, int $video_id, EntityManagerInterface $entityManager): Response
    {
        $trick = $this->getTrickOr404($trick_slug, $entityManager);
        $video = $this->getMediaOr404($entityManager, $trick, $video_id, Video::class);
        $entityManager->remove($video);
        $entityManager->flush();
        $this->addFlash('success', 'Vidéo supprimée avec succès.');
        return $this->redirectToRoute('trick_details', ['slug' => $trick_slug]);
    }
 
    #[Route('/trick/{trick_slug}/add-media/image', name: 'trick_add_media_image', methods: ['POST'])]
    public function addMediaImage(string $trick_slug, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $trick = $this->getTrickOr404($trick_slug, $entityManager);
        $this->assertUserIsCreator($trick);
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
    
    #[Route('/trick/{trick_slug}/add-media/video', name: 'trick_add_media_video', methods: ['POST'])]
    public function addMediaVideo(string $trick_slug, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $trick = $this->getTrickOr404($trick_slug, $entityManager);
        $this->assertUserIsCreator($trick);
        $data = json_decode($request->getContent(), true);
        if (!isset($data['embedCode']) || empty($data['embedCode'])) {
            return new JsonResponse(['success' => false, 'message' => 'No video embed code provided.'], Response::HTTP_BAD_REQUEST);
        }
        $video = new Video();
        $video->setEmbedCode($this->convertToEmbed($data['embedCode']));
        $video->setTrick($trick);
        $entityManager->persist($video);
        $entityManager->flush();
        return new JsonResponse(['success' => true]);
    }
 
    #[Route('/trick/{slug}/edit', name: 'trick_edit', methods: ['GET', 'POST'])]
    public function edit(string $slug, Request $request, EntityManagerInterface $entityManager): Response
    {
        $trick = $this->getTrickOr404($slug, $entityManager);
        $this->assertUserIsCreator($trick);
        $form = $this->createForm(TrickType::class, $trick, ['include_media' => false]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $newSlug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $trick->getName()), '-'));
            $trick->setSlug($newSlug);
            $entityManager->flush();
            $this->addFlash('success', 'La figure a été modifiée avec succès.');
            return $this->redirectToRoute('trick_details', ['slug' => $trick->getSlug()]);
        }
        return $this->render('trick/edit.html.twig', [
            'form'  => $form->createView(),
            'trick' => $trick,
        ]);
    }
    
    #[Route('/trick/{slug}/message', name: 'trick_post_message', methods: ['POST'])]
    public function postMessage(string $slug, Request $request, EntityManagerInterface $entityManager): Response
    {
        $trick = $this->getTrickOr404($slug, $entityManager);
        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            $this->addFlash('error', 'Vous devez être connecté pour poster un message.');
            return $this->redirectToRoute('trick_details', ['slug' => $slug]);
        }
        $content = trim($request->request->get('message'));
        if (empty($content)) {
            $this->addFlash('error', 'Le message est obligatoire.');
            return $this->redirectToRoute('trick_details', ['slug' => $slug]);
        }
        $message = new Message();
        $message->setContent($content);
        $message->setTrick($trick);
        $message->setUser($this->getUser());
        $entityManager->persist($message);
        $entityManager->flush();
        $this->addFlash('success', 'Message posté avec succès.');
        return $this->redirectToRoute('trick_details', ['slug' => $slug]);
    }

    // HELPER METHODS

    private function getTrickOr404(string $slug, EntityManagerInterface $entityManager): Trick
    {
        $trick = $entityManager->getRepository(Trick::class)->findOneBy(['slug' => $slug]);
        if (!$trick) {
            throw $this->createNotFoundException('Trick not found.');
        }
        return $trick;
    }

    private function assertUserIsCreator(Trick $trick): void
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            throw new \LogicException('The logged-in user is not a valid User entity.');
        }
        if ((int)$trick->getCreator()->getId() !== (int)$user->getId()) {
            $this->addFlash('danger', 'Vous n’êtes pas autorisé à modifier cette figure.');
            throw $this->createAccessDeniedException();
        }
    }

    /** * Helper to retrieve a media entity (Illustration or Video) or throw a 404 if not found or not linked.*/
    private function getMediaOr404(EntityManagerInterface $entityManager, Trick $trick, int $mediaId, string $mediaClass): object
    {
        $media = $entityManager->getRepository($mediaClass)->find($mediaId);
        if (!$media || $media->getTrick() !== $trick) {
            $mediaName = $mediaClass === Video::class ? 'Vidéo' : 'Image';
            throw $this->createNotFoundException("$mediaName not found or does not belong to this trick.");
        }
        return $media;
    }

    private function processUploadedImages(Trick $trick, array $uploadedImages, EntityManagerInterface $entityManager): void
    {
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
    }

    private function processVideoEmbeds(Trick $trick, array $videoEmbeds, EntityManagerInterface $entityManager): void
    {
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
                $entityManager->persist($video);
            }
        }
    }
}
