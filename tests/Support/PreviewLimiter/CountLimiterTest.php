<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PreviewGenerator\Tests\Support\PreviewLimiter;

use OCA\PreviewGenerator\Support\PreviewLimiter\CountLimiter;
use PHPUnit\Framework\TestCase;

class CountLimiterTest extends TestCase {
	public function testNext(): void {
		$limiter = new CountLimiter(3);

		$this->assertTrue($limiter->next());
		$this->assertTrue($limiter->next());
		$this->assertTrue($limiter->next());
		$this->assertFalse($limiter->next());
	}

	public function testNextWithZeroPreviews(): void {
		$limiter = new CountLimiter(0);

		$this->assertFalse($limiter->next());
	}
}
