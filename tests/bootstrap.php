<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

define('PHPUNIT_RUN', 1);

require_once __DIR__ . '/../../../lib/base.php';

\OC_App::loadApp('previewgenerator');

OC_Hook::clear();
