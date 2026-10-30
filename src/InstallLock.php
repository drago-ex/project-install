<?php declare(strict_types=1);

namespace Install;

use RuntimeException;
use function sprintf;


/**
 * Marks a finished installation. The lock lives in local/ next to db.neon: the directory the installer
 * writes to, which is kept on the server and not in git, so clearing temp/ does not reopen the installer.
 */
class InstallLock
{
	public const string DirName = 'local',
		FileName = 'installed.lock';


	public static function getPath(string $rootDir): string
	{
		return sprintf('%s/%s/%s', $rootDir, self::DirName, self::FileName);
	}


	public static function exists(string $rootDir): bool
	{
		return is_file(self::getPath($rootDir));
	}


	/** @throws RuntimeException */
	public static function create(string $rootDir, string $message = 'installed'): void
	{
		$path = self::getPath($rootDir);
		$content = $message . ': ' . date('Y-m-d H:i:s');
		if (@file_put_contents($path, $content) === false) { // @ the failure is reported by the exception below
			throw new RuntimeException("Unable to write the installation lock '$path'.");
		}
	}
}
