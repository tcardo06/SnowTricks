<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;
    
    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
         $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
         $admin = new User();
         $admin->setUsername('admin');
         $admin->setEmail('admin@example.com');
         $admin->setFullName('Admin User');
         $admin->setIsActive(true);
         $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
         // Optional, photo remains null for now.
         $manager->persist($admin);
         $manager->flush();

         // Save a reference to the admin user for later use
         $this->addReference('admin_user', $admin);
    }
}
