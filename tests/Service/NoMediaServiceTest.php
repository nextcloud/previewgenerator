<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PreviewGenerator\Tests\Service;

use OCA\PreviewGenerator\Service\NoMediaService;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use PHPUnit\Framework\TestCase;

class NoMediaServiceTest extends TestCase {
	private NoMediaService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->service = new NoMediaService();
	}

	public function testHasNoMediaFileInParent(): void {
		$file = $this->createMock(File::class);
		$file->method('getPath')
			->willReturn('/user/files/d1/d2/file.png');
		$dir2 = $this->createMock(Folder::class);
		$dir2->method('getPath')
			->willReturn('/user/files/d1/d2');
		$dir2->expects(self::once())
			->method('nodeExists')
			->with('.nomedia')
			->willReturn(true);
		$dir1 = $this->createMock(Folder::class);
		$dir1->method('getPath')
			->willReturn('/user/files/d1');
		$dir1->expects(self::never())
			->method('nodeExists');
		$file->method('getParent')
			->willReturn($dir2);
		$dir2->method('getParent')
			->willReturn($dir1);
		$dir1->method('getParent')
			->willThrowException(new NotFoundException());

		$this->assertEquals(true, $this->service->hasNoMediaFile($file));
	}

	public function testHasNoMediaFileInDeepParent(): void {
		$file = $this->createMock(File::class);
		$file->method('getPath')
			->willReturn('/user/files/d1/d2/file.png');
		$dir2 = $this->createMock(Folder::class);
		$dir2->method('getPath')
			->willReturn('/user/files/d1/d2');
		$dir2->expects(self::once())
			->method('nodeExists')
			->with('.nomedia')
			->willReturn(false);
		$dir1 = $this->createMock(Folder::class);
		$dir1->method('getPath')
			->willReturn('/user/files/d1');
		$dir1->expects(self::once())
			->method('nodeExists')
			->with('.nomedia')
			->willReturn(true);
		$file->method('getParent')
			->willReturn($dir2);
		$dir2->method('getParent')
			->willReturn($dir1);
		$dir1->method('getParent')
			->willThrowException(new NotFoundException());

		$this->assertEquals(true, $this->service->hasNoMediaFile($file));
	}

	public function testHasNoMediaFileUntilRoot(): void {
		$file = $this->createMock(File::class);
		$file->method('getPath')
			->willReturn('/user/files/d1/d2/file.png');
		$dir2 = $this->createMock(Folder::class);
		$dir2->method('getPath')
			->willReturn('/user/files/d1/d2');
		$dir2->expects(self::once())
			->method('nodeExists')
			->with('.nomedia')
			->willReturn(false);
		$dir1 = $this->createMock(Folder::class);
		$dir1->method('getPath')
			->willReturn('/user/files/d1');
		$dir1->expects(self::once())
			->method('nodeExists')
			->with('.nomedia')
			->willReturn(false);
		$file->method('getParent')
			->willReturn($dir2);
		$dir2->method('getParent')
			->willReturn($dir1);
		$dir1->method('getParent')
			->willThrowException(new NotFoundException());

		$this->assertEquals(false, $this->service->hasNoMediaFile($file));
	}

	public function testHasNoMediaFileWithCachedParent(): void {
		$file = $this->createMock(File::class);
		$file->method('getPath')
			->willReturn('/user/files/d1/d2/file.png');
		$dir2 = $this->createMock(Folder::class);
		$dir2->method('getPath')
			->willReturn('/user/files/d1/d2');
		$dir2->expects(self::once())
			->method('nodeExists')
			->with('.nomedia')
			->willReturn(true);
		$dir1 = $this->createMock(Folder::class);
		$dir1->method('getPath')
			->willReturn('/user/files/d1');
		$dir1->expects(self::never())
			->method('nodeExists');
		$file->method('getParent')
			->willReturn($dir2);
		$dir2->method('getParent')
			->willReturn($dir1);
		$dir1->method('getParent')
			->willThrowException(new NotFoundException());
		$this->assertEquals(true, $this->service->hasNoMediaFile($file));

		$file2 = $this->createMock(File::class);
		$file2->method('getPath')
			->willReturn('/user/files/d1/d2/file2.png');
		$file2->method('getParent')
			->willReturn($dir2);
		$this->assertEquals(true, $this->service->hasNoMediaFile($file2));
	}

	public function testHasNoMediaFileWithCachedDeepParent(): void {
		$file = $this->createMock(File::class);
		$file->method('getPath')
			->willReturn('/user/files/d1/d2a/file.png');
		$dir2 = $this->createMock(Folder::class);
		$dir2->method('getPath')
			->willReturn('/user/files/d1/d2a');
		$dir2->expects(self::once())
			->method('nodeExists')
			->with('.nomedia')
			->willReturn(false);
		$dir1 = $this->createMock(Folder::class);
		$dir1->method('getPath')
			->willReturn('/user/files/d1');
		$dir1->expects(self::once())
			->method('nodeExists')
			->with('.nomedia')
			->willReturn(true);
		$file->method('getParent')
			->willReturn($dir2);
		$dir2->method('getParent')
			->willReturn($dir1);
		$dir1->method('getParent')
			->willThrowException(new NotFoundException());
		$this->assertEquals(true, $this->service->hasNoMediaFile($file));

		$file2 = $this->createMock(File::class);
		$file2->method('getPath')
			->willReturn('/user/files/d1/d2b/file.png');
		$dir2b = $this->createMock(Folder::class);
		$dir2b->method('getPath')
			->willReturn('/user/files/d1/d2b');
		$dir2b->expects(self::never())
			->method('nodeExists');
		$file2->method('getParent')
			->willReturn($dir2b);
		$dir2b->method('getParent')
			->willReturn($dir1);
		$this->assertEquals(true, $this->service->hasNoMediaFile($file2));
	}
}
