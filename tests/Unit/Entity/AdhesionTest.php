<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Adhesion;
use App\Entity\AideFinanciere;
use App\Entity\Reglement;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

/** Montants d'une licence (en centimes), statut de paiement et cohérence du plan de paiement. */
final class AdhesionTest extends TestCase
{
    private function adhesion(int $base, int $reduction = 0): Adhesion
    {
        return (new Adhesion())->setMontantBaseCentimes($base)->setReductionCentimes($reduction);
    }

    private function reglement(int $montant, bool $recu): Reglement
    {
        return (new Reglement())->setMontantCentimes($montant)->setRecu($recu);
    }

    private function aide(int $montant, bool $recue): AideFinanciere
    {
        return (new AideFinanciere())->setMontantCentimes($montant)->setStatut($recue ? AideFinanciere::STATUT_RECUE : AideFinanciere::STATUT_ATTENDUE);
    }

    public function testAmountDueNeverGoesBelowZero(): void
    {
        self::assertSame(12000, $this->adhesion(15000, 3000)->getMontantDuCentimes());
        self::assertSame(0, $this->adhesion(5000, 9000)->getMontantDuCentimes());
    }

    public function testUnpaidThenPartialThenPaid(): void
    {
        $adhesion = $this->adhesion(15000)->addReglement($this->reglement(5000, false))->addReglement($this->reglement(10000, false));
        self::assertSame(Adhesion::PAIEMENT_IMPAYE, $adhesion->getStatutPaiement());
        self::assertSame(15000, $adhesion->getResteCentimes());

        $premier = $adhesion->getReglements()->first();
        self::assertInstanceOf(Reglement::class, $premier);
        $premier->setRecu(true);
        self::assertSame(Adhesion::PAIEMENT_PARTIEL, $adhesion->getStatutPaiement());
        self::assertSame(10000, $adhesion->getResteCentimes());

        $dernier = $adhesion->getReglements()->last();
        self::assertInstanceOf(Reglement::class, $dernier);
        $dernier->setRecu(true);
        self::assertSame(Adhesion::PAIEMENT_PAYE, $adhesion->getStatutPaiement());
        self::assertSame(0, $adhesion->getResteCentimes());
    }

    public function testFreeLicenceIsPaid(): void
    {
        self::assertSame(Adhesion::PAIEMENT_PAYE, $this->adhesion(0)->getStatutPaiement());
        self::assertSame(Adhesion::PAIEMENT_PAYE, $this->adhesion(5000, 5000)->getStatutPaiement());
    }

    public function testWaitingForAidesWhenTheFamilyHasPaidItsShare(): void
    {
        $adhesion = $this->adhesion(15000)
            ->addAide($this->aide(5000, false))
            ->addReglement($this->reglement(10000, true));

        self::assertSame(Adhesion::PAIEMENT_ATTENTE_AIDE, $adhesion->getStatutPaiement());
        self::assertSame(0, $adhesion->getResteFamilleCentimes());
        self::assertSame(5000, $adhesion->getAidesAttenduesCentimes());
        self::assertSame(5000, $adhesion->getResteCentimes());

        $aide = $adhesion->getAides()->first();
        self::assertInstanceOf(AideFinanciere::class, $aide);
        $aide->setStatut(AideFinanciere::STATUT_RECUE);
        self::assertSame(Adhesion::PAIEMENT_PAYE, $adhesion->getStatutPaiement());
        self::assertSame(15000, $adhesion->getRecuCentimes());
    }

    public function testPaymentPlanMustCoverExactlyTheAmountDue(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        $short = $this->adhesion(15000)->addReglement($this->reglement(10000, false));
        $messages = array_map(static fn ($v) => (string) $v->getMessage(), iterator_to_array($validator->validate($short)));
        self::assertCount(1, $messages);
        self::assertStringContainsString('Il manque 50,00', $messages[0]);

        $over = $this->adhesion(15000)->addReglement($this->reglement(16000, false));
        $messages = array_map(static fn ($v) => (string) $v->getMessage(), iterator_to_array($validator->validate($over)));
        self::assertStringContainsString('dépasse le prix de la licence de 10,00', implode(' ', $messages));

        $exact = $this->adhesion(15000)->addAide($this->aide(5000, false))->addReglement($this->reglement(10000, false));
        $violations = array_filter(iterator_to_array($validator->validate($exact)), static fn ($v) => 'reglements' === $v->getPropertyPath());
        self::assertSame([], $violations);
    }
}
