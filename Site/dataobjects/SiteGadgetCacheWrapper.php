<?php


/**
 * A recordset wrapper for gadget caches
 *
 * @package   Site
 * @copyright 2009-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @see       SiteGadgetCache
 */
class SiteGadgetCacheWrapper extends SwatDBRecordsetWrapper
{
	// {{{ protected function init()

	protected function init()
	{
		parent::init();
		$this->row_wrapper_class = SwatDBClassMap::get('SiteGadgetCache');
		$this->index_field = 'id';
	}

	// }}}
}

?>
