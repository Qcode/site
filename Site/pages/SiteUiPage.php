<?php

require_once 'Swat/SwatUI.php';
require_once 'Site/pages/SitePageDecorator.php';

/**
 * Base class for UI pages
 *
 * @package   Site
 * @copyright 2008 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
abstract class SiteUiPage extends SitePageDecorator
{
	// {{{ protected properties

	/**
	 * @var SwatUI
	 */
	protected $ui;

	// }}}
	// {{{ abstract protected function getUiXml()

	abstract protected function getUiXml();

	// }}}

	// init phase
	// {{{ public function init()

	public function init()
	{
		parent::init();

		$this->ui = new SwatUI();
		$this->ui->loadFromXML($this->getUiXml());

		$this->initInternal();

		$this->ui->init();
	}

	// }}}
	// {{{ protected function initInternal()

	protected function initInternal()
	{
	}

	// }}}

	// build phase
	// {{{ public function build()

	public function build()
	{
		$this->page->build();

		$this->buildInternal();
		$this->buildTitle();
		$this->buildMetaDescription();
		$this->buildNavBar();
		$this->buildContent();
	}

	// }}}
	// {{{ protected function buildInternal()

	protected function buildInternal()
	{
		try {
			$message_display = $this->ui->getWidget('message_display');
			foreach ($this->app->messages->getAll() as $message) {
				$message_display->add($message);
			}
		} catch (SwatWidgetNotFoundException $e) {
		}
	}

	// }}}
	// {{{ protected function buildContent()

	protected function buildContent()
	{
		$this->layout->startCapture('content');
		$this->ui->display();
		$this->layout->endCapture();
	}

	// }}}

	// finalize phase
	// {{{ public function finalize()

	public function finalize()
	{
		parent::finalize();
		$this->layout->addHtmlHeadEntrySet(
			$this->ui->getRoot()->getHtmlHeadEntrySet());
	}

	// }}}
}

?>

