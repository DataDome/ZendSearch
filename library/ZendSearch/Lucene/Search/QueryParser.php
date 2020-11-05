<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Search
 */

namespace ZendSearch\Lucene\Search;

use Exception;
use ZendSearch\Lucene;
use ZendSearch\Lucene\Analysis\Analyzer;
use ZendSearch\Lucene\Analysis\Analyzer\Common\TextNumWithDotAndIpV6;
use ZendSearch\Lucene\Exception\RuntimeException;
use ZendSearch\Lucene\Index;
use ZendSearch\Lucene\Search\Exception\QueryParserException;
use ZendSearch\Lucene\Search\Query\AbstractQuery;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Search
 */
class QueryParser extends Lucene\AbstractFSM
{
    /**
     * Parser instance
     *
     * @var QueryParser
     */
    private static $_instance = null;


    /**
     * Query lexer
     *
     * @var QueryLexer
     */
    private $_lexer;

    /**
     * Tokens list
     * Array of Zend_Search_Lucene_Search_QueryToken objects
     *
     * @var array
     */
    private $_tokens;

    /**
     * Current token
     *
     * @var integer|string|QueryToken
     */
    private $_currentToken;

    /**
     * Last token
     *
     * It can be processed within FSM states, but this addirional state simplifies FSM
     *
     * @var QueryToken
     */
    private $_lastToken = null;

    /**
     * Range query first term
     *
     * @var string
     */
    private $_rqFirstTerm = null;

    /**
     * Current query parser context
     *
     * @var QueryParserContext
     */
    private $_context;

    /**
     * Context stack
     *
     * @var array
     */
    private $_contextStack;

    /**
     * Query string encoding
     *
     * @var string
     */
    private $_encoding;

    /**
     * Query string default encoding
     *
     * @var string
     */
    private $_defaultEncoding = 'UTF-8';


    private $_fieldMapping = [];

    private $_privateFields = [];

    private $_disallowNonSpecifiedTerm = false;

    /**
     * Defines query parsing mode.
     *
     * If this option is turned on, then query parser suppress query parser exceptions
     * and constructs multi-term query using all words from a query.
     *
     * That helps to avoid exceptions caused by queries, which don't conform to query language,
     * but limits possibilities to check, that query entered by user has some inconsistencies.
     *
     *
     * Default is true.
     *
     * Use {@link Zend_Search_Lucene::suppressQueryParsingExceptions()},
     * {@link Zend_Search_Lucene::dontSuppressQueryParsingExceptions()} and
     * {@link Zend_Search_Lucene::checkQueryParsingExceptionsSuppressMode()} to operate
     * with this setting.
     *
     * @var boolean
     */
    private $_suppressQueryParsingExceptions = true;

    /**
     * Boolean operators constants
     */
    const B_OR  = 0;
    const B_AND = 1;

    /**
     * Default boolean queries operator
     *
     * @var integer
     */
    private $_defaultOperator = self::B_OR;


    /** Query parser State Machine states */
    const ST_COMMON_QUERY_ELEMENT       = 0;   // Terms, phrases, operators
    const ST_CLOSEDINT_RQ_START         = 1;   // Range query start (closed interval) - '['
    const ST_CLOSEDINT_RQ_FIRST_TERM    = 2;   // First term in '[term1 to term2]' construction
    const ST_CLOSEDINT_RQ_TO_TERM       = 3;   // 'TO' lexeme in '[term1 to term2]' construction
    const ST_CLOSEDINT_RQ_LAST_TERM     = 4;   // Second term in '[term1 to term2]' construction
    const ST_CLOSEDINT_RQ_END           = 5;   // Range query end (closed interval) - ']'
    const ST_OPENEDINT_RQ_START         = 6;   // Range query start (opened interval) - '{'
    const ST_OPENEDINT_RQ_FIRST_TERM    = 7;   // First term in '{term1 to term2}' construction
    const ST_OPENEDINT_RQ_TO_TERM       = 8;   // 'TO' lexeme in '{term1 to term2}' construction
    const ST_OPENEDINT_RQ_LAST_TERM     = 9;   // Second term in '{term1 to term2}' construction
    const ST_OPENEDINT_RQ_END           = 10;  // Range query end (opened interval) - '}'

    /**
     * Parser constructor
     */
    public function __construct()
    {
        parent::__construct([
                self::ST_COMMON_QUERY_ELEMENT,
                self::ST_CLOSEDINT_RQ_START,
                self::ST_CLOSEDINT_RQ_FIRST_TERM,
                self::ST_CLOSEDINT_RQ_TO_TERM,
                self::ST_CLOSEDINT_RQ_LAST_TERM,
                self::ST_CLOSEDINT_RQ_END,
                self::ST_OPENEDINT_RQ_START,
                self::ST_OPENEDINT_RQ_FIRST_TERM,
                self::ST_OPENEDINT_RQ_TO_TERM,
                self::ST_OPENEDINT_RQ_LAST_TERM,
                self::ST_OPENEDINT_RQ_END
            ],
            QueryToken::getTypes()
        );

        $this->addRules([
            [self::ST_COMMON_QUERY_ELEMENT, QueryToken::TT_WORD,             self::ST_COMMON_QUERY_ELEMENT],
            [self::ST_COMMON_QUERY_ELEMENT, QueryToken::TT_PHRASE,           self::ST_COMMON_QUERY_ELEMENT],
            [self::ST_COMMON_QUERY_ELEMENT, QueryToken::TT_FIELD,            self::ST_COMMON_QUERY_ELEMENT],
            [self::ST_COMMON_QUERY_ELEMENT, QueryToken::TT_REQUIRED,         self::ST_COMMON_QUERY_ELEMENT],
            [self::ST_COMMON_QUERY_ELEMENT, QueryToken::TT_PROHIBITED,       self::ST_COMMON_QUERY_ELEMENT],
            [self::ST_COMMON_QUERY_ELEMENT, QueryToken::TT_FUZZY_PROX_MARK,  self::ST_COMMON_QUERY_ELEMENT],
            [self::ST_COMMON_QUERY_ELEMENT, QueryToken::TT_BOOSTING_MARK,    self::ST_COMMON_QUERY_ELEMENT],
            [self::ST_COMMON_QUERY_ELEMENT, QueryToken::TT_RANGE_INCL_START, self::ST_CLOSEDINT_RQ_START],
            [self::ST_COMMON_QUERY_ELEMENT, QueryToken::TT_RANGE_EXCL_START, self::ST_OPENEDINT_RQ_START],
            [self::ST_COMMON_QUERY_ELEMENT, QueryToken::TT_SUBQUERY_START,   self::ST_COMMON_QUERY_ELEMENT],
            [self::ST_COMMON_QUERY_ELEMENT, QueryToken::TT_SUBQUERY_END,     self::ST_COMMON_QUERY_ELEMENT],
            [self::ST_COMMON_QUERY_ELEMENT, QueryToken::TT_AND_LEXEME,       self::ST_COMMON_QUERY_ELEMENT],
            [self::ST_COMMON_QUERY_ELEMENT, QueryToken::TT_OR_LEXEME,        self::ST_COMMON_QUERY_ELEMENT],
            [self::ST_COMMON_QUERY_ELEMENT, QueryToken::TT_NOT_LEXEME,       self::ST_COMMON_QUERY_ELEMENT],
            [self::ST_COMMON_QUERY_ELEMENT, QueryToken::TT_NUMBER,           self::ST_COMMON_QUERY_ELEMENT],

            // Regular closed range
            [self::ST_CLOSEDINT_RQ_START,      QueryToken::TT_WORD,           self::ST_CLOSEDINT_RQ_FIRST_TERM],
            [self::ST_CLOSEDINT_RQ_FIRST_TERM, QueryToken::TT_TO_LEXEME,      self::ST_CLOSEDINT_RQ_TO_TERM],
            [self::ST_CLOSEDINT_RQ_TO_TERM,    QueryToken::TT_WORD,           self::ST_CLOSEDINT_RQ_LAST_TERM],
            [self::ST_CLOSEDINT_RQ_LAST_TERM,  QueryToken::TT_RANGE_INCL_END, self::ST_COMMON_QUERY_ELEMENT],

            // IPV6 closed range
//            [self::ST_CLOSEDINT_RQ_START,      QueryToken::TT_FIELD,           self::ST_CLOSEDINT_RQ_FIRST_TERM],
//            [self::ST_CLOSEDINT_RQ_FIRST_TERM, QueryToken::TT_FIELD,           self::ST_CLOSEDINT_RQ_TO_TERM],
//            [self::ST_CLOSEDINT_RQ_TO_TERM,    QueryToken::TT_FIELD,           self::ST_CLOSEDINT_RQ_LAST_TERM],
//            [self::ST_CLOSEDINT_RQ_LAST_TERM,  QueryToken::TT_FIELD,           self::ST_COMMON_QUERY_ELEMENT],

            // Regular openend range
            [self::ST_OPENEDINT_RQ_START,      QueryToken::TT_WORD,           self::ST_OPENEDINT_RQ_FIRST_TERM],
            [self::ST_OPENEDINT_RQ_FIRST_TERM, QueryToken::TT_TO_LEXEME,      self::ST_OPENEDINT_RQ_TO_TERM],
            [self::ST_OPENEDINT_RQ_TO_TERM,    QueryToken::TT_WORD,           self::ST_OPENEDINT_RQ_LAST_TERM],
            [self::ST_OPENEDINT_RQ_LAST_TERM,  QueryToken::TT_RANGE_EXCL_END, self::ST_COMMON_QUERY_ELEMENT]

        ]);



        $addTermEntryAction             = new Lucene\FSMAction($this, 'addTermEntry');
        $addPhraseEntryAction           = new Lucene\FSMAction($this, 'addPhraseEntry');
        $setFieldAction                 = new Lucene\FSMAction($this, 'setField');
        $setSignAction                  = new Lucene\FSMAction($this, 'setSign');
        $setFuzzyProxAction             = new Lucene\FSMAction($this, 'processFuzzyProximityModifier');
        $processModifierParameterAction = new Lucene\FSMAction($this, 'processModifierParameter');
        $subqueryStartAction            = new Lucene\FSMAction($this, 'subqueryStart');
        $subqueryEndAction              = new Lucene\FSMAction($this, 'subqueryEnd');
        $logicalOperatorAction          = new Lucene\FSMAction($this, 'logicalOperator');
        $openedRQFirstTermAction        = new Lucene\FSMAction($this, 'openedRQFirstTerm');
        $openedRQLastTermAction         = new Lucene\FSMAction($this, 'openedRQLastTerm');
        $closedRQFirstTermAction        = new Lucene\FSMAction($this, 'closedRQFirstTerm');
        $closedRQLastTermAction         = new Lucene\FSMAction($this, 'closedRQLastTerm');


        $this->addInputAction(self::ST_COMMON_QUERY_ELEMENT, QueryToken::TT_WORD,            $addTermEntryAction);
        $this->addInputAction(self::ST_COMMON_QUERY_ELEMENT, QueryToken::TT_PHRASE,          $addPhraseEntryAction);
        $this->addInputAction(self::ST_COMMON_QUERY_ELEMENT, QueryToken::TT_FIELD,           $setFieldAction);
        $this->addInputAction(self::ST_COMMON_QUERY_ELEMENT, QueryToken::TT_REQUIRED,        $setSignAction);
        $this->addInputAction(self::ST_COMMON_QUERY_ELEMENT, QueryToken::TT_PROHIBITED,      $setSignAction);
        $this->addInputAction(self::ST_COMMON_QUERY_ELEMENT, QueryToken::TT_FUZZY_PROX_MARK, $setFuzzyProxAction);
        $this->addInputAction(self::ST_COMMON_QUERY_ELEMENT, QueryToken::TT_NUMBER,          $processModifierParameterAction);
        $this->addInputAction(self::ST_COMMON_QUERY_ELEMENT, QueryToken::TT_SUBQUERY_START,  $subqueryStartAction);
        $this->addInputAction(self::ST_COMMON_QUERY_ELEMENT, QueryToken::TT_SUBQUERY_END,    $subqueryEndAction);
        $this->addInputAction(self::ST_COMMON_QUERY_ELEMENT, QueryToken::TT_AND_LEXEME,      $logicalOperatorAction);
        $this->addInputAction(self::ST_COMMON_QUERY_ELEMENT, QueryToken::TT_OR_LEXEME,       $logicalOperatorAction);
        $this->addInputAction(self::ST_COMMON_QUERY_ELEMENT, QueryToken::TT_NOT_LEXEME,      $logicalOperatorAction);

        $this->addEntryAction(self::ST_OPENEDINT_RQ_FIRST_TERM, $openedRQFirstTermAction);
        $this->addEntryAction(self::ST_OPENEDINT_RQ_LAST_TERM,  $openedRQLastTermAction);
        $this->addEntryAction(self::ST_CLOSEDINT_RQ_FIRST_TERM, $closedRQFirstTermAction);
        $this->addEntryAction(self::ST_CLOSEDINT_RQ_LAST_TERM,  $closedRQLastTermAction);


        $this->_lexer = new QueryLexer();
    }

    /**
     * Get query parser instance
     *
     * @return QueryParser
     */
    private static function _getInstance()
    {
        if (self::$_instance === null) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Set query string default encoding
     *
     * @param string $encoding
     */
    public static function setDefaultEncoding($encoding)
    {
        self::_getInstance()->_defaultEncoding = $encoding;
    }

    /**
     * Get query string default encoding
     *
     * @return string
     */
    public static function getDefaultEncoding()
    {
       return self::_getInstance()->_defaultEncoding;
    }

    /**
     * Set default boolean operator
     *
     * @param integer $operator
     */
    public static function setDefaultOperator($operator)
    {
        self::_getInstance()->_defaultOperator = $operator;
    }

    /**
     * Get default boolean operator
     *
     * @return integer
     */
    public static function getDefaultOperator()
    {
        return self::_getInstance()->_defaultOperator;
    }

    /**
     * Turn on 'suppress query parser exceptions' mode.
     */
    public static function suppressQueryParsingExceptions()
    {
        self::_getInstance()->_suppressQueryParsingExceptions = true;
    }
    /**
     * Turn off 'suppress query parser exceptions' mode.
     */
    public static function dontSuppressQueryParsingExceptions()
    {
        self::_getInstance()->_suppressQueryParsingExceptions = false;
    }
    /**
     * Check 'suppress query parser exceptions' mode.
     * @return boolean
     */
    public static function queryParsingExceptionsSuppressed()
    {
        return self::_getInstance()->_suppressQueryParsingExceptions;
    }


    /**
     * Escape keyword to force it to be parsed as one term
     *
     * @param string $keyword
     * @return string
     */
    public static function escape($keyword)
    {
        return '\\' . implode('\\', str_split($keyword));
    }

    /**
     * Parses a query string
     *
     * @param string $strQuery
     * @param string $encoding
     *
     * @return AbstractQuery
     * @throws RuntimeException*@throws \Exception
     * @throws QueryParserException
     */
    public static function parse($strQuery, $encoding = null)
    {
        self::_getInstance();

        // Reset FSM if previous parse operation didn't return it into a correct state
        self::$_instance->reset();

        try {
            self::$_instance->_encoding     = ($encoding !== null) ? $encoding : self::$_instance->_defaultEncoding;
            self::$_instance->_lastToken    = null;
            self::$_instance->_context      = new QueryParserContext(self::$_instance->_encoding);
            self::$_instance->_contextStack = [];

            $ipv6withoutCidr = '/' . TextNumWithDotAndIpV6::IPV6_REGEXP_PATTERN . '/';
            $ipv6WithCIDR = '/^((([0-9A-Fa-f]{1,4}:){7}([0-9A-Fa-f]{1,4}|:))|(([0-9A-Fa-f]{1,4}:){6}(:[0-9A-Fa-f]{1,4}|((25[0-5]|2[0-4]d|1dd|[1-9]?d)(.(25[0-5]|2[0-4]d|1dd|[1-9]?d)){3})|:))|(([0-9A-Fa-f]{1,4}:){5}(((:[0-9A-Fa-f]{1,4}){1,2})|:((25[0-5]|2[0-4]d|1dd|[1-9]?d)(.(25[0-5]|2[0-4]d|1dd|[1-9]?d)){3})|:))|(([0-9A-Fa-f]{1,4}:){4}(((:[0-9A-Fa-f]{1,4}){1,3})|((:[0-9A-Fa-f]{1,4})?:((25[0-5]|2[0-4]d|1dd|[1-9]?d)(.(25[0-5]|2[0-4]d|1dd|[1-9]?d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){3}(((:[0-9A-Fa-f]{1,4}){1,4})|((:[0-9A-Fa-f]{1,4}){0,2}:((25[0-5]|2[0-4]d|1dd|[1-9]?d)(.(25[0-5]|2[0-4]d|1dd|[1-9]?d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){2}(((:[0-9A-Fa-f]{1,4}){1,5})|((:[0-9A-Fa-f]{1,4}){0,3}:((25[0-5]|2[0-4]d|1dd|[1-9]?d)(.(25[0-5]|2[0-4]d|1dd|[1-9]?d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){1}(((:[0-9A-Fa-f]{1,4}){1,6})|((:[0-9A-Fa-f]{1,4}){0,4}:((25[0-5]|2[0-4]d|1dd|[1-9]?d)(.(25[0-5]|2[0-4]d|1dd|[1-9]?d)){3}))|:))|(:(((:[0-9A-Fa-f]{1,4}){1,7})|((:[0-9A-Fa-f]{1,4}){0,5}:((25[0-5]|2[0-4]d|1dd|[1-9]?d)(.(25[0-5]|2[0-4]d|1dd|[1-9]?d)){3}))|:)))(%.+)?(\/([0-9]|[1-9][0-9]|1[0-1][0-9]|12[0-8]))?$/';
            preg_match_all(
                $ipv6withoutCidr,
                $strQuery,
                $matchedIpV6s
            );

            $ipV6s = $matchedIpV6s[0];
            foreach($ipV6s as $ipV6InQuery) {
                $ipv6withoutDoubleDots = str_replace(':','Z' /*char that cant be in an ip v6*/, $ipV6InQuery);
                $strQuery = str_replace($ipV6InQuery, $ipv6withoutDoubleDots, $strQuery);
            }
            self::$_instance->_tokens       = self::$_instance->_lexer->tokenize($strQuery, self::$_instance->_encoding);
//            var_dump(self::$_instance->_tokens);

            foreach(self::$_instance->_tokens as $token) {
//                var_dump('TOKEN:' . $token->getText());
                $originalIpV6Text = str_replace('Z', ':', $token->getText());
                foreach($ipV6s as $ipV6) {
//                    var_dump($originalIpV6Text, $ipV6);
                    if ($token instanceof QueryToken && str_contains($originalIpV6Text, $ipV6)) {
//                        var_dump('replaced!!!');
                        $token->setText($originalIpV6Text);
                        continue 1;
                    }
                }
            }
//            die;
//            var_dump(self::$_instance->_tokens);die;

            // Empty query
            if (count(self::$_instance->_tokens) == 0) {
                return new Query\Insignificant();
            }

            foreach (self::$_instance->_tokens as $token) {

                try {
                    self::$_instance->_currentToken = $token;
//                    var_dump($token);
                    self::$_instance->process($token->type);

                    self::$_instance->_lastToken = $token;
                } catch (Exception $e) {
                    if (strpos($e->getMessage(), 'There is no any rule for') !== false) {
                        throw new QueryParserException( 'Syntax error at char position ' . $token->position . '.', 0, $e);
                    }

                    throw $e;
                }
            }

            if (count(self::$_instance->_contextStack) != 0) {
                throw new QueryParserException('Syntax Error: mismatched parentheses, every opening must have closing.' );
            }

            return self::$_instance->_context->getQuery();
        } catch (QueryParserException $e) {

//            var_dump($e->getMessage().PHP_EOL.$e->getTraceAsString());die;
            if (self::$_instance->_suppressQueryParsingExceptions) {
                $queryTokens = Analyzer\Analyzer::getDefault()->tokenize($strQuery, self::$_instance->_encoding);

                $query = new Query\MultiTerm();
                $termsSign = (self::$_instance->_defaultOperator == self::B_AND) ? true /* required term */ :
                                                                                   null /* optional term */;
                foreach ($queryTokens as $token) {
                    $query->addTerm(new Index\Term($token->getTermText()), $termsSign);
                }

                return $query;
            } else {
                throw $e;
            }
        }
    }

    public static function preTokenizeIpV6($str)
    {

    }

    /*********************************************************************
     * Actions implementation
     *
     * Actions affect on recognized lexemes list
     *********************************************************************/

    /**
     * Add term to a query
     * @throws Exception
     */
    public function addTermEntry()
    {
        $field = $this->_context->getField();

        if($this->_disallowNonSpecifiedTerm && !$field) {
            throw new QueryParserException('A term without a specified field is not allowed.');
        }
        $this->validateField($field);

        $entry = new QueryEntry\Term($this->_currentToken->text, $this->mapFieldName($field));
        $this->_context->addEntry($entry);
    }

    /**
     * Add phrase to a query
     * @throws Exception
     */
    public function addPhraseEntry()
    {
        $field = $this->_context->getField();

        if($this->_disallowNonSpecifiedTerm && !$field) {
            throw new QueryParserException('A phrase without a specified field is not allowed.');
        }

        $this->validateField($field);

        $entry = new QueryEntry\Phrase($this->_currentToken->text, $this->mapFieldName($field));
        $this->_context->addEntry($entry);
    }

    /**
     * Set entry field
     */
    public function setField()
    {
        $this->_context->setNextEntryField($this->_currentToken->text);
    }

    /**
     * Set entry sign
     */
    public function setSign()
    {
        $this->_context->setNextEntrySign($this->_currentToken->type);
    }


    /**
     * Process fuzzy search/proximity modifier - '~'
     */
    public function processFuzzyProximityModifier()
    {
        $this->_context->processFuzzyProximityModifier();
    }

    /**
     * Process modifier parameter
     *
     * @throws QueryParserException
     * @throws RuntimeException
     */
    public function processModifierParameter()
    {
        if ($this->_lastToken === null) {
            throw new QueryParserException('Lexeme modifier parameter must follow lexeme modifier. Char position 0.' );
        }

        switch ($this->_lastToken->type) {
            case QueryToken::TT_FUZZY_PROX_MARK:
                $this->_context->processFuzzyProximityModifier($this->_currentToken->text);
                break;

            case QueryToken::TT_BOOSTING_MARK:
                $this->_context->boost($this->_currentToken->text);
                break;

            default:
                // It's not a user input exception
                throw new RuntimeException('Lexeme modifier parameter must follow lexeme modifier. Char position 0.' );
        }
    }


    /**
     * Start subquery
     */
    public function subqueryStart()
    {
        $this->_contextStack[] = $this->_context;
        $this->_context        = new QueryParserContext($this->_encoding, $this->_context->getField());
    }

    /**
     * End subquery
     */
    public function subqueryEnd()
    {
        if (count($this->_contextStack) == 0) {
            throw new QueryParserException('Syntax Error: mismatched parentheses, every opening must have closing. Char position ' . $this->_currentToken->position . '.' );
        }

        $query          = $this->_context->getQuery();
        $this->_context = array_pop($this->_contextStack);

        $this->_context->addEntry(new QueryEntry\Subquery($query));
    }

    /**
     * Process logical operator
     */
    public function logicalOperator()
    {
        $this->_context->addLogicalOperator($this->_currentToken->type);
    }

    /**
     * Process first range query term (opened interval)
     */
    public function openedRQFirstTerm()
    {
        $this->_rqFirstTerm = $this->_currentToken->text;
    }

    /**
     * Process last range query term (opened interval)
     *
     * @throws QueryParserException
     */
    public function openedRQLastTerm()
    {
        $tokens = Analyzer\Analyzer::getDefault()->tokenize($this->_rqFirstTerm, $this->_encoding);
        if (count($tokens) > 1) {
            throw new QueryParserException('Range query boundary terms must be non-multiple word terms');
        } elseif (count($tokens) == 1) {
            $from = new Index\Term(reset($tokens)->getTermText(), $this->_context->getField());
        } else {
            $from = null;
        }

        $tokens = Analyzer\Analyzer::getDefault()->tokenize($this->_currentToken->text, $this->_encoding);
        if (count($tokens) > 1) {
            throw new QueryParserException('Range query boundary terms must be non-multiple word terms');
        } elseif (count($tokens) == 1) {
            $to = new Index\Term(reset($tokens)->getTermText(), $this->_context->getField());
        } else {
            $to = null;
        }

        if ($from === null  &&  $to === null) {
            throw new QueryParserException('At least one range query boundary term must be non-empty term');
        }

        if($this->_disallowNonSpecifiedTerm && ($from->field === null || $to->field === null)) {
            throw new QueryParserException('Field declaration is missing in range.');
        }

        $this->validateField($from->field);
        $this->validateField($to->field);
        $from->field = $this->mapFieldName($from->field);
        $to->field   = $this->mapFieldName($to->field);

        $rangeQuery = new Query\Range($from, $to, false);
        $entry      = new QueryEntry\Subquery($rangeQuery);
        $this->_context->addEntry($entry);
    }

    /**
     * @param $field
     * @return bool
     */
    private function validateField($field)
    {
        if($field && $this->_fieldMapping && ! array_key_exists($field, $this->_fieldMapping)) {
            $authorizedFields = implode(', ', array_diff(array_keys($this->_fieldMapping), $this->_privateFields));
            throw new QueryParserException("Field $field is not authorized. Authorized fields are: $authorizedFields.");
        }
        return true;
    }

    private function mapFieldName($fieldName)
    {
        if ($fieldName && array_key_exists($fieldName, $this->_fieldMapping)) {
           return $this->_fieldMapping[$fieldName];
        }
        return $fieldName;
    }

    /**
     * Process first range query term (closed interval)
     */
    public function closedRQFirstTerm()
    {
        $this->_rqFirstTerm = $this->_currentToken->text;
    }

    /**
     * Process last range query term (closed interval)
     *
     * @throws QueryParserException
     */
    public function closedRQLastTerm()
    {
        $tokens = Analyzer\Analyzer::getDefault()->tokenize($this->_rqFirstTerm, $this->_encoding);
        if (count($tokens) > 1) {
//            var_dump($tokens);die;
            var_dump($tokens);
            die;
            throw new QueryParserException('Range query boundary terms must be non-multiple word terms');
        } elseif (count($tokens) == 1) {
            $from = new Index\Term(reset($tokens)->getTermText(), $this->_context->getField());
        } else {
            $from = null;
        }

        $tokens = Analyzer\Analyzer::getDefault()->tokenize($this->_currentToken->text, $this->_encoding);
        if (count($tokens) > 1) {
            throw new QueryParserException('Range query boundary terms must be non-multiple word terms');
        } elseif (count($tokens) == 1) {
            $to = new Index\Term(reset($tokens)->getTermText(), $this->_context->getField());
        } else {
            $to = null;
        }

        if ($from === null  &&  $to === null) {
            throw new QueryParserException('At least one range query boundary term must be non-empty term');
        }

        if($this->_disallowNonSpecifiedTerm && ($from->field === null || $to->field === null)) {
            throw new QueryParserException('Field declaration is missing in range.');
        }

        $this->validateField($from->field);
        $this->validateField($to->field);
        $from->field = $this->mapFieldName($from->field);
        $to->field   = $this->mapFieldName($to->field);

        $rangeQuery = new Query\Range($from, $to, true);
        $entry      = new QueryEntry\Subquery($rangeQuery);
        $this->_context->addEntry($entry);
    }



    /**
     * @return array
     */
    public static function getFieldMapping(): array
    {
        return self::_getInstance()->_fieldMapping;
    }

    /**
     * @param array $fieldMapping
     */
    public static function setFieldMapping(array $fieldMapping)
    {
        self::_getInstance()->_fieldMapping = $fieldMapping;
    }

    /**
     * @return array
     */
    public static function getPrivateFields(): array
    {
        return self::_getInstance()->_privateFields;
    }

    /**
     * @param array $privateFields
     */
    public static function setPrivateFields(array $privateFields)
    {
        self::_getInstance()->_privateFields = $privateFields;
    }

    /**
     * @return bool
     */
    public static function isDisallowNonSpecifiedTerm(): bool
    {
        return self::_getInstance()->_disallowNonSpecifiedTerm;
    }

    /**
     * @param bool $disallowNonSpecifiedTerm
     */
    public static function setDisallowNonSpecifiedTerm(bool $disallowNonSpecifiedTerm)
    {
        self::_getInstance()->_disallowNonSpecifiedTerm = $disallowNonSpecifiedTerm;
    }
}
