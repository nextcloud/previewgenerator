<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PreviewGenerator\BackgroundJob;

use OCA\PreviewGenerator\Exceptions\EncryptionEnabledException;
use OCA\PreviewGenerator\Service\ConfigService;
use OCA\PreviewGenerator\Service\PreGenerateService;
use OCA\PreviewGenerator\Support\PreviewLimiter\CountLimiter;
use OCA\PreviewGenerator\Support\PreviewLimiter\ExecutionTimeLimiter;
use OCA\PreviewGenerator\Support\PreviewLimiter\MultiLimiter;
use OCA\PreviewGenerator\Support\PreviewLimiter\PreviewLimiter;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class PreviewJob extends TimedJob {
	private readonly PreviewLimiter $limiter;

	public function __construct(
		ITimeFactory $time,
		private readonly PreGenerateService $preGenerateService,
		private readonly LoggerInterface $logger,
		private readonly ConfigService $configService,
	) {
		parent::__construct($time);
		$this->setInterval(5 * 60);
		$this->setTimeSensitivity(self::TIME_SENSITIVE);

		$limiters = [];

		$maxPreviews = $this->configService->getMaxBackgroundJobPreviews();
		if ($maxPreviews > 0) {
			$limiters[] = new CountLimiter($maxPreviews);
		}

		$maxExecutionTime = $this->configService->getMaxBackgroundJobExecutionTime();
		if ($maxExecutionTime > 0) {
			$limiters[] = new ExecutionTimeLimiter($time, $maxExecutionTime);
		}

		$this->limiter = new MultiLimiter($limiters);
	}

	protected function run($argument) {
		if ($this->configService->isBackgroundJobDisabled()
			|| !$this->configService->usesCronDaemon()) {
			return;
		}

		$this->preGenerateService->setLogger($this->logger);
		$this->preGenerateService->setLimiter($this->limiter);

		try {
			$this->preGenerateService->preGenerate();
		} catch (EncryptionEnabledException $e) {
			// Just skip the job silently
		}
	}
}
