<?php

declare(strict_types=1);

namespace Install;


class InstallLock
{
	public const string FileName = 'installed.lock';


	public static function getPath(string $filePath): string
	{
		return $filePath . '/var/' . self::FileName;
	}


	public static function create(string $filePath, string $message = 'installed'): void
	{
		$content = $message . ': ' . date('Y-m-d H:i:s');
		file_put_contents(self::getPath($filePath), $content);
	}
}
