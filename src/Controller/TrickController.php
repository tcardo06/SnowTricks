<?php
namespace App\Controller;

use App\Entity\Trick;
use App\Form\TrickType;
use App\Service\TrickManager;
use App\Service\MessageManager;
use App\Service\TrickValidationService;
use App\Service\MediaManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TrickController extends AbstractController
{
    private $trickManager;
    private $messageManager;
    private $trickValidationService;
    private $mediaManager;

    public function __construct(TrickManager $trickManager, TrickValidationService $trickValidationService, MediaManager $mediaManager, MessageManager $messageManager)
    {
      $this->trickManager = $trickManager;
      $this->trickValidationService = $trickValidationService;
      $this->mediaManager = $mediaManager;
      $this->messageManager = $messageManager;
    }

    #[Route('/trick/create', name: 'trick_create')]
    public function create(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $trick = new Trick();
        $trick->setCreator($this->getUser());
        $form = $this->createForm(TrickType::class, $trick);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Call TrickManager to handle business logic
                $images = $form->get('images')->getData();
                $videos = $form->get('videos')->getData();

                // Delegate the creation and media handling to the service
                $this->trickManager->createTrickWithMedia($trick, $images, $videos);

                $this->addFlash('success', 'La figure a été ajoutée avec succès !');
                return $this->redirectToRoute('home');
            } catch (\Exception $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('trick/create.html.twig', ['form' => $form->createView()]);
    }

    #[Route('/trick/{slug}', name: 'trick_details')]
    public function details(Trick $trick, Request $request): Response
    {
        $currentPage = $request->query->getInt('page', 1);

        // Delegate the logic to MessageManager to fetch paginated messages
        list($messages, $currentPage, $totalPages) = $this->messageManager->getMessagesForTrickWithPagination($trick, $currentPage);

        $message = new \App\Entity\Message();
        $form = $this->createForm(\App\Form\MessageType::class, $message, [
             'action' => $this->generateUrl('trick_post_message', ['slug' => $trick->getSlug()])
        ]);

        return $this->render('trick/details.html.twig', [
             'trick'       => $trick,
             'messages'    => $messages,
             'currentPage' => $currentPage,
             'totalPages'  => $totalPages,
             'messageForm' => $form->createView(),
        ]);
    }

    #[Route('/trick/{slug}/edit', name: 'trick_edit', methods: ['GET', 'POST'])]
    public function edit(string $slug, Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // Fetch the Trick entity
        $trick = $this->trickManager->getTrickBySlug($slug);
        if (!$trick) {
            throw $this->createNotFoundException('Trick not found.');
        }

        // Check if the current user is the creator
        $this->trickManager->assertUserIsCreator($trick, $this->getUser());

        // Create the form for editing the Trick
        $form = $this->createForm(TrickType::class, $trick, ['include_media' => false]);
        $form->handleRequest($request);

        // Delegate form handling and persistence to the TrickManager
        if ($form->isSubmitted() && $form->isValid()) {
            // Handle the trick edit in the service
            if ($this->trickManager->handleTrickEdit($trick)) {
                $this->addFlash('success', 'The trick was successfully updated.');
                return $this->redirectToRoute('trick_details', ['slug' => $trick->getSlug()]);
            }
        }

        return $this->render('trick/edit.html.twig', [
            'form'  => $form->createView(),
            'trick' => $trick,
        ]);
    }

    #[Route('/trick/{slug}/delete', name: 'trick_delete', methods: ['GET'])]
    public function delete(Trick $trick): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $this->assertUserIsCreator($trick);

        $this->trickManager->deleteTrick($trick);

        $this->addFlash('success', 'La figure a été supprimée avec succès.');
        return $this->redirectToRoute('home');
    }

    #[Route('/trick/{slug}/message', name: 'trick_post_message')]
    public function postMessage(string $slug, Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $trick = $this->trickManager->getTrickBySlug($slug);
        if (!$trick) {
            throw $this->createNotFoundException('Figure non trouvée.');
        }

        $message = new \App\Entity\Message();
        $form = $this->createForm(\App\Form\MessageType::class, $message, [
             'action' => $this->generateUrl('trick_post_message', ['slug' => $trick->getSlug()])
        ]);
        $form->handleRequest($request);

        // Delegate form handling and message creation to the MessageManager
        if ($this->messageManager->handleMessageForm($form, $trick, $this->getUser())) {
            $this->addFlash('success', 'Message posté avec succès.');
            return $this->redirectToRoute('trick_details', ['slug' => $slug]);
        }

        $currentPage = $request->query->getInt('page', 1);
        list($messages, $currentPage, $totalPages) = $this->messageManager->getMessagesForTrickWithPagination($trick, $currentPage);

        return $this->render('trick/details.html.twig', [
            'trick'       => $trick,
            'messages'    => $messages,
            'currentPage' => $currentPage,
            'totalPages'  => $totalPages,
            'messageForm' => $form->createView(),
        ]);
    }

    /**
     * Vérifie que l'utilisateur connecté est bien le créateur de la figure.
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
