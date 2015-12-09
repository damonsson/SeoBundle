<?php

/*
 * This file is part of the Symfony CMF package.
 *
 * (c) 2011-2015 Symfony CMF
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Cmf\Bundle\SeoBundle\Tests\WebTest\Admin;

use Symfony\Cmf\Component\Testing\Functional\BaseTestCase;

/**
 * This test will cover all behavior with the provides admin extension.
 *
 * @author Maximilian Berghoff <Maximilian.Berghoff@gmx.de>
 */
class SeoContentAdminExtensionTest extends BaseTestCase
{
    public function setUp()
    {
        $this->db('PHPCR')->loadFixtures(array(
            'Symfony\Cmf\Bundle\SeoBundle\Tests\Resources\DataFixtures\Phpcr\LoadContentData',
        ));
    }

    public function testAdminDashboard()
    {
        $this->getClient()->request('GET', '/admin/dashboard');
        $response = $this->getClient()->getResponse();

        $this->assertResponseSuccess($response);
    }

    public function testAdminExtensionExists()
    {
        $crawler = $this->getClient()->request('GET', '/admin/cmf/seo/seoawarecontent/list');
        $response = $this->getClient()->getResponse();

        $this->assertResponseSuccess($response);
        $this->assertCount(1, $crawler->filter('html:contains("content-1")'));
    }

    public function testItemEditView()
    {
        $crawler = $this->getClient()->request('GET', '/admin/cmf/seo/seoawarecontent/test/content/content-1/edit');
        $response = $this->getClient()->getResponse();

        $this->assertResponseSuccess($response);

        $this->assertCount(1, $crawler->filter('html:contains("SEO")'));
        $this->assertCount(1, $crawler->filter('html:contains("Page title")'));
        $this->assertCount(1, $crawler->filter('html:contains("Original URL")'));
        $this->assertCount(1, $crawler->filter('html:contains("description")'));
        $this->assertCount(1, $crawler->filter('html:contains("keywords")'));
    }

    public function testExtraPropertyEditView()
    {
        $crawler = $this->getClient()->request('GET', '/admin/cmf/seo/seoawarecontent/test/content/content-extra/edit');
        $response = $this->getClient()->getResponse();

        $this->assertResponseSuccess($response);
        $this->assertCount(1, $crawler->filter('html:contains("Key")'));
        $this->assertCount(1, $crawler->filter('html:contains("Value")'));
    }

    public function testItemCreate()
    {
        $crawler = $this->client->request('GET', '/admin/cmf/seo/seoawarecontent/create');
        $response = $this->client->getResponse();

        $this->assertResponseSuccess($response);

        $this->assertCount(1, $crawler->filter('html:contains("SEO")'));
        $this->assertCount(1, $crawler->filter('html:contains("Page title")'));
        $this->assertCount(1, $crawler->filter('html:contains("Original URL")'));
        $this->assertCount(1, $crawler->filter('html:contains("description")'));
        $this->assertCount(1, $crawler->filter('html:contains("keywords")'));
    }
}
