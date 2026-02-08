<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PreviewGenerator\Tests\Unit\BackgroundJob;

use OCA\PreviewGenerator\BackgroundJob\PreviewJob;
use OCA\PreviewGenerator\Service\ConfigService;
use OCA\PreviewGenerator\Service\PreGenerateService;
use OCA\PreviewGenerator\Support\PreviewLimiter\MultiLimiter;
use OCA\PreviewGenerator\Support\PreviewLimiter\PreviewLimiter;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PreviewJobTest extends TestCase {
	private PreviewJob $previewJob;

	private ITimeFactory&MockObject $time;
	private PreGenerateService&MockObject $preGenerateService;
	private LoggerInterface&MockObject $logger;
	private ConfigService&MockObject $configService;
	private IJobList&MockObject $jobList;

	protected function setUp(): void {
		parent::setUp();

		$this->time = $this->createMock(ITimeFactory::class);
		$this->preGenerateService = $this->createMock(PreGenerateService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->configService = $this->createMock(ConfigService::class);
		$this->jobList = $this->createMock(IJobList::class);

		$this->time->method('getTime')
			->willReturnCallback(time(...));

		$this->configService->expects(self::once())
			->method('getMaxBackgroundJobPreviews')
			->willReturn(100);
		$this->configService->expects(self::once())
			->method('getMaxBackgroundJobExecutionTime')
			->willReturn(300);

		$this->previewJob = new PreviewJob(
			$this->time,
			$this->preGenerateService,
			$this->logger,
			$this->configService,
		);
	}

	public function testJobSettings(): void {
		$this->assertEquals(300, $this->previewJob->getInterval());
		$this->assertTrue($this->previewJob->isTimeSensitive());
	}

	public function testRun(): void {
		$this->configService->method('isBackgroundJobDisabled')
			->willReturn(false);
		$this->configService->method('usesCronDaemon')
			->willReturn(true);

		$this->preGenerateService->expects(self::once())
			->method('setLogger')
			->with($this->logger);
		$this->preGenerateService->expects(self::once())
			->method('setLimiter')
			->willReturnCallback(function (PreviewLimiter $limiter): void {
				$this->assertInstanceOf(MultiLimiter::class, $limiter);
			});

		$this->preGenerateService->expects(self::once())
			->method('preGenerate');

		$this->previewJob->start($this->jobList);
	}

	/** @dataProvider runSkipsDataProvider */
	public function testRunSkips(bool $isBackgroundJobDisabled, bool $usesCronDaemon): void {
		$this->configService->method('isBackgroundJobDisabled')
			->willReturn($isBackgroundJobDisabled);
		$this->configService->method('usesCronDaemon')
			->willReturn($usesCronDaemon);

		$this->preGenerateService->expects(self::never())
			->method('setLogger');
		$this->preGenerateService->expects(self::never())
			->method('setLimiter');

		$this->preGenerateService->expects(self::never())
			->method('preGenerate');

		$this->previewJob->start($this->jobList);
	}

	public static function runSkipsDataProvider(): array {
		return [
			'background job is disabled' => [
				'isBackgroundJobDisabled' => true,
				'usesCronDaemon' => true,
			],
			'cron daemon is not used' => [
				'isBackgroundJobDisabled' => false,
				'usesCronDaemon' => false,
			],
			'background job is disabled and no cron daemon is used' => [
				'isBackgroundJobDisabled' => true,
				'usesCronDaemon' => false,
			],
		];
	}
}
