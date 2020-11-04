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
use ZendSearch\Lucene\Analysis\Analyzer\AnalyzerInterface;
use ZendSearch\Lucene\Analysis\Analyzer\Common;
use ZendSearch\Lucene\Analysis\Analyzer\Common\Text;
use ZendSearch\Lucene\Analysis\Analyzer\Common\TextNum;
use ZendSearch\Lucene\Analysis\Analyzer\Common\Utf8;
use ZendSearch\Lucene\Analysis\Analyzer\Common\Utf8Num;
use PHPUnit\Framework\TestCase;
use ZendSearch\Lucene\Analysis\TokenFilter\ShortWords;
use ZendSearch\Lucene\Analysis\TokenFilter\StopWords;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage UnitTests
 * @group      Zend_Search_Lucene
 */
class AnalysisTest extends TestCase
{
    public function testAnalyzer()
    {
        $currentAnalyzer = Analyzer::getDefault();
        $this->assertTrue($currentAnalyzer instanceof AnalyzerInterface);

        /** Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num */

        $newAnalyzer = new Common\Utf8Num();
        Analyzer::setDefault($newAnalyzer);
        $this->assertSame(Analyzer::getDefault(), $newAnalyzer);

        // Set analyzer to the default value (used in other tests)
        Analyzer::setDefault($currentAnalyzer);
    }

    public function testText()
    {
        /** Zend_Search_Lucene_Analysis_Analyzer_Common_Text */

        $analyzer = new Common\Text();

        $tokenList = $analyzer->tokenize('Word1 Word2 anotherWord');

        $this->assertCount(3, $tokenList);

        $this->assertEquals('Word', $tokenList[0]->getTermText());
        $this->assertEquals(0, $tokenList[0]->getStartOffset());
        $this->assertEquals(4, $tokenList[0]->getEndOffset());
        $this->assertEquals(1, $tokenList[0]->getPositionIncrement());

        $this->assertEquals('Word', $tokenList[1]->getTermText());
        $this->assertEquals(6, $tokenList[1]->getStartOffset());
        $this->assertEquals(10, $tokenList[1]->getEndOffset());
        $this->assertEquals(1, $tokenList[1]->getPositionIncrement());

        $this->assertEquals('anotherWord', $tokenList[2]->getTermText());
        $this->assertEquals(12, $tokenList[2]->getStartOffset());
        $this->assertEquals(23, $tokenList[2]->getEndOffset());
        $this->assertEquals(1, $tokenList[2]->getPositionIncrement());
    }

    public function testTextCaseInsensitive()
    {
        /** Zend_Search_Lucene_Analysis_Analyzer_Common_Text_CaseInsensitive */

        $analyzer = new Text\CaseInsensitive();

        $tokenList = $analyzer->tokenize('Word1 Word2 anotherWord');

        $this->assertCount(3, $tokenList);

        $this->assertEquals('word', $tokenList[0]->getTermText());
        $this->assertEquals(0, $tokenList[0]->getStartOffset());
        $this->assertEquals(4, $tokenList[0]->getEndOffset());
        $this->assertEquals(1, $tokenList[0]->getPositionIncrement());

        $this->assertEquals('word', $tokenList[1]->getTermText());
        $this->assertEquals(6, $tokenList[1]->getStartOffset());
        $this->assertEquals(10, $tokenList[1]->getEndOffset());
        $this->assertEquals(1, $tokenList[1]->getPositionIncrement());

        $this->assertEquals('anotherword', $tokenList[2]->getTermText());
        $this->assertEquals(12, $tokenList[2]->getStartOffset());
        $this->assertEquals(23, $tokenList[2]->getEndOffset());
        $this->assertEquals(1, $tokenList[2]->getPositionIncrement());
    }

    public function testTextNum()
    {
        /** Zend_Search_Lucene_Analysis_Analyzer_Common_TextNum */

        $analyzer = new Common\TextNum();

        $tokenList = $analyzer->tokenize('Word1 Word2 anotherWord');

        $this->assertCount(3, $tokenList);

        $this->assertEquals('Word1', $tokenList[0]->getTermText());
        $this->assertEquals(0, $tokenList[0]->getStartOffset());
        $this->assertEquals(5, $tokenList[0]->getEndOffset());
        $this->assertEquals(1, $tokenList[0]->getPositionIncrement());

        $this->assertEquals('Word2', $tokenList[1]->getTermText());
        $this->assertEquals(6, $tokenList[1]->getStartOffset());
        $this->assertEquals(11, $tokenList[1]->getEndOffset());
        $this->assertEquals(1, $tokenList[1]->getPositionIncrement());

        $this->assertEquals('anotherWord', $tokenList[2]->getTermText());
        $this->assertEquals(12, $tokenList[2]->getStartOffset());
        $this->assertEquals(23, $tokenList[2]->getEndOffset());
        $this->assertEquals(1, $tokenList[2]->getPositionIncrement());
    }

    public function testTextNumCaseInsensitive()
    {
        /** Zend_Search_Lucene_Analysis_Analyzer_Common_TextNum_CaseInsensitive */

        $analyzer = new TextNum\CaseInsensitive();

        $tokenList = $analyzer->tokenize('Word1 Word2 anotherWord');

        $this->assertCount(3, $tokenList);

        $this->assertEquals('word1', $tokenList[0]->getTermText());
        $this->assertEquals(0, $tokenList[0]->getStartOffset());
        $this->assertEquals(5, $tokenList[0]->getEndOffset());
        $this->assertEquals(1, $tokenList[0]->getPositionIncrement());

        $this->assertEquals('word2', $tokenList[1]->getTermText());
        $this->assertEquals(6, $tokenList[1]->getStartOffset());
        $this->assertEquals(11, $tokenList[1]->getEndOffset());
        $this->assertEquals(1, $tokenList[1]->getPositionIncrement());

        $this->assertEquals('anotherword', $tokenList[2]->getTermText());
        $this->assertEquals(12, $tokenList[2]->getStartOffset());
        $this->assertEquals(23, $tokenList[2]->getEndOffset());
        $this->assertEquals(1, $tokenList[2]->getPositionIncrement());
    }

    public function testUtf8()
    {
        if (@preg_match('/\pL/u', 'a') != 1) {
            // PCRE unicode support is turned off
            return;
        }

        /** Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8 */

        $analyzer = new Common\Utf8();

        // UTF-8 text with a cyrillic symbols
        $tokenList = $analyzer->tokenize('Слово1 Слово2 ДругоеСлово', 'UTF-8');

        $this->assertCount(3, $tokenList);

        $this->assertEquals('Слово', $tokenList[0]->getTermText());
        $this->assertEquals(0, $tokenList[0]->getStartOffset());
        $this->assertEquals(5, $tokenList[0]->getEndOffset());
        $this->assertEquals(1, $tokenList[0]->getPositionIncrement());

        $this->assertEquals('Слово', $tokenList[1]->getTermText());
        $this->assertEquals(7, $tokenList[1]->getStartOffset());
        $this->assertEquals(12, $tokenList[1]->getEndOffset());
        $this->assertEquals(1, $tokenList[1]->getPositionIncrement());

        $this->assertEquals('ДругоеСлово', $tokenList[2]->getTermText());
        $this->assertEquals(14, $tokenList[2]->getStartOffset());
        $this->assertEquals(25, $tokenList[2]->getEndOffset());
        $this->assertEquals(1, $tokenList[2]->getPositionIncrement());
    }

    public function testUtf8Num()
    {
        if (@preg_match('/\pL/u', 'a') != 1) {
            // PCRE unicode support is turned off
            return;
        }

        /** Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num */

        $analyzer = new Common\Utf8Num();

        // UTF-8 text with a cyrillic symbols
        $tokenList = $analyzer->tokenize('Слово1 Слово2 ДругоеСлово', 'UTF-8');

        $this->assertCount(3, $tokenList);

        $this->assertEquals('Слово1', $tokenList[0]->getTermText());
        $this->assertEquals(0, $tokenList[0]->getStartOffset());
        $this->assertEquals(6, $tokenList[0]->getEndOffset());
        $this->assertEquals(1, $tokenList[0]->getPositionIncrement());

        $this->assertEquals('Слово2', $tokenList[1]->getTermText());
        $this->assertEquals(7, $tokenList[1]->getStartOffset());
        $this->assertEquals(13, $tokenList[1]->getEndOffset());
        $this->assertEquals(1, $tokenList[1]->getPositionIncrement());

        $this->assertEquals('ДругоеСлово', $tokenList[2]->getTermText());
        $this->assertEquals(14, $tokenList[2]->getStartOffset());
        $this->assertEquals(25, $tokenList[2]->getEndOffset());
        $this->assertEquals(1, $tokenList[2]->getPositionIncrement());
    }

    public function testUtf8CaseInsensitive()
    {
        if (@preg_match('/\pL/u', 'a') != 1) {
            // PCRE unicode support is turned off
            return;
        }
        if (!function_exists('mb_strtolower')) {
            // mbstring extension is disabled
            return;
        }

        /** Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8_CaseInsensitive */

        $analyzer = new Utf8\CaseInsensitive();

        // UTF-8 text with a cyrillic symbols
        $tokenList = $analyzer->tokenize('Слово1 Слово2 ДругоеСлово', 'UTF-8');

        $this->assertCount(3, $tokenList);

        $this->assertEquals('слово', $tokenList[0]->getTermText());
        $this->assertEquals(0, $tokenList[0]->getStartOffset());
        $this->assertEquals(5, $tokenList[0]->getEndOffset());
        $this->assertEquals(1, $tokenList[0]->getPositionIncrement());

        $this->assertEquals('слово', $tokenList[1]->getTermText());
        $this->assertEquals(7, $tokenList[1]->getStartOffset());
        $this->assertEquals(12, $tokenList[1]->getEndOffset());
        $this->assertEquals(1, $tokenList[1]->getPositionIncrement());

        $this->assertEquals('другоеслово', $tokenList[2]->getTermText());
        $this->assertEquals(14, $tokenList[2]->getStartOffset());
        $this->assertEquals(25, $tokenList[2]->getEndOffset());
        $this->assertEquals(1, $tokenList[2]->getPositionIncrement());
    }

    public function testUtf8NumCaseInsensitive()
    {
        if (@preg_match('/\pL/u', 'a') != 1) {
            // PCRE unicode support is turned off
            return;
        }
        if (!function_exists('mb_strtolower')) {
            // mbstring extension is disabled
            return;
        }

        /** Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num_CaseInsensitive */
        $analyzer = new Utf8Num\CaseInsensitive();

        // UTF-8 text with a cyrillic symbols
        $tokenList = $analyzer->tokenize('Слово1 Слово2 ДругоеСлово', 'UTF-8');

        $this->assertCount(3, $tokenList);

        $this->assertEquals('слово1', $tokenList[0]->getTermText());
        $this->assertEquals(0, $tokenList[0]->getStartOffset());
        $this->assertEquals(6, $tokenList[0]->getEndOffset());
        $this->assertEquals(1, $tokenList[0]->getPositionIncrement());

        $this->assertEquals('слово2', $tokenList[1]->getTermText());
        $this->assertEquals(7, $tokenList[1]->getStartOffset());
        $this->assertEquals(13, $tokenList[1]->getEndOffset());
        $this->assertEquals(1, $tokenList[1]->getPositionIncrement());

        $this->assertEquals('другоеслово', $tokenList[2]->getTermText());
        $this->assertEquals(14, $tokenList[2]->getStartOffset());
        $this->assertEquals(25, $tokenList[2]->getEndOffset());
        $this->assertEquals(1, $tokenList[2]->getPositionIncrement());
    }

    public function testEncoding()
    {
        if (PHP_OS == 'AIX') {
            $this->markTestSkipped('Test not available on AIX');
        }

        /** Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8 */

        $analyzer = new Common\Utf8();

        // UTF-8 text with a cyrillic symbols
        $tokenList = $analyzer->tokenize(
            iconv('UTF-8', 'Windows-1251', 'Слово1 Слово2 ДругоеСлово'),
            'Windows-1251'
        );

        $this->assertCount(3, $tokenList);

        $this->assertEquals('Слово', $tokenList[0]->getTermText());
        $this->assertEquals(0, $tokenList[0]->getStartOffset());
        $this->assertEquals(5, $tokenList[0]->getEndOffset());
        $this->assertEquals(1, $tokenList[0]->getPositionIncrement());

        $this->assertEquals('Слово', $tokenList[1]->getTermText());
        $this->assertEquals(7, $tokenList[1]->getStartOffset());
        $this->assertEquals(12, $tokenList[1]->getEndOffset());
        $this->assertEquals(1, $tokenList[1]->getPositionIncrement());

        $this->assertEquals('ДругоеСлово', $tokenList[2]->getTermText());
        $this->assertEquals(14, $tokenList[2]->getStartOffset());
        $this->assertEquals(25, $tokenList[2]->getEndOffset());
        $this->assertEquals(1, $tokenList[2]->getPositionIncrement());
    }

    public function testStopWords()
    {
        /** Zend_Search_Lucene_Analysis_Analyzer_Common_Text_CaseInsensitive */

        /** Zend_Search_Lucene_Analysis_TokenFilter_StopWords */

        $analyzer = new Text\CaseInsensitive();
        $stopWordsFilter = new StopWords(['word', 'and', 'or']);

        $analyzer->addFilter($stopWordsFilter);

        $tokenList = $analyzer->tokenize('Word1 Word2 anotherWord');

        $this->assertCount(1, $tokenList);

        $this->assertEquals('anotherword', $tokenList[0]->getTermText());
        $this->assertEquals(12, $tokenList[0]->getStartOffset());
        $this->assertEquals(23, $tokenList[0]->getEndOffset());
        $this->assertEquals(1, $tokenList[0]->getPositionIncrement());
    }

    public function testShortWords()
    {
        /** Zend_Search_Lucene_Analysis_Analyzer_Common_Text_CaseInsensitive */

        /** Zend_Search_Lucene_Analysis_TokenFilter_ShortWords */

        $analyzer = new Text\CaseInsensitive();
        $stopWordsFilter = new ShortWords(4 /* Minimal length */);

        $analyzer->addFilter($stopWordsFilter);

        $tokenList = $analyzer->tokenize('Word1 and anotherWord');

        $this->assertCount(2, $tokenList);

        $this->assertEquals('word', $tokenList[0]->getTermText());
        $this->assertEquals(0, $tokenList[0]->getStartOffset());
        $this->assertEquals(4, $tokenList[0]->getEndOffset());
        $this->assertEquals(1, $tokenList[0]->getPositionIncrement());

        $this->assertEquals('anotherword', $tokenList[1]->getTermText());
        $this->assertEquals(10, $tokenList[1]->getStartOffset());
        $this->assertEquals(21, $tokenList[1]->getEndOffset());
        $this->assertEquals(1, $tokenList[1]->getPositionIncrement());
    }
}
