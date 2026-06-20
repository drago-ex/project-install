<?php

declare(strict_types=1);

namespace App\Presentation\Install;

use Drago\Application\UI\ExtraTemplate;


class InstallTemplate extends ExtraTemplate
{
	public string $lang;
	public int $step;

	/** @var list<string> */
	public array $migrationFiles = [];
}
