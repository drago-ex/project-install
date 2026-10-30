<?php declare(strict_types=1);

namespace App\Presentation\Install;

use Closure;
use Drago\Migration\MigrationFiles;
use Drago\Migration\MigrationRunner;
use RuntimeException;
use Throwable;
use Tracy\Debugger;
use function count, strlen;


/**
 * Runs the database migrations for the web installer.
 *
 * The migrations of the installed packages run straight from vendor/ in their order (by migration-priority),
 * followed by the project's own migrations/; nothing has to be copied. Files are run by drago-ex/migration,
 * so they are recorded in the `migrations` table exactly as with `vendor/bin/migration db:migrate --packages`:
 * an already executed file is skipped, a changed one is refused, and the console can continue later.
 *
 * Other packages may add migrations by a service tagged `install.migrations`: a callable object taking the project
 * root and returning a list of [file, package, priority]; its files run after the package migrations and before
 * the project's own ones, so they can use the tables of the packages.
 */
final readonly class MigrationService
{
	public function __construct(
		private MigrationRunner $runner,
		private string $projectRoot,
		/**
		 * For tests; MigrationFiles::forProject() by default.
		 * @var (Closure(string): list<array{file: string, package: string, priority: ?int}>)|null
		 */
		private ?Closure $listMigrations = null,
		/**
		 * Services tagged install.migrations.
		 * @var array<callable(string): list<array{file: string, package: string, priority: ?int}>>
		 */
		private array $sources = [],
	) {
	}


	/**
	 * The migrations in the order they must run, as paths relative to the project root.
	 * @return list<string>
	 */
	public function getFiles(): array
	{
		return array_keys($this->migrations());
	}


	/**
	 * Runs one migration; only a file from getFiles() is accepted.
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
		$fullPath = $this->migrations()[$file] ?? throw new RuntimeException('Migration file not found.');

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


	/** @return array<string, string> path relative to the project root => absolute path, in the order they run */
	private function migrations(): array
	{
		$root = str_replace('\\', '/', (string) (realpath($this->projectRoot) ?: $this->projectRoot));
		$list = $this->listMigrations ?? MigrationFiles::forProject(...);

		// further migrations go after the package migrations, before the project's own (the files without a priority)
		$all = $list($this->projectRoot);
		$projectStart = array_search(null, array_column($all, 'priority'), true);
		$projectStart = $projectStart === false ? count($all) : $projectStart;
		foreach (array_reverse($this->sources) as $source) {
			array_splice($all, $projectStart, 0, $source($this->projectRoot));
		}

		$migrations = [];
		foreach ($all as $migration) {
			$path = str_replace('\\', '/', $migration['file']);
			$relative = str_starts_with($path, $root . '/') ? substr($path, strlen($root) + 1) : $path;
			$migrations[$relative] = $migration['file'];
		}

		return $migrations;
	}
}
