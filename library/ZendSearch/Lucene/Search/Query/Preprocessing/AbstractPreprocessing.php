<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Search
 */

namespace ZendSearch\Lucene\Search\Query\Preprocessing;

use ZendSearch\Lucene;
use ZendSearch\Lucene\Exception\UnsupportedMethodCallException;
use ZendSearch\Lucene\Index\DocsFilter;
use ZendSearch\Lucene\Search\Query;
use ZendSearch\Lucene\Search\Query\AbstractQuery;
use ZendSearch\Lucene\SearchIndexInterface;

/**
 * It's an internal abstract class intended to finalize ase a query processing after query parsing.
 * This type of query is not actually involved into query execution.
 *
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Search
 * @internal
 */
abstract class AbstractPreprocessing extends AbstractQuery
{
    /**
     * Matched terms.
     *
     * Matched terms list.
     * It's filled during rewrite operation and may be used for search result highlighting
     *
     * Array of Zend_Search_Lucene_Index_Term objects
     *
     * @var array
     */
    protected $_matches = null;

    /**
     * Optimize query in the context of specified index
     *
     * @param SearchIndexInterface $index
     *
     * @return AbstractQuery
     *@throws UnsupportedMethodCallException
     */
    public function optimize(SearchIndexInterface $index)
    {
        throw new UnsupportedMethodCallException('This query is not intended to be executed.');
    }

    /**
     * Constructs an appropriate Weight implementation for this query.
     *
     * @param SearchIndexInterface $reader
     *
     * @throws UnsupportedMethodCallException
     */
    public function createWeight(SearchIndexInterface $reader)
    {
        throw new UnsupportedMethodCallException('This query is not intended to be executed.');
    }

    /**
     * Execute query in context of index reader
     * It also initializes necessary internal structures
     *
     * @param SearchIndexInterface $reader
     * @param DocsFilter|null $docsFilter
     *
     * @throws UnsupportedMethodCallException
     */
    public function execute(SearchIndexInterface $reader, $docsFilter = null)
    {
        throw new UnsupportedMethodCallException('This query is not intended to be executed.');
    }

    /**
     * Get document ids likely matching the query
     *
     * It's an array with document ids as keys (performance considerations)
     *
     * @return array
     *@throws UnsupportedMethodCallException
     */
    public function matchedDocs()
    {
        throw new UnsupportedMethodCallException('This query is not intended to be executed.');
    }

    /**
     * Score specified document
     *
     * @param integer $docId
     * @param SearchIndexInterface $reader
     *
     * @return float
     *@throws UnsupportedMethodCallException
     */
    public function score($docId, SearchIndexInterface $reader)
    {
        throw new UnsupportedMethodCallException('This query is not intended to be executed.');
    }

    /**
     * Return query terms
     *
     * @return array
     *@throws UnsupportedMethodCallException
     */
    public function getQueryTerms()
    {
        throw new UnsupportedMethodCallException('Rewrite operation has to be done before retrieving query terms.');
    }
}
