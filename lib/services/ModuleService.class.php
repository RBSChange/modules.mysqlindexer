<?php
/**
 * @package modules.mysqlindexer.lib.services
 */
class mysqlindexer_ModuleService extends ModuleBaseService
{
	/**
	 * Singleton
	 * @var mysqlindexer_ModuleService
	 */
	private static $instance = null;

	/**
	 * @return mysqlindexer_ModuleService
	 */
	public static function getInstance()
	{
		if (is_null(self::$instance))
		{
			self::$instance = new self();
		}
		return self::$instance;
	}
}