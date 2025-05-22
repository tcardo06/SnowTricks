<?php
namespace App\Service;

use App\Entity\Message;
use App\Entity\Trick;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;

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

    public function createAndPostMessage(Trick $trick, User $user, string $content): void
    {
         // Create the message entity
         $message = new Message();
         $message->setTrick($trick);
         $message->setUser($user);
         $message->setContent($content);

         // Persist the message
         $this->em->persist($message);
         $this->em->flush();
    }

    /**
     * Handles the form submission for creating a message and persists it.
     *
     * @param FormInterface $form
     * @param Trick $trick
     * @param User $user
     * @return bool Returns true if form is valid and message is created
     */
    public function handleMessageForm(FormInterface $form, Trick $trick, User $user): bool
    {
        if ($form->isSubmitted() && $form->isValid()) {
            // Get the content from the form and create the message
            $content = $form->getData()->getContent();
            $this->createAndPostMessage($trick, $user, $content);
            return true; // Indicating success
        }
        return false; // Indicating failure
    }

    /**
     * Récupère les messages d'une figure avec pagination.
     *
     * @param Trick $trick
     * @param int $currentPage
     * @param int $limit
     * @return array [$messages, $currentPage, $totalPages]
     */
    public function getMessagesForTrickWithPagination(Trick $trick, int $currentPage = 1, int $limit = 10): array
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
