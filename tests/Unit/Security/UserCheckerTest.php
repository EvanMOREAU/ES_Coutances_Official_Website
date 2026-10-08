<?php

namespace App\Tests\Unit\Security;

use App\Entity\Famille;
use App\Entity\Licencie;
use App\Entity\Saison;
use App\Entity\User;
use App\Repository\FamilleRepository;
use App\Repository\LicencieRepository;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

final class UserCheckerTest extends TestCase
{
    /** @param list<Licencie> $own */
    private function checker(array $own = [], ?Famille $famille = null): UserChecker
    {
        $licencies = $this->createStub(LicencieRepository::class);
        $licencies->method('findBy')->willReturn($own);
        $familles = $this->createStub(FamilleRepository::class);
        $familles->method('findOneBy')->willReturn($famille);

        return new UserChecker($licencies, $familles);
    }

    private function licencie(string $seasonEnd, bool $actif = true): Licencie
    {
        return (new Licencie())->setActif($actif)->setSaison(
            (new Saison())->setLibelle('S')->setDateDebut(new \DateTimeImmutable('2025-07-01'))->setDateFin(new \DateTimeImmutable($seasonEnd)),
        );
    }

    public function testAnonymizedAccountIsRefused(): void
    {
        $user = (new User())->setEmail('a@test.local')->markAnonymized();

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Ce compte a été supprimé.');

        $this->checker()->checkPreAuth($user);
    }

    public function testStaffIsNeverBlockedByLicences(): void
    {
        $user = (new User())->setEmail('staff@test.local')->setRoles(['ROLE_EDITOR']);

        $this->checker([$this->licencie('-1 year')])->checkPreAuth($user);

        $this->addToAssertionCount(1);
    }

    public function testAccountWithoutAnyLicenseeCanLogIn(): void
    {
        $this->checker()->checkPreAuth((new User())->setEmail('nouveau@test.local'));

        $this->addToAssertionCount(1);
    }

    public function testAccountWithACurrentLicenceCanLogIn(): void
    {
        $this->checker([$this->licencie('-1 year'), $this->licencie('+1 month')])->checkPreAuth((new User())->setEmail('a@test.local'));

        $this->addToAssertionCount(1);
    }

    public function testAccountWhoseLicencesAllEndedIsBlocked(): void
    {
        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessageMatches('/licence n\'est plus valide/');

        $this->checker([$this->licencie('-1 month')])->checkPreAuth((new User())->setEmail('a@test.local'));
    }

    public function testInactiveLicenseeIsBlocked(): void
    {
        $this->expectException(CustomUserMessageAccountStatusException::class);

        $this->checker([$this->licencie('+1 month', false)])->checkPreAuth((new User())->setEmail('a@test.local'));
    }

    public function testFamilyLicenseesCountToo(): void
    {
        $famille = new Famille();
        $famille->addLicencie($this->licencie('+1 month'));

        $this->checker([], $famille)->checkPreAuth((new User())->setEmail('parent@test.local'));

        $this->addToAssertionCount(1);
    }
}
