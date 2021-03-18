<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApplicationTest extends WebTestCase
{
    public function testGetIndex()
    {
        self::createClient()->request('GET', '/');

        self::assertResponseStatusCodeSame(200);
    }

    public function testGetError()
    {
        self::createClient()->request('GET', '/error');

        self::assertResponseStatusCodeSame(404);
    }
}
