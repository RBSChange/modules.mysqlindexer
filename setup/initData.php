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
}