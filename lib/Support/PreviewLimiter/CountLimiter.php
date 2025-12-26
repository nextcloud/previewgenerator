<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PreviewGenerator\Support\PreviewLimiter;

class CountLimiter implements PreviewLimiter {
	private int $previews = 0;

	public function __construct(
		private readonly int $maxPreviews,
	) {
	}

	public function next(): bool {
		if ($this->previews >= $this->maxPreviews) {
			return false;
		}

		$this->previews++;
		return true;
	}
}
