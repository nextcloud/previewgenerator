<?php

declare(strict_types=1);
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

namespace OCA\PreviewGenerator\Command;

use OCA\PreviewGenerator\SizeHelper;
use OCP\Encryption\IManager;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\StorageNotAvailableException;
use OCP\IConfig;
use OCP\IPreview;
use OCP\IUser;
use OCP\IUserManager;
use OCA\Files_External\Service\GlobalStoragesService;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Generate extends Command {

	/** @var ?GlobalStoragesService */
	protected $globalService;

	/** @var IUserManager */
	protected $userManager;

	/** @var IRootFolder */
	protected $rootFolder;

	/** @var IPreview */
	protected $previewGenerator;

	/** @var IConfig */
	protected $config;

	/** @var OutputInterface */
	protected $output;

	/** @var int[][] */
	protected $sizes;

	/** @var IManager */
	protected $encryptionManager;

	public function __construct(IRootFolder $rootFolder,
								IUserManager $userManager,
								IPreview $previewGenerator,
								IConfig $config,
								IManager $encryptionManager,
								ContainerInterface $container) {
		parent::__construct();

		$this->userManager = $userManager;
		$this->rootFolder = $rootFolder;
		$this->previewGenerator = $previewGenerator;
		$this->config = $config;
		$this->encryptionManager = $encryptionManager;

		try {
			$this->globalService = $container->get(GlobalStoragesService::class);
		} catch (ContainerExceptionInterface $e) {
			$this->globalService = null;
		}
	}

	protected function configure() {
		$this
			->setName('preview:generate-all')
			->setDescription('Generate previews')
			->addArgument(
				'user_id',
				InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
				'Generate previews for the given user(s)'
			)->addOption(
				'path',
				'p',
				InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
				'limit scan to this path, eg. --path="/alice/files/Photos", the user_id is determined by the path and all user_id arguments are ignored, multiple usages allowed'
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

		$this->sizes = SizeHelper::calculateSizes($this->config);

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
				array_push($mountPaths, $userFolder.$mount->getMountPoint());
			}
		}
		return $mountPaths;
	}

	private function generatePathPreviews(IUser $user, string $path) {
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

	private function generateUserPreviews(IUser $user) {
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
				$this->output->writeln('Skipping folder ' . $folderPath);
				return;
			}

			$this->output->writeln('Scanning folder ' . $folderPath);

			$nodes = $folder->getDirectoryListing();

			foreach ($nodes as $node) {
				if ($node instanceof Folder) {
					$this->parseFolder($node, $noPreviewMountPaths);
				} elseif ($node instanceof File) {
					$this->parseFile($node);
				}
			}
		} catch (StorageNotAvailableException $e) {
			$this->output->writeln(sprintf('<error>Storage for folder folder %s is not available: %s</error>',
				$folder->getPath(),
				$e->getHint()
			));
		}
	}

	private function parseFile(File $file) {
		if ($this->previewGenerator->isMimeSupported($file->getMimeType())) {
			if ($this->output->getVerbosity() > OutputInterface::VERBOSITY_VERBOSE) {
				$this->output->writeln('Generating previews for ' . $file->getPath());
			}

			try {
				$specifications = array_merge(
					array_map(function ($squareSize) {
						return ['width' => $squareSize, 'height' => $squareSize, 'crop' => true];
					}, $this->sizes['square']),
					array_map(function ($heightSize) {
						return ['width' => -1, 'height' => $heightSize, 'crop' => false];
					}, $this->sizes['height']),
					array_map(function ($widthSize) {
						return ['width' => $widthSize, 'height' => -1, 'crop' => false];
					}, $this->sizes['width'])
				);
				$this->previewGenerator->generatePreviews($file, $specifications);
			} catch (NotFoundException $e) {
				// Maybe log that previews could not be generated?
			} catch (\InvalidArgumentException $e) {
				$error = $e->getMessage();
				$this->output->writeln("<error>${error}</error>");
			}
		}
	}
}
