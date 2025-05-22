<?php
namespace App\Controller;

use App\Service\TrickManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    private $trickManager;

    public function __construct(TrickManager $trickManager)  // Inject TrickManager
    {
        $this->trickManager = $trickManager;
    }

    #[Route('/', name: 'home')]
    public function index(): Response
    {
        // Delegate fetching paginated tricks to TrickManager
        $tricks = $this->trickManager->getPaginatedTricks(15, 0);

        return $this->render('home/index.html.twig', [
            'tricks' => $tricks,
        ]);
    }

    #[Route('/load-more-tricks/{offset}', name: 'load_more_tricks', methods: ['GET'])]
    public function loadMore(int $offset = 0): JsonResponse
    {
        // Delegate fetching more tricks to TrickManager
        $tricks = $this->trickManager->getPaginatedTricks(15, $offset);

        // Delegate HTML generation to TrickManager
        $html = $this->trickManager->generateTrickHtml($tricks);

        return $this->json([
            'html' => $html,
            'hasMore' => count($tricks) === 15,
        ]);
    }
}
