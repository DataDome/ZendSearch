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
use ZendSearch\Lucene\Analysis\Analyzer;
use ZendSearch\Lucene\Index;
use ZendSearch\Lucene\Search\Exception\QueryParserException;
use ZendSearch\Lucene\Search\Highlighter\HighlighterInterface as Highlighter;
use ZendSearch\Lucene\Search\Query;
use Zend\Stdlib\ErrorHandler;
use ZendSearch\Lucene\Search\QueryLexer;

/**
 * It's an internal abstract class intended to finalize ase a query processing after query parsing.
 * This type of query is not actually involved into query execution.
 *
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Search
 * @internal
 */
class Term extends AbstractPreprocessing
{
    /**
     * word (query parser lexeme) to find.
     *
     * @var string
     */
    private $_word;

    /**
     * Word encoding (field name is always provided using UTF-8 encoding since it may be retrieved from index).
     *
     * @var string
     */
    private $_encoding;


    /**
     * Field name.
     *
     * @var string
     */
    private $_field;

    /**
     * Class constructor.  Create a new preprocessing object for prase query.
     *
     * @param string $word       Non-tokenized word (query parser lexeme) to search.
     * @param string $encoding   Word encoding.
     * @param string $fieldName  Field name.
     */
    public function __construct($word, $encoding, $fieldName)
    {
        $this->_word     = $word;
        $this->_encoding = $encoding;
        $this->_field    = $fieldName;
    }

    /**
     * Re-write query into primitive queries in the context of specified index
     *
     * @param \ZendSearch\Lucene\SearchIndexInterface $index
     * @throws \ZendSearch\Lucene\Search\Exception\QueryParserException
     * @return \ZendSearch\Lucene\Search\Query\AbstractQuery
     */
    public function rewrite(Lucene\SearchIndexInterface $index)
    {
        if ($this->_field === null) {
            $query = new Query\MultiTerm();
            $query->setBoost($this->getBoost());

            $hasInsignificantSubqueries = false;

            if (Lucene\Lucene::getDefaultSearchField() === null) {
                $searchFields = $index->getFieldNames(true);
            } else {
                $searchFields = [Lucene\Lucene::getDefaultSearchField()];
            }

            foreach ($searchFields as $fieldName) {
                $subquery = new Term($this->_word,
                                     $this->_encoding,
                                     $fieldName);
                $rewrittenSubquery = $subquery->rewrite($index);
                foreach ($rewrittenSubquery->getQueryTerms() as $term) {
                    $query->addTerm($term);
                }

                if ($rewrittenSubquery instanceof Query\Insignificant) {
                    $hasInsignificantSubqueries = true;
                }
            }

            if (count($query->getTerms()) == 0) {
                $this->_matches = [];
                if ($hasInsignificantSubqueries) {
                    return new Query\Insignificant();
                } else {
                    return new Query\EmptyResult();
                }
            }

            $this->_matches = $query->getQueryTerms();
            return $query;
        }

        // -------------------------------------
        // Recognize exact term matching (it corresponds to Keyword fields stored in the index)
        // encoding is not used since we expect binary matching
        $term = new Index\Term($this->_word, $this->_field);
        if ($index->hasTerm($term)) {
            $query = new Query\Term($term);
            $query->setBoost($this->getBoost());

            $this->_matches = $query->getQueryTerms();
            return $query;
        }


        // -------------------------------------
        // Recognize wildcard queries

        /**
         * @todo check for PCRE unicode support may be performed through Zend_Environment in some future
         */
        ErrorHandler::start(E_WARNING);
        $result = preg_match('/\pL/u', 'a');
        ErrorHandler::stop();
        if ($result == 1) {
            $word = iconv($this->_encoding, 'UTF-8', $this->_word);
            $wildcardsPattern = '/[*?]/u';
            $subPatternsEncoding = 'UTF-8';
        } else {
            $word = $this->_word;
            $wildcardsPattern = '/[*?]/';
            $subPatternsEncoding = $this->_encoding;
        }

        $subPatterns = preg_split($wildcardsPattern, $word, -1, PREG_SPLIT_OFFSET_CAPTURE);

        if (count($subPatterns) > 1) {
            // Wildcard query is recognized

            $pattern = '';

            foreach ($subPatterns as $id => $subPattern) {
                // Append corresponding wildcard character to the pattern before each sub-pattern (except first)
                if ($id != 0) {
                    $pattern .= $word[ $subPattern[1] - 1 ];
                }

                // Check if each subputtern is a single word in terms of current analyzer
                $tokens = Analyzer\Analyzer::getDefault()->tokenize($subPattern[0], $subPatternsEncoding);
                if (count($tokens) > 1) {
                    throw new QueryParserException('Wildcard search is supported only for non-multiple word terms');
                }
                foreach ($tokens as $token) {
                    $pattern .= $token->getTermText();
                }
            }

            $term  = new Index\Term($pattern, $this->_field);
            $query = new Query\Wildcard($term);
            $query->setBoost($this->getBoost());

            // Get rewritten query. Important! It also fills terms matching container.
            $rewrittenQuery = $query->rewrite($index);
            $this->_matches = $query->getQueryTerms();

            return $rewrittenQuery;
        }


        // -------------------------------------
        // Recognize one-term multi-term and "insignificant" queries
        $tokens = Analyzer\Analyzer::getDefault()->tokenize($this->_word, $this->_encoding);

        if (count($tokens) == 0) {
            $this->_matches = [];
            return new Query\Insignificant();
        }

        if (count($tokens) == 1) {
            $term  = new Index\Term($tokens[0]->getTermText(), $this->_field);
            $query = new Query\Term($term);
            $query->setBoost($this->getBoost());

            $this->_matches = $query->getQueryTerms();
            return $query;
        }

        //It's not insignificant or one term query
        $query = new Query\MultiTerm();

        /**
         * @todo Process $token->getPositionIncrement() to support stemming, synonyms and other
         * analizer design features
         */
        foreach ($tokens as $token) {
            $term = new Index\Term($token->getTermText(), $this->_field);
            $query->addTerm($term, true); // all subterms are required
        }

        $query->setBoost($this->getBoost());

        $this->_matches = $query->getQueryTerms();
        return $query;
    }

    /**
     * Print a query
     *
     * @return string
     */
    public function __toString()
    {
        if ($this->_field !== null) {
            $query = $this->_field . ':';
        } else {
            $query = '';
        }
        $charsToEscape = array_merge(
            str_split(QueryLexer::QUERY_SYNT_CHARS),
            str_split(QueryLexer::QUERY_MUTABLE_CHARS),
            str_split(QueryLexer::QUERY_REGEXP_DELIMITER_CHAR),
            str_split(QueryLexer::QUERY_DOUBLECHARLEXEME_CHARS),
            str_split(QueryLexer::QUERY_LEXEMEMODIFIER_CHARS),
            str_split(QueryLexer::QUERY_SINGLE_CHAR_WILDCARD_CHAR)
        );
        $escapedWord = '';
        for($charIndex = 0; $charIndex < mb_strlen($this->_word, $this->_encoding); $charIndex++) {
            if(in_array($this->_word[$charIndex], $charsToEscape) &&
                ($charIndex === 0 || $this->_word[$charIndex - 1] != '\\')) {
                $escapedWord .= '\\';
            }
            $escapedWord .= $this->_word[$charIndex];
        }

        $query .= $escapedWord;

        if ($this->getBoost() != 1) {
            $query .= '^' . round($this->getBoost(), 4);
        }

        return $query;
    }
}
