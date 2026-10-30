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

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Thelia\Tools\URL;

/**
 * A queue may hand a job out twice: the second delivery finds a job that is no longer
 * queued, and the runner claims a job only once, so it runs nothing.
 */
#[AsMessageHandler]
final readonly class RunCatalogImportJobHandler
{
    public function __construct(
        private ImportJobRepository $jobs,
        private QueuedJobExecutor $executor,
        #[Autowire(service: 'thelia.url.manager')] private URL $urlManagerThatTheRewritingReadsThroughItsInstance,
    ) {
    }

    public function __invoke(RunCatalogImportJob $message): void
    {
        $job = $this->jobs->find($message->jobId);

        if (null !== $job) {
            $this->executor->execute($job);
        }
    }
}
