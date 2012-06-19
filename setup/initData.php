<?php
/**
 * @package modules.mysqlindexer.setup
 */
class mysqlindexer_Setup extends object_InitDataSetup
{
	public function install()
	{
		$this->addProjectConfigurationEntry('injection/class/indexer_IndexService', 'mysqlindexer_IndexService');
	}

	/**
	 * @return string[]
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