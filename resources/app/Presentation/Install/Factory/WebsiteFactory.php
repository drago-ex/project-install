<?php declare(strict_types=1);

namespace App\Presentation\Install\Factory;

use App\Core\Settings\SettingsEntity;
use App\Presentation\Install\Steps;
use Dibi\Connection;
use Dibi\Exception;
use Nette\Application\UI\Form;


final readonly class WebsiteFactory
{
	public function __construct(
		private Steps $steps,
		private Connection $db,
	) {
	}


	public function create(): Form
	{
		$form = new Form;
		$form->addText(WebsiteValues::Website, 'Site name')
			->setHtmlAttribute('autocomplete', 'off')
			->setRequired()
			->addRule($form::MaxLength, 'The site name can have at most %d characters.', SettingsEntity::ColumnValueSize);

		$form->addText(WebsiteValues::Description, 'Site description')
			->setHtmlAttribute('autocomplete', 'off')
			->setRequired()
			->addRule($form::MaxLength, 'The site description can have at most %d characters.', SettingsEntity::ColumnValueSize);

		$form->addSubmit('send', 'Save data');
		$form->onSuccess[] = $this->processWebsiteSettingsForm(...);

		return $form;
	}


	/**
	 * Handles the successful submission of the website settings form.
	 * @throws Exception
	 */
	public function processWebsiteSettingsForm(Form $form, WebsiteValues $data): void
	{
		$settings = [
			WebsiteValues::Website => $data->website,
			WebsiteValues::Description => $data->description,
		];

		// The step can run again (e.g. after the cache with the steps was cleared), and a name is unique.
		$this->db->delete(SettingsEntity::Table)
			->where('%n IN %in', SettingsEntity::ColumnName, array_keys($settings))
			->execute();

		foreach ($settings as $name => $value) {
			$this->db->insert(SettingsEntity::Table, [
				SettingsEntity::ColumnName => $name,
				SettingsEntity::ColumnValue => $value,
			])->execute();
		}

		$this->steps->setStep(4);
	}
}
