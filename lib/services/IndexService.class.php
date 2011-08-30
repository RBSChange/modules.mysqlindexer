<?php
class mysqlindexer_IndexService extends indexer_IndexService
{

	/**
	 * @var mysqlindexer_IndexService
	 */
	private static $instance = null;
	
	/**
	 * @return mysqlindexer_IndexService
	 */
	public static function getInstance()
	{
		if (null === self::$instance)
		{
			self::$instance = new self();
		}
		return self::$instance;
	}
	

	public function clearIndex()
	{
		$this->clearDb();
		parent::clearIndex();
	}
	
	public function optimizeIndex()
	{
		$this->optimizeDB();
	}
	
	public function rebuildSpellCheckIndexForLang($lang)
	{
		//TODO: 
	}
	
	/**
	 * @param integer $documentId
	 * @param string[] $langs
	 * @return string
	 */	
	protected function deleteDocumentIdForLangs($documentId, $langs)
	{
		foreach ($langs as $lang) 
		{
			$this->deleteInDb($documentId . '/' . $lang);
		}
		return parent::deleteDocumentIdForLangs($documentId, $langs);
	}
	
	/**
	 * @param indexer_IndexedDocument $indexedDocument
	 * @return string
	 */
	protected function addInIndex($indexedDocument)
	{
		$this->addDocument($indexedDocument);
		return parent::addInIndex($indexedDocument);
	}

	/**
	 * Execute $query on the configured <strong>frontoffice</strong> indexer using the standard request handler
	 * (search on label and full text with a boost on the label). 
	 * 
	 * @param indexer_Query $query
	 * @param String[] $suggestionTerms
	 * @return indexer_SolrSearchResults
	 */
	public function search(indexer_Query $query, $suggestionTerms = null)
	{
		$con = $this->getPersistentProvider()->getDriver();

		$limit = " LIMIT " . $query->getFirstHitOffset() . " , " . $query->getReturnedHitsCount();
		$where = array("`searchfo`=1", "`lang`=" . $con->quote($query->getLang()));
		$filter = $this->toSql($query->getFilterQuery(), $con);
		if ($filter !== null)
		{
			$where[] = $filter;
		}  
		$words = $query->getTerms();
		$fullText = false;

		$score = $this->buildScoreFieldWords($words, $fullText, $con);
		$where[] = $this->buildTextCriteria($words, $fullText, $con);

		$sql = "SELECT count(`final_id`) AS `nbrows`, MAX($score) AS `maxscore` FROM `m_mysqlindexer_index` WHERE " . implode(' AND ', $where) . "";
		foreach ($con->query($sql, PDO::FETCH_ASSOC) as $row) 
		{
	       $rowCount = $row['nbrows'];
	       $maxScore = $row['maxscore'];
		}

		
		//$where[] = $this->generateSQLSearchArray('document_accessor', $matches[1], $con);
		
		$orders = array();
		foreach ($query->getSortArray() as $name => $descending) 
		{
			if ($name == 'score')
			{
				$orders[] = 'score ' . ($descending ? 'desc' : 'asc');
			}
			elseif ($name == 'id')
			{
				$orders[] = 'document_id ' . ($descending ? 'desc' : 'asc');
			}
			elseif ($name == 'sortable_date_idx_dt')
			{
				$orders[] = 'sortable_date ' . ($descending ? 'desc' : 'asc');
			}
		}
		$order =  (count($orders)) ? " ORDER BY " . implode(', ', $orders) : '';
		
		$sql = "SELECT $score AS `score`, `final_id`, `document_id`, `document_model`, `module`, `lang`, `label`, `text`, `extras` FROM `m_mysqlindexer_index` WHERE " . implode(' AND ', $where) . $order . $limit;
		$result = array();		
		foreach ($con->query($sql, PDO::FETCH_ASSOC) as $row) 
		{
	       $result[] =  $this->highlightWords($row, $words);
		}
		
		$searchResults = new mysqlindexer_SearchResults($result, $rowCount, $maxScore, $query->getFirstHitOffset(), $query->getReturnedHitsCount());
		return $searchResults;
	}
	
	/**
	 * @param indexer_Query $query
	 * @param PDO $con
	 */
	private function toSql($query, $con)
	{
		if ($query instanceof indexer_TermQuery) 
		{
			
			$name = $query->getFieldName();
			switch ($name)
			{
				case 'websiteIds_vol_mul_int':
					return 'websiteids  LIKE ' . $con->quote('%|'.$query->getValue().'|%');
				case 'documentModel':
					return 'document_model  = ' . $con->quote($query->getValue());
				case 'editmodule_idx_str':
					return 'module = ' . $con->quote($query->getValue());
				case 'changeId':
					return 'document_id  = ' . $con->quote($query->getValue());		
				case 'document_accessor':
				case 'document_ancestor':
					return $name . ' LIKE ' . $con->quote('%|'.$query->getValue().'|%');
			}
		}
		elseif ($query instanceof indexer_BooleanQuery)
		{
			$sql = array();
			foreach ($query->getSubqueries() as $subquery) 
			{
				$data = $this->toSql($subquery, $con);
				if ($data !== null)
				{
					$sql[] = $data;
				}
			}
			
			if (count($sql))
			{
				return '(' . implode(' ' . $query->getType() . ' ', $sql) . ')';
			}
		}
		return null;
	}
	
	/**
	 * Execute $query on the configured <strong>backoffice</strong> indexer using the standard request handler
	 * (search on label and full text with a boost on the label). 
	 * 
	 * @param indexer_Query $query
	 * @return indexer_SearchResults
	 */
	public function searchBackoffice(indexer_Query $query)
	{
		$con = $this->getPersistentProvider()->getDriver();
		
		$limit = " LIMIT " . $query->getFirstHitOffset() . " , " . $query->getReturnedHitsCount();
		$where = array("`searchbo`=1");
		$filter = $this->toSql($query->getFilterQuery(), $con);
		if ($filter !== null)
		{
			$where[] = $filter;
		}  
		$words = array();
		foreach ($query->getTerms() as $word) 
		{
			$text = str_replace('*', '', $word);
			$match = null;
			if (preg_match('/^[0-9]+\/([a-z]{2})$/', $text, $match))
			{
				$query->setLang($match[1]);
			}
			else
			{
				$words[] = $text;
			}
		}
		
		if ($query->getLang())
		{
			$where[] = "`lang`=" . $con->quote($query->getLang());
		}		
		
		$fullText = false;

		$score = $this->buildScoreFieldWords($words, $fullText, $con);
		$where[] = $this->buildTextCriteria($words, $fullText, $con);

		$sql = "SELECT count(`final_id`) AS `nbrows`, MAX($score) AS `maxscore` FROM `m_mysqlindexer_index` WHERE " . implode(' AND ', $where) . "";
		foreach ($con->query($sql, PDO::FETCH_ASSOC) as $row) 
		{
	       $rowCount = $row['nbrows'];
	       $maxScore = $row['maxscore'];
		}

		$orders = array();
		foreach ($query->getSortArray() as $name => $descending) 
		{
			if ($name == 'score')
			{
				$orders[] = 'score ' . ($descending ? 'desc' : 'asc');
			}
			elseif ($name == 'id')
			{
				$orders[] = 'document_id ' . ($descending ? 'desc' : 'asc');
			}
			elseif ($name == 'sortable_date_idx_dt')
			{
				$orders[] = 'sortable_date ' . ($descending ? 'desc' : 'asc');
			}
			elseif (strpos($name, 'sortableLabel') !== false)
			{
				$orders[] = 'label ' . ($descending ? 'desc' : 'asc');
			}
			elseif ($name == 'creationdate_idx_dt')
			{
				$orders[] = 'document_id ' . ($descending ? 'desc' : 'asc');
			}
			elseif ($name == 'modificationdate_idx_dt')
			{
				$orders[] = 'sortable_date ' . ($descending ? 'desc' : 'asc');
			}
		}
		$order =  (count($orders)) ? " ORDER BY " . implode(', ', $orders) : '';
		
		$sql = "SELECT $score AS `score`, `final_id`, `document_id`, `document_model`, `module`, `sortable_date`, `lang`, `label`, `text`, `extras` FROM `m_mysqlindexer_index` WHERE " . implode(' AND ', $where) . $order . $limit;
		$result = array();		
		foreach ($con->query($sql, PDO::FETCH_ASSOC) as $row) 
		{
	       $result[] =  $this->highlightWords($row, $words);
		}
		
		$searchResults = new mysqlindexer_SearchResults($result, $rowCount, $maxScore, $query->getFirstHitOffset(), $query->getReturnedHitsCount());
		return $searchResults;
	}	
	
	/**
	 * Get an array of at most $count suggestions for the word $word from the spellchecker for $lang. 
	 *
	 * @param String $word
	 * @param String $lang
	 * @param String $count
	 * @return Array<String>
	 */
	public function getSuggestionArrayForWord($word, $lang = null, $count = null)
	{
		//TODO
		return parent::getSuggestionArrayForWord($word, $lang, $count);
	}
	
	/**
	 * @param indexer_IndexedDocument $doc
	 */
	private function addDocument($doc)
	{
		$fields = array();
		$extra = array();
		foreach ($doc->getFields() as $name => $info) 
		{
			$value = $info['value'];
			switch ($name) 
			{
				case 'id':  $fields['final_id'] = strval($value); break;
				case 'changeId':  $fields['document_id'] = intval($value); break;
				case 'documentModel':  $fields['document_model'] = strval($value); break;
				case 'editmodule_idx_str':  $fields['module'] = strval($value); break;
				case 'module_idx_str':  if (!isset($fields['module'])) {$fields['module'] = strval($value);} break;
				case 'lang':  $fields['lang'] = strval($value); break;
				case 'SEARCHFO_idx_int':  $fields['searchfo'] = intval($value); break;
				case 'SEARCHBO_idx_int':  $fields['searchbo'] = intval($value); break;
				case 'label':  $fields['label'] = strval($value); break;
				case 'text':  $fields['text'] = strval($value); break;
				case 'document_accessor':
				case 'document_ancestor':
					if (is_array($value)) {$fields[$name] = '|' . implode('|', $value) . '|';}
					break;
				case 'websiteIds_vol_mul_int':
					if (is_array($value)) {$fields['websiteids'] = '|' . implode('|', $value) . '|';}
					break;					
				case 'sortable_date_idx_dt':  $fields['sortable_date'] = strval($value); break;
				case 'modificationdate_idx_dt':  if (!isset($fields['sortable_date'])) {$fields['sortable_date'] = strval($value);} break;
				
				default:
					if (strpos($name, 'aggregateText') !== false)
					{
						if (is_array($value)) {$value = implode('. ', array_unique($value));}
						if (isset($fields['aggregateText']))
						{
							$fields['aggregateText'] .= '. ' . $value;
						}
						else
						{
							$fields['aggregateText'] = $value;
						}
					}
					else
					{
						$extra[$name] = $value;
					}
					break;
			}	
		}
		if (isset( $fields['final_id']))
		{
			$finalId = $fields['final_id'];
			$fields['extras'] = serialize($extra);					
			$this->deleteInDb($fields['final_id']);
			$this->insertInDb($fields);
			if (count($extra))
			{
				$this->insertPropsInDb($finalId, $extra);
			}
		}
	}
	
	
	private function optimizeDB()
	{
		if (Framework::isInfoEnabled())
		{
			Framework::info(__METHOD__);
		}
		$con = $this->getPersistentProvider()->getDriver();
		$sql = "OPTIMIZE TABLE `m_mysqlindexer_index` , `m_mysqlindexer_props`";
		$con->exec($sql);
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
		
		$sql = "DELETE FROM `m_mysqlindexer_props` WHERE `final_id` = :final_id";
		$stmt = $con->prepare($sql);
		$stmt->bindValue(':final_id', $finalId, PDO::PARAM_STR);
		$stmt->execute();
	}
	
	private function clearDb()
	{
		if (Framework::isInfoEnabled())
		{
			Framework::info(__METHOD__);
		}		
		$con = $this->getPersistentProvider()->getDriver();
		$sql = "TRUNCATE TABLE `m_mysqlindexer_index`";
		$con->exec($sql);
		
		$sql = "TRUNCATE TABLE `m_mysqlindexer_props`";
		$con->exec($sql);
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
			if (is_int($value))
			{
				$stmt->bindValue(':' . $name , $value, PDO::PARAM_INT);
			}
			elseif ($value === null)
			{
				$stmt->bindValue(':' . $name , $value, PDO::PARAM_NULL);
			}
			else
			{
				$stmt->bindValue(':' . $name , $value, PDO::PARAM_STR);
			}
		}
		$stmt->execute();
	}
	
	private function insertPropsInDb($finalId, $extra)
	{
		$con = $this->getPersistentProvider()->getDriver();
		$sql = "INSERT INTO `m_mysqlindexer_props` (`final_id`, `prop`, `data`) VALUES (:final_id, :prop, :data)";	
		$stmt = $con->prepare($sql);
		foreach ($extra as $name => $datas) 
		{
			if (is_array($datas))
			{
				foreach ($datas as $data) 
				{
					$value = strval($data);
					if ($value !==  '' && strlen($value) < 100)
					{
						$stmt->execute(array(':final_id' => $finalId, ':prop' => $name, ':data' => $value));
					}
				}
			}
			else
			{
				$value = strval($datas);
				if ($value !==  '' && strlen($value) < 100)
				{
					$stmt->execute(array(':final_id' => $finalId, ':prop' => $name, ':data' => $value));
				}
			}
		}
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
			return "MATCH (`label`,`text`, `aggregateText`) AGAINST (" . $con->quote(implode(' ', $words))  . ")";
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
			return "MATCH (`label`,`text`, `aggregateText`) AGAINST (" . $con->quote("+" . implode('* +', $words) . '*')." IN BOOLEAN MODE)";
		}
		else
		{
			$sql = array();
			foreach ($words as $name => $text) 
			{
				$escapedText = $con->quote('%'.$text.'%');
				if (is_numeric($text))
				{
					$sql[$escapedText] = "(`label` LIKE $escapedText OR `text` LIKE $escapedText OR `aggregateText` LIKE $escapedText OR `document_id` = $text)"; 
				}
				else
				{
					$sql[$escapedText] = "(`label` LIKE $escapedText OR `text` LIKE $escapedText OR `aggregateText` LIKE $escapedText)";
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