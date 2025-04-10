<?php
// src/Service/MessageManager.php

namespace App\Service;

use App\Entity\Message;
use App\Entity\Trick;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class MessageManager
{
    private $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
         $this->em = $entityManager;
    }

    public function postMessage(Trick $trick, User $user, string $content): void
    {
         $message = new Message();
         $message->setTrick($trick);
         $message->setUser($user);
         $message->setContent($content);
         $this->em->persist($message);
         $this->em->flush();
    }

    /**
     * Récupère les messages d'une figure avec pagination.
     *
     * @param Trick $trick
     * @param int $currentPage
     * @param int $limit
     * @return array [$messages, $currentPage, $totalPages]
     */
    public function getMessagesForTrick(Trick $trick, int $currentPage = 1, int $limit = 10): array
    {
         $offset = ($currentPage - 1) * $limit;
         $repo = $this->em->getRepository(Message::class);
         $messages = $repo->findBy(
             ['trick' => $trick],
             ['createdAt' => 'DESC'],
             $limit,
             $offset
         );
         $totalMessages = $repo->count(['trick' => $trick]);
         $totalPages = (int) ceil($totalMessages / $limit);
         return [$messages, $currentPage, $totalPages];
    }
}
