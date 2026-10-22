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

namespace CsvImporter\Job;

use CsvImporter\Import\ImportMode;
use CsvImporter\Import\RowOutcome;
use CsvImporter\Model\CsvImporterJob;
use CsvImporter\Model\CsvImporterJobQuery;
use CsvImporter\Model\CsvImporterJobRow;
use CsvImporter\Report\ReportMessage;

final readonly class ImportJobRepository
{
    public function create(?int $profileId, string $sourcePath, string $sourceName, ImportMode $mode, bool $dryRun, ?int $adminId): CsvImporterJob
    {
        $job = (new CsvImporterJob())
            ->setProfileId($profileId)
            ->setAdminId($adminId)
            ->setStatus(JobStatus::Queued->value)
            ->setMode($mode->value)
            ->setDryRun($dryRun ? 1 : 0)
            ->setSourceName($sourceName)
            ->setSourcePath($sourcePath);
        $job->save();

        return $job;
    }

    public function claim(CsvImporterJob $job): bool
    {
        $claimed = CsvImporterJobQuery::create()
            ->filterById($job->getId())
            ->filterByStatus(JobStatus::Queued->value)
            ->update(['Status' => JobStatus::Running->value, 'StartedAt' => date('Y-m-d H:i:s')]);

        if ($claimed > 0) {
            $job->reload();
        }

        return $claimed > 0;
    }

    public function markRunning(CsvImporterJob $job, int $totalRows): void
    {
        $job
            ->setStatus(JobStatus::Running->value)
            ->setTotalRows($totalRows)
            ->save();
    }

    public function saveProgress(CsvImporterJob $job, int $processed, array $counts): void
    {
        $job
            ->setProcessedRows($processed)
            ->setCreatedRows($counts[RowOutcome::Created->value] ?? 0)
            ->setUpdatedRows($counts[RowOutcome::Updated->value] ?? 0)
            ->setUnchangedRows($counts[RowOutcome::Unchanged->value] ?? 0)
            ->setSkippedRows($counts[RowOutcome::Skipped->value] ?? 0)
            ->setFailedRows($counts[RowOutcome::Failed->value] ?? 0)
            ->save();
    }

    public function markDone(CsvImporterJob $job): void
    {
        $job
            ->setStatus(JobStatus::Done->value)
            ->setFinishedAt(new \DateTimeImmutable())
            ->save();
    }

    public function markFailed(CsvImporterJob $job, ReportMessage $reason): void
    {
        $job
            ->setStatus(JobStatus::Failed->value)
            ->setError($reason->toJson())
            ->setFinishedAt(new \DateTimeImmutable())
            ->save();
    }

    public function addLine(CsvImporterJob $job, int $lineNumber, string $outcome, ?string $reference, ?ReportMessage $message): void
    {
        (new CsvImporterJobRow())
            ->setJobId($job->getId())
            ->setLineNumber($lineNumber)
            ->setOutcome($outcome)
            ->setReference(null === $reference ? null : mb_substr($reference, 0, 255))
            ->setMessage($message?->toJson())
            ->save();
    }
}
