<?php

declare(strict_types=1);

namespace Install;


class InstallLock
{
	public const string
		DirName = 'temp',
		FileName = 'installed.lock';


	public static function getPath(string $filePath): string
	{
		return sprintf('%s/%s/%s', $filePath, self::DirName, self::FileName);
	}


	public static function create(string $filePath, string $message = 'installed'): void
	{
		$content = $message . ': ' . date('Y-m-d H:i:s');
		file_put_contents(self::getPath($filePath), $content);
	}
}
