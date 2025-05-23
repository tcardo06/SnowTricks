<?php
namespace App\Service;

use App\Entity\Trick;
use App\Entity\Illustration;
use App\Entity\Video;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class TrickManager
{
    private $em;
    private $mediaManager;

    public function __construct(EntityManagerInterface $entityManager, MediaManager $mediaManager)
    {
         $this->em = $entityManager;
         $this->mediaManager = $mediaManager;
    }

    /**
     * Crée une nouvelle figure, en générant le slug et en traitant les médias.
     */
    public function createTrickWithMedia(Trick $trick, array $uploadedImages, array $videoEmbeds): void
    {
        // Validate the trick name
        $this->validateUniqueTrickName($trick);

        // Generate the slug
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $trick->getName()), '-'));
        $trick->setSlug($slug);

        // Handle media uploads via MediaManager
        foreach ($uploadedImages as $image) {
            $this->mediaManager->addImage($trick, $image); // Add images
        }

        foreach ($videoEmbeds as $video) {
            $this->mediaManager->addVideo($trick, $video); // Add videos
        }

        // Persist the trick
        $this->em->persist($trick);
        $this->em->flush();
    }

    /**
     * Validates that the trick name is unique.
     *
     * @throws \Exception if the trick name already exists
     */
    private function validateUniqueTrickName(Trick $trick): void
    {
        $existingTrick = $this->em->getRepository(Trick::class)->findOneBy(['name' => $trick->getName()]);
        if ($existingTrick !== null) {
            throw new \Exception('Ce nom de figure existe déjà. Veuillez en choisir un autre.');
        }
    }

    /**
     * Verifies that the user is the creator of the trick.
     *
     * @throws AccessDeniedException if the user is not the creator
     */
    public function assertUserIsCreator(Trick $trick, $currentUser): void
    {
        if ($trick->getCreator()->getId() !== $currentUser->getId()) {
            throw new AccessDeniedException('Vous n’êtes pas autorisé à modifier cette figure.');
        }
    }

    /**
     * Handles the editing of a Trick.
     *
     * @param Trick $trick The trick to be edited
     * @return bool True if the edit was successful
     */
    public function handleTrickEdit(Trick $trick): bool
    {
        // Generate the new slug for the trick
        $newSlug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $trick->getName()), '-'));
        $trick->setSlug($newSlug);

        // Persist the updated trick
        $this->em->flush();

        return true; // Indicating the edit was successful
    }

    /**
     * Deletes a Trick from the database.
     */
    public function deleteTrick(Trick $trick): void
    {
        $this->em->remove($trick);
        $this->em->flush();
    }

    /**
     * Gets a Trick by its slug.
     *
     * @return Trick|null
     */
    public function getTrickBySlug(string $slug): ?Trick
    {
        return $this->em->getRepository(Trick::class)->findOneBy(['slug' => $slug]);
    }

    /**
     * Retrieves a paginated list of tricks.
     */
    public function getPaginatedTricks(int $limit, int $offset): array
    {
        return $this->em->getRepository(Trick::class)
            ->createQueryBuilder('t')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Generates the HTML for a list of tricks.
     *
     * @param Trick[] $tricks
     * @return string
     */
    public function generateTrickHtml(array $tricks): string
    {
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
                '/trick/' . $trick->getSlug(),
                htmlspecialchars($trick->getName(), ENT_QUOTES),
                htmlspecialchars($trick->getDescription(), ENT_QUOTES)
            );
        }
        return $html;
    }
}
