<?php

declare(strict_types=1);

namespace App\Presentation\Install;

use App\Core\Settings\SettingsEntity;
use App\Presentation\Backend\Permission\Component\Users\UsersRolesEntity;
use App\Presentation\Install\Factory\DatabaseFactory;
use App\Presentation\Install\Factory\WebsiteFactory;
use App\Presentation\Sign\SignUpFactory;
use Dibi\Connection;
use Dibi\Exception;
use Drago\Application\UI\Alert;
use Drago\Localization\TranslatorAdapter;
use Install\InstallLock;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;


/** @property InstallTemplate $template */
final class InstallPresenter extends Presenter
{
	use TranslatorAdapter;

	public function __construct(
		private readonly string $tempPath,
		private readonly Connection $connection,
		private readonly Steps $steps,
		private readonly DatabaseFactory $databaseFactory,
		private readonly WebsiteFactory $websiteFactory,
		private readonly SignUpFactory $userSingUpFactory,
		private readonly MigrationService $migrationService,
	) {
		parent::__construct();
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


	public function handleRun(): void
	{
		$this->steps->setStep(1);
	}


	public function handleRunMigration(string $file): void
	{
		$this->sendJson($this->migrationService->run($file));
	}


	public function handleMigrationsDone(): void
	{
		$this->steps->setStep(3);
		$this->flashMessage('Database installation was successful.', Alert::Success);
	}


	public function handleMigrationsFail(): void
	{
		$this->flashMessage('Database installation failed.', Alert::Danger);
	}


	protected function createComponentDatabase(): Form
	{
		$form = $this->databaseFactory->create();
		$form->setTranslator($this->translator);
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
		$form->onSuccess[] = function () {
			$this->connection->insert(UsersRolesEntity::Table, [
				UsersRolesEntity::ColumnUserId => 1,
				UsersRolesEntity::ColumnRoleId => 1,
			])->execute();
			$this->steps->setStep(5);
			$this->flashMessage('Account administrator registration successful.', Alert::Success);
		};

		return $form;
	}


	/** @throws Exception */
	public function handleFinish(): void
	{
		$this->connection->insert(SettingsEntity::Table, [
			SettingsEntity::ColumnName => 'installed',
			SettingsEntity::ColumnValue => '1',
		])->execute();

		InstallLock::create(dirname($this->tempPath));
		$this->redirectUrl('/admin');
	}
}
