<?php

namespace App\DataFixtures;

use App\Entity\Trick;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class TrickFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $slugger = new AsciiSlugger();

        // Define a list of trick names and descriptions
        $tricks = [
            ['name' => 'Ollie', 'description' => 'Un saut de base où le rider poppe son board.'],
            ['name' => 'Nosegrab', 'description' => 'Attraper la partie avant du board en l\'air.'],
            ['name' => 'Tailgrab', 'description' => 'Attraper la partie arrière du board en l\'air.'],
            ['name' => 'Mute Grab', 'description' => 'Grab effectué avec la main arrière sur la partie avant.'],
            ['name' => 'Stale Grab', 'description' => 'Grab effectué avec la main avant sur la partie arrière.'],
            ['name' => 'Indy Grab', 'description' => 'Grab classique entre les fixations.'],
            ['name' => 'Method Grab', 'description' => 'Grab stylé avec une torsion du board.'],
            ['name' => 'Truck Driver', 'description' => 'Rotation combinée à un grab, inspiré du skateboard.'],
            ['name' => 'Switch 180', 'description' => 'Rotation de 180° en changeant de stance.'],
            ['name' => 'Backside 180', 'description' => 'Rotation de 180° avec le dos face à la direction du saut.'],
            ['name' => 'Frontside 180', 'description' => 'Rotation de 180° avec le ventre face à la direction du saut.'],
            ['name' => 'Cork 180', 'description' => 'Rotation de 180° avec un effet off-axis.'],
            ['name' => 'Backflip', 'description' => 'Un flip arrière audacieux.'],
            ['name' => 'Frontflip', 'description' => 'Un flip avant spectaculaire.'],
            ['name' => 'Double Cork', 'description' => 'Double rotation off-axis en l\'air.'],
            ['name' => 'Nose Slide', 'description' => 'Glisser sur un rail en utilisant le nez du board.'],
            ['name' => 'Tail Slide', 'description' => 'Glisser sur un rail en utilisant la queue du board.'],
            ['name' => 'Board Slide', 'description' => 'Glisser sur un rail avec le board en position perpendiculaire.'],
            ['name' => 'Lipslide', 'description' => 'Glisser depuis le rebord d’un rail.'],
            ['name' => 'Stair Slide', 'description' => 'Glisser sur des marches ou des structures similaires.'],
        ];

        // Define a list of group names
        $groups = ['Grab', 'Flip', 'Slide', 'Rotation'];

        foreach ($tricks as $index => $data) {
            $trick = new Trick();
            $trick->setName($data['name']);
            $trick->setDescription($data['description']);
            // Cycle through the groups
            $group = $groups[$index % count($groups)];
            $trick->setGroupName($group);
            $slug = strtolower(trim($slugger->slug($data['name']), '-'));
            $trick->setSlug($slug);
            // Assign the admin user as creator.
            $trick->setCreator($this->getReference('admin_user'));

            $manager->persist($trick);
        }
        $manager->flush();
    }

    public function getDependencies()
    {
         return [
             UserFixtures::class,
         ];
    }
}
