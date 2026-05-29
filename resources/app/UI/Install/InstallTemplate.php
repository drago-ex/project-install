<?php

declare(strict_types=1);

namespace App\UI\Install;

use App\UI\BaseTemplate;


/** Template for the installation process. */
class InstallTemplate extends BaseTemplate
{
	public int $step;

	/** @var list<string> */
	public array $migrationFiles = [];
}
