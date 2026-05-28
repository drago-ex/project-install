<?php

declare(strict_types=1);

namespace App\UI\Install;

use App\UI\Backend\Sign\SignUpFactory;
use App\UI\Install\Factory\DatabaseFactory;
use App\UI\Install\Factory\WebsiteFactory;
use Drago\Application\UI\Alert;
use Drago\Localization\TranslatorAdapter;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Throwable;


/**
 * Installation and configuration application.
 * @property InstallTemplate $template
 */
final class InstallPresenter extends Presenter
{
	use TranslatorAdapter;


	public function __construct(
		private readonly Steps $steps,
		private readonly DatabaseFactory $databaseFactory,
		private readonly WebsiteFactory $websiteFactory,
		private readonly SignUpFactory $userSingUpFactory,
		private readonly MigrationService $migrationService,
	) {
		parent::__construct();
	}


	/** Prepare the installation step before rendering. @throws Throwable */
	protected function beforeRender(): void
	{
		parent::beforeRender();
		$step = $this->steps->getStep();
		$this->template->step = $step ?? 0;
		$this->template->migrationFiles = $this->migrationService->getFiles();
	}


	/** Render default installation page. */
	public function renderDefault(): void
	{
		$this->redrawControl('install');
	}


	/** Handle the installation process start. */
	public function handleRun(): void
	{
		$this->steps->setStep(1);
	}


	/** Handle migration run. */
	public function handleRunMigration(): void
	{
		$file = $this->getHttpRequest()->getQuery('file');
		$this->sendJson($this->migrationService->run((string) $file));
	}


	/** Handle migration success. */
	public function handleMigrationsDone(): void
	{
		$this->steps->setStep(3);
		$this->flashMessage('Instalace proběhla úspěšně!', Alert::Success);
		//$this->redrawControl('install');
	}


	/** Handle migration failure. */
	public function handleMigrationsFail(): void
	{
		$this->flashMessage('Instalace selhala.', Alert::Danger);
	}


	/** Create and return the database configuration form. */
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


	/** Create and return the website configuration form. */
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


	/** Create and return the account creation form for the administrator. */
	protected function createComponentAccount(): Form
	{
		$form = $this->userSingUpFactory->create();
		$form->setTranslator($this->translator);
		$form->onSuccess[] = function () {
			$this->steps->setStep(5);
			$this->flashMessage('Account administrator registration successful.', Alert::Success);
		};

		return $form;
	}
}
