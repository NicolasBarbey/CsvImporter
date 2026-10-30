<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CsvImporter\Tests\Integration;

use CsvImporter\Export\CatalogExporter;
use CsvImporter\Reader\CsvFileReader;
use Thelia\Model\Currency;
use Thelia\Test\IntegrationTestCase;

final class CatalogExportTest extends IntegrationTestCase
{
    use ImportTestSupport;

    private const HEADER = ['Référence', 'Désignation', 'Description courte', 'Description', 'Prix HT', 'Stock', 'Poids', 'Famille', 'Marque', 'Gabarit', 'EAN', 'C:Couleur'];

    private function source(): string
    {
        return $this->csv([
            self::HEADER,
            ['EXP-1', 'Chaise "pliante" 😀', 'Courte', 'Longue', '12,5', '7', '1,25', 'Maison EXP > Cuisine EXP', 'Marque EXP', 'Gabarit EXP', '4006381333931', 'Rouge'],
            ['EXP-2', 'Table', '', '', '1234,56', '0', '', 'Maison EXP', '', '', '', ''],
            ['EXP-3', 'Étagère a>b', 'x', '<p>Paragraphe</p>', '3', '2', '0,5', 'Famille \> escapée', '', '', '', 'Bleu'],
        ]);
    }

    private function exported(): array
    {
        $path = sys_get_temp_dir().'/csv-importer-export-'.bin2hex(random_bytes(6)).'.csv';
        $this->temporaryFiles[] = $path;

        (new CatalogExporter())->export($path, $this->locale(), Currency::getDefaultCurrency()->getId());

        return [$path, $this->ourLines($path)];
    }

    private function ourLines(string $path): string
    {
        $lines = [];

        foreach (preg_split('/\R(?=(?:[^"]*"[^"]*")*[^"]*$)/', (string) file_get_contents($path)) as $line) {
            if ('' !== $line && (str_starts_with($line, "\xEF\xBB\xBFRéférence") || str_starts_with($line, 'EXP-'))) {
                $lines[] = $line;
            }
        }

        return implode("\n", $lines)."\n";
    }

    public function testTheExportCarriesEveryImportedFieldInTheFormatTheImportReads(): void
    {
        $this->importCsv($this->source());
        [$path] = $this->exported();

        $rows = [];

        foreach ((new CsvFileReader($path))->rows() as $row) {
            if (str_starts_with($row->cells['Référence'] ?? '', 'EXP-')) {
                $rows[$row->cells['Référence']] = $row->cells;
            }
        }

        self::assertCount(3, $rows);
        self::assertSame('Chaise "pliante" 😀', $rows['EXP-1']['Titre']);
        self::assertSame('12,5', $rows['EXP-1']['Prix HT']);
        self::assertSame('Maison EXP > Cuisine EXP', $rows['EXP-1']['Catégorie']);
        self::assertSame('Marque EXP', $rows['EXP-1']['Marque']);
        self::assertSame('Rouge', $rows['EXP-1']['C:Couleur']);
        self::assertSame('4006381333931', $rows['EXP-1']['EAN']);
        self::assertSame('1234,56', $rows['EXP-2']['Prix HT']);
        self::assertSame('Famille \> escapée', $rows['EXP-3']['Catégorie']);
    }

    public function testWhatTheExportWritesImportsBackAsUnchangedAndExportsAgainIdentically(): void
    {
        $this->importCsv($this->source());
        [, $first] = $this->exported();

        $job = $this->importCsv($first);

        self::assertSame(3, $job->getUnchangedRows(), implode(' | ', array_map(static fn ($line): string => (string) $line->getMessage(), $this->reportLines($job, 'failed'))));
        self::assertSame(0, $job->getFailedRows());

        [, $second] = $this->exported();

        self::assertSame($first, $second);
    }
}
