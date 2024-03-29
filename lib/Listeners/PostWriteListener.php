<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Richard Steinmetz <richard@steinmetz.cloud>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\PreviewGenerator\Listeners;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\Folder;
use OCP\IDBConnection;
use OCP\IUserManager;

class PostWriteListener implements IEventListener {
	private IDBConnection $connection;
	private IUserManager $userManager;

	public function __construct(IDBConnection $connection,
		IUserManager $userManager) {
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
		$cursor = $qb->execute();
		$inTable = $cursor->fetch() !== false;
		$cursor->closeCursor();

		// Don't insert if there is already such an entry
		if ($inTable) {
			return;
		}

		$qb = $this->connection->getQueryBuilder();
		$qb->insert('preview_generation')
			->setValue('uid', $qb->createNamedParameter($owner))
			->setValue('file_id', $qb->createNamedParameter($node->getId()));
		$qb->execute();
	}
}
