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

namespace CsvImporter\Command;

use CsvImporter\Admin\UploadStore;
use CsvImporter\Import\CatalogImportRunner;
use CsvImporter\Job\ImportJobRepository;
use CsvImporter\Job\JobStatus;
use CsvImporter\Model\CsvImporterJob;
use CsvImporter\Profile\ProfileRepository;
use CsvImporter\Report\ReportMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Command\ContainerAwareCommand;

#[AsCommand(
    name: 'csvimporter:run-job',
    description: 'Runs a queued catalog import job, as the screens start it.',
)]
final class RunJobCommand extends ContainerAwareCommand
{
    public function __construct(
        private readonly CatalogImportRunner $runner,
        private readonly ImportJobRepository $jobs,
        private readonly ProfileRepository $profiles,
        private readonly UploadStore $uploads,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('job', InputArgument::REQUIRED, 'Id of the job.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initRequest();

        $id = (string) $input->getArgument('job');
        $job = ctype_digit($id) ? $this->jobs->find((int) $id) : null;

        if (null === $job) {
            $output->writeln('No such job.');

            return self::INVALID;
        }

        if (JobStatus::Queued->value !== $job->getStatus()) {
            $output->writeln(\sprintf('The job is %s, only a queued job can be started.', $job->getStatus()));

            return self::FAILURE;
        }

        if (0 === $job->getDryRun() && $this->mappingChangedSinceRequested($job)) {
            $this->jobs->markFailed($job, new ReportMessage('The mapping changed after the import was requested: simulate again'));
            $output->writeln('The mapping changed after the import was requested.');

            return self::FAILURE;
        }

        $this->runner->run($job, null, $this->uploads->imagesDirectoryOf($job->getSourcePath()));
        $job->reload();

        return JobStatus::Done->value === $job->getStatus() ? self::SUCCESS : self::FAILURE;
    }

    /**
     * The import runs the profile as it is when it starts, not as it was when the
     * administrator asked: a change in between is a mapping nobody simulated. Whole seconds:
     * a change within the second of the request is not seen.
     */
    private function mappingChangedSinceRequested(CsvImporterJob $job): bool
    {
        $updated = null === $job->getProfileId() ? null : $this->profiles->updatedAt($job->getProfileId());

        return null === $updated || null === $job->getCreatedAt() || $updated->getTimestamp() > $job->getCreatedAt()->getTimestamp();
    }
}
