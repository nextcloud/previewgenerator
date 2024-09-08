<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PreviewGenerator\Service;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;

class NoMediaService {
	private array $noMediaCache = [];

	/**
	 * Try to find a .nomedia file recursively in any of the file's parent directories.
	 */
	public function hasNoMediaFile(File $file): bool {
		$path = $file->getPath();
		foreach ($this->noMediaCache as $dir => $has) {
			if (str_starts_with($path, $dir)) {
				return $has;
			}
		}

		/** @var ?Folder $parent */
		$parent = null;
		while (true) {
			try {
				$parent = ($parent ?? $file)->getParent();
			} catch (NotFoundException $e) {
				// Root has been reached
				break;
			}

			if ($parent->nodeExists('.nomedia')) {
				$this->noMediaCache[$parent->getPath()] = true;
				return true;
			}
		}

		return false;
	}
}
