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
use CsvImporter\Model\CsvImporterJobRowQuery;
use CsvImporter\Report\ReportMessage;
use Propel\Runtime\ActiveQuery\Criteria;

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

    public function createDraft(int $profileId, string $sourcePath, string $sourceName, ImportMode $mode, ?int $adminId): CsvImporterJob
    {
        $job = (new CsvImporterJob())
            ->setProfileId($profileId)
            ->setAdminId($adminId)
            ->setStatus(JobStatus::Draft->value)
            ->setMode($mode->value)
            ->setDryRun(1)
            ->setSourceName($sourceName)
            ->setSourcePath($sourcePath);
        $job->save();

        return $job;
    }

    public function find(int $id): ?CsvImporterJob
    {
        return CsvImporterJobQuery::create()->findPk($id);
    }

    /**
     * @return list<CsvImporterJob>
     */
    public function recent(int $limit): array
    {
        return array_values(CsvImporterJobQuery::create()->orderById(Criteria::DESC)->limit($limit)->find()->getData());
    }

    public function findDraftOf(string $sourcePath): ?CsvImporterJob
    {
        return CsvImporterJobQuery::create()->filterBySourcePath($sourcePath)->filterByStatus(JobStatus::Draft->value)->orderById(Criteria::DESC)->findOne();
    }

    public function hasLiveJobOn(string $sourcePath): bool
    {
        return CsvImporterJobQuery::create()
            ->filterBySourcePath($sourcePath)
            ->filterByStatus([JobStatus::Queued->value, JobStatus::Running->value], Criteria::IN)
            ->count() > 0;
    }

    public function hasRealRunSince(string $sourcePath, \DateTimeInterface $since): bool
    {
        return CsvImporterJobQuery::create()
            ->filterBySourcePath($sourcePath)
            ->filterByDryRun(0)
            ->filterByStatus(JobStatus::Draft->value, Criteria::NOT_EQUAL)
            ->filterByCreatedAt($since, Criteria::GREATER_EQUAL)
            ->count() > 0;
    }

    /**
     * Lines of the report after the given id, in the order they were written: a page
     * costs the same however far into the report it is.
     *
     * @return list<CsvImporterJobRow>
     */
    public function linesAfter(CsvImporterJob $job, int $afterId, int $limit): array
    {
        return array_values(CsvImporterJobRowQuery::create()->filterByJobId($job->getId())->filterById($afterId, Criteria::GREATER_THAN)->orderById()->limit($limit)->find()->getData());
    }

    public function abandon(CsvImporterJob $job): void
    {
        $this->markFailed($job, new ReportMessage('The job was abandoned by an administrator'));
    }

    public function changeMode(CsvImporterJob $job, ImportMode $mode): void
    {
        $job->setMode($mode->value)->save();
    }

    public function hasLiveRealRun(string $sourcePath): bool
    {
        return CsvImporterJobQuery::create()
            ->filterBySourcePath($sourcePath)
            ->filterByDryRun(0)
            ->filterByStatus([JobStatus::Queued->value, JobStatus::Running->value], Criteria::IN)
            ->count() > 0;
    }

    public function countLines(CsvImporterJob $job, ?string $outcome): int
    {
        return $this->linesOf($job, $outcome)->count();
    }

    /**
     * @return list<CsvImporterJobRow>
     */
    public function lines(CsvImporterJob $job, ?string $outcome, int $offset, int $limit): array
    {
        return array_values($this->linesOf($job, $outcome)->orderByLineNumber()->orderById()->offset($offset)->limit($limit)->find()->getData());
    }

    /**
     * @return array<string, int>
     */
    public function lineCountsByOutcome(CsvImporterJob $job): array
    {
        $counts = [];

        foreach (CsvImporterJobRowQuery::create()->filterByJobId($job->getId())->withColumn('COUNT(*)', 'total')->select(['Outcome', 'total'])->groupByOutcome()->find() as $row) {
            $counts[(string) $row['Outcome']] = (int) $row['total'];
        }

        return $counts;
    }

    private function linesOf(CsvImporterJob $job, ?string $outcome): CsvImporterJobRowQuery
    {
        $query = CsvImporterJobRowQuery::create()->filterByJobId($job->getId());

        return null === $outcome ? $query : $query->filterByOutcome($outcome);
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
