<?php

declare(strict_types=1);

namespace App\Presentation\Install\Factory;

use Drago\Utils\ExtraArrayHash;


class DatabaseValues extends ExtraArrayHash
{
	public const string
		Host = 'host',
		User = 'user',
		Password = 'password',
		Database = 'database';

	public string $host;
	public string $user;
	public string $password;
	public string $database;
}
