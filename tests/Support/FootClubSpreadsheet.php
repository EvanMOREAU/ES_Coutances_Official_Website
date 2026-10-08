<?php

namespace App\Tests\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Fabrique de petits exports « Foot Club » (.xlsx) pour les tests d'import. */
final class FootClubSpreadsheet
{
    public const HEADERS = [
        'Numéro personne', 'Numéro licence', 'Nom', 'Prénom', 'Né(e) le', 'Code postal', 'Type licence', 'Mobile personnel', 'Email principal',
        'Nom Prénom repr. légal 1', 'Voie / Rue repr. légal 1', 'Code postal repr. légal 1', 'Email repr. légal 1',
    ];

    /**
     * @param list<list<string|int|null>> $rows lignes de données, dans l'ordre de self::HEADERS
     * @param list<string>                $headers
     */
    public static function create(array $rows, array $headers = self::HEADERS): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Feuil1');
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        $path = tempnam(sys_get_temp_dir(), 'footclub').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
