<?php

namespace App\Tests\Unit\Service\Import;

use App\Service\Import\FootClubImportParser;
use App\Tests\Support\FootClubSpreadsheet;
use PHPUnit\Framework\TestCase;

final class FootClubImportParserTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    /** @param list<list<string|int|null>> $rows @param list<string> $headers */
    private function parse(array $rows, array $headers = FootClubSpreadsheet::HEADERS): array
    {
        $this->files[] = $file = FootClubSpreadsheet::create($rows, $headers);

        return (new FootClubImportParser())->parseFile($file);
    }

    public function testNormalizeHeaderIgnoresAccentsCaseAndPunctuation(): void
    {
        self::assertSame('numero personne', FootClubImportParser::normalizeHeader('Numéro personne'));
        self::assertSame('ne e le', FootClubImportParser::normalizeHeader('Né(e) le'));
        self::assertSame('nom prenom repr legal 1', FootClubImportParser::normalizeHeader('  Nom / Prénom  repr. légal 1 '));
    }

    public function testNormalizeForComparison(): void
    {
        self::assertSame('', FootClubImportParser::normalize(null));
        self::assertSame(FootClubImportParser::normalize('  Élodie   DUPONT '), FootClubImportParser::normalize('elodie dupont'));
    }

    public function testRowsAreReadAndCleaned(): void
    {
        $rows = $this->parse([
            [12345, 'L1', 'MARTIN', 'Lucas', '12/04/2014', '5200', 'Libre', '612345678', ' PARENT@Example.COM ', 'MARTIN Paul', '1 rue de la Gare', '50200', 'paul@example.com'],
        ]);

        self::assertCount(1, $rows);
        $row = $rows[0];
        self::assertSame('12345', $row->numeroPersonne);
        self::assertSame('MARTIN', $row->nom);
        self::assertSame('2014-04-12', $row->dateNaissance?->format('Y-m-d'));
        self::assertSame('05200', $row->codePostal, 'Les zéros de tête perdus par Excel sont restaurés.');
        self::assertSame('0612345678', $row->mobilePersonnel);
        self::assertSame('parent@example.com', $row->emailPrincipal);
        self::assertTrue($row->aReprLegal1());
    }

    public function testEmptyRowsAreSkipped(): void
    {
        $rows = $this->parse([
            [1, 'L1', 'A', 'Un', '01/01/2010', '50200', 'Libre', null, null, null, null, null, null],
            [null, null, null, null, null, null, null, null, null, null, null, null, null],
            [2, 'L2', 'B', 'Deux', '02/02/2011', '50200', 'Libre', null, null, null, null, null, null],
        ]);

        self::assertSame(['1', '2'], array_map(static fn ($r) => $r->numeroPersonne, $rows));
    }

    public function testSeveralLicenceTypesOfOnePersonAreMerged(): void
    {
        $rows = $this->parse([
            [7, 'L1', 'DURAND', 'Paul', '01/01/1980', '50200', 'Dirigeant', null, null, null, null, null, null],
            [7, 'L1', 'DURAND', 'Paul', '01/01/1980', '50200', 'Libre', null, 'paul@example.com', null, null, null, null],
        ]);

        self::assertCount(1, $rows);
        self::assertSame('Dirigeant + Libre', $rows[0]->typeLicence);
        self::assertSame('paul@example.com', $rows[0]->emailPrincipal, 'Les champs absents d\'une ligne sont repris de l\'autre.');
    }

    public function testInvalidBirthDateIsIgnored(): void
    {
        $rows = $this->parse([[1, 'L', 'A', 'B', 'pas une date', '50200', 'Libre', null, null, null, null, null, null]]);

        self::assertNull($rows[0]->dateNaissance);
    }

    public function testFileWithoutExpectedColumnsIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ne ressemble pas à un export Foot Club');

        $this->parse([['a', 'b']], ['Colonne A', 'Colonne B']);
    }

    public function testUnreadableFileIsRejected(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'bad');
        file_put_contents($file, 'ceci n\'est pas un classeur Excel');
        $this->files[] = $file;

        $this->expectException(\RuntimeException::class);

        (new FootClubImportParser())->parseFile($file);
    }

    public function testSiblingsWithTheSameLegalGuardianShareAFamily(): void
    {
        $parser = new FootClubImportParser();
        $rows = $this->parse([
            [1, 'L1', 'MARTIN', 'Lucas', '12/04/2014', '50200', 'Libre', null, null, 'MARTIN Paul', '1 rue de la Gare', '50200', 'paul@example.com'],
            [2, 'L2', 'MARTIN', 'Léa', '03/09/2016', '50200', 'Libre', null, null, 'martin paul', '1 RUE DE LA GARE', '50200', 'paul@example.com'],
            [3, 'L3', 'BERNARD', 'Tom', '01/01/2015', '50200', 'Libre', null, null, 'BERNARD Anne', '9 rue Neuve', '50200', 'anne@example.com'],
        ]);

        $groups = $parser->groupRows($rows);

        self::assertCount(2, $groups);
        self::assertSame([2, 1], array_map('count', array_values($groups)));
    }

    public function testAdultWithoutGuardianIsTheirOwnFamily(): void
    {
        $parser = new FootClubImportParser();
        $rows = $this->parse([
            [1, 'L1', 'DURAND', 'Paul', '01/01/1980', '50200', 'Libre', null, null, null, null, null, null],
            [2, 'L2', 'DURAND', 'Marc', '01/01/1982', '50200', 'Libre', null, null, null, null, null, null],
        ]);

        self::assertCount(2, $parser->groupRows($rows));
    }
}
