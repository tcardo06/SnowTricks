<?php
namespace App\Controller;

use App\Service\TrickManager;
use App\Service\MediaManager;
use App\Entity\Trick;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MediaController extends AbstractController
{
    private $trickManager;
    private $mediaManager;

    public function __construct(TrickManager $trickManager, MediaManager $mediaManager)
    {
         $this->trickManager = $trickManager;
         $this->mediaManager = $mediaManager;
    }

    #[Route('/trick/{trick_slug}/delete-image/{image_id}', name: 'media_delete_image', methods: ['GET'])]
    public function deleteImage(string $trick_slug, int $image_id): Response
    {
         $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
         $trick = $this->trickManager->getTrickBySlug($trick_slug);
         if (!$trick) {
              throw $this->createNotFoundException('Figure non trouvée.');
         }
         $this->assertUserIsCreator($trick);
         $illustration = null;
         foreach ($trick->getIllustrations() as $img) {
              if ($img->getId() == $image_id) {
                   $illustration = $img;
                   break;
              }
         }
         if (!$illustration) {
              throw $this->createNotFoundException('Illustration non trouvée.');
         }
         $this->mediaManager->deleteMedia($illustration);
         $this->addFlash('success', 'Image supprimée avec succès.');
         return $this->redirectToRoute('trick_details', ['slug' => $trick->getSlug()]);
    }

    #[Route('/trick/{trick_slug}/edit-image/{image_id}', name: 'media_edit_image', methods: ['POST'])]
    public function editImage(string $trick_slug, int $image_id, Request $request): JsonResponse
    {
         $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
         $trick = $this->trickManager->getTrickBySlug($trick_slug);
         if (!$trick) {
              return new JsonResponse(['success' => false, 'message' => 'Figure non trouvée.'], Response::HTTP_NOT_FOUND);
         }
         $this->assertUserIsCreator($trick);
         $uploadedFile = $request->files->get('image');
         if (!$uploadedFile) {
              return new JsonResponse(['success' => false, 'message' => 'Aucun fichier envoyé'], Response::HTTP_BAD_REQUEST);
         }
         $illustration = null;
         foreach ($trick->getIllustrations() as $img) {
              if ($img->getId() == $image_id) {
                   $illustration = $img;
                   break;
              }
         }
         if (!$illustration) {
              return new JsonResponse(['success' => false, 'message' => 'Illustration non trouvée.'], Response::HTTP_NOT_FOUND);
         }
         try {
              $this->mediaManager->updateImage($illustration, $uploadedFile);
              return new JsonResponse(['success' => true]);
         } catch (\Exception $e) {
              return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
         }
    }

    #[Route('/trick/{trick_slug}/add-media/image', name: 'media_add_image', methods: ['POST'])]
    public function addMediaImage(string $trick_slug, Request $request): JsonResponse
    {
         $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
         $trick = $this->trickManager->getTrickBySlug($trick_slug);
         if (!$trick) {
              throw $this->createNotFoundException('Figure non trouvée.');
         }
         $this->assertUserIsCreator($trick);
         $uploadedFile = $request->files->get('image');
         if (!$uploadedFile) {
              return new JsonResponse(['success' => false, 'message' => 'Aucune image téléversée'], Response::HTTP_BAD_REQUEST);
         }
         try {
              $this->mediaManager->addImage($trick, $uploadedFile);
              return new JsonResponse(['success' => true]);
         } catch (\Exception $e) {
              return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
         }
    }

    #[Route('/trick/{trick_slug}/add-media/video', name: 'media_add_video', methods: ['POST'])]
    public function addMediaVideo(string $trick_slug, Request $request): JsonResponse
    {
         $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
         $trick = $this->trickManager->getTrickBySlug($trick_slug);
         if (!$trick) {
              throw $this->createNotFoundException('Figure non trouvée.');
         }
         $this->assertUserIsCreator($trick);
         $data = json_decode($request->getContent(), true);
         if (!isset($data['embedCode']) || empty($data['embedCode'])) {
              return new JsonResponse(['success' => false, 'message' => 'Code embed manquant'], Response::HTTP_BAD_REQUEST);
         }
         try {
              $this->mediaManager->addVideo($trick, $data['embedCode']);
              return new JsonResponse(['success' => true]);
         } catch (\Exception $e) {
              return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
         }
    }

    #[Route('/trick/{trick_slug}/edit-video/{video_id}', name: 'media_edit_video', methods: ['POST'])]
    public function editVideo(string $trick_slug, int $video_id, Request $request): JsonResponse
    {
         $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
         $trick = $this->trickManager->getTrickBySlug($trick_slug);
         if (!$trick) {
              return new JsonResponse(['success' => false, 'message' => 'Figure non trouvée.'], Response::HTTP_NOT_FOUND);
         }
         $this->assertUserIsCreator($trick);
         $data = json_decode($request->getContent(), true);
         if (!isset($data['embedCode']) || empty($data['embedCode'])) {
              return new JsonResponse(['success' => false, 'message' => 'Code embed manquant'], Response::HTTP_BAD_REQUEST);
         }
         $video = null;
         foreach ($trick->getVideos() as $v) {
              if ($v->getId() == $video_id) {
                   $video = $v;
                   break;
              }
         }
         if (!$video) {
              return new JsonResponse(['success' => false, 'message' => 'Vidéo non trouvée.'], Response::HTTP_NOT_FOUND);
         }
         try {
              $this->mediaManager->updateVideo($video, $data['embedCode']);
              return new JsonResponse(['success' => true]);
         } catch (\Exception $e) {
              return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
         }
    }

    /**
     * Méthode privée pour vérifier que l'utilisateur connecté est bien le créateur de la figure.
     *
     * @throws \LogicException si ce n'est pas le cas
     */
    private function assertUserIsCreator(Trick $trick): void
    {
         /** @var \App\Entity\User $currentUser */
         $currentUser = $this->getUser();
         if ((int)$trick->getCreator()->getId() !== (int)$currentUser->getId()) {
              $this->addFlash('danger', 'Vous n’êtes pas autorisé à accéder à cette action.');
              throw $this->createAccessDeniedException();
         }
    }
}
