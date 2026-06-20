<?php

declare(strict_types=1);

namespace App\Presentation\Install\Factory;

use Drago\Utils\ExtraArrayHash;


class WebsiteValues extends ExtraArrayHash
{
	public const string
		Website = 'website',
		Description = 'description';

	public string $website;
	public string $description;
}
