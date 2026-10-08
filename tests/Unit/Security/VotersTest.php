<?php

namespace App\Tests\Unit\Security;

use App\Entity\Famille;
use App\Entity\Licencie;
use App\Entity\User;
use App\Security\FamilleVoter;
use App\Security\LicencieVoter;
use App\Security\UserVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class VotersTest extends TestCase
{
    /** @param list<string> $roles */
    private function user(string $email, array $roles = []): User
    {
        return (new User())->setEmail($email)->setRoles($roles);
    }

    private function token(User $user): UsernamePasswordToken
    {
        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }

    public function testUserVoterAdminCanEditOrdinaryUser(): void
    {
        $voter = new UserVoter();
        $admin = $this->user('admin@x.fr', ['ROLE_ADMIN']);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($this->token($admin), $this->user('e@x.fr', ['ROLE_EDITOR']), ['USER_EDIT']));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($this->token($admin), $this->user('e@x.fr'), ['USER_DELETE']));
    }

    public function testUserVoterEditorCannotManageUsers(): void
    {
        $voter = new UserVoter();
        $editor = $this->user('editor@x.fr', ['ROLE_EDITOR']);

        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($this->token($editor), $this->user('u@x.fr'), ['USER_EDIT']));
    }

    public function testUserVoterOnlyDeveloperCanActOnDeveloper(): void
    {
        $voter = new UserVoter();
        $target = $this->user('dev@x.fr', ['ROLE_DEV']);

        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($this->token($this->user('a@x.fr', ['ROLE_ADMIN'])), $target, ['USER_DELETE']));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($this->token($this->user('d2@x.fr', ['ROLE_DEV'])), $target, ['USER_DELETE']));
    }

    public function testUserVoterAbstainsOnUnknownAttributeOrSubject(): void
    {
        $voter = new UserVoter();
        $token = $this->token($this->user('a@x.fr', ['ROLE_ADMIN']));

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, $this->user('u@x.fr'), ['AUTRE']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, new \stdClass(), ['USER_EDIT']));
    }

    public function testFamilleVoterOnlyOwnerSeesFamily(): void
    {
        $owner = $this->user('owner@x.fr');
        $other = $this->user('other@x.fr');
        $famille = (new Famille())->setUser($owner);
        $voter = new FamilleVoter();

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($this->token($owner), $famille, ['FAMILLE_VIEW']));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($this->token($other), $famille, ['FAMILLE_VIEW']));
    }

    public function testLicencieVoterAllowsLicenseeAndFamilyOnly(): void
    {
        $parent = $this->user('parent@x.fr');
        $enfant = $this->user('enfant@x.fr');
        $stranger = $this->user('stranger@x.fr');
        $famille = (new Famille())->setUser($parent);
        $licencie = (new Licencie())->setFamille($famille)->setUser($enfant);
        $voter = new LicencieVoter();

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($this->token($parent), $licencie, ['LICENCIE_VIEW']));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($this->token($enfant), $licencie, ['LICENCIE_VIEW']));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($this->token($stranger), $licencie, ['LICENCIE_VIEW']));
    }
}
