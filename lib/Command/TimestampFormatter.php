<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2015 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\PreviewGenerator\Command;

use OCP\IConfig;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyleInterface;

/**
 * TODO: This was taken from the server. At some point we should have it in OCP
 * and then just use that
 *
 * Class TimestampFormatter
 *
 * @package OCA\PreviewGenerator\Command
 */
class TimestampFormatter implements OutputFormatterInterface {
	protected IConfig $config;
	protected OutputFormatterInterface $formatter;

	/**
	 * @param IConfig $config
	 * @param OutputFormatterInterface $formatter
	 */
	public function __construct(IConfig $config, OutputFormatterInterface $formatter) {
		$this->config = $config;
		$this->formatter = $formatter;
	}

	/**
	 * Sets the decorated flag.
	 *
	 * @param bool $decorated Whether to decorate the messages or not
	 */
	public function setDecorated($decorated): void {
		$this->formatter->setDecorated($decorated);
	}

	/**
	 * Gets the decorated flag.
	 *
	 * @return bool true if the output will decorate messages, false otherwise
	 */
	public function isDecorated(): bool {
		return $this->formatter->isDecorated();
	}

	/**
	 * Sets a new style.
	 *
	 * @param string $name The style name
	 * @param OutputFormatterStyleInterface $style The style instance
	 */
	public function setStyle($name, OutputFormatterStyleInterface $style): void {
		$this->formatter->setStyle($name, $style);
	}

	/**
	 * Checks if output formatter has style with specified name.
	 *
	 * @param string $name
	 * @return bool
	 */
	public function hasStyle($name): bool {
		return $this->formatter->hasStyle($name);
	}

	/**
	 * Gets style options from style with specified name.
	 *
	 * @param string $name
	 * @return OutputFormatterStyleInterface
	 * @throws \InvalidArgumentException When style isn't defined
	 */
	public function getStyle($name): OutputFormatterStyleInterface {
		return $this->formatter->getStyle($name);
	}

	/**
	 * Formats a message according to the given styles.
	 *
	 * @param string $message The message to style
	 * @return string The styled message, prepended with a timestamp using the
	 *                log timezone and dateformat, e.g. "2015-06-23T17:24:37+02:00"
	 */
	public function format($message): string {
		$timeZone = $this->config->getSystemValue('logtimezone', 'UTC');
		$timeZone = $timeZone !== null ? new \DateTimeZone($timeZone) : null;

		$time = new \DateTime('now', $timeZone);
		$timestampInfo = $time->format($this->config->getSystemValue('logdateformat', \DateTime::ATOM));

		return $timestampInfo . ' ' . $this->formatter->format($message);
	}
}
