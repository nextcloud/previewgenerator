<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PreviewGenerator\Tests\Support\PreviewLimiter;

use OCA\PreviewGenerator\Support\PreviewLimiter\MultiLimiter;
use OCA\PreviewGenerator\Support\PreviewLimiter\PreviewLimiter;
use PHPUnit\Framework\TestCase;

class MultiLimiterTest extends TestCase {
	public function testNext(): void {
		$limiter1 = $this->createMock(PreviewLimiter::class);
		$limiter2 = $this->createMock(PreviewLimiter::class);

		$limiter1Next = true;
		$limiter2Next = true;

		$limiter1->expects(self::exactly(6))
			->method('next')
			->willReturnCallback(static function () use (&$limiter1Next) {
				return $limiter1Next;
			});
		$limiter2->expects(self::exactly(4))
			->method('next')
			->willReturnCallback(static function () use (&$limiter2Next) {
				return $limiter2Next;
			});

		$limiter = new MultiLimiter([$limiter1, $limiter2]);

		$this->assertTrue($limiter->next());
		$this->assertTrue($limiter->next());

		$limiter1Next = false;
		$this->assertFalse($limiter->next());

		$limiter1Next = true;
		$limiter2Next = false;
		$this->assertFalse($limiter->next());

		$limiter1Next = false;
		$limiter2Next = false;
		$this->assertFalse($limiter->next());

		$limiter1Next = true;
		$limiter2Next = true;
		$this->assertTrue($limiter->next());
	}

	public function testNextWithoutLimiters(): void {
		$limiter = new MultiLimiter([]);

		$this->assertTrue($limiter->next());
		$this->assertTrue($limiter->next());
	}
}
