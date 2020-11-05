<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Search
 */

namespace ZendSearchTest\Lucene;

use ZendSearch\Lucene\Analysis\Analyzer\Analyzer;
use ZendSearch\Lucene\Analysis\Analyzer\Common\TextNumWithDot;
use ZendSearch\Lucene\Analysis\Analyzer\Common\TextNumWithDotAndIpV6;
use ZendSearch\Lucene\Search\Query;
use ZendSearch\Lucene;
use ZendSearch\Lucene\Search;
use PHPUnit\Framework\TestCase;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage UnitTests
 * @group      Zend_Search_Lucene
 */
class SearchTest extends TestCase
{
    public function testQueryParser()
    {
        $wildcardMinPrefix = Query\Wildcard::getMinPrefixLength();
        Query\Wildcard::setMinPrefixLength(0);

        $defaultPrefixLength = Query\Fuzzy::getDefaultPrefixLength();
        Query\Fuzzy::setDefaultPrefixLength(0);

        Analyzer::setDefault(new TextNumWithDot());

        $testQueries = [
//            ['title:"The Right Way" AND text:go', '+(title:"The Right Way") +(text:go)'],
//            ['title:"Do it right" AND right', '+(title:"Do it right") +(right)'],
//            ['title:Do it right', '(title:Do) (it) (right)'],
//            ['te?t', '(te\?t)'],
//            ['test*', '(test*)'],
//            ['te*t', '(te*t)'],
//            ['?Ma*', '(\?Ma*)'],
//            ['test~', '(test\~)'],
//            ['contents:[business TO by]', '(contents:[business TO by])'],
//            ['"jakarta apache" jakarta', '("jakarta apache") (jakarta)'],
//            ['"jakarta apache" OR jakarta', '("jakarta apache") (jakarta)'],
//            ['"jakarta apache" || jakarta', '("jakarta apache") (jakarta)'],
//            ['"jakarta apache" AND "Apache Lucene"', '+("jakarta apache") +("Apache Lucene")'],
//            ['"jakarta apache" && "Apache Lucene"', '+("jakarta apache") +("Apache Lucene")'],
//            ['+jakarta apache', '+(jakarta) (apache)'],
//            ['"jakarta apache" AND NOT "Apache Lucene"', '+("jakarta apache") -("Apache Lucene")'],
//            ['"jakarta apache" && !"Apache Lucene"', '+("jakarta apache") -("Apache Lucene")'],
//            ['\\ ', '( )'],
//            ['NOT "jakarta apache"', '<InsignificantQuery>'],
//            ['!"jakarta apache"', '<InsignificantQuery>'],
//            ['"jakarta apache" -"Apache Lucene"', '("jakarta apache") -("Apache Lucene")'],
//            ['(jakarta OR apache) AND website', '+((jakarta) (apache)) +(website)'],
//            ['title:(+return +"pink panther")', '(+(title:return) +(title:"pink panther"))'],
//
//            ['title:(+re\\turn\\ value +"pink panther\\"" +body:cool)', '(+(title:return value) +(title:"pink panther"") +(body:cool))'],
//            ['contents:apache AND type:1 AND id:5', '+(contents:apache) +(type:1) +(id:5)'],
//            ['f1:word1 f1:word2 and f1:word3', '(f1:word1) (+(f1:word2) +(f1:word3))'],
//            ['f1:word1 not f1:word2 and f1:word3', '(f1:word1) (-(f1:word2) +(f1:word3))'],
//            ['ip:[1.2.3.4 TO 1.2.3.6]', '(ip:[1.2.3.4 TO 1.2.3.6])'],
//            ['ip:"2a02:5180:0:2669:0:0:0:0"', '(ip:"2a02:5180:0:2669:0:0:0:0")'],
            ['ip:[2a02:5180:0:2669:0:0:0:0 TO 2a02:5180:0:2669:ffff:ffff:ffff:ffff]', 'ip:[2a02:5180:0:2669:0:0:0:0 TO 2a02:5180:0:2669:ffff:ffff:ffff:ffff])'],
            ['ip:[2a02\:5180\:0\:2669\:0\:0\:0\:0 to 2a02\:5180\:0\:2669\:ffff\:ffff\:ffff\:ffff]', 'ip:[2a02:5180:0:2669:0:0:0:0 TO 2a02:5180:0:2669:ffff:ffff:ffff:ffff])'],

//            ['ip:[2a02:5180:0:2669:0:0:0:0 TO 2a02:5180:0:2669:0:0:0:0]', '(ip:["2a02:5180:0:2669:0:0:0:0" TO "2a02:5180:0:2669:0:0:0:0"])'],
        ];




        foreach ($testQueries as $testQuery) {
            [$queryString, $rewrittenQuery] = $testQuery;
            $query = Search\QueryParser::parse($queryString);
//            var_dump($query);
            $this->assertTrue($query instanceof Query\AbstractQuery);
            $this->assertEquals( $rewrittenQuery, $query->__toString(),'Base query string:' . $queryString);
        }

        Query\Wildcard::setMinPrefixLength($wildcardMinPrefix);
        Query\Fuzzy::setDefaultPrefixLength($defaultPrefixLength);
    }

    public function testQueryParserExceptionsHandling()
    {
        $this->markTestSkipped();
        $this->assertTrue(Search\QueryParser::queryParsingExceptionsSuppressed());

        $query = Search\QueryParser::parse('contents:[business TO by}');

        $this->assertEquals('contents business TO by', $query->__toString());

        Search\QueryParser::dontSuppressQueryParsingExceptions();
        $this->assertFalse(Search\QueryParser::queryParsingExceptionsSuppressed());

        try {
            $query = Search\QueryParser::parse('contents:[business TO by}');

            $this->fail('exception wasn\'t raised while parsing a query');
        } catch (Lucene\Exception\ExceptionInterface $e) {
            $this->assertEquals('Syntax error at char position 25.', $e->getMessage());
        }


        Search\QueryParser::suppressQueryParsingExceptions();
        $this->assertTrue(Search\QueryParser::queryParsingExceptionsSuppressed());
    }



}
