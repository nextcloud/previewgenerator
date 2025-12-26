<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PreviewGenerator\Service;

use OCP\AppFramework\Services\IAppConfig;
use OCP\IConfig;

class ConfigService {
	public function __construct(
		private readonly IConfig $config,
		private readonly IAppConfig $appConfig,
	) {
	}

	public function getPreviewMaxX(): int {
		return (int)$this->config->getSystemValue('preview_max_x', 4096);
	}

	public function getPreviewMaxY(): int {
		return (int)$this->config->getSystemValue('preview_max_y', 4096);
	}

	public function isBackgroundJobDisabled(): bool {
		return $this->appConfig->getAppValueBool('job_disabled');
	}

	public function getMaxBackgroundJobExecutionTime(): int {
		return $this->appConfig->getAppValueInt('job_max_execution_time', 5 * 60);
	}

	public function getMaxBackgroundJobPreviews(): int {
		return $this->appConfig->getAppValueInt('job_max_previews');
	}

	public function usesCronDaemon(): bool {
		return $this->config->getAppValue('core', 'backgroundjobs_mode') === 'cron';
	}
}
