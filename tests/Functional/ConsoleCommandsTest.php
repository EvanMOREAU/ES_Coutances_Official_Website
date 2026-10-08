<?php

namespace App\Tests\Functional;

use App\Entity\PageContenu;
use App\Entity\User;
use App\Tests\Support\DatabaseTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ConsoleCommandsTest extends DatabaseTestCase
{
    private function command(string $name): CommandTester
    {
        return new CommandTester((new Application(self::$kernel))->find($name));
    }

    public function testCreateUserCreatesAnAccountWithAHashedPassword(): void
    {
        $tester = $this->command('app:create-user');

        $tester->execute(['email' => 'nouveau@test.local', 'password' => 'S3cret-Pass!', 'nom' => 'Nouveau Compte', 'role' => 'ROLE_ADMIN']);

        $tester->assertCommandIsSuccessful();
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'nouveau@test.local']);
        self::assertInstanceOf(User::class, $user);
        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertNotSame('S3cret-Pass!', $user->getPassword());
        self::assertTrue(password_verify('S3cret-Pass!', (string) $user->getPassword()));
    }

    public function testCreateUserDefaultsToTheEditorRole(): void
    {
        $this->command('app:create-user')->execute(['email' => 'editeur@test.local', 'password' => 'x', 'nom' => 'Editeur']);

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'editeur@test.local']);
        self::assertSame(['ROLE_EDITOR', 'ROLE_USER'], $user->getRoles());
    }

    public function testInitPagesIsIdempotent(): void
    {
        $repository = $this->em->getRepository(PageContenu::class);
        $before = $repository->count([]);

        $this->command('app:init-pages')->execute([]);
        $afterFirst = $repository->count([]);
        $second = $this->command('app:init-pages');
        $second->execute([]);

        self::assertGreaterThanOrEqual($before, $afterFirst);
        self::assertSame($afterFirst, $repository->count([]), 'Relancer la commande ne doit pas créer de doublons.');
        self::assertNotNull($repository->findOneBy(['slug' => 'histoire']));
        self::assertNotNull($repository->findOneBy(['slug' => 'infrastructure']));
        self::assertSame(Command::SUCCESS, $second->getStatusCode());
    }

    public function testPurgeChatDocumentsRuns(): void
    {
        $tester = $this->command('app:messagerie:purger-documents');

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('document(s)', $tester->getDisplay());
    }

    public function testAuditPurgeDeletesOnlyEntriesOlderThanTheRetention(): void
    {
        $connection = $this->em->getConnection();
        $insert = static fn (string $date, string $summary) => $connection->insert('audit_log', [
            'occurred_at' => $date, 'type' => 'requete', 'category' => 'Autre', 'summary' => $summary, 'source' => 'web',
        ]);
        $insert((new \DateTimeImmutable('-13 months'))->format('Y-m-d H:i:s'), 'ancienne-entree');
        $insert((new \DateTimeImmutable('-1 month'))->format('Y-m-d H:i:s'), 'entree-recente');

        $tester = $this->command('app:audit:purger');
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $summaries = $connection->fetchFirstColumn("SELECT summary FROM audit_log WHERE summary IN ('ancienne-entree', 'entree-recente')");
        self::assertSame(['entree-recente'], $summaries);
    }

    public function testProductionCheckReportsAnInsecureConfiguration(): void
    {
        $tester = $this->command('app:securite:verifier');
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode(), 'APP_ENV=test et APP_SECRET du dépôt : le contrôle doit échouer.');
        self::assertStringContainsString('APP_ENV=prod', $tester->getDisplay());
    }
}
