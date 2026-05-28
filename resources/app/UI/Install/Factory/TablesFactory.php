<?php

declare(strict_types=1);

namespace App\UI\Install\Factory;

use App\UI\Install\Steps;
use Nette\Application\UI\Form;


/** Starts database installation process (AJAX-driven). */
final readonly class TablesFactory
{
	public function __construct(
		private Steps $steps,
	) {
	}

	public function create(): Form
	{
		$form = new Form;
		$form->addSubmit('send', 'Install database')
			->getControlPrototype()
			->setAttribute('id', 'installBtn');

		$form->onSuccess[] = $this->success(...);
		return $form;
	}

	public function success(Form $form): void
	{
		//$this->steps->setStep(2);
		if ($form->getPresenter()->isAjax()) {
			$form->getPresenter()->redrawControl('install');
		}
	}
}
