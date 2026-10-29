<?php declare(strict_types=1);

namespace Install;

use RuntimeException;
use function sprintf;


/**
 * Marks a finished installation. The lock lives in the app directory next to db.neon, which
 * the installer writes anyway, so clearing temp/ does not reopen the installer.
 */
class InstallLock
{
	public const string DirName = 'app',
		LegacyDirName = 'temp',
		FileName = 'installed.lock';


	public static function getPath(string $rootDir): string
	{
		return sprintf('%s/%s/%s', $rootDir, self::DirName, self::FileName);
	}


	/**
	 * Also accepts the lock in temp/ written by older versions and moves it to the current location.
	 */
	public static function exists(string $rootDir): bool
	{
		if (is_file(self::getPath($rootDir))) {
			return true;
		}

		$legacyPath = sprintf('%s/%s/%s', $rootDir, self::LegacyDirName, self::FileName);
		if (is_file($legacyPath)) {
			self::create($rootDir, 'moved from ' . self::LegacyDirName);
			return true;
		}

		return false;
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
