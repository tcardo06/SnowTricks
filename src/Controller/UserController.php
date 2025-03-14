<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{
    #[Route('/user/photo/{id}', name: 'user_photo', methods: ['GET'])]
    public function photo($id, EntityManagerInterface $entityManager): Response
    {
        // Fetch the user by id
        $user = $entityManager->getRepository(User::class)->find($id);
        if (!$user) {
            throw $this->createNotFoundException('User not found.');
        }
    
        // Retrieve the photo data using getter
        $photoData = $user->getPhoto();
        if (!$photoData) {
            throw $this->createNotFoundException('No photo available.');
        }
    
        // Retrieve the MIME type from the user entity (fallback if missing)
        $mimeType = $user->getPhotoMime() ?: 'image/jpeg';
    
        $response = new Response($photoData);
        $response->headers->set('Content-Type', $mimeType);
    
        return $response;
    }    
}
