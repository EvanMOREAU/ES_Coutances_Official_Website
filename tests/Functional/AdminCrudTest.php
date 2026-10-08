<?php

namespace App\Tests\Functional;

use App\Entity\Equipe;
use App\Entity\Saison;
use App\Tests\Support\DatabaseTestCase;

final class AdminCrudTest extends DatabaseTestCase
{
    public function testTeamCanBeCreatedEditedAndDeleted(): void
    {
        $this->loginAsDev();

        // Création
        $crawler = $this->client->request('GET', '/admin/equipes/nouvelle');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name=equipe]')->form(['equipe[nom]' => 'ES Coutances U13 A', 'equipe[categorie]' => 'U13']);
        $this->client->submit($form);
        self::assertResponseRedirects('/admin/equipes');
        $equipe = $this->em->getRepository(Equipe::class)->findOneBy(['nom' => 'ES Coutances U13 A']);
        self::assertNotNull($equipe);
        self::assertSame('U13', $equipe->getCategorie());

        // Modification
        $crawler = $this->client->request('GET', sprintf('/admin/equipes/%d/modifier', $equipe->getId()));
        $this->client->submit($crawler->filter('form[name=equipe]')->form(['equipe[nom]' => 'ES Coutances U13 B']));
        self::assertResponseRedirects('/admin/equipes');
        $this->em->clear();
        self::assertSame('ES Coutances U13 B', $this->em->find(Equipe::class, $equipe->getId())->getNom());

        // La liste l'affiche
        $this->client->request('GET', '/admin/equipes');
        self::assertSelectorTextContains('body', 'ES Coutances U13 B');
    }

    public function testInvalidTeamIsRedisplayedWithoutBeingSaved(): void
    {
        $this->loginAsDev();
        $before = $this->em->getRepository(Equipe::class)->count([]);

        $crawler = $this->client->request('GET', '/admin/equipes/nouvelle');
        $this->client->submit($crawler->filter('form[name=equipe]')->form(['equipe[nom]' => '']));

        self::assertResponseStatusCodeSame(422);
        self::assertSame($before, $this->em->getRepository(Equipe::class)->count([]));
    }

    public function testDeleteNeedsAValidCsrfToken(): void
    {
        $this->loginAsDev();
        $equipe = (new Equipe())->setNom('À supprimer')->setCategorie('U11');
        $this->em->persist($equipe);
        $this->em->flush();
        $id = $equipe->getId();

        $this->client->request('POST', sprintf('/admin/equipes/%d/supprimer', $id), ['_token' => 'faux']);
        self::assertResponseRedirects('/admin/equipes');
        self::assertNotNull($this->em->find(Equipe::class, $id), 'Sans jeton valide, rien ne doit être supprimé.');

        $this->client->request('POST', sprintf('/admin/equipes/%d/supprimer', $id));
        self::assertNotNull($this->em->find(Equipe::class, $id));

        $crawler = $this->client->request('GET', '/admin/equipes');
        $token = $crawler->filter(sprintf('form[action$="/admin/equipes/%d/supprimer"] input[name=_token]', $id))->attr('value');
        $this->client->request('POST', sprintf('/admin/equipes/%d/supprimer', $id), ['_token' => $token]);
        self::assertResponseRedirects('/admin/equipes');
        $this->em->clear();
        self::assertNull($this->em->find(Equipe::class, $id));
    }

    public function testDeleteIsNotAllowedWithGet(): void
    {
        $this->loginAsDev();
        $equipe = (new Equipe())->setNom('Intouchable')->setCategorie('U11');
        $this->em->persist($equipe);
        $this->em->flush();

        $this->client->request('GET', sprintf('/admin/equipes/%d/supprimer', $equipe->getId()));

        self::assertResponseStatusCodeSame(405);
    }

    public function testEditorWithOnlyViewPermissionCannotCreate(): void
    {
        $profil = (new \App\Entity\ProfilAutorisation())->setNom('Lecteur équipes')->setPermissions(['equipe.voir']);
        $this->em->persist($profil);
        $user = $this->createUser(['ROLE_EDITOR'])->setProfil($profil);
        $this->em->flush();
        $this->loginAs([], $user);

        $this->client->request('GET', '/admin/equipes');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/admin/equipes/nouvelle');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/admin/equipes/1/supprimer');
        self::assertResponseStatusCodeSame(403);
    }

    public function testSeasonCanBeCreated(): void
    {
        $this->loginAsDev();

        $crawler = $this->client->request('GET', '/admin/saisons/nouvelle');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name=saison]')->form();
        $form['saison[libelle]'] = '2098-2099';
        $form['saison[dateDebut]'] = '2098-07-01';
        $form['saison[dateFin]'] = '2099-06-30';
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/saisons');
        self::assertNotNull($this->em->getRepository(Saison::class)->findOneBy(['libelle' => '2098-2099']));
    }

    public function testToggleAndBulkActionsRejectUnknownTypes(): void
    {
        $this->loginAsDev();

        $this->client->request('POST', '/admin/basculer/inconnu/1');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/admin/actions-groupees/inconnu');
        self::assertResponseStatusCodeSame(403);
    }
}
