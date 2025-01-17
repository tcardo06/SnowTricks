<?php

namespace App\Controller;

use App\Repository\TrickRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(TrickRepository $trickRepository): Response
    {
        $tricks = $trickRepository->findPaginated(15, 0);

        return $this->render('home/index.html.twig', [
            'tricks' => $tricks,
        ]);
    }

    #[Route('/load-more-tricks/{offset}', name: 'load_more_tricks', methods: ['GET'])]
    public function loadMore(int $offset = 0, TrickRepository $trickRepository): JsonResponse
    {
        $tricks = $trickRepository->findBy([], ['createdAt' => 'DESC'], 15, $offset);

        $html = '';
        foreach ($tricks as $trick) {
            $html .= sprintf(
                '<div class="col">
                    <div class="card">
                        <img src="/images/placeholder.jpg" class="card-img-top" alt="%s">
                        <div class="card-body">
                            <h5 class="card-title">
                                <a href="%s">%s</a>
                            </h5>
                            <p>%s</p>
                        </div>
                    </div>
                </div>',
                htmlspecialchars($trick->getName(), ENT_QUOTES),
                $this->generateUrl('trick_details', ['slug' => $trick->getSlug()]),
                htmlspecialchars($trick->getName(), ENT_QUOTES),
                htmlspecialchars($trick->getDescription(), ENT_QUOTES)
            );
        }

        return $this->json([
            'html' => $html,
            'hasMore' => count($tricks) === 15,
        ]);
    }

}
