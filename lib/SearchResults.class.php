<?php
class mysqlindexer_SearchResults extends ArrayObject implements indexer_SearchResults
{
	
	private $totalHits;
	private $offset;
	private $returnedHits;
	private $maxScore;
	private $results = array();
	private $rows = 0;
	
	
	public function __construct($results, $rowCount, $maxScore, $offset, $rows)
	{
		$this->offset = $offset;
		$this->maxScore = $maxScore;
		$this->totalHits = $rowCount;
		$this->rows = $rows;
		$this->returnedHits = count($results);
		foreach ($results as $row) 
		{
			//`score`, `final_id`, `document_id`, `document_model`, `module`, `lang`, `label`, `text`, `extras`, `sortable_date`
			$result = new indexer_SearchResult();
			foreach ($row as $name => $value) 
			{
				switch ($name) 
				{
					case 'score':
						$result->setProperty('score', $value);
						$result->setProperty('normalizedScore', $value);
						break;	
					case 'final_id': $result->setProperty('id', $value); break;
					case 'sortable_date': $result->setProperty('modificationdate', $value); break;
					case 'document_id': $result->setProperty('changeId', $value); break;
					case 'document_model': $result->setProperty('documentModel', $value); break;
					case 'module': $result->setProperty('module', $value); $result->setProperty('editmodule', $value);   break;
					case 'lang': 
					case 'label':
					case 'text':
						$result->setProperty($name, $value); 
						break;
					case 'extras':
						$array = unserialize($value);
						foreach ($array as $extraName => $extraValue) 
						{
							$p = explode('_', $extraName);
							$result->setProperty($p[0], $extraValue); 
						}
						break;
				}
			}
			$this->results[] = $result;
		}
		parent::__construct($this->results);
	}
	
	/**
	 * @see indexer_SearchResults::getFacetResult()
	 */
	public function getFacetResult($fieldName)
	{
		return null;	
	}

	/**
	 * @see indexer_SearchResults::getFacetResults()
	 */
	public function getFacetResults()
	{
		return array();
	}

	public function getTotalHitsCount()
	{
		return $this->totalHits;
	}
	
	public function getReturnedHitsCount()
	{
		return $this->returnedHits;
	}
	
	public function getFirstHitOffset()
	{
		return $this->offset;
	}
	public function getReturnedHits()
	{
		return $this->results;
	}

	public function getRequestedHitsPerPageCount()
	{
		return $this->rows;
	}
	
	/**
	 * @see indexer_SearchResults::getSuggestion()
	 */
	public function getSuggestion()
	{
		return null;
	}
}