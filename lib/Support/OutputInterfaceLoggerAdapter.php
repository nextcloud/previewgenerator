<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PreviewGenerator\Support;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\OutputInterface;

class OutputInterfaceLoggerAdapter implements OutputInterface {
	private string $logLevel = LogLevel::INFO;

	public function __construct(
		private readonly LoggerInterface $logger,
	) {
	}

	public function setLogLevel(string $logLevel): void {
		$this->logLevel = $logLevel;
	}

	public function writeln(string|iterable $messages, int $options = 0) {
		if (is_iterable($messages)) {
			$message = implode(' ', [...$messages]);
		} else {
			$message = $messages;
		}

		$this->logger->log($this->logLevel, $message, [
			'source' => self::class,
		]);
	}

	public function write(
		iterable|string $messages,
		bool $newline = false,
		int $options = 0,
	) {
		$this->writeln($messages, $options);
	}

	public function setVerbosity(int $level) {
		$this->logLevel = match ($level) {
			self::VERBOSITY_DEBUG => LogLevel::DEBUG,
			self::VERBOSITY_VERY_VERBOSE => LogLevel::INFO,
			self::VERBOSITY_VERBOSE => LogLevel::NOTICE,
			self::VERBOSITY_NORMAL => LogLevel::WARNING,
			_ => LogLevel::ERROR,
		};
	}

	public function getVerbosity(): int {
		return match ($this->logLevel) {
			LogLevel::DEBUG => self::VERBOSITY_DEBUG,
			LogLevel::INFO => self::VERBOSITY_VERY_VERBOSE,
			LogLevel::NOTICE => self::VERBOSITY_VERBOSE,
			LogLevel::WARNING => self::VERBOSITY_NORMAL,
			_ => self::VERBOSITY_QUIET,
		};
	}

	public function isQuiet(): bool {
		return $this->getVerbosity() === self::VERBOSITY_QUIET;
	}

	public function isVerbose(): bool {
		return $this->getVerbosity() === self::VERBOSITY_VERBOSE;
	}

	public function isVeryVerbose(): bool {
		return $this->getVerbosity() === self::VERBOSITY_VERY_VERBOSE;
	}

	public function isDebug(): bool {
		return $this->getVerbosity() === self::VERBOSITY_DEBUG;
	}

	public function setDecorated(bool $decorated) {
	}

	public function isDecorated(): bool {
		throw new RuntimeException('Not implemented');
	}

	public function setFormatter(OutputFormatterInterface $formatter) {
	}

	public function getFormatter(): OutputFormatterInterface {
		throw new RuntimeException('Not implemented');
	}
}
