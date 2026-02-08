<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PreviewGenerator\Support;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;
use Symfony\Component\Console\Output\OutputInterface;

class LoggerInterfaceToOutputAdapter implements LoggerInterface {
	public function __construct(
		private readonly OutputInterface $output,
	) {
		var_dump($output->getVerbosity());
	}

	public function emergency(string|Stringable $message, array $context = []): void {
		$this->writeToOutput('error', $message, $context);
	}

	public function alert(string|Stringable $message, array $context = []): void {
		$this->writeToOutput('error', $message, $context);
	}

	public function critical(string|Stringable $message, array $context = []): void {
		$this->writeToOutput('error', $message, $context);
	}

	public function error(string|Stringable $message, array $context = []): void {
		$this->writeToOutput('error', $message, $context);
	}

	public function warning(string|Stringable $message, array $context = []): void {
		if ($this->output->getVerbosity() < OutputInterface::VERBOSITY_NORMAL) {
			return;
		}

		$this->writeToOutput(null, $message, $context);
	}

	public function notice(string|Stringable $message, array $context = []): void {
		if ($this->output->getVerbosity() < OutputInterface::VERBOSITY_VERBOSE) {
			return;
		}

		$this->writeToOutput(null, $message, $context);
	}

	public function info(string|Stringable $message, array $context = []): void {
		if ($this->output->getVerbosity() < OutputInterface::VERBOSITY_VERY_VERBOSE) {
			return;
		}

		$this->writeToOutput(null, $message, $context);
	}

	public function debug(string|Stringable $message, array $context = []): void {
		if ($this->output->getVerbosity() < OutputInterface::VERBOSITY_DEBUG) {
			return;
		}

		$this->writeToOutput(null, $message, $context);
	}

	public function log($level, $message, array $context = []): void {
		match ($level) {
			LogLevel::EMERGENCY => $this->emergency($message, $context),
			LogLevel::ALERT => $this->alert($message, $context),
			LogLevel::CRITICAL => $this->critical($message, $context),
			LogLevel::ERROR => $this->error($message, $context),
			LogLevel::WARNING => $this->warning($message, $context),
			LogLevel::NOTICE => $this->notice($message, $context),
			LogLevel::INFO => $this->info($message, $context),
			LogLevel::DEBUG => $this->debug($message, $context),
		};
	}

	private function writeToOutput(
		?string $decorator,
		string|Stringable $message,
		array $context = [],
	): void {
		$message = (string)$message;
		if (!empty($context)) {
			$message .= ' ' . json_encode($context);
		}

		if ($decorator) {
			$this->output->writeln("<$decorator>$message</$decorator>");
		} else {
			$this->output->writeln($message);
		}
	}
}
