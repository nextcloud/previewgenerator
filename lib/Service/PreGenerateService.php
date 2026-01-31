<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PreviewGenerator\Service;

use OC\DB\Exceptions\DbalException;
use OCA\PreviewGenerator\Command\TimestampFormatter;
use OCA\PreviewGenerator\Exceptions\EncryptionEnabledException;
use OCA\PreviewGenerator\SizeHelper;
use OCA\PreviewGenerator\Support\OutputInterfaceLoggerAdapter;
use OCA\PreviewGenerator\Support\PreviewLimiter\PreviewLimiter;
use OCP\AppFramework\Db\TTransactional;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception;
use OCP\Encryption\IManager;
use OCP\Files\File;
use OCP\Files\GenericFileException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IPreview;
use OCP\IUserManager;
use Symfony\Component\Console\Output\OutputInterface;

class PreGenerateService {
	use TTransactional;

	/* @return array{width: int, height: int, crop: bool} */
	private array $specifications;

	private ?OutputInterface $output = null;
	private ?PreviewLimiter $limiter = null;

	public function __construct(
		private IRootFolder $rootFolder,
		private IUserManager $userManager,
		private IPreview $previewGenerator,
		private IConfig $config,
		private IDBConnection $connection,
		private IManager $encryptionManager,
		private ITimeFactory $time,
		private SizeHelper $sizeHelper,
		private NoMediaService $noMediaService,
	) {
	}

	public function setLimiter(PreviewLimiter $limiter): void {
		$this->limiter = $limiter;
	}

	/**
	 * @throws EncryptionEnabledException If encryption is enabled.
	 */
	public function preGenerate(OutputInterface $output): void {
		if ($this->encryptionManager->isEnabled()) {
			throw new EncryptionEnabledException();
		}

		// Set timestamp output
		if (!($output instanceof OutputInterfaceLoggerAdapter)) {
			$formatter = new TimestampFormatter($this->config, $output->getFormatter());
			$output->setFormatter($formatter);
		}

		$this->output = $output;

		if ($this->limiter) {
			$output->writeln('Using limiter: ' . get_class($this->limiter));
		}

		$this->specifications = $this->sizeHelper->generateSpecifications();
		if ($this->output->getVerbosity() > OutputInterface::VERBOSITY_VERY_VERBOSE) {
			$output->writeln('Specifications: ' . json_encode($this->specifications));
		}
		$this->startProcessing();
	}

	private function startProcessing(): void {
		while ($this->limiter?->next() ?? true) {
			/*
			 * Get and delete the row so that if preview generation fails for some reason the next
			 * run can just continue. Wrap in transaction to make sure that one row is handled by
			 * one process only.
			 */
			$row = $this->atomic(function () {
				$qb = $this->connection->getQueryBuilder();
				$qb->select('*')
					->from('preview_generation')
					->orderBy('id')
					->setMaxResults(1);
				$result = $qb->executeQuery();
				$row = $result->fetch();
				$result->closeCursor();

				if (!$row) {
					return null;
				}

				$qb = $this->connection->getQueryBuilder();
				$qb->delete('preview_generation')
					->where($qb->expr()->eq('id', $qb->createNamedParameter($row['id'])));
				$qb->executeStatement();

				return $row;
			}, $this->connection);


			if (!$row) {
				break;
			}

			$this->processRow($row);
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
			} catch (\InvalidArgumentException|GenericFileException $e) {
				$class = $e::class;
				$error = $e->getMessage();
				$this->output->writeln("<error>{$class}: {$error}</error>");
			} catch (DbalException $e) {
				// Since the introduction of the oc_previews table, preview duplication caused by
				// duplicated specifications will cause a UniqueConstraintViolationException. We can
				// simply ignore this exception here and carry on.
				if ($e->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
					throw $e;
				}
			}
		}
	}
}
