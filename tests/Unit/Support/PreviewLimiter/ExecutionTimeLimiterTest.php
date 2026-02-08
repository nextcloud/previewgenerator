<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PreviewGenerator\Tests\Unit\Support\PreviewLimiter;

use OC\AppFramework\Utility\TimeFactory;
use OCA\PreviewGenerator\Support\PreviewLimiter\ExecutionTimeLimiter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ExecutionTimeLimiterTest extends TestCase {
	private TimeFactory&MockObject $time;

	protected function setUp(): void {
		parent::setUp();

		$this->time = $this->createMock(TimeFactory::class);
	}

	public function testNext(): void {
		$now = 1000;

		$this->time->expects(self::exactly(4))
			->method('getTime')
			->willReturnCallback(static function () use (&$now) {
				return $now;
			});

		$limiter = new ExecutionTimeLimiter($this->time, 10);

		$this->assertTrue($limiter->next());

		$now = 1010;
		$this->assertFalse($limiter->next());

		$now = 1100;
		$this->assertFalse($limiter->next());
	}

	public function testNextWithZeroExecutionTime(): void {
		$now = 1000;

		$this->time->expects(self::exactly(3))
			->method('getTime')
			->willReturnCallback(static function () use (&$now) {
				return $now;
			});

		$limiter = new ExecutionTimeLimiter($this->time, 0);

		$this->assertFalse($limiter->next());

		$now = 1100;
		$this->assertFalse($limiter->next());
	}
}
