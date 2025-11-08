<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PreviewGenerator\Command;

use OCA\Files_External\Service\GlobalStoragesService;
use OCA\PreviewGenerator\Model\WorkerConfig;
use OCA\PreviewGenerator\SizeHelper;
use OCP\Encryption\IManager;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\GenericFileException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\StorageInvalidException;
use OCP\Files\StorageNotAvailableException;
use OCP\IConfig;
use OCP\IPreview;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Generate extends Command {
	private const ENV_WORKER_CONF = 'PREVIEWGENERATOR_WORKER_CONF';
	private const OPT_WORKERS = 'workers';

	/* @return array{width: int, height: int, crop: bool} */
	protected array $specifications;

	protected ?GlobalStoragesService $globalService;
	protected IUserManager $userManager;
	protected IRootFolder $rootFolder;
	protected IPreview $previewGenerator;
	protected IConfig $config;
	protected OutputInterface $output;
	protected IManager $encryptionManager;
	protected SizeHelper $sizeHelper;

	private ?WorkerConfig $workerConfig = null;

	public function __construct(IRootFolder $rootFolder,
		IUserManager $userManager,
		IPreview $previewGenerator,
		IConfig $config,
		IManager $encryptionManager,
		ContainerInterface $container,
		SizeHelper $sizeHelper) {
		parent::__construct();

		$this->userManager = $userManager;
		$this->rootFolder = $rootFolder;
		$this->previewGenerator = $previewGenerator;
		$this->config = $config;
		$this->encryptionManager = $encryptionManager;
		$this->sizeHelper = $sizeHelper;

		try {
			$this->globalService = $container->get(GlobalStoragesService::class);
		} catch (ContainerExceptionInterface $e) {
			$this->globalService = null;
		}
	}

	protected function configure(): void {
		$this
			->setName('preview:generate-all')
			->setDescription('Generate previews for all images')
			->addArgument(
				'user_id',
				InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
				'Generate previews for the given user(s)'
			)->addOption(
				'path',
				'p',
				InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
				'limit scan to this path, eg. --path="/alice/files/Photos", the user_id is determined by the path and all user_id arguments are ignored, multiple usages allowed'
			)->addOption(
				self::OPT_WORKERS,
				'w',
				InputOption::VALUE_OPTIONAL,
				'Spawn multiple parallel workers to increase speed of preview generation',
			);
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
		if ($this->output->getVerbosity() > OutputInterface::VERBOSITY_VERY_VERBOSE
			&& !getenv(self::ENV_WORKER_CONF)
		) {
			$output->writeln('Specifications: ' . json_encode($this->specifications));
		}

		if (getenv(self::ENV_WORKER_CONF)) {
			return $this->executeWorker($input);
		}

		if ($input->getOption(self::OPT_WORKERS)) {
			return $this->executeCoordinator($input);
		}

		return $this->executeDefault($input);
	}

	private function executeCoordinator(InputInterface $input) {

		$workerCount = (int)$input->getOption(self::OPT_WORKERS);
		if ($workerCount <= 0) {
			$this->output->writeln("<error>Invalid worker count: $workerCount</error>");
			return 1;
		}

		$workerPids = [];
		for ($i = 0; $i < $workerCount; $i++) {
			$this->output->writeln("Spawning worker $i");

			$workerconfig = new WorkerConfig($i, $workerCount);
			$pid = pcntl_fork();
			if ($pid == -1) {
				$this->output->writeln('<error>Failed to fork worker</error>');
				return 1;
			} elseif ($pid) {
				// Parent
				$workerPids[] = $pid;
			} else {
				// Child
				$argv = $_SERVER['argv'];
				$env = getenv();
				$env[self::ENV_WORKER_CONF] = json_encode($workerconfig, JSON_THROW_ON_ERROR);
				pcntl_exec($argv[0], array_slice($argv, 1), $env);
			}
		}

		$workerFailed = false;
		foreach ($workerPids as $index => $pid) {
			$status = 0;
			pcntl_waitpid($pid, $status);
			$exitCode = pcntl_wexitstatus($status);

			if ($exitCode !== 0) {
				$workerFailed = true;
			}

			$this->output->writeln("Worker $index exited with code $exitCode");
		}

		return $workerFailed ? 1 : 0;
	}

	private function executeWorker(InputInterface $input): int {
		$workerConfigEnv = getenv(self::ENV_WORKER_CONF);
		$data = json_decode($workerConfigEnv, true);
		$this->workerConfig = WorkerConfig::fromJson($data);
		return $this->executeDefault($input);
	}

	private function executeDefault(InputInterface $input): int {
		$inputPaths = $input->getOption('path');
		if ($inputPaths) {
			foreach ($inputPaths as $inputPath) {
				$inputPath = '/' . trim($inputPath, '/');
				[, $userId,] = explode('/', $inputPath, 3);
				$user = $this->userManager->get($userId);
				if ($user !== null) {
					$this->generatePathPreviews($user, $inputPath);
				}
			}
		} else {
			$userIds = $input->getArgument('user_id');
			if (count($userIds) === 0) {
				$this->userManager->callForSeenUsers(function (IUser $user) {
					$this->generateUserPreviews($user);
				});
			} else {
				foreach ($userIds as $userId) {
					$user = $this->userManager->get($userId);
					if ($user !== null) {
						$this->generateUserPreviews($user);
					}
				}
			}
		}

		return 0;
	}

	private function getNoPreviewMountPaths(IUser $user): array {
		if ($this->globalService === null) {
			return [];
		}
		$mountPaths = [];
		$userId = $user->getUID();
		$mounts = $this->globalService->getStorageForAllUsers();
		foreach ($mounts as $mount) {
			if (in_array($userId, $mount->getApplicableUsers()) &&
				$mount->getMountOptions()['previews'] === false
			) {
				$userFolder = $this->rootFolder->getUserFolder($userId)->getPath();
				array_push($mountPaths, $userFolder . $mount->getMountPoint());
			}
		}
		return $mountPaths;
	}

	private function generatePathPreviews(IUser $user, string $path): void {
		\OC_Util::tearDownFS();
		\OC_Util::setupFS($user->getUID());
		$userFolder = $this->rootFolder->getUserFolder($user->getUID());
		try {
			$relativePath = $userFolder->getRelativePath($path);
		} catch (NotFoundException $e) {
			$this->output->writeln('Path not found');
			return;
		}
		$pathFolder = $userFolder->get($relativePath);
		$noPreviewMountPaths = $this->getNoPreviewMountPaths($user);
		$this->parseFolder($pathFolder, $noPreviewMountPaths);
	}

	private function generateUserPreviews(IUser $user): void {
		\OC_Util::tearDownFS();
		\OC_Util::setupFS($user->getUID());

		$userFolder = $this->rootFolder->getUserFolder($user->getUID());
		$noPreviewMountPaths = $this->getNoPreviewMountPaths($user);
		$this->parseFolder($userFolder, $noPreviewMountPaths);
	}

	private function parseFolder(Folder $folder, array $noPreviewMountPaths): void {
		try {
			$folderPath = $folder->getPath();

			// Respect the '.nomedia' file. If present don't traverse the folder
			// Same for external mounts with previews disabled
			if ($folder->nodeExists('.nomedia') || in_array($folderPath, $noPreviewMountPaths)) {
				if ($this->workerConfig === null) {
					$this->output->writeln('Skipping folder ' . $folderPath);
				}
				return;
			}

			if ($this->workerConfig === null) {
				$this->output->writeln('Scanning folder ' . $folderPath);
			}

			$nodes = $folder->getDirectoryListing();

			foreach ($nodes as $node) {
				if ($node instanceof Folder) {
					$this->parseFolder($node, $noPreviewMountPaths);
				} elseif ($node instanceof File) {
					$this->parseFile($node);
				}
			}
		} catch (StorageNotAvailableException|StorageInvalidException $e) {
			$this->output->writeln(sprintf('<error>Storage for folder %s is not available: %s</error>',
				$folder->getPath(),
				$e->getMessage(),
			));
		}
	}

	private function parseFile(File $file): void {
		if (!$this->previewGenerator->isMimeSupported($file->getMimeType())) {
			return;
		}

		if ($this->workerConfig !== null) {
			$hash = $this->hashFileId($file->getId());
			if (($hash % $this->workerConfig->getWorkerCount()) !== $this->workerConfig->getWorkerIndex()) {
				return;
			}
		}

		if ($this->output->getVerbosity() > OutputInterface::VERBOSITY_VERBOSE) {
			$prefix = '';
			if ($this->workerConfig !== null) {
				$workerIndex = $this->workerConfig->getWorkerIndex();
				$prefix = "[WORKER $workerIndex] ";
			}
			$this->output->writeln("{$prefix}Generating previews for " . $file->getPath());
		}

		try {
			$this->previewGenerator->generatePreviews($file, $this->specifications);
		} catch (NotFoundException $e) {
			// Maybe log that previews could not be generated?
		} catch (\InvalidArgumentException|GenericFileException $e) {
			$class = $e::class;
			$error = $e->getMessage();
			$this->output->writeln("<error>{$class}: {$error}</error>");
		}
	}

	/**
	 * Hash the given file id into an integer to ensure even distribution of work between workers.
	 */
	private function hashFileId(int $fileId): int {
		// Fall back to 32 bit hash on 32 bit systems
		if (PHP_INT_SIZE === 4) {
			$digest = hash('xxh32', (string)$fileId, true);
			return unpack('l', $digest)[1];
		}

		$digest = hash('xxh3', (string)$fileId, true);
		return unpack('q', $digest)[1];
	}
}
