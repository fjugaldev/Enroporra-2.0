<?php

namespace Enroporra\EnroporraBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DefaultControllerTest extends WebTestCase
{
    public function testIndex()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/n');

        $this->assertTrue($crawler->filter('html:contains("Home")')->count() > 0);
    }
}
