<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PreviewGenerator\Service;

use OC\Encryption\Exceptions\ModuleDoesNotExistsException;
use OCP\Encryption\IManager as IEncryptionManager;

class EncryptionService {
	public function __construct(
		private readonly IEncryptionManager $encryptionManager,
	) {
	}

	public function isCompatibleWithCurrentEncryption(): bool {
		if (!$this->encryptionManager->isEnabled()) {
			return true;
		}

		try {
			$encryptionModule = $this->encryptionManager->getEncryptionModule();
		} catch (ModuleDoesNotExistsException $e) {
			return false;
		}

		return !$encryptionModule->needDetailedAccessList();
	}
}
