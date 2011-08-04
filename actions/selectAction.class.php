<?php
/**
 * mysqlindexer_SelectAction
 * @package modules.mysqlindexer.actions
 */
class mysqlindexer_selectAction extends change_Action
{
	/**
	 * @param change_Context $context
	 * @param change_Request $request
	 */
	public function _execute($context, $request)
	{
		if (Framework::isInfoEnabled())
		{
			Framework::info($_SERVER['REQUEST_URI']);
		}
		try 
		{
			$result = mysqlindexer_ModuleService::getInstance()->select($request->getParameters());
		} 
		catch (Exception $e)
		{
			Framework::exception($e);
		}	
			
		if ($result === null)
		{
			$result = '<result status="0"></result>';
		}
		die($result);
	}
	
	/**
	 * @see f_action_BaseAction::isSecure()
	 *
	 * @return boolean
	 */
	public function isSecure()
	{
		return false;
	}
}