<?php

/**
 * Test: App\Presentation\Install\MigrationService
 */

declare(strict_types=1);

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


/** @return array{MigrationService, MemoryMigrationRepository, string} */
function createService(): array
{
	$dir = __DIR__ . '/../../tmp/migrations-' . bin2hex(random_bytes(4));
	@mkdir(dirname($dir), 0o777, true);
	Helpers::purge($dir);
	file_put_contents($dir . '/002_second.sql', 'SELECT 2;');
	file_put_contents($dir . '/001_first.sql', 'SELECT 1;');

	$repository = new MemoryMigrationRepository;
	return [new MigrationService(new MigrationRunner($repository), $dir), $repository, $dir];
}


test('Files are listed in the order in which they run', function () {
	[$service] = createService();
	Assert::same(['001_first.sql', '002_second.sql'], $service->getFiles());
});


test('A migration is executed and recorded like a console migration of the project', function () {
	[$service, $repository] = createService();

	Assert::same(['file' => '001_first.sql', 'status' => 'success'], $service->run('001_first.sql'));
	Assert::same(['001_first.sql'], $repository->getExecuted());
	Assert::same(['core/001_first.sql'], array_keys($repository->getRecords()));
});


test('A recorded migration is skipped, so the installer can be run again', function () {
	[$service, $repository] = createService();
	$service->run('001_first.sql');

	Assert::same(['file' => '001_first.sql', 'status' => 'skipped'], $service->run('001_first.sql'));
	Assert::same(['001_first.sql'], $repository->getExecuted());
});


test('A recorded migration that was changed is refused', function () {
	[$service, , $dir] = createService();
	$service->run('001_first.sql');
	file_put_contents($dir . '/001_first.sql', 'SELECT 100;');

	$result = $service->run('001_first.sql');
	Assert::same('error', $result['status']);
	Assert::match('%a%was modified after execution%a%', $result['message']);
});


test('A failing migration is reported and not recorded', function () {
	[$service, $repository, $dir] = createService();
	file_put_contents($dir . '/003_broken.sql', 'FAIL');

	$result = $service->run('003_broken.sql');
	Assert::same('error', $result['status']);
	Assert::same('SQL error in 003_broken.sql', $result['message']);
	Assert::same([], $repository->getRecords());
});


test('Only file names from the migrations directory are accepted', function () {
	[$service] = createService();
	Assert::exception(fn() => $service->run('../app.neon'), InvalidArgumentException::class, 'Invalid file name.');
	Assert::exception(fn() => $service->run('missing.sql'), RuntimeException::class, 'Migration file not found.');
});
