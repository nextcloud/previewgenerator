<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PreviewGenerator\Support\PreviewLimiter;

use OCP\AppFramework\Utility\ITimeFactory;

class ExecutionTimeLimiter implements PreviewLimiter {
	private readonly int $deadline;

	public function __construct(
		private readonly ITimeFactory $time,
		private readonly int $maxExecutionTimeSeconds,
	) {
		$this->deadline = $time->getTime() + $this->maxExecutionTimeSeconds;
	}

	public function next(): bool {
		return $this->time->getTime() < $this->deadline;
	}
}
