<?php

declare(strict_types=1);

namespace App\Presentation\Install;

use App\Core\Settings\SettingsEntity;
use App\Presentation\Install\Factory\DatabaseFactory;
use App\Presentation\Install\Factory\WebsiteFactory;
use App\Presentation\Sign\SignUpFactory;
use Dibi\Connection;
use Dibi\Exception;
use Drago\Application\UI\Alert;
use Drago\Localization\TranslatorAdapter;
use Install\InstallLock;
use Nette\Application\BadRequestException;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;


/**
 * Other packages may act on the new administrator account by a service tagged `install.account`: a callable object
 * taking the id of the user, run right after the account is created.
 * @property InstallTemplate $template
 */
final class InstallPresenter extends Presenter
{
	use TranslatorAdapter;

	/** @param array<callable(int): void> $accountHandlers services tagged install.account */
	public function __construct(
		private readonly string $tempPath,
		private readonly Connection $connection,
		private readonly Steps $steps,
		private readonly DatabaseFactory $databaseFactory,
		private readonly WebsiteFactory $websiteFactory,
		private readonly SignUpFactory $userSingUpFactory,
		private readonly MigrationService $migrationService,
		private readonly array $accountHandlers = [],
	) {
		parent::__construct();
	}


	public function handleRun(): void
	{
		$this->steps->setStep(1);
	}


	public function handleRunMigration(string $file): void
	{
		$this->requireStep(2);
		$this->sendJson($this->migrationService->run($file));
	}


	public function handleMigrationsDone(): void
	{
		$this->requireStep(2);
		$this->steps->setStep(3);
		$this->flashMessage('Database installation was successful.', Alert::Success);
	}


	public function handleMigrationsFail(): void
	{
		$this->flashMessage('Database installation failed.', Alert::Danger);
	}


	/** @throws Exception */
	public function handleFinish(): void
	{
		$this->requireStep(5);

		$installed = $this->connection->select('1')
			->from(SettingsEntity::Table)
			->where('%n = ?', SettingsEntity::ColumnName, 'installed')
			->fetchSingle();

		if (!$installed) {
			$this->connection->insert(SettingsEntity::Table, [
				SettingsEntity::ColumnName => 'installed',
				SettingsEntity::ColumnValue => '1',
			])->execute();
		}

		InstallLock::create(dirname($this->tempPath));

		// A URL, not a link: the installer container does not know the presenters of the administration.
		// The base path keeps it working when the project runs in a subdirectory.
		$this->redirectUrl($this->getHttpRequest()->getUrl()->getBasePath() . 'admin');
	}


	protected function beforeRender(): void
	{
		parent::beforeRender();
		$step = $this->steps->getStep();
		$this->template->step = $step ?? 0;
		$this->template->migrationFiles = $this->migrationService->getFiles();
	}


	public function renderDefault(): void
	{
		$this->redrawControl('install');
	}


	protected function createComponentDatabase(): Form
	{
		$form = $this->databaseFactory->create();
		$form->setTranslator($this->translator);
		$form->onValidate[] = fn() => $this->requireStep(1);
		$form->onSuccess[] = function () {
			$this->steps->setStep(2);
			$this->flashMessage('Database settings were successful.', Alert::Success);
		};
		return $form;
	}


	protected function createComponentWebsite(): Form
	{
		$form = $this->websiteFactory->create();
		$form->setTranslator($this->translator);
		$form->onValidate[] = fn() => $this->requireStep(3);
		$form->onSuccess[] = function () {
			$this->steps->setStep(4);
			$this->flashMessage('Site settings successful.', Alert::Success);
		};
		return $form;
	}


	protected function createComponentAccount(): Form
	{
		$form = $this->userSingUpFactory->create();
		$form->setTranslator($this->translator);
		$form->onValidate[] = fn() => $this->requireStep(4);
		$form->onSuccess[] = function () {
			$userId = (int) $this->connection->getInsertId();
			foreach ($this->accountHandlers as $handler) {
				$handler($userId);
			}

			$this->steps->setStep(5);
			$this->flashMessage('Account administrator registration successful.', Alert::Success);
		};

		return $form;
	}


	/**
	 * Signals of the wizard can be sent directly, so each one checks that the previous steps are done.
	 * @throws BadRequestException
	 */
	private function requireStep(int $step): void
	{
		if ($this->steps->getStep() !== $step) {
			$this->error('This installation step is not available now.', 403);
		}
	}
}
