<?php
/**
 * commands_mysqlindexer_CreateDbIndex
 * @package modules.mysqlindexer.command
 */
class commands_mysqlindexer_CreateDbIndex extends commands_AbstractChangeCommand
{
	/**
	 * @return String
	 * @example "<moduleName> <name>"
	 */
	function getUsage()
	{
		return "Generate mysql indexer table";
	}

	/**
	 * @return String
	 * @example "initialize a document"
	 */
	function getDescription()
	{
		return "Generate mysql indexer table";
	}
	
	/**
	 * @param String[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 */
//	protected function validateArgs($params, $options)
//	{
//	}

	/**
	 * @return String[]
	 */
//	function getOptions()
//	{
//	}

	/**
	 * @param String[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 * @see c_ChangescriptCommand::parseArgs($args)
	 */
	function _execute($params, $options)
	{
		$this->message("== CreateDbIndex ==");

		$this->loadFramework();
		
		$sql = "CREATE TABLE IF NOT EXISTS `m_mysqlindexer_index` (
		`client` varchar(100) character set ascii collate ascii_bin NOT NULL,
		`final_id` varchar(100) character set ascii collate ascii_bin NOT NULL,
		`document_id` int(11),
		`document_model` varchar(100) character set ascii collate ascii_bin NOT NULL,
		`module` varchar(50) character set ascii collate ascii_bin,
		`lang` char(2) character set ascii collate ascii_bin NOT NULL,
		`parentwebsite_id` int(11),
		`document_accessor` text character set ascii collate ascii_bin,
		`document_ancestor` text character set ascii collate ascii_bin,
	
		`label` varchar(255) character set latin1 collate latin1_general_ci NOT NULL, 
		`text` text character set latin1 collate latin1_general_ci, 
			
		`sortable_label` varchar(255) character set latin1 collate latin1_general_ci NOT NULL, 
		`sortable_date` varchar(25) character set latin1 collate latin1_general_ci NOT NULL, 
		`extras` text character set latin1 collate latin1_general_ci, 		 
		PRIMARY KEY ( `final_id` ),
	    FULLTEXT KEY `TEXT` (`label`, `text`)
		) ENGINE=MyISAM DEFAULT CHARSET=latin1";
		
		$pp = f_persistentdocument_PersistentProvider::getInstance();
		try 
		{
			$pp->executeSQLScript($sql);
		}
		catch (Exception $e)
		{
			$this->warnMessage($e->getMessage());
		}
		$this->quitOk("Command successfully executed");
	}
}