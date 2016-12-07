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
namespace OCA\PreviewGenerator\Command;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IPreview;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Generate extends Command {

	/** @var IUserManager */
	protected $userManager;

	/** @var IRootFolder */
	protected $rootFolder;

	/** @var IPreview */
	protected $previewGenerator;

	/** @var IConfig */
	protected $config;

	/** @var int[][] */
	protected $sizes;


	/**
	 * @param IRootFolder $rootFolder
	 * @param IUserManager $userManager
	 * @param IPreview $previewGenerator
	 * @param IConfig $config
	 */
	public function __construct(IRootFolder $rootFolder,
						 IUserManager $userManager,
						 IPreview $previewGenerator,
						 IConfig $config) {
		parent::__construct();

		$this->userManager = $userManager;
		$this->rootFolder = $rootFolder;
		$this->previewGenerator = $previewGenerator;
		$this->config = $config;
	}

	protected function configure() {
		$this
			->setName('preview:generate-all')
			->setDescription('Generate previews')
			->addArgument(
				'user_id',
				InputArgument::OPTIONAL,
				'Generate previews for the given user'
			);
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$userId = $input->getArgument('user_id');
		$this->calculateSizes();

		if ($userId === null) {
			$this->userManager->callForSeenUsers(function (IUser $user) {
				$this->generateUserPreviews($user);
			});
		} else {
			$user = $this->userManager->get($userId);
			if ($user !== null) {
				$this->generateUserPreviews($user);
			}
		}

		return 0;
	}

	private function calculateSizes() {
		$this->sizes = [
			'square' => [
				32,
				64,
			],
			'height' => [],
			'width' => [],
		];

		$maxW = (int)$this->config->getSystemValue('preview_max_x', 2048);
		$maxH = (int)$this->config->getSystemValue('preview_max_y', 2048);

		$w = 32;
		while($w <= $maxW) {
			$this->sizes['width'][] = $w;
			$w *= 2;
		}

		$h = 32;
		while($h <= $maxH) {
			$this->sizes['height'][] = $h;
			$h *= 2;
		}
	}

	/**
	 * @param IUser $user
	 */
	private function generateUserPreviews(IUser $user) {
		\OC_Util::tearDownFS();
		\OC_Util::setupFS($user->getUID());

		$userFolder = $this->rootFolder->getUserFolder($user->getUID());
		$this->parseFolder($userFolder);
	}

	/**
	 * @param Folder $folder
	 */
	private function parseFolder(Folder $folder) {
		$nodes = $folder->getDirectoryListing();

		foreach ($nodes as $node) {
			if ($node instanceof Folder) {
				$this->parseFolder($node);
			} else if ($node instanceof File) {
				$this->parseFile($node);
			}
		}
	}

	/**
	 * @param File $file
	 */
	private function parseFile(File $file) {
		if ($this->previewGenerator->isMimeSupported($file->getMimeType())) {
			try {
				foreach ($this->sizes['square'] as $size) {
					$this->previewGenerator->getPreview($file, $size, $size, true);
				}

				// Height previews
				foreach ($this->sizes['height'] as $height) {
					$this->previewGenerator->getPreview($file, -1, $height, false);
				}

				// Width previews
				foreach ($this->sizes['width'] as $width) {
					$this->previewGenerator->getPreview($file, $width, -1, false);
				}
			} catch (NotFoundException $e) {
				// Maybe log that previews could not be generated?
			}
		}
	}

}
