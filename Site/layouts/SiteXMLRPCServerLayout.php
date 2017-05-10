<?php

/**
 * Layout for an XMLRPC Server
 *
 * @package   Site
 * @copyright 2006-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class SiteXMLRPCServerLayout extends SiteLayout
{
	// {{{ public function __construct()

	public function __construct($app, $filename = null)
	{
		parent::__construct($app, 'Site/layouts/xhtml/xmlrpcserver.php');
	}

	// }}}
}

?>
