<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Article;
use App\Entity\ArticleVariante;
use App\Entity\CodePromo;
use App\Entity\Commande;
use App\Entity\CommandeLigne;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class BoutiqueEntitiesTest extends TestCase
{
    private function article(string $nom = 'Maillot domicile', int $prix = 3500, int ...$stocks): Article
    {
        $article = (new Article())->setNom($nom)->setPrixCentimes($prix);
        foreach ($stocks ?: [5] as $i => $stock) {
            $article->addVariante((new ArticleVariante())->setLibelle(0 === $i && 1 === \count($stocks ?: [5]) ? 'Unique' : 'T'.$i)->setStock($stock));
        }

        return $article;
    }

    private function user(int $id): User
    {
        $user = new User();
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }

    // -- Article -----------------------------------------------------------------

    public function testArticleSlugBase(): void
    {
        self::assertSame('maillot-domicile-2026', $this->article('Maillot Domicile 2026')->slugBase());
        self::assertSame('ete-en-fete', $this->article('Été en fête !')->slugBase());
        self::assertSame('article', (new Article())->slugBase());
    }

    public function testArticleStock(): void
    {
        $article = $this->article('Polo', 2000, 3, 0, 2);

        self::assertSame(5, $article->getStockTotal());
        self::assertTrue($article->isDisponible());
        self::assertFalse($this->article('Polo', 2000, 0)->isDisponible());
    }

    public function testArticleWithoutVariantsOptions(): void
    {
        self::assertTrue($this->article('Écharpe', 1000, 4)->isSansDeclinaison());
        self::assertFalse($this->article('Maillot', 3000, 1, 1)->isSansDeclinaison());
    }

    public function testArticleActiveFlagMapsToStatus(): void
    {
        $article = new Article();
        self::assertTrue($article->isActif());

        $article->setActif(false);
        self::assertFalse($article->isActif());
        self::assertSame(Article::STATUT_ARCHIVED, $article->getStatut());
        self::assertSame('Archivé', $article->getStatutLabel());
    }

    public function testInvalidStatusIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Article())->setStatut('inconnu');
    }

    // -- Commande ----------------------------------------------------------------

    public function testOrderTotalsFollowItsLines(): void
    {
        $commande = new Commande();
        $commande->addLigne(CommandeLigne::depuis($this->article('Maillot', 3500, 5)->getVariantes()->first(), 2));
        $commande->addLigne(CommandeLigne::depuis($this->article('Écharpe', 1200, 5)->getVariantes()->first(), 1));

        self::assertSame(8200, $commande->getSousTotalCentimes());
        self::assertSame(8200, $commande->getTotalCentimes());
        self::assertSame(3, $commande->getNombreArticles());
    }

    public function testOrderLineKeepsASnapshotOfTheArticle(): void
    {
        $article = $this->article('Maillot', 3500, 5);
        $ligne = CommandeLigne::depuis($article->getVariantes()->first(), 2);

        $article->setNom('Renommé')->setPrixCentimes(9999);

        self::assertSame('Maillot', $ligne->getArticleNom());
        self::assertSame(3500, $ligne->getPrixCentimes());
        self::assertSame(7000, $ligne->getTotalCentimes());
    }

    public function testAddingTheSameLineTwiceDoesNotDuplicateIt(): void
    {
        $commande = new Commande();
        $ligne = CommandeLigne::depuis($this->article()->getVariantes()->first(), 1);

        $commande->addLigne($ligne)->addLigne($ligne);

        self::assertCount(1, $commande->getLignes());
        self::assertSame($commande, $ligne->getCommande());
    }

    public function testDiscountNeverGoesBelowZero(): void
    {
        $commande = (new Commande())->addLigne(CommandeLigne::depuis($this->article('Maillot', 1000, 5)->getVariantes()->first(), 1));

        $commande->appliquerReduction(null, 5000);
        self::assertSame(0, $commande->getTotalCentimes());

        $commande->appliquerReduction(null, 300);
        self::assertSame(700, $commande->getTotalCentimes());
        self::assertSame(1000, $commande->getSousTotalCentimes());

        $commande->appliquerReduction(null, -50);
        self::assertSame(1000, $commande->getTotalCentimes());
    }

    public function testOrderLifecycleFlags(): void
    {
        $commande = new Commande();
        self::assertSame(Commande::STATUT_NOUVELLE, $commande->getStatut());
        self::assertTrue($commande->isAnnulable());
        self::assertTrue($commande->isReglementDu());
        self::assertFalse($commande->isPayee());

        $commande->marquerPayee();
        self::assertTrue($commande->isPayee());
        self::assertFalse($commande->isReglementDu());
        self::assertInstanceOf(\DateTimeImmutable::class, $commande->getPayeeLe());

        $commande->setStatut(Commande::STATUT_RETIREE);
        self::assertFalse($commande->isAnnulable());
        self::assertFalse($commande->isEnCours());

        $commande->setStatut(Commande::STATUT_ANNULEE);
        self::assertFalse($commande->isAnnulable());
    }

    public function testPaymentDateIsNotOverwritten(): void
    {
        $commande = (new Commande())->marquerPayee();
        $first = $commande->getPayeeLe();

        usleep(1000);
        $commande->marquerPayee();

        self::assertSame($first, $commande->getPayeeLe());
    }

    public function testCancelledOrderHasNothingDue(): void
    {
        $commande = (new Commande())->setStatut(Commande::STATUT_ANNULEE);

        self::assertFalse($commande->isReglementDu());
    }

    public function testOrderTrackingTokenIsGeneratedAndLong(): void
    {
        $a = new Commande();
        $b = new Commande();

        self::assertGreaterThanOrEqual(32, strlen((string) $a->getToken()));
        self::assertNotSame($a->getToken(), $b->getToken());
    }

    // -- CodePromo ---------------------------------------------------------------

    private function codePromo(bool $reserved = false, bool $approved = true): CodePromo
    {
        $code = (new CodePromo())->setCode('ABC')->setApprouve($approved);
        if ($reserved) {
            $code->setUtilisateur($this->user(7));
        }

        return $code;
    }

    public function testInactiveCodeIsInvalid(): void
    {
        self::assertTrue($this->codePromo()->isValide());
        self::assertFalse($this->codePromo()->setActif(false)->isValide());
    }

    public function testCodeValidityWindow(): void
    {
        $code = $this->codePromo()
            ->setDateDebut(new \DateTimeImmutable('2026-09-01'))
            ->setDateFin(new \DateTimeImmutable('2026-09-30'));

        self::assertFalse($code->isValide(new \DateTimeImmutable('2026-08-31')));
        self::assertTrue($code->isValide(new \DateTimeImmutable('2026-09-15')));
        self::assertFalse($code->isValide(new \DateTimeImmutable('2026-10-01')));
    }

    public function testCodeUsageLimit(): void
    {
        $code = $this->codePromo()->setUsageMax(2);

        self::assertTrue($code->isValide());
        $code->incrementerUsage();
        self::assertTrue($code->isValide());
        $code->incrementerUsage();
        self::assertFalse($code->isValide());
        self::assertSame(2, $code->getUsageActuel());
    }

    public function testOpenCodeMustBeApprovedButReservedCodeNeedsNoApproval(): void
    {
        self::assertFalse($this->codePromo(false, false)->isValide());
        self::assertTrue($this->codePromo(true, false)->isValide());
    }

    public function testCodeReservedToAUser(): void
    {
        $open = $this->codePromo();
        $reserved = $this->codePromo(true);

        self::assertTrue($open->estUtilisablePar(null));
        self::assertTrue($reserved->estUtilisablePar($this->user(7)));
        self::assertFalse($reserved->estUtilisablePar($this->user(8)));
        self::assertFalse($reserved->estUtilisablePar(null));
    }
}
