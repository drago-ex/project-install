<?php

declare(strict_types=1);

namespace App\UI\Install;

use Dibi\Connection;
use Throwable;


final readonly class MigrationService
{
	public function __construct(
		private Connection $db,
		private string $migrationsPath,
	) {
	}


	/** Returns a list of file migrations. */
	public function getFiles(): array
	{
		$files = glob($this->migrationsPath . '/*.sql');
		$files = array_map('basename', $files);
		sort($files);
		return $files;
	}


	/** Runs one migration. */
	public function run(string $file): array
	{
		if (!preg_match('~^[a-zA-Z0-9._-]+\.sql$~', $file)) {
			throw new \InvalidArgumentException('Invalid file name.');
		}

		$fullPath = $this->migrationsPath . '/' . $file;
		if (!is_file($fullPath)) {
			throw new \RuntimeException('Migration file not found.');
		}

		try {
			$this->db->loadFile($fullPath);
			return [
				'file' => $file,
				'status' => 'success',
			];

		} catch (Throwable $t) {
			return [
				'file' => $file,
				'status' => 'error',
				'message' => $t->getMessage(),
			];
		}
	}
}
