<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PreviewGenerator\Support\PreviewLimiter;

class MultiLimiter implements PreviewLimiter {
	/**
	 * @param PreviewLimiter[] $limiters
	 */
	public function __construct(
		private readonly array $limiters,
	) {
	}

	public function next(): bool {
		foreach ($this->limiters as $limiter) {
			if (!$limiter->next()) {
				return false;
			}
		}

		return true;
	}
}
