<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PreviewGenerator\Service;

final class ModuloService {
	/**
	 * Perform the modulo operation ensuring that the remainder is always positive (0 <= remainder
	 * < $n).
	 *
	 * Ref https://stackoverflow.com/a/4409320
	 */
	public static function absMod(int $x, int $n): int {
		$r = $x % $n;
		if ($r < 0) {
			$r += abs($n);
		}

		return $r;
	}
}
