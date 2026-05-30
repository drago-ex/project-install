<?php

declare(strict_types=1);

namespace Install;


/** Service for managing the installation lock file. */
class InstallLock
{
	public const string
		DirName = 'var',
		FileName = 'installed.lock';


	/**
	 * Get the path to the lock file.
	 */
	public static function getPath(string $filePath): string
	{
		return sprintf('%s/%s/%s', $filePath, self::DirName, self::FileName);
	}


	/** Create the lock file. */
	public static function create(string $filePath, string $message = 'installed'): void
	{
		$content = $message . ': ' . date('Y-m-d H:i:s');
		file_put_contents(self::getPath($filePath), $content);
	}
}
