<?php declare(strict_types=1);

namespace App\Presentation\Install\Factory;

use App\Presentation\Install\Steps;
use dibi;
use Dibi\Bridges\Nette\DibiExtension3;
use Nette\Application\UI\Form;
use Nette\DI\Config\Adapters\NeonAdapter;
use Nette\Utils\FileSystem;
use Throwable;
use Tracy\Debugger;
use Tracy\ILogger;


final readonly class DatabaseFactory
{
	public function __construct(
		private Steps $steps,
		private NeonAdapter $neonAdapter,
		private string $configPath,
		private string $tempDir,
	) {
	}


	public function create(): Form
	{
		$form = new Form;
		$form->addText(DatabaseValues::Host, 'Database server')
			->setHtmlAttribute('autocomplete', 'off')
			->setRequired();

		$form->addText(DatabaseValues::User, 'Username')
			->setHtmlAttribute('autocomplete', 'off')
			->setRequired();

		$form->addText(DatabaseValues::Password, 'Password')
			->setHtmlAttribute('autocomplete', 'off')
			->setRequired();

		$form->addText(DatabaseValues::Database, 'Database name')
			->setHtmlAttribute('autocomplete', 'off')
			->setRequired();

		$form->addSubmit('send', 'Connection test');
		$form->onSuccess[] = $this->processDatabaseConnectionForm(...);
		return $form;
	}


	public function processDatabaseConnectionForm(Form $form, DatabaseValues $data): void
	{
		try {
			if (dibi::connect($data->toArray())->isConnected()) {
				$arr = [
					'extensions' => [
						'dibi' => DibiExtension3::class,
					],
					'dibi' => [
						'host' => $data->host,
						'username' => $data->user,
						'password' => $data->password,
						'database' => $data->database,
						'driver' => 'mysqli',
						'lazy' => true,
					],
				];

				$content = $this->neonAdapter->dump($arr);
				$filePath = $this->configPath . '/db.neon';
				if (file_put_contents($filePath, $content) === false) {
					throw new \RuntimeException('Cannot write to file: ' . $filePath);
				}

				// Without debug mode Nette does not notice the changed config, so the compiled containers must go.
				FileSystem::delete($this->tempDir . '/cache/nette.configurator');
				$this->steps->setStep(2);
			}

		} catch (Throwable $t) {
			// Every failure stops the step; without an error the wizard would go on without db.neon.
			$message = match ($t->getCode()) {
				1044 => 'Access denied, check database settings.',
				1045 => 'Failed to verify database username or password.',
				1049 => 'The database name does not exist.',
				2002 => 'The database server did not respond.',
				default => null,
			};
			if ($message === null) {
				Debugger::log($t, ILogger::EXCEPTION);
				$message = 'Unknown status code.';
			}

			$form->addError($message);
		}
	}
}
