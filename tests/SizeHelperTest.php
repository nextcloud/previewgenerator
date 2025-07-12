<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PreviewGenerator\Tests;

use OCA\PreviewGenerator\SizeHelper;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SizeHelperTest extends TestCase {
	private SizeHelper $sizeHelper;

	private IConfig|MockObject $config;

	public function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);

		$this->sizeHelper = new SizeHelper($this->config);
	}

	private function mockMaxDimensions(int $maxW = 4096, int $maxH = 4096): void {
		$this->config->method('getSystemValue')
			->willReturnMap([
				['preview_max_x', 4096, $maxW],
				['preview_max_y', 4096, $maxH],
			]);
	}

	public static function provideGenerateSpecificationsData(): array {
		return [
			// Fallback and precedence for squareUncroppedSizes
			[
				[
					'squareSizes' => '',
					'squareUncroppedSizes' => '256',
					'fillWidthHeightSizes' => null,
					'coverWidthHeightSizes' => '',
					'widthSizes' => '',
					'heightSizes' => '',
				],
				[['width' => 256, 'height' => 256, 'crop' => false, 'mode' => 'fill']],
			],
			[
				[
					'squareSizes' => '',
					'squareUncroppedSizes' => '256',
					'fillWidthHeightSizes' => '',
					'coverWidthHeightSizes' => '',
					'widthSizes' => '',
					'heightSizes' => '',
				],
				[],
			],
			[
				[
					'squareSizes' => '',
					'squareUncroppedSizes' => '',
					'fillWidthHeightSizes' => '256',
					'coverWidthHeightSizes' => '',
					'widthSizes' => '',
					'heightSizes' => '',
				],
				[['width' => 256, 'height' => 256, 'crop' => false, 'mode' => 'fill']],
			],
			[
				[
					'squareSizes' => '',
					'squareUncroppedSizes' => null,
					'fillWidthHeightSizes' => '256',
					'coverWidthHeightSizes' => '',
					'widthSizes' => '',
					'heightSizes' => '',
				],
				[['width' => 256, 'height' => 256, 'crop' => false, 'mode' => 'fill']],
			],
			// No default value for coverWidthHeightSizes
			[
				[
					'squareSizes' => '',
					'squareUncroppedSizes' => '',
					'fillWidthHeightSizes' => '',
					'coverWidthHeightSizes' => null,
					'widthSizes' => '',
					'heightSizes' => '',
				],
				[],
			],
		];
	}

	/** @dataProvider provideGenerateSpecificationsData */
	public function testGenerateSpecifications(array $config, array $expectedSpecs): void {
		$this->mockMaxDimensions();

		$this->config->method('getAppValue')
			->willReturnCallback(function (string $appName, string $key, ?string $default) use ($config) {
				$this->assertEquals($appName, 'previewgenerator');
				$this->assertNull($default);
				return $config[$key] ?? $default;
			});

		$actualSpecs = $this->sizeHelper->generateSpecifications();
		$this->assertEqualsCanonicalizing($expectedSpecs, $actualSpecs);
	}
}
