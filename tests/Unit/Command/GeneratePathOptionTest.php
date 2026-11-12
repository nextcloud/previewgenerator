<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PreviewGenerator\Tests\Unit\Command;

use OCA\PreviewGenerator\Command\Generate;
use OCA\PreviewGenerator\Service\EncryptionService;
use OCA\PreviewGenerator\SizeHelper;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\IUserFolder;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IPreview;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Tester\CommandTester;

class GeneratePathOptionTest extends TestCase {
	private CommandTester $tester;

	private IUserManager&MockObject $userManager;
	private IRootFolder&MockObject $rootFolder;
	private Folder&MockObject $userFolder;

	protected function setUp(): void {
		parent::setUp();

		$this->userManager = $this->createMock(IUserManager::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);

		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValue')
			->willReturnArgument(1);

		$encryptionService = $this->createMock(EncryptionService::class);
		$encryptionService->method('isCompatibleWithCurrentEncryption')
			->willReturn(true);

		// The file system setup needs a running server, so the command under test skips it.
		$command = new class($this->rootFolder, $this->userManager, $this->createMock(IPreview::class), $config, $encryptionService, $this->createMock(ContainerInterface::class), $this->createMock(SizeHelper::class)) extends Generate {
			protected function setupUserFileSystem(IUser $user): void {
			}
		};
		$this->tester = new CommandTester($command);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')
			->willReturn('alice');
		$this->userManager->method('get')
			->with('alice')
			->willReturn($user);

		// IRootFolder::getUserFolder() returns IUserFolder on newer servers and Folder on older ones.
		$this->userFolder = $this->createMock(
			interface_exists(IUserFolder::class) ? IUserFolder::class : Folder::class
		);
		$this->userFolder->method('getPath')
			->willReturn('/alice/files');
		$this->rootFolder->method('getUserFolder')
			->with('alice')
			->willReturn($this->userFolder);
	}

	private function createEmptyFolder(string $path): Folder&MockObject {
		$folder = $this->createMock(Folder::class);
		$folder->method('getPath')
			->willReturn($path);
		$folder->method('nodeExists')
			->willReturn(false);
		$folder->method('getDirectoryListing')
			->willReturn([]);
		return $folder;
	}

	public function testPathOutsideUserFolderIsReportedAsError(): void {
		$this->userFolder->method('getRelativePath')
			->with('/alice/cache/photo.jpg')
			->willReturn(null);
		$this->userFolder->expects(self::never())
			->method('get');

		$status = $this->tester->execute(['--path' => ['/alice/cache/photo.jpg']]);

		self::assertSame(1, $status);
		self::assertStringContainsString(
			'Path /alice/cache/photo.jpg is not inside the files folder of user alice',
			$this->tester->getDisplay(),
		);
	}

	public function testPathInsideUserFolderIsScanned(): void {
		$this->userFolder->method('getRelativePath')
			->with('/alice/files/Photos')
			->willReturn('/Photos');
		$this->userFolder->expects(self::once())
			->method('get')
			->with('/Photos')
			->willReturn($this->createEmptyFolder('/alice/files/Photos'));

		$status = $this->tester->execute(['--path' => ['/alice/files/Photos']]);

		self::assertSame(0, $status);
		self::assertStringContainsString('Scanning folder /alice/files/Photos', $this->tester->getDisplay());
	}

	public function testMissingPathInsideUserFolderIsReportedAsError(): void {
		$this->userFolder->method('getRelativePath')
			->with('/alice/files/nope')
			->willReturn('/nope');
		$this->userFolder->method('get')
			->with('/nope')
			->willThrowException(new NotFoundException());

		$status = $this->tester->execute(['--path' => ['/alice/files/nope']]);

		self::assertSame(1, $status);
		self::assertStringContainsString('Path not found: /alice/files/nope', $this->tester->getDisplay());
	}

	public function testValidPathIsStillScannedAfterAMissingOne(): void {
		$this->userFolder->method('getRelativePath')
			->willReturnMap([
				['/alice/files/nope', '/nope'],
				['/alice/files/Photos', '/Photos'],
			]);
		$this->userFolder->method('get')
			->willReturnCallback(function (string $path) {
				if ($path === '/nope') {
					throw new NotFoundException();
				}
				return $this->createEmptyFolder('/alice/files/Photos');
			});

		$status = $this->tester->execute(['--path' => ['/alice/files/nope', '/alice/files/Photos']]);

		self::assertSame(1, $status);
		$display = $this->tester->getDisplay();
		self::assertStringContainsString('Path not found: /alice/files/nope', $display);
		self::assertStringContainsString('Scanning folder /alice/files/Photos', $display);
	}

	public function testValidPathIsStillScannedAfterAnInvalidOne(): void {
		$this->userFolder->method('getRelativePath')
			->willReturnMap([
				['/alice/cache/photo.jpg', null],
				['/alice/files/Photos', '/Photos'],
			]);
		$this->userFolder->expects(self::once())
			->method('get')
			->with('/Photos')
			->willReturn($this->createEmptyFolder('/alice/files/Photos'));

		$status = $this->tester->execute(['--path' => ['/alice/cache/photo.jpg', '/alice/files/Photos']]);

		self::assertSame(1, $status);
		$display = $this->tester->getDisplay();
		self::assertStringContainsString('is not inside the files folder of user alice', $display);
		self::assertStringContainsString('Scanning folder /alice/files/Photos', $display);
	}
}
