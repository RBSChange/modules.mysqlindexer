<?php
/**
 * mysqlindexer_UpdateAction
 * @package modules.mysqlindexer.actions
 */
class mysqlindexer_updateAction extends f_action_BaseAction
{
	/**
		/update  <delete><query>client:inthause.dev301</query></delete>
		/update  <commit/>
	 * @param Context $context
	 * @param Request $request
	 */
	public function _execute($context, $request)
	{
		$xml = file_get_contents('php://input');
		try 
		{
			$doc = new DOMDocument();
			$doc->loadXML($xml);
			$result = mysqlindexer_ModuleService::getInstance()->update($doc);
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