<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Search
 */

namespace ZendSearch\Lucene\Search\Query;

use ZendSearch\Lucene;
use ZendSearch\Lucene\Document;
use ZendSearch\Lucene\Search\Highlighter\DefaultHighlighter;
use ZendSearch\Lucene\Search\Highlighter\HighlighterInterface as Highlighter;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Search
 */
abstract class AbstractQuery
{
    /**
     * query boost factor
     *
     * @var float
     */
    private $_boost = 1;

    /**
     * AbstractQuery weight
     *
     * @var \ZendSearch\Lucene\Search\Weight\AbstractWeight
     */
    protected $_weight = null;

    /**
     * Gets the boost for this clause.  Documents matching
     * this clause will (in addition to the normal weightings) have their score
     * multiplied by boost.   The boost is 1.0 by default.
     *
     * @return float
     */
    public function getBoost()
    {
        return $this->_boost;
    }

    /**
     * Sets the boost for this query clause to $boost.
     *
     * @param float $boost
     */
    public function setBoost($boost)
    {
        $this->_boost = $boost;
    }

    /**
     * Score specified document
     *
     * @param integer $docId
     * @param \ZendSearch\Lucene\SearchIndexInterface $reader
     * @return float
     */
    abstract public function score($docId, Lucene\SearchIndexInterface $reader);

    /**
     * Get document ids likely matching the query
     *
     * It's an array with document ids as keys (performance considerations)
     *
     * @return array
     */
    abstract public function matchedDocs();

    /**
     * Execute query in context of index reader
     * It also initializes necessary internal structures
     *
     * AbstractQuery specific implementation
     *
     * @param \ZendSearch\Lucene\SearchIndexInterface $reader
     * @param \ZendSearch\Lucene\Index\DocsFilter|null $docsFilter
     */
    abstract public function execute(Lucene\SearchIndexInterface $reader, $docsFilter = null);

    /**
     * Constructs an appropriate Weight implementation for this query.
     *
     * @param \ZendSearch\Lucene\SearchIndexInterface $reader
     * @return \ZendSearch\Lucene\Search\Weight\AbstractWeight
     */
    abstract public function createWeight(Lucene\SearchIndexInterface $reader);

    /**
     * Constructs an initializes a Weight for a _top-level_query_.
     *
     * @param \ZendSearch\Lucene\SearchIndexInterface $reader
     */
    protected function _initWeight(Lucene\SearchIndexInterface $reader)
    {
        // Check, that it's a top-level query and query weight is not initialized yet.
        if ($this->_weight !== null) {
            return $this->_weight;
        }

        $this->createWeight($reader);
        $sum = $this->_weight->sumOfSquaredWeights();
        $queryNorm = $reader->getSimilarity()->queryNorm($sum);
        $this->_weight->normalize($queryNorm);
    }

    /**
     * Re-write query into primitive queries in the context of specified index
     *
     * @param \ZendSearch\Lucene\SearchIndexInterface $index
     * @return \ZendSearch\Lucene\Search\Query\AbstractQuery
     */
    abstract public function rewrite(Lucene\SearchIndexInterface $index);

    /**
     * Optimize query in the context of specified index
     *
     * @param \ZendSearch\Lucene\SearchIndexInterface $index
     * @return \ZendSearch\Lucene\Search\Query\AbstractQuery
     */
    abstract public function optimize(Lucene\SearchIndexInterface $index);

    /**
     * Reset query, so it can be reused within other queries or
     * with other indeces
     */
    public function reset()
    {
        $this->_weight = null;
    }


    /**
     * Print a query
     *
     * @return string
     */
    abstract public function __toString();

    /**
     * Return query terms
     *
     * @return array
     */
    abstract public function getQueryTerms();
}
