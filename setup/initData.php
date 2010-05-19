<?php
/**
 * @package modules.mysqlindexer.setup
 */
class mysqlindexer_Setup extends object_InitDataSetup
{
	public function install()
	{
	}

	/**
	 * @return String[]
	 */
	public function getRequiredPackages()
	{
		// Return an array of packages name if the data you are inserting in
		// this file depend on the data of other packages.
		// Example:
		// return array('modules_website', 'modules_users');
		return array();
	}
}