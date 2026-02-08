<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PreviewGenerator\Tests\Unit\Service;

use OCA\PreviewGenerator\Service\ModuloService;
use PHPUnit\Framework\TestCase;

class ModuloServiceTest extends TestCase {
	public static function moduloDataProvider(): array {
		return [
			[3, 10, 3],
			[-3, 10, 7],
			[13, 10, 3],
			[-13, 10, 7],
		];
	}

	/** @dataProvider moduloDataProvider */
	public function testAbsMod(int $x, int $n, int $expected): void {
		$this->assertEquals($expected, ModuloService::absMod($x, $n));
	}
}
