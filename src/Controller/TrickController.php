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
        $trick->setCreator($this->getUser());
        $form = $this->createForm(TrickType::class, $trick);
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
    
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
    
            $videoEmbeds = $form->get('videos')->getData();
            foreach ($videoEmbeds as $embedCode) {
                if (!empty($embedCode)) {
                    $video = new Video();

                    // ✅ Fix YouTube URL Handling
                    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([\w-]+)/', $embedCode, $matches)) {
                        // Convert to embed URL
                        $video->setEmbedCode('https://www.youtube.com/embed/' . $matches[1]);
                    } 
                    // ✅ Dailymotion
                    elseif (preg_match('/dailymotion\.com\/video\/([\w-]+)/', $embedCode, $matches)) {
                        $video->setEmbedCode('https://www.dailymotion.com/embed/video/' . $matches[1]);
                    } 
                    // ✅ Already an Embed URL (No Changes)
                    else {
                        $video->setEmbedCode($embedCode);
                    }

                    $video->setTrick($trick);
                    $trick->addVideo($video);
                    $entityManager->persist($video);
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
}
