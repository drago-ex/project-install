<?php

declare(strict_types=1);

namespace App\Presentation\Install;

use Closure;
use Composer\InstalledVersions;
use Drago\Migration\MigrationFiles;
use Drago\Migration\MigrationRunner;
use RuntimeException;
use Throwable;
use Tracy\Debugger;


/**
 * Runs the database migrations for the web installer.
 *
 * The migrations of the installed packages run straight from vendor/ in their order (by migration-priority),
 * followed by the project's own migrations/; nothing has to be copied. Files are run by drago-ex/migration,
 * so they are recorded in the `migrations` table exactly as with `vendor/bin/migration db:migrate --packages`:
 * an already executed file is skipped, a changed one is refused, and the console can continue later.
 *
 * With drago-ex/project-permission installed, the privileges of the other packages (their migrations-permission/,
 * imported by hand otherwise) run after the package migrations and before the project's own ones, so roles can be
 * given them in the administration right after the installation.
 */
final readonly class MigrationService
{
	/** The package whose `resources` table the migrations-permission/ files fill. */
	private const string PermissionPackage = 'drago-ex/project-permission';

	/** Directory of the privileges in a package. */
	private const string PermissionDir = 'migrations-permission';


	/**
	 * @param (Closure(string): list<array{file: string, package: string, priority: ?int}>)|null $listMigrations
	 *        for tests; MigrationFiles::forProject() by default
	 * @param (Closure(string): list<array{file: string, package: string, priority: ?int}>)|null $listPermissionMigrations
	 *        for tests; permissionMigrations() by default
	 */
	public function __construct(
		private MigrationRunner $runner,
		private string $projectRoot,
		private ?Closure $listMigrations = null,
		private ?Closure $listPermissionMigrations = null,
	) {
	}


	/**
	 * The migrations-permission/ files of the installed packages in their migration-priority order,
	 * or none without drago-ex/project-permission. Paths are resolved like MigrationFiles::forProject()
	 * (not vendor/composer/../), so the runner records them under their package; the project itself and
	 * drago-ex/migration are left out.
	 * @return list<array{file: string, package: string, priority: ?int}>
	 */
	public static function permissionMigrations(string $projectRoot): array
	{
		if (!InstalledVersions::isInstalled(self::PermissionPackage)) {
			return [];
		}

		$packages = [];
		$root = realpath($projectRoot);
		$migration = InstalledVersions::getInstallPath('drago-ex/migration');
		$migration = $migration !== null ? realpath($migration) : false;
		foreach (InstalledVersions::getInstalledPackages() as $name) {
			$path = InstalledVersions::getInstallPath($name);
			$path = $path !== null ? realpath($path) : false;
			if ($path === false || $path === $root || $path === $migration) {
				continue;
			}

			if (is_dir($path . '/' . self::PermissionDir)) {
				$packages[] = [
					'name' => $name,
					'priority' => MigrationFiles::packagePriority($path),
					'files' => MigrationFiles::list($path . '/' . self::PermissionDir),
				];
			}
		}

		usort($packages, fn(array $a, array $b): int => [$a['priority'], $a['name']] <=> [$b['priority'], $b['name']]);

		$migrations = [];
		foreach ($packages as $package) {
			foreach ($package['files'] as $file) {
				$migrations[] = ['file' => $file, 'package' => $package['name'], 'priority' => $package['priority']];
			}
		}

		return $migrations;
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


	/**
	 * @return array<string, string> path relative to the project root => absolute path, in the order they run
	 */
	private function migrations(): array
	{
		$root = str_replace('\\', '/', (string) (realpath($this->projectRoot) ?: $this->projectRoot));
		$list = $this->listMigrations ?? MigrationFiles::forProject(...);
		$listPermission = $this->listPermissionMigrations ?? self::permissionMigrations(...);

		// the privileges go after the package migrations (they need the resources table), before the project's own,
		// which are the files without a priority
		$all = $list($this->projectRoot);
		$projectStart = array_search(null, array_column($all, 'priority'), true);
		$projectStart = $projectStart === false ? count($all) : $projectStart;
		array_splice($all, $projectStart, 0, $listPermission($this->projectRoot));

		$migrations = [];
		foreach ($all as $migration) {
			$path = str_replace('\\', '/', $migration['file']);
			$relative = str_starts_with($path, $root . '/') ? substr($path, strlen($root) + 1) : $path;
			$migrations[$relative] = $migration['file'];
		}

		return $migrations;
	}
}
