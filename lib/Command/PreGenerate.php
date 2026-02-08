<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PreviewGenerator\Command;

use OCA\PreviewGenerator\Exceptions\EncryptionEnabledException;
use OCA\PreviewGenerator\Service\PreGenerateService;
use OCA\PreviewGenerator\Support\LoggerInterfaceToOutputAdapter;
use OCP\IConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PreGenerate extends Command {
	public function __construct(
		private readonly PreGenerateService $preGenerateService,
		private readonly IConfig $config,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('preview:pre-generate')
			->setDescription('Pre-generate only images that have been added or changed since the last regular run');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		// Set timestamp output
		$formatter = new TimestampFormatter($this->config, $output->getFormatter());
		$output->setFormatter($formatter);

		$this->preGenerateService->setLogger(new LoggerInterfaceToOutputAdapter($output));

		try {
			$this->preGenerateService->preGenerate();
		} catch (EncryptionEnabledException $e) {
			$output->writeln('<error>Encryption is enabled. Aborted.</error>');
			return 1;
		}

		return 0;
	}
}
