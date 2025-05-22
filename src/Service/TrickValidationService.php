<?php

namespace App\Service;

use App\Entity\Trick;
use Doctrine\ORM\EntityManagerInterface;

class TrickValidationService
{
    private $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    public function validateUniqueName(Trick $trick): bool
    {
        $existingTrick = $this->em->getRepository(Trick::class)->findOneBy(['name' => $trick->getName()]);
        return $existingTrick === null;
    }
}
