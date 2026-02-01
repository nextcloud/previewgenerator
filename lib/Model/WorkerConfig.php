<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PreviewGenerator\Model;

final class WorkerConfig implements \JsonSerializable {
	private const WORKER_INDEX_KEY = 'workerIndex';
	private const WORKER_COUNT_KEY = 'workerCount';

	public function __construct(
		private readonly int $workerIndex,
		private readonly int $workerCount,
	) {
	}

	/**
	 * @throws \InvalidArgumentException If the given JSON data is not valid
	 */
	public static function fromJson(array $data): self {
		$workerIndex = $data[self::WORKER_INDEX_KEY] ?? null;
		if (!is_int($workerIndex)) {
			throw new \InvalidArgumentException('Invalid worker data: Missing worker index');
		}

		$workerCount = $data[self::WORKER_COUNT_KEY] ?? null;
		if (!is_int($workerCount)) {
			throw new \InvalidArgumentException('Invalid worker data: Missing worker count');
		}

		return new self($workerIndex, $workerCount);
	}

	public function getWorkerIndex(): int {
		return $this->workerIndex;
	}

	public function getWorkerCount(): int {
		return $this->workerCount;
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			self::WORKER_INDEX_KEY => $this->workerIndex,
			self::WORKER_COUNT_KEY => $this->workerCount,
		];
	}
}
