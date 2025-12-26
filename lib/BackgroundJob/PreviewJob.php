<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PreviewGenerator\BackgroundJob;

use OCA\PreviewGenerator\Service\ConfigService;
use OCA\PreviewGenerator\Service\PreGenerateService;
use OCA\PreviewGenerator\Support\OutputInterfaceLoggerAdapter;
use OCA\PreviewGenerator\Support\PreviewLimiter\CountLimiter;
use OCA\PreviewGenerator\Support\PreviewLimiter\ExecutionTimeLimiter;
use OCA\PreviewGenerator\Support\PreviewLimiter\MultiLimiter;
use OCA\PreviewGenerator\Support\PreviewLimiter\PreviewLimiter;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

class PreviewJob extends TimedJob {
	private readonly PreviewLimiter $limiter;

	public function __construct(
		ITimeFactory $time,
		private readonly PreGenerateService $preGenerateService,
		private readonly OutputInterfaceLoggerAdapter $outputInterface,
		private readonly ConfigService $configService,
	) {
		parent::__construct($time);

		$this->limiter = new MultiLimiter([
			new CountLimiter($this->configService->getMaxBackgroundJobPreviews()),
			new ExecutionTimeLimiter(
				$time,
				$this->configService->getMaxBackgroundJobExecutionTime(),
			),
		]);

		$this->setInterval(5 * 60);
		$this->setTimeSensitivity(self::TIME_SENSITIVE);
	}

	protected function run($argument) {
		if ($this->configService->isBackgroundJobDisabled()
			|| !$this->configService->usesCronDaemon()) {
			return;
		}

		$this->preGenerateService->setLimiter($this->limiter);
		$this->preGenerateService->preGenerate($this->outputInterface);
	}
}
