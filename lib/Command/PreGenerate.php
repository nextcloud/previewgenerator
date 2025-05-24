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

namespace OCA\PreviewGenerator\Command;

use OCA\PreviewGenerator\Service\NoMediaService;
use OCA\PreviewGenerator\SizeHelper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Encryption\IManager;
use OCP\Files\File;
use OCP\Files\GenericFileException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IPreview;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PreGenerate extends Command {
	/* @return array{width: int, height: int, crop: bool} */
	protected array $specifications;

	protected string $appName;
	protected IUserManager $userManager;
	protected IRootFolder $rootFolder;
	protected IPreview $previewGenerator;
	protected IConfig $config;
	protected IDBConnection $connection;
	protected OutputInterface $output;
	protected IManager $encryptionManager;
	protected ITimeFactory $time;

	/**
	 * @param string $appName
	 * @param IRootFolder $rootFolder
	 * @param IUserManager $userManager
	 * @param IPreview $previewGenerator
	 * @param IConfig $config
	 * @param IDBConnection $connection
	 * @param IManager $encryptionManager
	 * @param ITimeFactory $time
	 */
	public function __construct(string $appName,
		IRootFolder $rootFolder,
		IUserManager $userManager,
		IPreview $previewGenerator,
		IConfig $config,
		IDBConnection $connection,
		IManager $encryptionManager,
		ITimeFactory $time,
		NoMediaService $noMediaService,
		SizeHelper $sizeHelper) {
		parent::__construct();

		$this->appName = $appName;
		$this->userManager = $userManager;
		$this->rootFolder = $rootFolder;
		$this->previewGenerator = $previewGenerator;
		$this->config = $config;
		$this->connection = $connection;
		$this->encryptionManager = $encryptionManager;
		$this->time = $time;
		$this->noMediaService = $noMediaService;
		$this->sizeHelper = $sizeHelper;
	}

	protected function configure(): void {
		$this
			->setName('preview:pre-generate')
			->setDescription('Pre generate only images that have been added or changed since the last regular run');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		if ($this->encryptionManager->isEnabled()) {
			$output->writeln('Encryption is enabled. Aborted.');
			return 1;
		}

		// Set timestamp output
		$formatter = new TimestampFormatter($this->config, $output->getFormatter());
		$output->setFormatter($formatter);
		$this->output = $output;

		$this->specifications = $this->sizeHelper->generateSpecifications();
		if ($this->output->getVerbosity() > OutputInterface::VERBOSITY_VERY_VERBOSE) {
			$output->writeln('Specifications: ' . json_encode($this->specifications));
		}
		$this->startProcessing();

		return 0;
	}

	private function startProcessing(): void {
		// random sleep between 0 and 50ms to avoid collision between 2 processes
		usleep(rand(0,50000));

		while (true) {
			$qb = $this->connection->getQueryBuilder();
			$row = $qb->select('*')
				->from('preview_generation')
				->orderBy('id')
				->where($qb->expr()->eq('locked', $qb->createNamedParameter(false)))
				->setMaxResults(1)
				->execute()
				->fetch();

			if ($row === false) {
				break;
			}

			$qb->update('preview_generation')
			   ->where($qb->expr()->eq('id', $qb->createNamedParameter($row['id'])))
			   ->set('locked', $qb->createNamedParameter(true))
			   ->execute();
			try {
				$this->processRow($row);
			} finally {
				$qb->delete('preview_generation')
					->where($qb->expr()->eq('id', $qb->createNamedParameter($row['id'])))
				    ->execute();
			}
		}
	}

	private function processRow($row): void {
		//Get user
		$user = $this->userManager->get($row['uid']);

		if ($user === null) {
			return;
		}

		\OC_Util::tearDownFS();
		\OC_Util::setupFS($row['uid']);

		try {
			$userFolder = $this->rootFolder->getUserFolder($user->getUID());
			$userRoot = $userFolder->getParent();
		} catch (NotFoundException $e) {
			return;
		}

		//Get node
		$nodes = $userRoot->getById($row['file_id']);

		if ($nodes === []) {
			return;
		}

		$node = $nodes[0];
		if ($node instanceof File) {
			$this->processFile($node);
		}
	}

	private function processFile(File $file): void {
		$absPath = ltrim($file->getPath(), '/');
		$pathComponents = explode('/', $absPath);
		if (isset($pathComponents[1]) && $pathComponents[1] === 'files_trashbin') {
			return;
		}

		if ($this->noMediaService->hasNoMediaFile($file)) {
			return;
		}

		if ($this->previewGenerator->isMimeSupported($file->getMimeType())) {
			if ($this->output->getVerbosity() > OutputInterface::VERBOSITY_VERBOSE) {
				$this->output->writeln('Generating previews for ' . $file->getPath());
			}

			try {
				$this->previewGenerator->generatePreviews($file, $this->specifications);
			} catch (NotFoundException $e) {
				// Maybe log that previews could not be generated?
				if ($this->output->getVerbosity() > OutputInterface::VERBOSITY_VERBOSE) {
					$error = $e->getMessage();
					$this->output->writeln("<error>${error} " . $file->getPath() . " not found.</error>");
				}
			} catch (\InvalidArgumentException|GenericFileException $e) {
				$class = $e::class;
				$error = $e->getMessage();
				$this->output->writeln("<error>{$class}: {$error}</error>");
			}
		}
	}

	private function setPID(): void {
		$this->config->setAppValue($this->appName, 'pid', posix_getpid());
	}

	private function clearPID(): void {
		$this->config->deleteAppValue($this->appName, 'pid');
	}

	private function getPID(): int {
		return (int)$this->config->getAppValue($this->appName, 'pid', -1);
	}

	private function checkAlreadyRunning(): bool {
		$pid = $this->getPID();

		// No PID set so just continue
		if ($pid === -1) {
			return false;
		}

		// Get the gid of non running processes so continue
		if (posix_getpgid($pid) === false) {
			return false;
		}

		// Seems there is already a running process generating previews
		return true;
	}
}
