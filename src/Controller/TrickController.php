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

class TrickController extends AbstractController
{
    #[Route('/trick/create', name: 'trick_create')]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
    
        $trick = new Trick();
        $form = $this->createForm(TrickType::class, $trick);
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
    
            // ✅ Generate Slug Before Saving
            $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', $trick->getName()), '-'));
            $trick->setSlug($slug);
    
            // ✅ Convert images to binary and persist
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
    
            $entityManager->persist($trick);
            $entityManager->flush();
    
            $this->addFlash('success', 'La figure a été ajoutée avec succès !');
            return $this->redirectToRoute('home');
        }
    
        return $this->render('trick/create.html.twig', [
            'form' => $form->createView(),
        ]);
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
}
