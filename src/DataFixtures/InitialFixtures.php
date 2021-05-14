<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\FederalState;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class InitialFixtures extends Fixture implements FixtureGroupInterface
{
    public const CATEGORIES = [
        'Bildung und Soziales',
        'Freizeit',
        'Infrastruktur',
        'Kunst und Kultur',
        'Mobilität',
        'Umwelt',
    ];

    public const FEDERAL_STATES = [
        'Baden-Württemberg',
        'Bayern',
        'Berlin',
        'Brandenburg',
        'Bremen',
        'Hamburg',
        'Hessen',
        'Mecklenburg-Vorpommern',
        'Niedersachsen',
        'Nordrhein-Westfalen',
        'Reinland-Pfalz',
        'Saarland',
        'Sachsen',
        'Sachsen-Anhalt',
        'Schleswig-Holstein',
        'Thüringen',
    ];

    public static function getGroups(): array
    {
        return ['initial'];
    }

    public function load(ObjectManager $manager)
    {
        foreach (self::CATEGORIES as $name) {
            $cat = new Category();
            $cat->setName($name);
            $manager->persist($cat);
        }

        foreach (self::FEDERAL_STATES as $name) {
            $state = new FederalState();
            $state->setName($name);
            $manager->persist($state);
        }

        $manager->flush();
    }
}
