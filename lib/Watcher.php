<?php
/**
 * @copyright Copyright (c) 2016, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license GNU AGPL version 3 or any later version
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
namespace OCA\PreviewGenerator;

use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\IDBConnection;
use OCP\IUserManager;

class Watcher {

	/** @var IDBConnection */
	private $connection;

	/** @var IUserManager */
	private $userManager;

	/**
	 * Watcher constructor.
	 *
	 * @param IDBConnection $connection
	 * @param IUserManager $userManager
	 */
	public function __construct(IDBConnection $connection,
								IUserManager $userManager) {
		$this->connection = $connection;
		$this->userManager = $userManager;
	}

	/**
	 * A node has been updated. We just store the file id
	 * with the current user in the DB
	 *
	 * @param Node $node
	 */
	public function postWrite(Node $node) {
		$absPath = ltrim($node->getPath(), '/');
		$owner = explode('/', $absPath)[0];

		if (!$this->userManager->userExists($owner) || $node instanceof Folder) {
			return;
		}

		$qb = $this->connection->getQueryBuilder();
		$qb->insert('preview_generation')
			->setValue('uid', $qb->createNamedParameter($owner))
			->setValue('file_id', $qb->createNamedParameter($node->getId()));
		$qb->execute();
	}
}
