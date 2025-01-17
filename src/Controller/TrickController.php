<?php

namespace App\Controller;

use App\Entity\Trick;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TrickController extends AbstractController
{
    #[Route('/trick/{slug}', name: 'trick_details')]
    public function details(Trick $trick): Response
    {
        return $this->render('trick/details.html.twig', [
            'trick' => $trick,
        ]);
    }    
}
