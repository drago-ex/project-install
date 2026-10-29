<?php declare(strict_types=1);

/**
 * Test: App\Presentation\Install\MigrationService
 */

use App\Presentation\Install\MigrationService;
use Drago\Migration\MigrationRunner;
use Drago\Migration\Repository;
use Tester\Assert;
use Tester\Helpers;

require __DIR__ . '/../../bootstrap.php';


/** Keeps the migrations table in memory and records the executed SQL files. */
readonly class MemoryMigrationRepository extends Repository
{
	public function __construct(
		private ArrayObject $records = new ArrayObject,
		private ArrayObject $executed = new ArrayObject,
	) {
	}


	public function acquireLock(string $name, int $timeout = 30): bool
	{
		return true;
	}


	public function releaseLock(string $name): void
	{
	}


	public function migrationsTableExists(): bool
	{
		return true;
	}


	public function getMigrationChecksum(string $package, string $file): ?string
	{
		return $this->records["$package/$file"] ?? null;
	}


	public function getChecksumsElsewhere(string $file, string $package): array
	{
		return [];
	}


	public function insertMigration(string $package, string $file, string $checksum): void
	{
		$this->records["$package/$file"] = $checksum;
	}


	public function begin(): void
	{
	}


	public function commit(): void
	{
	}


	public function rollback(): void
	{
	}


	public function runSqlFile(string $file): void
	{
		if (str_contains((string) file_get_contents($file), 'FAIL')) {
			throw new RuntimeException('SQL error in ' . basename($file));
		}

		$this->executed[] = basename($file);
	}


	/** @return array<string, string> */
	public function getRecords(): array
	{
		return $this->records->getArrayCopy();
	}


	/** @return list<string> */
	public function getExecuted(): array
	{
		return $this->executed->getArrayCopy();
	}
}


/**
 * @param array<callable(string): list<array{file: string, package: string, priority: ?int}>> $sources
 * @return array{MigrationService, MemoryMigrationRepository, string}
 */
function createService(array $sources = []): array
{
	$root = __DIR__ . '/../../tmp/project-' . bin2hex(random_bytes(4));
	@mkdir($root . '/vendor/acme/users/migrations', 0o777, true); // @ directory may already exist
	@mkdir($root . '/migrations', 0o777, true); // @ directory may already exist
	Helpers::purge($root . '/migrations');
	file_put_contents($root . '/vendor/acme/users/migrations/001_first.sql', 'SELECT 1;');
	file_put_contents($root . '/migrations/002_second.sql', 'SELECT 2;');

	// The order comes from MigrationFiles::forProject(); here a fixed list with one package and the project.
	$list = fn(string $projectRoot): array => [
		['file' => realpath($projectRoot) . '/vendor/acme/users/migrations/001_first.sql', 'package' => 'acme/users', 'priority' => 10],
		['file' => realpath($projectRoot) . '/migrations/002_second.sql', 'package' => 'core', 'priority' => null],
	];

	$repository = new MemoryMigrationRepository;
	return [new MigrationService(new MigrationRunner($repository), $root, $list, $sources), $repository, $root];
}


test('Files are listed in the order in which they run, relative to the project root', function () {
	[$service] = createService();
	Assert::same(['vendor/acme/users/migrations/001_first.sql', 'migrations/002_second.sql'], $service->getFiles());
});


test('A package migration runs from vendor/ and is recorded under its package, like in the console', function () {
	[$service, $repository] = createService();

	Assert::same(
		['file' => 'vendor/acme/users/migrations/001_first.sql', 'status' => 'success'],
		$service->run('vendor/acme/users/migrations/001_first.sql'),
	);
	Assert::same(
		['file' => 'migrations/002_second.sql', 'status' => 'success'],
		$service->run('migrations/002_second.sql'),
	);
	Assert::same(['001_first.sql', '002_second.sql'], $repository->getExecuted());
	Assert::same(['acme/users/001_first.sql', 'core/002_second.sql'], array_keys($repository->getRecords()));
});


test('A recorded migration is skipped, so the installer can be run again', function () {
	[$service, $repository] = createService();
	$service->run('migrations/002_second.sql');

	Assert::same(['file' => 'migrations/002_second.sql', 'status' => 'skipped'], $service->run('migrations/002_second.sql'));
	Assert::same(['002_second.sql'], $repository->getExecuted());
});


test('A recorded migration that was changed is refused', function () {
	[$service, , $root] = createService();
	$service->run('migrations/002_second.sql');
	file_put_contents($root . '/migrations/002_second.sql', 'SELECT 100;');

	$result = $service->run('migrations/002_second.sql');
	Assert::same('error', $result['status']);
	Assert::match('%a%was modified after execution%a%', $result['message']);
});


test('A failing migration is reported and not recorded', function () {
	[$service, $repository, $root] = createService();
	file_put_contents($root . '/migrations/002_second.sql', 'FAIL');

	$result = $service->run('migrations/002_second.sql');
	Assert::same('error', $result['status']);
	Assert::same('SQL error in 002_second.sql', $result['message']);
	Assert::same([], $repository->getRecords());
});


test('Only files from the list of migrations are accepted', function () {
	[$service] = createService();
	Assert::exception(fn() => $service->run('../app.neon'), RuntimeException::class, 'Migration file not found.');
	Assert::exception(fn() => $service->run('migrations/missing.sql'), RuntimeException::class, 'Migration file not found.');
	Assert::exception(fn() => $service->run('app/db.neon'), RuntimeException::class, 'Migration file not found.');
});


test('Migrations of a service tagged install.migrations run after the package ones, before the project ones', function () {
	$source = function (string $projectRoot): array {
		@mkdir($projectRoot . '/vendor/acme/blog/migrations-permission', 0o777, true); // @ directory may already exist
		file_put_contents($projectRoot . '/vendor/acme/blog/migrations-permission/001_blog_resources.sql', 'SELECT 3;');
		return [[
			'file' => realpath($projectRoot) . '/vendor/acme/blog/migrations-permission/001_blog_resources.sql',
			'package' => 'acme/blog',
			'priority' => 110,
		]];
	};
	[$service, $repository] = createService([$source]);
	Assert::same([
		'vendor/acme/users/migrations/001_first.sql',
		'vendor/acme/blog/migrations-permission/001_blog_resources.sql',
		'migrations/002_second.sql',
	], $service->getFiles());

	$result = $service->run('vendor/acme/blog/migrations-permission/001_blog_resources.sql');
	Assert::same('success', $result['status']);
	Assert::same(['acme/blog/001_blog_resources.sql'], array_keys($repository->getRecords()));
});
