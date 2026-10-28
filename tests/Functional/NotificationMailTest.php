<?php

namespace App\Tests\Functional;

use App\Entity\Entrainement;
use App\Entity\Famille;
use App\Entity\Licencie;
use App\Entity\User;
use App\Service\Notification\NotificationMailer;
use App\Tests\Support\DatabaseTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mime\Email;

/** Les e-mails « il y a du nouveau » partent aux comptes qui les ont gardés activés, dans leur langue. */
final class NotificationMailTest extends DatabaseTestCase
{
    private function mailer(): NotificationMailer
    {
        return static::getContainer()->get(NotificationMailer::class);
    }

    /** Envoie ce que la requête en cours a repéré (en production : à la fin de la réponse, sur kernel.terminate). */
    private function terminate(): void
    {
        $this->mailer()->flush();
    }

    /** @return list<array{to: string, subject: string, html: string}> */
    private function sent(): array
    {
        $out = [];
        foreach (self::getMailerMessages() as $message) {
            self::assertInstanceOf(Email::class, $message);
            $out[] = ['to' => $message->getTo()[0]->getAddress(), 'subject' => (string) $message->getSubject(), 'html' => (string) $message->getHtmlBody()];
        }

        return $out;
    }

    /** @return list<string> sujets reçus par ce compte */
    private function subjectsFor(User $user): array
    {
        return array_values(array_map(
            static fn (array $m): string => $m['subject'],
            array_filter($this->sent(), static fn (array $m): bool => $m['to'] === $user->getEmail()),
        ));
    }

    private function startClean(): void
    {
        $this->terminate();
    }

    public function testTheOnlyAdministratorIsToldAboutANewMember(): void
    {
        $admin  = $this->createUser(['ROLE_ADMIN']);
        $saison = $this->createSaison('2026-2027', '2026-07-01', '2027-06-30', true);
        $this->startClean();
        $before = \count($this->subjectsFor($admin));

        $this->createLicencie($saison, 'Durand', 'Emma');
        $this->terminate();

        $subjects = $this->subjectsFor($admin);
        self::assertGreaterThan($before, \count($subjects));
        self::assertContains('Nouveau licencié — ES Coutances', $subjects, 'Le créateur lui-même doit recevoir l\'e-mail tant qu\'il ne l\'a pas désactivé.');
    }

    public function testDisabledPreferenceStopsTheEmail(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);
        $admin->setNotificationPreferences(['email' => ['licencies' => false]]);
        $saison = $this->createSaison('2026-2027', '2026-07-01', '2027-06-30', true);
        $this->em->flush();
        $this->startClean();

        $this->createLicencie($saison, 'Durand', 'Emma');
        $this->terminate();

        self::assertNotContains('Nouveau licencié — ES Coutances', $this->subjectsFor($admin));
        self::assertContains('Nouveaux comptes — ES Coutances', $this->subjectsFor($admin), 'Les autres rubriques restent actives.');
    }

    public function testEmailIsWrittenInTheRecipientsLanguage(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);
        $admin->setLocale('de');
        $saison = $this->createSaison('2026-2027', '2026-07-01', '2027-06-30', true);
        $this->em->flush();
        $this->startClean();

        $this->createLicencie($saison, 'Durand', 'Emma');
        $this->terminate();

        self::assertContains('Neues Mitglied — ES Coutances', $this->subjectsFor($admin));
        $mail = array_values(array_filter($this->sent(), static fn (array $m): bool => 'Neues Mitglied — ES Coutances' === $m['subject']))[0];
        self::assertStringContainsString('Hallo', $mail['html']);
    }

    public function testBulkCreationGivesOneSummaryAndNothingToFamilies(): void
    {
        $admin  = $this->createUser(['ROLE_ADMIN']);
        $saison = $this->createSaison('2026-2027', '2026-07-01', '2027-06-30', true);
        $this->startClean();

        $famille = null;
        for ($i = 1; $i <= 5; ++$i) {
            $licencie = $this->createLicencie($saison, 'Import'.$i, 'Enfant');
            $famille  = $licencie->getFamille();
        }
        $this->terminate();

        $summaries = array_filter($this->subjectsFor($admin), static fn (string $s): bool => str_contains($s, '5 nouveaux licenciés'));
        self::assertCount(1, $summaries);
        self::assertSame([], $this->subjectsFor($famille?->getUser() ?? $admin) === $this->subjectsFor($admin) ? [] : $this->subjectsFor($famille->getUser()), 'Aucun e-mail aux familles lors d\'une saisie en série.');
    }

    public function testFamilyIsToldWhenAMemberIsAddedToIt(): void
    {
        $saison = $this->createSaison('2026-2027', '2026-07-01', '2027-06-30', true);
        $first  = $this->createLicencie($saison, 'Martin', 'Lucas');
        $this->startClean();
        $famille = $first->getFamille();
        self::assertInstanceOf(Famille::class, $famille);
        $familyUser = $famille->getUser();
        self::assertInstanceOf(User::class, $familyUser);

        $second = (new Licencie())
            ->setNom('Martin')->setPrenom('Jade')
            ->setDateNaissance(new \DateTimeImmutable('2016-03-01'))
            ->setFamille($famille)
            ->setUser($this->createUser(['ROLE_LICENCIE']))
            ->setSaison($saison);
        $this->em->persist($second);
        $this->em->flush();
        $this->terminate();

        self::assertContains('Jade Martin a été ajouté(e) à votre famille — ES Coutances', $this->subjectsFor($familyUser));
    }

    public function testNewPlanningEventReachesStaffWhoKeptIt(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);
        $this->startClean();

        $event = (new Entrainement())
            ->setTitre('Match amical')
            ->setDate(new \DateTimeImmutable('+3 days'))
            ->setHeureDebut(new \DateTimeImmutable('14:00'));
        $this->em->persist($event);
        $this->em->flush();
        $this->terminate();

        self::assertContains('Nouvel entraînement : Match amical — ES Coutances', $this->subjectsFor($admin));
    }

    public function testDayBeforeReminderCommand(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);
        $event = (new Entrainement())
            ->setTitre('Entraînement U11')
            ->setDate(new \DateTimeImmutable('tomorrow'))
            ->setHeureDebut(new \DateTimeImmutable('18:00'));
        $this->em->persist($event);
        $this->em->flush();
        $this->startClean();

        $application = new Application(self::$kernel);
        $tester      = new CommandTester($application->find('app:rappels:envoyer'));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertContains('Rappel pour demain : Entraînement U11 — ES Coutances', $this->subjectsFor($admin));
    }

    public function testContactFormMessageIsForwardedToStaffWhoKeptIt(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);
        $this->startClean();

        $crawler = $this->client->request('GET', '/contact');
        $form    = $crawler->filter('form[name=contact]')->form();
        foreach (['nom' => 'Sophie Leroy', 'email' => 'sophie@test.local', 'telephone' => '0600000000', 'type' => 'inscription', 'sujet' => 'Inscription U9', 'message' => 'Bonjour, je souhaite inscrire mon fils en U9.'] as $field => $value) {
            $form['contact['.$field.']'] = $value;
        }
        $this->client->submit($form);
        $this->terminate();

        self::assertContains('Nouveau message depuis le formulaire de contact — ES Coutances', $this->subjectsFor($admin));
    }

    public function testTestEmailButtonSendsAMessageToTheCurrentUser(): void
    {
        $user = $this->loginAs(['ROLE_ADMIN']);
        $this->startClean();

        $crawler = $this->client->request('GET', '/admin/parametres/preferences');
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->filter('form[action$="/preferences/test"]')->form());

        self::assertResponseRedirects('/admin/parametres/preferences');
        self::assertContains('E-mail de test — ES Coutances', $this->subjectsFor($user));
    }

    public function testNewCatalogueEntriesAreOfferedAndSaved(): void
    {
        $user = $this->loginAs(['ROLE_ADMIN']);

        $crawler = $this->client->request('GET', '/admin/parametres/preferences');
        self::assertResponseIsSuccessful();
        foreach (['adhesions', 'partenaires', 'contact', 'stock'] as $key) {
            self::assertCount(1, $crawler->filter('input[name="prefs[email]['.$key.']"]'), $key);
        }

        // On coche tout sauf « contact » : la décoche est mémorisée.
        $form = $crawler->filter('form[action$="/admin/parametres/preferences"]')->form();
        $form->remove('prefs[email][contact]');
        $this->client->submit($form);
        self::assertResponseRedirects();

        $this->em->clear();
        $saved = $this->em->getRepository(User::class)->find($user->getId());
        self::assertInstanceOf(User::class, $saved);
        self::assertFalse($saved->getNotificationPreferences()['email']['contact']);
        self::assertTrue($saved->getNotificationPreferences()['email']['adhesions']);
    }
}
