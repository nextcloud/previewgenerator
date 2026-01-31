<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PreviewGenerator\Listeners;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\Folder;
use OCP\IDBConnection;
use OCP\IUserManager;

class PostWriteListener implements IEventListener {
	private IDBConnection $connection;
	private IUserManager $userManager;

	public function __construct(
		IDBConnection $connection,
		IUserManager $userManager,
		private ITimeFactory $time,
	) {
		$this->connection = $connection;
		$this->userManager = $userManager;
	}

	public function handle(Event $event): void {
		if (!($event instanceof NodeWrittenEvent)) {
			return;
		}

		$node = $event->getNode();
		$absPath = ltrim($node->getPath(), '/');
		$owner = explode('/', $absPath)[0];

		if ($node instanceof Folder || !$this->userManager->userExists($owner)) {
			return;
		}

		$qb = $this->connection->getQueryBuilder();
		$qb->select('id')
			->from('preview_generation')
			->where(
				$qb->expr()->andX(
					$qb->expr()->eq('uid', $qb->createNamedParameter($owner)),
					$qb->expr()->eq('file_id', $qb->createNamedParameter($node->getId()))
				)
			)->setMaxResults(1);
		$cursor = $qb->executeQuery();
		$inTable = $cursor->fetch() !== false;
		$cursor->closeCursor();

		// Don't insert if there is already such an entry
		if ($inTable) {
			return;
		}

		$qb = $this->connection->getQueryBuilder();
		$qb->insert('preview_generation')
			->setValue('uid', $qb->createNamedParameter($owner))
			->setValue('file_id', $qb->createNamedParameter($node->getId()))
			->setValue('queued_at', $qb->createNamedParameter($this->time->getTime()));
		$qb->executeStatement();
	}
}
