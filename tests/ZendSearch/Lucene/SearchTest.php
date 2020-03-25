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

        $queries = array('title:"The Right Way" AND text:go',
                         'title:"Do it right" AND right',
                         'title:Do it right',
                         'te?t',
                         'test*',
                         'te*t',
                         '?Ma*',
                         // 'te?t~20^0.8',
                         'test~',
                         'test~0.4',
                         '"jakarta apache"~10',
                         'contents:[business TO by]',
                         '{wish TO zzz}',
                         'jakarta apache',
                         'jakarta^4 apache',
                         '"jakarta apache"^4 "Apache Lucene"',
                         '"jakarta apache" jakarta',
                         '"jakarta apache" OR jakarta',
                         '"jakarta apache" || jakarta',
                         '"jakarta apache" AND "Apache Lucene"',
                         '"jakarta apache" && "Apache Lucene"',
                         '+jakarta apache',
                         '"jakarta apache" AND NOT "Apache Lucene"',
                         '"jakarta apache" && !"Apache Lucene"',
                         '\\ ',
                         'NOT "jakarta apache"',
                         '!"jakarta apache"',
                         '"jakarta apache" -"Apache Lucene"',
                         '(jakarta OR apache) AND website',
                         '(jakarta || apache) && website',
                         'title:(+return +"pink panther")',
                         'title:(+re\\turn\\ value +"pink panther\\"" +body:cool)',
                         '+contents:apache +type:1 +id:5',
                         'contents:apache AND type:1 AND id:5',
                         'f1:word1 f1:word2 and f1:word3',
                         'f1:word1 not f1:word2 and f1:word3'
                         );

        $rewrittenQueries = array('+(title:"the right way") +(text:go)',
                                  '+(title:"do it right") +(path:right modified:right contents:right)',
                                  '(title:do) (path:it modified:it contents:it) (path:right modified:right contents:right)',
                                  '(contents:test contents:text)',
                                  '(contents:test contents:tested)',
                                  '(contents:test contents:text)',
                                  '(contents:amazon contents:email)',
                                  // ....
                                  '((contents:test) (contents:text^0.5))',
                                  '((contents:test) (contents:text^0.5833) (contents:latest^0.1667) (contents:left^0.1667) (contents:list^0.1667) (contents:meet^0.1667) (contents:must^0.1667) (contents:next^0.1667) (contents:post^0.1667) (contents:sect^0.1667) (contents:task^0.1667) (contents:tested^0.1667) (contents:that^0.1667) (contents:tort^0.1667))',
                                  '((path:"jakarta apache"~10) (modified:"jakarta apache"~10) (contents:"jakarta apache"~10))',
                                  '(contents:business contents:but contents:buy contents:buying contents:by)',
                                  '(path:wishlist contents:wishlist contents:wishlists contents:with contents:without contents:won contents:work contents:would contents:write contents:writing contents:written contents:www contents:xml contents:xmlrpc contents:you contents:your)',
                                  '(path:jakarta modified:jakarta contents:jakarta) (path:apache modified:apache contents:apache)',
                                  '((path:jakarta modified:jakarta contents:jakarta)^4) (path:apache modified:apache contents:apache)',
                                  '(((path:"jakarta apache") (modified:"jakarta apache") (contents:"jakarta apache"))^4) ((path:"apache lucene") (modified:"apache lucene") (contents:"apache lucene"))',
                                  '((path:"jakarta apache") (modified:"jakarta apache") (contents:"jakarta apache")) (path:jakarta modified:jakarta contents:jakarta)',
                                  '((path:"jakarta apache") (modified:"jakarta apache") (contents:"jakarta apache")) (path:jakarta modified:jakarta contents:jakarta)',
                                  '((path:"jakarta apache") (modified:"jakarta apache") (contents:"jakarta apache")) (path:jakarta modified:jakarta contents:jakarta)',
                                  '+((path:"jakarta apache") (modified:"jakarta apache") (contents:"jakarta apache")) +((path:"apache lucene") (modified:"apache lucene") (contents:"apache lucene"))',
                                  '+((path:"jakarta apache") (modified:"jakarta apache") (contents:"jakarta apache")) +((path:"apache lucene") (modified:"apache lucene") (contents:"apache lucene"))',
                                  '+(path:jakarta modified:jakarta contents:jakarta) (path:apache modified:apache contents:apache)',
                                  '+((path:"jakarta apache") (modified:"jakarta apache") (contents:"jakarta apache")) -((path:"apache lucene") (modified:"apache lucene") (contents:"apache lucene"))',
                                  '+((path:"jakarta apache") (modified:"jakarta apache") (contents:"jakarta apache")) -((path:"apache lucene") (modified:"apache lucene") (contents:"apache lucene"))',
                                  '(<InsignificantQuery>)',
                                  '<InsignificantQuery>',
                                  '<InsignificantQuery>',
                                  '((path:"jakarta apache") (modified:"jakarta apache") (contents:"jakarta apache")) -((path:"apache lucene") (modified:"apache lucene") (contents:"apache lucene"))',
                                  '+((path:jakarta modified:jakarta contents:jakarta) (path:apache modified:apache contents:apache)) +(path:website modified:website contents:website)',
                                  '+((path:jakarta modified:jakarta contents:jakarta) (path:apache modified:apache contents:apache)) +(path:website modified:website contents:website)',
                                  '(+(title:return) +(title:"pink panther"))',
                                  '(+(+title:return +title:value) +(title:"pink panther") +(body:cool))',
                                  '+(contents:apache) +(<InsignificantQuery>) +(<InsignificantQuery>)',
                                  '+(contents:apache) +(<InsignificantQuery>) +(<InsignificantQuery>)',
                                  '(f1:word) (+(f1:word) +(f1:word))',
                                  '(f1:word) (-(f1:word) +(f1:word))');



        foreach ($queries as $id => $queryString) {
            $query = Search\QueryParser::parse($queryString);

            $this->assertTrue($query instanceof Query\AbstractQuery);
            $this->assertEquals($query->__toString(), $rewrittenQueries[$id]);
        }

        Query\Wildcard::setMinPrefixLength($wildcardMinPrefix);
        Query\Fuzzy::setDefaultPrefixLength($defaultPrefixLength);
    }

    public function testQueryParserExceptionsHandling()
    {
        $this->assertTrue(Search\QueryParser::queryParsingExceptionsSuppressed());

        $query = Search\QueryParser::parse('contents:[business TO by}');

        $this->assertEquals('contents business to by', $query->__toString());

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
