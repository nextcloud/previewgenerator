<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PreviewGenerator\Command;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class QueueStats extends Command {
	private const OPT_INTERVAL = 'interval';

	private const DEFAULT_INTERVALS = [
		['Less than 10 minutes', 600],
		['Less than 30 minutes', 1800],
		['Less than one hour', 3600],
		['Less than three hours', 3600 * 3],
		['Less than a day', 3600 * 24],
		['Less than a week', 3600 * 24 * 7],
	];

	public function __construct(
		private readonly IDBConnection $dbConnection,
		private readonly ITimeFactory $time,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('preview:queue-stats')
			->setDescription('Show statistics about the background preview generation job queue')
			->addOption(
				self::OPT_INTERVAL,
				'i',
				InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
				'Set a custom interval in seconds',
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$intervals = self::DEFAULT_INTERVALS;
		if ($input->getOption(self::OPT_INTERVAL)) {
			$intervals = array_map(
				static fn ($interval) => ["Less than $interval seconds", (int)$interval],
				$input->getOption(self::OPT_INTERVAL),
			);
		}

		$intervals[] = ['All', null];

		$table = new Table($output);
		$table->setHeaders(['Age', 'Queued previews']);

		foreach ($intervals as [$prefix, $interval]) {
			$count = $this->countQueuedPreviewsInInterval($interval);
			$table->addRow([$prefix, $count]);
		}

		$table->render();
		return 0;
	}

	/**
	 * @throws Exception If the SQL SELECT query fails.
	 */
	private function countQueuedPreviewsInInterval(?int $interval): int {
		$qb = $this->dbConnection->getQueryBuilder();

		$now = $this->time->getTime();
		$qb->select($qb->func()->count('*'))
			->from('preview_generation');

		if ($interval !== null) {
			$qb->where(
				$qb->expr()->gte(
					'queued_at',
					$qb->createNamedParameter($now - $interval, IQueryBuilder::PARAM_INT),
					IQueryBuilder::PARAM_INT,
				),
				$qb->expr()->isNotNull('queued_at'),
			);
		}

		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();

		return $count;
	}
}
