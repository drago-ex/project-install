<?php

declare(strict_types=1);

namespace App\Presentation\Install;

use Drago\Migration\MigrationFiles;
use Drago\Migration\MigrationRunner;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Tracy\Debugger;


/**
 * Runs the SQL files of the project's migrations directory for the web installer.
 *
 * Files are run by drago-ex/migration, so they are recorded in the `migrations` table exactly as with
 * `vendor/bin/migration db:migrate migrations`: an already executed file is skipped, a changed one is
 * refused, and the console can continue with later migrations.
 */
final readonly class MigrationService
{
	public function __construct(
		private MigrationRunner $runner,
		private string $migrationsPath,
	) {
	}


	/**
	 * Returns the migration files in the order they must run (db-migration-order.json from sql-export).
	 * @return list<string>
	 */
	public function getFiles(): array
	{
		if (!is_dir($this->migrationsPath)) {
			return [];
		}

		return array_map(
			static fn(string $file): string => basename($file),
			MigrationFiles::list($this->migrationsPath),
		);
	}


	/**
	 * Runs one migration.
	 * @return array{
	 *     file: string,
	 *     status: 'success'|'skipped'
	 * }|array{
	 *     file: string,
	 *     status: 'error',
	 *     message: string
	 * }
	 */
	public function run(string $file): array
	{
		if (!preg_match('~^[a-zA-Z0-9._-]+\.sql$~', $file)) {
			throw new InvalidArgumentException('Invalid file name.');
		}

		$fullPath = $this->migrationsPath . '/' . $file;
		if (!is_file($fullPath)) {
			throw new RuntimeException('Migration file not found.');
		}

		$skipped = false;
		try {
			$this->runner->run($fullPath, function (string $message) use (&$skipped): void {
				$skipped = $skipped || str_contains($message, 'SKIP');
			});

			return [
				'file' => $file,
				'status' => $skipped ? 'skipped' : 'success',
			];

		} catch (Throwable $t) {
			Debugger::log($t);
			return [
				'file' => $file,
				'status' => 'error',
				'message' => $t->getMessage(),
			];
		}
	}
}
