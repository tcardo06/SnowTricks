<?php
namespace App\Controller;

use App\Entity\Trick;
use App\Form\TrickType;
use App\Service\TrickManager;
use App\Service\MessageManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TrickController extends AbstractController
{
    private $trickManager;
    private $messageManager;

    public function __construct(TrickManager $trickManager, MessageManager $messageManager)
    {
         $this->trickManager = $trickManager;
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
              $existingTrick = $this->getDoctrine()->getRepository(Trick::class)
                                  ->findOneBy(['name' => $trick->getName()]);
              if ($existingTrick) {
                   $this->addFlash('danger', 'Ce nom de figure existe déjà. Veuillez en choisir un autre.');
                   return $this->render('trick/create.html.twig', ['form' => $form->createView()]);
              }
              try {
                   $this->trickManager->createTrick(
                        $trick,
                        $form->get('images')->getData(),
                        $form->get('videos')->getData()
                   );
                   $this->addFlash('success', 'La figure a été ajoutée avec succès !');
                   return $this->redirectToRoute('home');
              } catch (\Exception $e) {
                   $this->addFlash('danger', $e->getMessage());
              }
         }
         return $this->render('trick/create.html.twig', ['form' => $form->createView()]);
    }

    /**
     * Utilisation du ParamConverter pour injecter directement le Trick.
     *
     * @ParamConverter("trick", class="App\Entity\Trick", options={"mapping": {"slug": "slug"}})
     */
    #[Route('/trick/{slug}', name: 'trick_details')]
    public function details(Trick $trick, Request $request): Response
    {
         $currentPage = $request->query->getInt('page', 1);
         list($messages, $currentPage, $totalPages) = $this->messageManager->getMessagesForTrick($trick, $currentPage);
         return $this->render('trick/details.html.twig', [
              'trick'       => $trick,
              'messages'    => $messages,
              'currentPage' => $currentPage,
              'totalPages'  => $totalPages,
         ]);
    }

    #[Route('/trick/{slug}/edit', name: 'trick_edit', methods: ['GET', 'POST'])]
    public function edit(string $slug, Request $request): Response
    {
         $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
         $trick = $this->trickManager->getTrickBySlug($slug);
         if (!$trick) {
              throw $this->createNotFoundException('Figure non trouvée.');
         }
         $this->assertUserIsCreator($trick);
         $form = $this->createForm(TrickType::class, $trick, ['include_media' => false]);
         $form->handleRequest($request);
         if ($form->isSubmitted() && $form->isValid()) {
              $this->trickManager->editTrick($trick);
              $this->addFlash('success', 'La figure a été modifiée avec succès.');
              return $this->redirectToRoute('trick_details', ['slug' => $trick->getSlug()]);
         }
         return $this->render('trick/edit.html.twig', [
              'form'  => $form->createView(),
              'trick' => $trick,
         ]);
    }

    #[Route('/trick/{slug}/delete', name: 'trick_delete')]
    public function delete(string $slug): Response
    {
         $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
         $trick = $this->trickManager->getTrickBySlug($slug);
         if (!$trick) {
              throw $this->createNotFoundException('Figure non trouvée.');
         }
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
         $content = trim($request->request->get('message'));
         if (empty($content)) {
              $this->addFlash('danger', 'Le message est obligatoire.');
              return $this->redirectToRoute('trick_details', ['slug' => $slug]);
         }
         $this->messageManager->postMessage($trick, $this->getUser(), $content);
         $this->addFlash('success', 'Message posté avec succès.');
         return $this->redirectToRoute('trick_details', ['slug' => $slug]);
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
