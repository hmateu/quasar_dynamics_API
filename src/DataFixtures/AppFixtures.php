<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Note;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Users
        $user1 = new User();
        $user1->setName('Héctor');
        $user1->setSurname('Mateu Ortolá');
        $user1->setEmail('hmateu.ortola@gmail.com');
        $manager->persist($user1);
        
        $user2 = new User();
        $user2->setName('Antonio');
        $user2->setSurname('Corraliza León');
        $user2->setEmail('antonio@gmail.com');
        $manager->persist($user2);

        $user3 = new User();
        $user3->setName('Marta');
        $user3->setSurname('Oltra Sanchis');
        $user3->setEmail('marta@gmail.com');
        $manager->persist($user3);

        // Categories
        $category1 = new Category();
        $category1->setName('Diseño');
        $category1->setDescription('Categoría que engloba todas las notas relacionadas con el diseño');
        $manager->persist($category1);

        $category2 = new Category();
        $category2->setName('Frontend');
        $category2->setDescription('Categoría que engloba todas las notas relacionadas con el frontend');
        $manager->persist($category2);

        $category3 = new Category();
        $category3->setName('Backend');
        $category3->setDescription('Categoría que engloba todas las notas relacionadas con el backend');
        $manager->persist($category3);

        // Notes
        $note1 = new Note();
        $note1->setDescription('Diseña la vista Home');
        $note1->setUser($user3);
        $note1->addCategory($category1);
        $manager->persist($note1);

        $note2 = new Note();
        $note2->setDescription('Diseña el diagrama de la base de datos');
        $note2->setUser($user1);
        $note2->addCategory($category3);
        $manager->persist($note2);

        $note3 = new Note();
        $note3->setDescription('Desarrolla la vista Login');
        $note3->setUser($user2);
        $note3->addCategory($category2);
        $manager->persist($note3);

        $manager->flush();
    }
}
