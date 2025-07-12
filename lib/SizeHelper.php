<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PreviewGenerator;

use OCA\PreviewGenerator\AppInfo\Application;
use OCP\IConfig;
use OCP\IPreview;

class SizeHelper {
	public function __construct(
		private IConfig $config,
	) {
	}

	/**
	 * @return array{width: int, height: int, crop: bool}
	 */
	public function generateSpecifications(): array {
		/*
		 * First calculate the systems max sizes
		 */

		$sizes = [
			'square' => [],
			'fillWidthHeight' => [],
			'coverWidthHeight' => [],
			'height' => [],
			'width' => [],
		];

		$maxW = (int)$this->config->getSystemValue('preview_max_x', 4096);
		$maxH = (int)$this->config->getSystemValue('preview_max_y', 4096);

		$s = 64;
		while ($s <= $maxW || $s <= $maxH) {
			$sizes['square'][] = $s;
			$sizes['fillWidthHeight'][] = $s;
			$s *= 4;
		}

		$w = 64;
		while ($w <= $maxW) {
			$sizes['width'][] = $w;
			$w *= 4;
		}

		$h = 64;
		while ($h <= $maxH) {
			$sizes['height'][] = $h;
			$h *= 4;
		}

		/*
		 * Now calculate the user provided max sizes
		 * Note that only powers of 4 matter but if users supply different
		 * stuff it is their own fault and we just ignore it
		 */
		$getCustomSizes = function ($key) {
			$raw = $this->config->getAppValue(Application::APP_ID, $key, null);
			if ($raw === null) {
				return null;
			}

			// User wants to skip those sizes deliberately
			if ($raw === '') {
				return [];
			}

			$values = [];
			foreach (explode(' ', $raw) as $value) {
				if (ctype_digit($value)) {
					$values[] = (int)$value;
				}
			}

			return $values;
		};

		$squares = $getCustomSizes('squareSizes');
		$fillWidthHeight = $getCustomSizes('fillWidthHeightSizes')
			?? $getCustomSizes('squareUncroppedSizes');
		$coverWidthHeight = $getCustomSizes('coverWidthHeightSizes');
		$widths = $getCustomSizes('widthSizes');
		$heights = $getCustomSizes('heightSizes');

		if ($squares !== null) {
			$sizes['square'] = array_intersect($sizes['square'], $squares);
		}

		if ($fillWidthHeight !== null) {
			$sizes['fillWidthHeight'] = array_intersect(
				$sizes['fillWidthHeight'],
				$fillWidthHeight,
			);
		}

		if ($coverWidthHeight !== null) {
			$sizes['coverWidthHeight'] = array_filter(
				$coverWidthHeight,
				static fn ($size) => $size <= $maxW && $size <= $maxH && self::isPowerOfTwo($size),
			);
		}

		if ($widths !== null) {
			$sizes['width'] = array_intersect($sizes['width'], $widths);
		}

		if ($heights !== null) {
			$sizes['height'] = array_intersect($sizes['height'], $heights);
		}

		return $this->mergeSpecifications($sizes);
	}

	/**
	 * @param int[][] $sizes
	 * @return array{width: int, height: int, crop: bool}
	 */
	private function mergeSpecifications(array $sizes): array {
		return array_merge(
			array_map(static function ($squareSize) {
				return ['width' => $squareSize, 'height' => $squareSize, 'crop' => true];
			}, $sizes['square']),
			array_map(static function ($size) {
				return [
					'width' => $size,
					'height' => $size,
					'crop' => false,
					'mode' => IPreview::MODE_COVER,
				];
			}, $sizes['coverWidthHeight']),
			array_map(static function ($size) {
				return [
					'width' => $size,
					'height' => $size,
					'crop' => false,
					'mode' => IPreview::MODE_FILL,
				];
			}, $sizes['fillWidthHeight']),
			array_map(static function ($heightSize) {
				return ['width' => -1, 'height' => $heightSize, 'crop' => false];
			}, $sizes['height']),
			array_map(static function ($widthSize) {
				return ['width' => $widthSize, 'height' => -1, 'crop' => false];
			}, $sizes['width'])
		);
	}

	private static function isPowerOfTwo(int $n): bool {
		return ($n & ($n - 1)) === 0;
	}
}
