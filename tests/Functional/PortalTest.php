<?php

namespace App\Tests\Functional;

use App\Entity\Saison;
use App\Entity\User;
use App\Tests\Support\DatabaseTestCase;
use Symfony\Component\Routing\RouterInterface;

final class PortalTest extends DatabaseTestCase
{
    private int $licencieId;
    private int $familleUserId;
    private int $licencieUserId;
    private int $saisonId;

    protected function setUp(): void
    {
        parent::setUp();
        $saison = $this->createSaison('2099-2100', '2099-07-01', '2100-06-30', true);
        $licencie = $this->createLicencie($saison, 'Portail', 'Emma');
        $this->saisonId = $saison->getId();
        $this->licencieId = $licencie->getId();
        $this->familleUserId = $licencie->getFamille()->getUser()->getId();
        $this->licencieUserId = $licencie->getUser()->getId();
        $this->em->clear(); // comme en production : tout est relu depuis la base
    }

    private function familyUser(): User
    {
        return $this->em->find(User::class, $this->familleUserId);
    }

    public function testFamilyAccountNeverGetsAServerErrorOnAnyPortalPage(): void
    {
        $this->loginAs([], $this->familyUser());

        $failures = [];
        foreach (static::getContainer()->get(RouterInterface::class)->getRouteCollection() as $name => $route) {
            $path = $route->getPath();
            if (!str_starts_with($path, '/mon-compte') || str_contains($path, '{') || !\in_array('GET', $route->getMethods() ?: ['GET'], true)) {
                continue;
            }
            if (\in_array($name, ['portail_logout', 'portail_parametres_export'], true)) {
                continue;
            }
            $this->client->request('GET', $path);
            if ($this->client->getResponse()->getStatusCode() >= 500) {
                $failures[] = sprintf('%s (%s) → HTTP %d', $name, $path, $this->client->getResponse()->getStatusCode());
            }
        }

        self::assertSame([], $failures);
    }

    public function testFamilyHomeListsItsLicensees(): void
    {
        $this->loginAs([], $this->familyUser());

        $this->client->request('GET', '/mon-compte');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Emma');
    }

    public function testFamilyCanOpenItsOwnLicenseeButNotAnotherFamilys(): void
    {
        $other = $this->createLicencie($this->em->find(Saison::class, $this->saisonId), 'Autre', 'Intrus');
        $otherId = $other->getId();
        $this->em->clear();
        $this->loginAs([], $this->familyUser());

        $this->client->request('GET', '/mon-compte/licencie/'.$this->licencieId);
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/mon-compte/licencie/'.$otherId);
        self::assertResponseStatusCodeSame(403);
    }

    public function testLicenseeSeesTheirOwnPage(): void
    {
        $this->loginAs([], $this->em->find(User::class, $this->licencieUserId));

        $this->client->request('GET', '/mon-compte/licencie/'.$this->licencieId);

        self::assertResponseIsSuccessful();
    }

    public function testPersonalDataExportContainsTheDataButNoSecrets(): void
    {
        $this->loginAs([], $this->familyUser());

        $this->client->request('GET', '/mon-compte/parametres/confidentialite/export');

        self::assertResponseIsSuccessful();
        $body = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Portail', $body);
        self::assertStringNotContainsString((string) $this->familyUser()->getPassword(), $body);
    }

    public function testAccountDeletionNeedsPostAndCsrf(): void
    {
        $this->loginAs([], $this->familyUser());

        $this->client->request('GET', '/mon-compte/parametres/confidentialite/supprimer');
        self::assertResponseStatusCodeSame(405);

        $this->client->request('POST', '/mon-compte/parametres/confidentialite/supprimer', ['_token' => 'faux']);
        self::assertFalse($this->familyUser()->isAnonymized(), 'Sans jeton valide, le compte ne doit pas être supprimé.');
    }

    public function testPortalPagesAreNotIndexable(): void
    {
        $this->loginAs([], $this->familyUser());

        $this->client->request('GET', '/mon-compte');

        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex');
    }

    public function testExpiredLicenceBlocksLogin(): void
    {
        $expired = $this->createSaison('2020-2021', '2020-07-01', '2021-06-30');
        $old = $this->createLicencie($expired, 'Perime', 'Ancien');

        $crawler = $this->client->request('GET', '/mon-compte/connexion');
        $this->client->submit($crawler->filter('form[method=post]')->first()->form(['email' => $old->getFamille()->getUser()->getEmail(), 'password' => self::PASSWORD]));
        $this->client->request('GET', '/mon-compte');

        self::assertResponseRedirects();
        self::assertStringContainsString('/mon-compte/connexion', (string) $this->client->getResponse()->headers->get('Location'));
    }
}
