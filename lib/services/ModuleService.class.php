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
			self::$instance = self::getServiceClassInstance(get_class());
		}
		return self::$instance;
	}
	
	/**
	 * @param Integer $documentId
	 * @return f_persistentdocument_PersistentTreeNode
	 */
//	public function getParentNodeForPermissions($documentId)
//	{
//		// Define this method to handle permissions on a virtual tree node. Example available in list module.
//	}
	
	/**
	 * @param DOMDocument $xmlDocument
	 * @return string
	 */
	public function update($xmlDocument)
	{
		if ($xmlDocument->documentElement)
		{
			$action = $xmlDocument->documentElement->nodeName;
			switch ($action)
			{
				case 'add':
					$this->addDocuments($xmlDocument->documentElement->getElementsByTagName('doc'));
					break;
				case 'optimize':
					$this->optimizeDB();
					break;
				case 'delete':
					$nl = $xmlDocument->documentElement->getElementsByTagName('query');
					if ($nl->length == 1)
					{
						$matches = array();
						$query = $nl->item(0)->textContent;
						if (preg_match('/^client:([^ )]+)$/', $query, $matches))
						{
							$this->deleteByClientId($matches[1]);	
						}
						else if (preg_match_all('/finalId:([^ )]+)/', $query, $matches))
						{
							$this->deleteByFinalIds($matches[1]);
						}
					}
					break;
					
			}
		}
		return null;
	}
	
	/**
	 * @param DOMNodeList $docs
	 */
	private function addDocuments($docs)
	{
		foreach ($docs as $doc) 
		{
			$this->addDocument($doc);
		}
	}

	/**
	 * @param DOMElement $doc
	 */
	private function addDocument($doc)
	{
		$datas = array();
		foreach ($doc->getElementsByTagName('field') as $xmlField) 
		{
			$name = $xmlField->getAttribute('name');
			if (isset($datas[$name]))
			{
				if (is_array($datas[$name]))
				{
					$datas[$name][] = $this->utf8Decode($xmlField->textContent);
				}
				else
				{
					$datas[$name] = array($datas[$name], $this->utf8Decode($xmlField->textContent));
				}
			}
			else 
			{
				$datas[$name] = $this->utf8Decode($xmlField->textContent);
			}
		}
		$fields = $this->compileRawFieldsArray($datas);
	
		$this->deleteInDb($fields['final_id']);
		$this->insertInDb($fields);
	}
	
	/**
	 * @param string $string
	 * @return string
	 */
	private function utf8Decode($string)
	{
		// The line below replaces quotes by an equivalent UTF-8 character (that's why it looks like it's not doing anything)
		// Cf LocaleService::processFile
		$string = str_replace('″', '"', $string);
		
		return utf8_decode($string);
	}
	
	/**
	 * @param array $datas
	 */
	private function compileRawFieldsArray($datas)
	{
		list($id, $lang) = explode('/', $datas['id']);
		if (isset($datas['lang']))
		{
			$lang = $datas['lang'];
			unset($datas['lang']);
		}
		
		$fields = array('client' => $datas['client'], 
			'final_id' => $datas['finalId'], 
			'document_id' =>$id, 
			'lang' =>$lang, 
			'label' => $datas['label_'.$lang]);
		
		unset($datas['id']);
		unset($datas['client']);
		unset($datas['finalId']);
		unset($datas['label_'.$lang]);
		
		if (isset($datas['documentModel']))
		{
			$fields['document_model'] = $datas['documentModel'];
			unset($datas['documentModel']);
		}
	
		if (isset($datas['text_'.$lang]))
		{
			$fields['text'] = preg_replace('/\s+/', ' ', $datas['text_'.$lang]);
			unset($datas['text_'.$lang]);
		}
		
		if (isset($datas[$lang . '_aggregateText']))
		{
			//$fields['aggregatetext'] = preg_replace('/\s+/', ' ', $datas[$lang . '_aggregateText']);
			unset($datas[$lang . '_aggregateText']);
		}
		
		if (isset($datas['__solrsearch_parentwebsite_id_idx_int']))
		{
			$fields['parentwebsite_id'] = $datas['__solrsearch_parentwebsite_id_idx_int'];
			unset($datas['__solrsearch_parentwebsite_id_idx_int']);
		}
			
		if (isset($datas['module_idx_str']))
		{
			$fields['module'] = $datas['module_idx_str'];
			unset($datas['module_idx_str']);
		}	
		
		if (isset($datas['document_accessor']))
		{
			$value =  (is_array($datas['document_accessor'])) ? implode('|', $datas['document_accessor']) : $datas['document_accessor'];
			$fields['document_accessor'] = '|' . $value . '|';
			unset($datas['document_accessor']);
		}
					
		if (isset($datas['document_ancestor']))
		{
			$value =  is_array($datas['document_ancestor']) ? implode('|', $datas['document_ancestor']) : $datas['document_ancestor'];
			$fields['document_ancestor'] = '|' . $value . '|';
			unset($datas['document_ancestor']);
		}	

		if (isset($datas[$lang . '_sortableLabel']))
		{
			$fields['sortable_label'] = $datas[$lang . '_sortableLabel'];
			unset($datas[$lang . '_sortableLabel']);
		}
		
		if (isset($datas['sortable_date_idx_dt']))
		{
			$fields['sortable_date'] = $datas['sortable_date_idx_dt'];
			unset($datas['sortable_date_idx_dt']);
		}
		
		if (isset($datas['modificationdate_idx_dt']))
		{
			$fields['sortable_date'] = $datas['modificationdate_idx_dt'];
		}		
		$fields['extras'] = serialize($datas);
		return $fields;
	}
	
	private function optimizeDB()
	{
		if (Framework::isInfoEnabled())
		{
			Framework::info(__METHOD__);
		}
		$sql = "OPTIMIZE TABLE `m_mysqlindexer_index`";
		$this->getPersistentProvider()->executeSQLScript($sql);	 
	}
	
	private function deleteByClientId($client)
	{
		if (Framework::isInfoEnabled())
		{
			Framework::info(__METHOD__ . "($client)");
		}
		$con = $this->getPersistentProvider()->getDriver();
		$sql = "DELETE FROM `m_mysqlindexer_index` WHERE `client` = :client";
		$stmt = $con->prepare($sql);
		$stmt->bindValue(':client', $client, PDO::PARAM_STR);
		$stmt->execute();		
	}
	
	private function deleteByFinalIds($finalIds)
	{
		if (Framework::isInfoEnabled())
		{
			Framework::info(__METHOD__);
		}
		if (is_array($finalIds))
		{
			foreach ($finalIds as $finalId) 
			{
				$this->deleteInDb($finalId);
			}
		}
		else
		{
			$this->deleteInDb($finalIds);
		}
	}
	
	private function deleteInDb($finalId)
	{
		
		$con = $this->getPersistentProvider()->getDriver();
		$sql = "DELETE FROM `m_mysqlindexer_index` WHERE `final_id` = :final_id";
		$stmt = $con->prepare($sql);
		$stmt->bindValue(':final_id', $finalId, PDO::PARAM_STR);
		$stmt->execute();
	}
	
	private function insertInDb($fields)
	{
		$con = $this->getPersistentProvider()->getDriver();
		$insert = array(); $values = array();
		foreach ($fields as $name => $value) 
		{
			$insert[] = "`" . $name . "`";
			$values[] = ":". $name;
		}
		$sql = "INSERT INTO `m_mysqlindexer_index` (". implode(', ', $insert) .") VALUES (" . implode(', ', $values) .")";		
		$stmt = $con->prepare($sql);
		foreach ($fields as $name => $value) 
		{
			$stmt->bindValue(':' . $name , $value, PDO::PARAM_STR);
		}
		$stmt->execute();
	}
	
	/**
	 * @param array $params
	 */
	public function select($params)
	{
		if (isset($params['fq']))
		{
			$client = $params['client'];
			if (strpos($client, '__backoffice'))
			{
				return $this->selectBo($params, $client);
			}
			else
			{
				return $this->selectFo($params, $client);
			}
			
		} 
		else if (isset($params['qt']) && isset($params['cmd']))
		{
			//?client=inthause.dev302&qt=spellchecker_en&cmd=rebuild
			if ($params['cmd'] === 'rebuild')
			{
				list(, $lang) = explode('_', $params['qt']);
				Framework::info('Rebuild Spell ' . $lang . ' for Client:' . $params['client']);
			}
		}
		return null;
	}
	
	private function getWords($words, &$fullText)
	{
		$result = array();
		foreach ($words as $word) 
		{
			$text = str_replace('*', '', $this->utf8Decode($word));
			if (strlen($text) > 3) 
			{
				$fullText = true;
			}
			$result[] = $text; 
		}
		
		if (count($result) == 1 && $fullText && is_numeric($result[0]))
		{
			$fullText = false;
		}	
		return $result;
	}
	
	private function buildScoreFieldWords($words, $fullText, $con)
	{
		if ($fullText)
		{
			return "MATCH (`label`,`text`) AGAINST (" . $con->quote(implode(' ', $words))  . ")";
		}
		else
		{
			return "1";
		}
	}
	
	private function buildTextCriteria($words, $fullText, $con)
	{
		
		if ($fullText)
		{
			return "MATCH (`label`,`text`) AGAINST (" . $con->quote("+" . implode('* +', $words) . '*')." IN BOOLEAN MODE)";
		}
		else
		{
			$sql = array();
			foreach ($words as $text) 
			{
				$escapedText = $con->quote('%'.$text.'%');
				if (is_numeric($text))
				{
					$sql[] = "(`label` LIKE $escapedText OR `text` LIKE $escapedText OR `document_id` LIKE $escapedText)"; 
				}
				else
				{
					$sql[] = "(`label` LIKE $escapedText OR `text` LIKE $escapedText)";
				}
			}
			return implode(' AND ', $sql);
		}
	}
	
	private function buildBoOrder($params, $lang)
	{
		//score_asc, fr_sortableLabel_asc creationdate_idx_dt_asc,  modificationdate_idx_dt_asc
		if (isset($params['score_desc']))
		{
			return " ORDER BY `score` DESC";
		} 
		else if (isset($params['score_asc']))
		{
			return " ORDER BY `score` ASC";
		}
		else if (isset($params[$lang . '_sortableLabel_desc']))
		{
			return " ORDER BY `sortable_label` DESC";
		} 
		else if (isset($params[$lang . '_sortableLabel_asc']))
		{
			return " ORDER BY `sortable_label` ASC";
		}
		else if (isset($params['creationdate_idx_dt_desc']))
		{
			return " ORDER BY `document_id` DESC";
		} 
		else if (isset($params['creationdate_idx_dt_asc']))
		{
			return " ORDER BY `document_id` ASC";
		}
		else if (isset($params['modificationdate_idx_dt_desc']))
		{
			return " ORDER BY `sortable_date` DESC";
		} 
		else if (isset($params['modificationdate_idx_dt_asc']))
		{
			return " ORDER BY `sortable_date` ASC";
		}	
		return '';
	}
	
	private function selectBo($params, $client)
	{
		$con = $this->getPersistentProvider()->getDriver();
		$matches = array();
		$limit = " LIMIT " . $params['start'] . " , " . $params['rows'];
		$where = array("`client`=" .$con->quote($client));
		$words = array();
		$lang = '';
		$fullText = false;
		if (preg_match_all('/text_([a-z]{2}):([^) ]*)/', $params['q'], $matches))
		{
			$lang = $matches[1][0];
			$where[] = "`lang`=" . $con->quote($lang);
			$words = $this->getWords($matches[2], $fullText);
			$score = $this->buildScoreFieldWords($words, $fullText, $con);
			$where[] = $this->buildTextCriteria($words, $fullText, $con);
		}
		$fq = $params['fq'];
		if (preg_match_all('/module_idx_str:([^) ]*)/', $fq, $matches))
		{
			$where[] = $this->generateSQLIn('module', $matches[1], $con);
		}
		if (preg_match_all('/document_accessor:([^) ]*)/', $fq, $matches))
		{
			$where[] = $this->generateSQLSearchArray('document_accessor', $matches[1], $con);
		}
		if (preg_match_all('/document_ancestor:([^) ]*)/', $fq, $matches))
		{
			$where[] = $this->generateSQLSearchArray('document_ancestor', $matches[1], $con);
		}
		$sql = "SELECT count(`final_id`) AS `nbrows`, MAX($score) AS `maxscore` FROM `m_mysqlindexer_index` WHERE " . implode(' AND ', $where) . "";
		foreach ($con->query($sql, PDO::FETCH_ASSOC) as $row) 
		{
	       $rowCount = $row['nbrows'];
	       $maxScore = $row['maxscore'];
		}
		$order = $this->buildBoOrder($params, $lang);
		$sql = "SELECT $score AS `score`, `final_id`, `document_id`, `document_model`, `module`, `lang`, `label`, `text`, `extras` FROM `m_mysqlindexer_index` WHERE " . implode(' AND ', $where) . $order . $limit;
		//Framework::info(__METHOD__ . ':' . $sql);
		$result = array();		
		foreach ($con->query($sql, PDO::FETCH_ASSOC) as $row) 
		{
	       $result[] =  $row;
		}
		return $this->buildXmlResponse($result, $rowCount, $maxScore, $params);
	}
	
	
	private function selectFo($params, $client)
	{
		$con = $this->getPersistentProvider()->getDriver();
		$matches = array();
		$limit = " LIMIT " . $params['start'] . " , " . $params['rows'];
		$where = array("`client`=" .$con->quote($client));
		$words = array();
		$lang = '';
		$fullText = false;
		if (preg_match_all('/text_([a-z]{2}):([^) ]*)/', $params['q'], $matches))
		{
			$lang = $matches[1][0];
			$where[] = "`lang`=" . $con->quote($lang);
			$words = $this->getWords($matches[2], $fullText);
			$score = $this->buildScoreFieldWords($words, $fullText, $con);
			$where[] = $this->buildTextCriteria($words, $fullText, $con);
		}
		
		$fq = $params['fq'];
		if (preg_match_all('/__solrsearch_parentwebsite_id_idx_int:([^) ]*)/', $fq, $matches))
		{
			$where[] = $this->generateSQLIn('parentwebsite_id', $matches[1], $con);
		}
		if (preg_match_all('/document_accessor:([^) ]*)/', $fq, $matches))
		{
			$where[] = $this->generateSQLSearchArray('document_accessor', $matches[1], $con);
		}
		$sql = "SELECT count(`final_id`) AS `nbrows`, MAX($score) AS `maxscore` FROM `m_mysqlindexer_index` WHERE " . implode(' AND ', $where) . "";
		foreach ($con->query($sql, PDO::FETCH_ASSOC) as $row) 
		{
	       $rowCount = $row['nbrows'];
	       $maxScore = $row['maxscore'];
		}

		if (isset($params['sort']) && $params['sort'] == 'score desc,id desc')
		{ 
			$order = " ORDER BY `score` DESC";
		}
		else
		{
			$order = " ORDER BY `sortable_date` DESC";
		}
		
		$sql = "SELECT $score AS `score`, `final_id`, `document_id`, `document_model`, `module`, `lang`, `label`, `text`, `extras` FROM `m_mysqlindexer_index` WHERE " . implode(' AND ', $where) . $order . $limit;
		//Framework::info(__METHOD__ . ':' . $sql);
		$result = array();		
		foreach ($con->query($sql, PDO::FETCH_ASSOC) as $row) 
		{
	       $result[] =  $this->highlightWords($row, $words);
		}
		
		
		return $this->buildXmlResponse($result, $rowCount, $maxScore, $params);
	}
	
	private function highlightWords($row, $words)
	{
		$string = $row['label'];
		$descr =  $row['text'];
        foreach ($words as $word)
        {
                $word = preg_quote(str_replace('*', '', $word));
                $string = preg_replace("/($word)/i", '<em>\1</em>', $string);
                $descr = preg_replace("/($word)/i", '<em>\1</em>', $descr);
        }
       	if (strpos($string, '<em>') !== false)
       	{
        	$row['hl_label'] = $string;
       	}
       	
	    if (($pos = strpos($descr, '<em>')) !== false)
       	{
       		$inword = false;
       		$start = $pos;
       		$word = 20;
       		$lastWord = $start;
       		while ($start > 0 && $word > 0)
       		{
       			switch ($descr[$start]) 
       			{
       				case ' ':
       				case ',':
       			       	if ($inword)
       					{
       						$lastWord = $start + 1;
       						$inword = false;
       						$word = $word -1;
       					}       					    					
       				case '.':
       				case '?':
       				case '!':
       				case ':':
       			       	if ($inword)
       					{
       						$lastWord = $start + 1;
       						$inword = false;
       						$word = 0;
       					} 
       					break;
       				default:
       					$inword = true;	
       					break;
       			}
       			$start = $start -1;
       		}
       		$start = ($word <= 0) ? $lastWord : 0;

       		$word = 20;
       		$end = $pos;
       		$lastWord = $end;
       		$nbChar = strlen($descr);
       		$inword = false;
       		
       		while ($end < $nbChar && $word > 0)
       		{
       			switch ($descr[$end]) 
       			{
       				case ' ':
       				case ',':
       			       	if ($inword)
       					{
       						$lastWord = $end;
       						$inword = false;
       						$word = $word -1;
       					}       					    					
       				case '.':
       				case '?':
       				case '!':
       				case ':':
       			       	if ($inword)
       					{
       						$lastWord = $end;
       						$inword = false;
       						$word = 0;
       					} 
       					break;
       				default:
       					$inword = true;	
       					break;
       			}
       			$end = $end +1;
       		}
       		$end = ($word == 0) ? $lastWord : $nbChar;       		
        	$row['hl_text'] = substr($descr, $start, ($end - $start));
       	}
       	return $row;
	}
	/**
	 * @param string $fieldName
	 * @param array $array
	 * @param PDO $con
	 * @return string
	 */
	private function generateSQLIn($fieldName, $array, $con)
	{
		$fieldName = ($fieldName[0] == '`') ? $fieldName : '`'.$fieldName.'`';
		$result = array();
		foreach ($array as $data) 
		{
			$result[] = $con->quote($data);
		}
		
		if (count($result) == 1)
		{
			return $fieldName . " = " . $result[0];
		}
		else
		{
			return $fieldName . " IN(" .implode(', ',$result) . ")";
		}
	}
	
	private function buildXmlResponse($result, $rowCount, $maxScore, $params)
	{
		$xmlWriter = new XMLWriter();
		$xmlWriter->openMemory();
		$xmlWriter->startDocument('1.0', 'UTF-8');
		$xmlWriter->startElement('response');
			
		$xmlWriter->startElement('lst');
		$xmlWriter->writeAttribute('name', 'responseHeader');
		
		$xmlWriter->startElement('int');$xmlWriter->writeAttribute('name', 'status');$xmlWriter->text('0');$xmlWriter->endElement();
		$xmlWriter->startElement('lst');
		$xmlWriter->writeAttribute('name', 'params');
		foreach ($params as $name => $val) 
		{
			$xmlWriter->startElement('str');
			$xmlWriter->writeAttribute('name', $name);
			$xmlWriter->text(htmlspecialchars($val));
			$xmlWriter->endElement();
		}
		$xmlWriter->endElement(); //lst	responseHeader	
		$xmlWriter->endElement(); //lst params
			
		$xmlWriter->startElement('result');
		$xmlWriter->writeAttribute('name', 'response');
		$xmlWriter->writeAttribute('numFound', strval($rowCount));
		$xmlWriter->writeAttribute('start', $params['start']);
		$xmlWriter->writeAttribute('maxScore', $maxScore);
		foreach ($result as $doc) 
		{
			$lang = $doc['lang'];
			$xmlWriter->startElement('doc');
			$xmlWriter->startElement('float');$xmlWriter->writeAttribute('name', 'score');$xmlWriter->text($doc['score']);$xmlWriter->endElement();
			$xmlWriter->startElement('str');$xmlWriter->writeAttribute('name', 'finalId');$xmlWriter->text($doc['final_id']);$xmlWriter->endElement();
			$xmlWriter->startElement('str');$xmlWriter->writeAttribute('name', 'documentModel');$xmlWriter->text($doc['document_model']);$xmlWriter->endElement();
			$xmlWriter->startElement('str');$xmlWriter->writeAttribute('name', 'id');$xmlWriter->text($doc['document_id'] . '/'. $lang);$xmlWriter->endElement();
			$xmlWriter->startElement('str');$xmlWriter->writeAttribute('name', 'lang');$xmlWriter->text($lang);$xmlWriter->endElement();
			
			$xmlWriter->startElement('str');$xmlWriter->writeAttribute('name', 'label_' . $lang);
			$xmlWriter->text(utf8_encode($doc['label']));$xmlWriter->endElement();
			
			$xmlWriter->startElement('str');$xmlWriter->writeAttribute('name', 'text_' . $lang);
			$xmlWriter->text(utf8_encode($doc['text']));$xmlWriter->endElement();
			
			if (isset($doc['module']))
			{
				$xmlWriter->startElement('str');$xmlWriter->writeAttribute('name', 'module_idx_str');
				$xmlWriter->text($doc['module']);$xmlWriter->endElement();				
			}
			
			if (isset($doc['extras']))
			{
				foreach (unserialize($doc['extras']) as $name => $value) 
				{
					if (is_array($value))
					{
						foreach ($value as $val) 
						{
							$xmlWriter->startElement('str');
							$xmlWriter->writeAttribute('name', $name);
							$xmlWriter->text(utf8_encode($val));
							$xmlWriter->endElement();
						}
					}
					else
					{
						$xmlWriter->startElement('str');
						$xmlWriter->writeAttribute('name', $name);
						$xmlWriter->text(utf8_encode($value));
						$xmlWriter->endElement();						
					}
				}
			}
			$xmlWriter->endElement(); //doc
		} 
		$xmlWriter->endElement(); //result
		
		if (isset($params['hl']))
		{
			$xmlWriter->startElement('lst');
			$xmlWriter->writeAttribute('name', 'highlighting');
			foreach ($result as $doc) 
			{
				$lang = $doc['lang'];
				$xmlWriter->startElement('lst');
				$xmlWriter->writeAttribute('name', $doc['final_id']);
				if (isset($doc['hl_label']))
				{
					$xmlWriter->startElement('arr');
					$xmlWriter->writeAttribute('name', 'label_'.$lang);
					$xmlWriter->startElement('str');
					$xmlWriter->text(utf8_encode($doc['hl_label']));
					$xmlWriter->endElement(); //str
					$xmlWriter->endElement(); //arr				
				}
				
				if (isset($doc['hl_text']))
				{
					$xmlWriter->startElement('arr');
					$xmlWriter->writeAttribute('name', 'text_'.$lang);
					$xmlWriter->startElement('str');
					$xmlWriter->text(utf8_encode($doc['hl_text']));
					$xmlWriter->endElement(); //str
					$xmlWriter->endElement(); //arr						
				}
				$xmlWriter->endElement(); //lst
			}
			$xmlWriter->endElement(); //highlighting
		}
		
		$xmlWriter->endElement(); //response
		$xmlWriter->endDocument();
		return $xmlWriter->outputMemory(true);
	}
	
	/**
	 * @param string $fieldName
	 * @param array $array
	 * @param PDO $con
	 * @return string
	 */
	private function generateSQLSearchArray($fieldName, $array, $con)
	{
		$fieldName = ($fieldName[0] == '`') ? $fieldName : '`'.$fieldName.'`';
		$result = array();
		foreach ($array as $data) 
		{
			$result[] = $fieldName . ' LIKE ' . $con->quote('%|'.$data.'|%');
		}
		
		if (count($result) == 1)
		{
			return $result[0];
		}
		else
		{
			return "(" .implode(' OR ',$result) . ")";
		}
	}	
}