<?php

namespace App\Tests\Functional\Service;

use App\Entity\Entrainement;
use App\Entity\Equipe;
use App\Service\PlanningService;
use App\Tests\Support\DatabaseTestCase;

final class PlanningServiceTest extends DatabaseTestCase
{
    private PlanningService $planning;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planning = static::getContainer()->get(PlanningService::class);
    }

    private function equipe(string $nom, string $categorie): Equipe
    {
        $equipe = (new Equipe())->setNom($nom)->setCategorie($categorie);
        $this->em->persist($equipe);
        $this->em->flush();

        return $equipe;
    }

    /** @return array<string, mixed> */
    private function training(array $override = []): array
    {
        return $override + ['type' => 'entrainement', 'categories' => ['U13'], 'date' => '2026-10-14', 'debut' => '18:00', 'fin' => '19:30'];
    }

    public function testParseDate(): void
    {
        self::assertSame('2026-02-28', $this->planning->parseDate('2026-02-28')?->format('Y-m-d'));
        self::assertNull($this->planning->parseDate('2026-02-30'), 'Le 30 février n\'existe pas.');
        self::assertNull($this->planning->parseDate('28/02/2026'));
        self::assertNull($this->planning->parseDate(''));
        self::assertNull($this->planning->parseDate(null));
    }

    public function testParseTime(): void
    {
        self::assertSame('18:05', $this->planning->parseTime('18:05')?->format('H:i'));
        self::assertNull($this->planning->parseTime('25:00'));
        self::assertNull($this->planning->parseTime('6h30'));
        self::assertNull($this->planning->parseTime(null));
    }

    public function testValidTrainingHasNoError(): void
    {
        $result = $this->planning->validate($this->training(['lieu' => ' Stade Michel Montmirel ']));

        self::assertSame([], $result['errors']);
        self::assertSame(Entrainement::TYPE_ENTRAINEMENT, $result['values']['type']);
        self::assertSame('Entraînement', $result['values']['titre']);
        self::assertSame('Stade Michel Montmirel', $result['values']['lieu']);
        self::assertSame(['U13'], $result['values']['categories']);
    }

    public function testTrainingNeedsACategoryAndRejectsUnknownOnes(): void
    {
        self::assertArrayHasKey('categories', $this->planning->validate($this->training(['categories' => []]))['errors']);
        self::assertArrayHasKey('categories', $this->planning->validate($this->training(['categories' => ['U99']]))['errors']);
    }

    public function testDateAndTimesAreRequired(): void
    {
        $errors = $this->planning->validate(['type' => 'entrainement', 'categories' => ['U13']])['errors'];

        self::assertArrayHasKey('date', $errors);
        self::assertArrayHasKey('debut', $errors);
        self::assertArrayNotHasKey('fin', $errors, 'L\'heure de fin est facultative.');
    }

    public function testEndMustBeAfterStart(): void
    {
        self::assertArrayHasKey('fin', $this->planning->validate($this->training(['debut' => '18:00', 'fin' => '18:00']))['errors']);
        self::assertArrayHasKey('fin', $this->planning->validate($this->training(['debut' => '18:00', 'fin' => '17:00']))['errors']);
        self::assertArrayHasKey('fin', $this->planning->validate($this->training(['fin' => 'tard']))['errors']);
    }

    public function testFieldLengthsAreBounded(): void
    {
        $errors = $this->planning->validate($this->training([
            'titre' => str_repeat('a', 151), 'lieu' => str_repeat('a', 151), 'description' => str_repeat('a', 2001),
        ]))['errors'];

        self::assertArrayHasKey('titre', $errors);
        self::assertArrayHasKey('lieu', $errors);
        self::assertArrayHasKey('description', $errors);
    }

    public function testRepeatingNeedsAnEndDateWithinTheLimit(): void
    {
        self::assertArrayHasKey('jusqua', $this->planning->validate($this->training(['repeter' => '1']))['errors']);
        self::assertArrayHasKey('jusqua', $this->planning->validate($this->training(['repeter' => '1', 'jusqua' => '2026-10-01']))['errors']);
        self::assertArrayHasKey('jusqua', $this->planning->validate($this->training(['repeter' => '1', 'jusqua' => '2030-10-14']))['errors'], 'Plus de 60 semaines.');
        self::assertSame([], $this->planning->validate($this->training(['repeter' => '1', 'jusqua' => '2026-12-16']))['errors']);
    }

    public function testMatchNeedsTwoDifferentTeamsAndGetsATitle(): void
    {
        $a = $this->equipe('ES Coutances', 'U13');
        $b = $this->equipe('Saint-Lô', 'U13');
        $base = ['type' => 'rencontre', 'date' => '2026-10-17', 'debut' => '15:00'];

        self::assertArrayHasKey('equipes', $this->planning->validate($base + ['equipes' => [$a->getId()]])['errors']);
        self::assertArrayHasKey('equipes', $this->planning->validate($base + ['equipes' => [$a->getId(), $a->getId()]])['errors']);
        self::assertArrayHasKey('equipes', $this->planning->validate($base + ['equipes' => [$a->getId(), 999999]])['errors']);

        $ok = $this->planning->validate($base + ['equipes' => [$a->getId(), $b->getId()]]);
        self::assertSame([], $ok['errors']);
        self::assertSame('ES Coutances – Saint-Lô', $ok['values']['titre']);
        self::assertSame(['U13'], $ok['values']['categories']);
    }

    public function testEventNeedsATitleAndAValidSharing(): void
    {
        $base = ['type' => 'evenement', 'date' => '2026-10-17', 'debut' => '15:00'];

        self::assertArrayHasKey('titre', $this->planning->validate($base)['errors']);
        self::assertSame([], $this->planning->validate($base + ['titre' => 'Assemblée générale'])['errors']);
        self::assertArrayHasKey('partage', $this->planning->validate($base + ['titre' => 'AG', 'partage' => 'groupe'])['errors']);
        self::assertArrayHasKey('partage', $this->planning->validate($base + ['titre' => 'AG', 'partage' => 'utilisateurs'])['errors']);
        self::assertSame([], $this->planning->validate($base + ['titre' => 'AG', 'partage' => 'groupe', 'roles' => ['ROLE_ADMIN']])['errors']);
    }

    public function testSharingRolesAreWhitelisted(): void
    {
        $result = $this->planning->validate(['type' => 'evenement', 'titre' => 'AG', 'date' => '2026-10-17', 'debut' => '15:00', 'partage' => 'groupe', 'roles' => ['ROLE_ADMIN', 'ROLE_SUPER_PIRATE']]);

        self::assertSame(['ROLE_ADMIN'], $result['values']['partageRoles']);
    }

    public function testUnknownTypeFallsBackToTraining(): void
    {
        self::assertSame(Entrainement::TYPE_ENTRAINEMENT, $this->planning->validate($this->training(['type' => 'n-importe-quoi']))['values']['type']);
    }
}
