<?php

/**
 * @see       https://github.com/laminas/laminas-http for the canonical source repository
 * @copyright https://github.com/laminas/laminas-http/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-http/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Http;

use Laminas\Http\Header;
use Laminas\Http\Headers;

class HeadersTest extends \PHPUnit_Framework_TestCase
{
    public function testHeadersImplementsProperClasses()
    {
        $headers = new Headers();
        $this->assertInstanceOf('Iterator', $headers);
        $this->assertInstanceOf('Countable', $headers);
    }

    public function testHeadersCanGetPluginClassLoader()
    {
        $headers = new Headers();
        $this->assertInstanceOf('Laminas\Http\HeaderLoader', $headers->getPluginClassLoader());
    }

    public function testHeadersFromStringFactoryCreatesSingleObject()
    {
        $headers = Headers::fromString("Fake: foo-bar");
        $this->assertEquals(1, $headers->count());

        $header = $headers->get('fake');
        $this->assertInstanceOf('Laminas\Http\Header\GenericHeader', $header);
        $this->assertEquals('Fake', $header->getFieldName());
        $this->assertEquals('foo-bar', $header->getFieldValue());
    }

    public function testHeadersFromStringFactoryCreatesSingleObjectWithContinuationLine()
    {
        $headers = Headers::fromString("Fake: foo-bar,\r\n      blah-blah");
        $this->assertEquals(1, $headers->count());

        $header = $headers->get('fake');
        $this->assertInstanceOf('Laminas\Http\Header\GenericHeader', $header);
        $this->assertEquals('Fake', $header->getFieldName());
        // any leading space MAY be replaced by a single space @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html
        $this->assertRegexp("#foo-bar,\r\n\s+blah-blah#", $header->getFieldValue());
    }

    public function testHeadersFromStringFactoryCreatesSingleObjectWithHeaderBreakLine()
    {
        $headers = Headers::fromString("Fake: foo-bar\r\n\r\n");
        $this->assertEquals(1, $headers->count());

        $header = $headers->get('fake');
        $this->assertInstanceOf('Laminas\Http\Header\GenericHeader', $header);
        $this->assertEquals('Fake', $header->getFieldName());
        $this->assertEquals('foo-bar', $header->getFieldValue());
    }

    public function testHeadersFromStringFactoryRespectsSpecAllowedMultiLineHeaders()
    {
        $headers = Headers::fromString("Foo: foo-bar\r\nX-Another: another\r\n X-Actually-A-Continuation:ofSomeKindOfValue\r\nX-Another: another\r\n");
        $this->assertEquals(3, $headers->count());

        // check continued header
        $header = $headers->get('X-Another');
        $this->assertEquals('X-Another', $header->getFieldName());
        $this->assertRegexp("#another\r\n\s+X-Actually-A-Continuation:ofSomeKindOfValue#", $header->getFieldValue());
    }

    public function testHeadersFromStringFactoryThrowsExceptionOnMalformedHeaderLine()
    {
        $this->setExpectedException('Laminas\Http\Exception\RuntimeException', 'does not match');
        Headers::fromString("Fake = foo-bar\r\n\r\n");
    }

    public function testHeadersFromStringFactoryCreatesMultipleObjects()
    {
        $headers = Headers::fromString("Fake: foo-bar\r\nAnother-Fake: boo-baz");
        $this->assertEquals(2, $headers->count());

        $header = $headers->get('fake');
        $this->assertInstanceOf('Laminas\Http\Header\GenericHeader', $header);
        $this->assertEquals('Fake', $header->getFieldName());
        $this->assertEquals('foo-bar', $header->getFieldValue());

        $header = $headers->get('anotherfake');
        $this->assertInstanceOf('Laminas\Http\Header\GenericHeader', $header);
        $this->assertEquals('Another-Fake', $header->getFieldName());
        $this->assertEquals('boo-baz', $header->getFieldValue());
    }

    public function testHeadersFromStringMultiHeaderWillAggregateLazyLoadedHeaders()
    {
        $headers = new Headers();
        /* @var $pcl \Laminas\Loader\PluginClassLoader */
        $pcl = $headers->getPluginClassLoader();
        $pcl->registerPlugin('foo', 'Laminas\Http\Header\GenericMultiHeader');
        $headers->addHeaderLine('foo: bar1,bar2,bar3');
        $headers->forceLoading();
        $this->assertEquals(3, $headers->count());
    }

    public function testHeadersHasAndGetWorkProperly()
    {
        $headers = new Headers();
        $headers->addHeaders(array($f = new Header\GenericHeader('Foo', 'bar'), new Header\GenericHeader('Baz', 'baz')));
        $this->assertFalse($headers->has('foobar'));
        $this->assertTrue($headers->has('foo'));
        $this->assertTrue($headers->has('Foo'));
        $this->assertSame($f, $headers->get('foo'));
    }

    public function testHeadersAggregatesHeaderObjects()
    {
        $fakeHeader = new Header\GenericHeader('Fake', 'bar');
        $headers = new Headers();
        $headers->addHeader($fakeHeader);
        $this->assertEquals(1, $headers->count());
        $this->assertSame($fakeHeader, $headers->get('Fake'));
    }

    public function testHeadersAggregatesHeaderThroughAddHeader()
    {
        $headers = new Headers();
        $headers->addHeader(new Header\GenericHeader('Fake', 'bar'));
        $this->assertEquals(1, $headers->count());
        $this->assertInstanceOf('Laminas\Http\Header\GenericHeader', $headers->get('Fake'));
    }

    public function testHeadersAggregatesHeaderThroughAddHeaderLine()
    {
        $headers = new Headers();
        $headers->addHeaderLine('Fake', 'bar');
        $this->assertEquals(1, $headers->count());
        $this->assertInstanceOf('Laminas\Http\Header\GenericHeader', $headers->get('Fake'));
    }

    public function testHeadersAddHeaderLineThrowsExceptionOnMissingFieldValue()
    {
        $this->setExpectedException('Laminas\Http\Exception\InvalidArgumentException', 'without a field');
        $headers = new Headers();
        $headers->addHeaderLine('Foo');
    }

    public function testHeadersAggregatesHeadersThroughAddHeaders()
    {
        $headers = new Headers();
        $headers->addHeaders(array(new Header\GenericHeader('Foo', 'bar'), new Header\GenericHeader('Baz', 'baz')));
        $this->assertEquals(2, $headers->count());
        $this->assertInstanceOf('Laminas\Http\Header\GenericHeader', $headers->get('Foo'));
        $this->assertEquals('bar', $headers->get('foo')->getFieldValue());
        $this->assertEquals('baz', $headers->get('baz')->getFieldValue());

        $headers = new Headers();
        $headers->addHeaders(array('Foo: bar', 'Baz: baz'));
        $this->assertEquals(2, $headers->count());
        $this->assertInstanceOf('Laminas\Http\Header\GenericHeader', $headers->get('Foo'));
        $this->assertEquals('bar', $headers->get('foo')->getFieldValue());
        $this->assertEquals('baz', $headers->get('baz')->getFieldValue());

        $headers = new Headers();
        $headers->addHeaders(array(array('Foo' => 'bar'), array('Baz' => 'baz')));
        $this->assertEquals(2, $headers->count());
        $this->assertInstanceOf('Laminas\Http\Header\GenericHeader', $headers->get('Foo'));
        $this->assertEquals('bar', $headers->get('foo')->getFieldValue());
        $this->assertEquals('baz', $headers->get('baz')->getFieldValue());

        $headers = new Headers();
        $headers->addHeaders(array(array('Foo', 'bar'), array('Baz', 'baz')));
        $this->assertEquals(2, $headers->count());
        $this->assertInstanceOf('Laminas\Http\Header\GenericHeader', $headers->get('Foo'));
        $this->assertEquals('bar', $headers->get('foo')->getFieldValue());
        $this->assertEquals('baz', $headers->get('baz')->getFieldValue());

        $headers = new Headers();
        $headers->addHeaders(array('Foo' => 'bar', 'Baz' => 'baz'));
        $this->assertEquals(2, $headers->count());
        $this->assertInstanceOf('Laminas\Http\Header\GenericHeader', $headers->get('Foo'));
        $this->assertEquals('bar', $headers->get('foo')->getFieldValue());
        $this->assertEquals('baz', $headers->get('baz')->getFieldValue());
    }

    public function testHeadersAddHeadersThrowsExceptionOnInvalidArguments()
    {
        $this->setExpectedException('Laminas\Http\Exception\InvalidArgumentException', 'Expected array or Trav');
        $headers = new Headers();
        $headers->addHeaders('foo');
    }

    public function testHeadersCanRemoveHeader()
    {
        $headers = new Headers();
        $headers->addHeaders(array('Foo' => 'bar', 'Baz' => 'baz'));
        $header = $headers->get('foo');
        $this->assertEquals(2, $headers->count());
        $headers->removeHeader($header);
        $this->assertEquals(1, $headers->count());
        $this->assertFalse($headers->get('foo'));
    }

    public function testHeadersCanClearAllHeaders()
    {
        $headers = new Headers();
        $headers->addHeaders(array('Foo' => 'bar', 'Baz' => 'baz'));
        $this->assertEquals(2, $headers->count());
        $headers->clearHeaders();
        $this->assertEquals(0, $headers->count());
    }

    public function testHeadersCanBeIterated()
    {
        $headers = new Headers();
        $headers->addHeaders(array('Foo' => 'bar', 'Baz' => 'baz'));
        $iterations = 0;
        /** @var \Laminas\Http\Header\HeaderInterface $header */
        foreach ($headers as $index => $header) {
            $iterations++;
            $this->assertInstanceOf('Laminas\Http\Header\GenericHeader', $header);
            switch ($index) {
                case 0:
                    $this->assertEquals('bar', $header->getFieldValue());
                    break;
                case 1:
                    $this->assertEquals('baz', $header->getFieldValue());
                    break;
                default:
                    $this->fail('Invalid index returned from iterator');
            }
        }
        $this->assertEquals(2, $iterations);
    }

    public function testHeadersCanBeCastToString()
    {
        $headers = new Headers();
        $headers->addHeaders(array('Foo' => 'bar', 'Baz' => 'baz'));
        $this->assertEquals('Foo: bar' . "\r\n" . 'Baz: baz' . "\r\n", $headers->toString());
    }

    public function testHeadersCanBeCastToArray()
    {
        $headers = new Headers();
        $headers->addHeaders(array('Foo' => 'bar', 'Baz' => 'baz'));
        $this->assertEquals(array('Foo' => 'bar', 'Baz' => 'baz'), $headers->toArray());
    }

    public function testCastingToArrayReturnsMultiHeadersAsArrays()
    {
        $headers = new Headers();
        $cookie1 = new Header\SetCookie('foo', 'bar');
        $cookie2 = new Header\SetCookie('bar', 'baz');
        $headers->addHeader($cookie1);
        $headers->addHeader($cookie2);
        $array   = $headers->toArray();
        $expected = array(
            'Set-Cookie' => array(
                $cookie1->getFieldValue(),
                $cookie2->getFieldValue(),
            ),
        );
        $this->assertEquals($expected, $array);
    }

    public function testCastingToStringReturnsAllMultiHeaderValues()
    {
        $headers = new Headers();
        $cookie1 = new Header\SetCookie('foo', 'bar');
        $cookie2 = new Header\SetCookie('bar', 'baz');
        $headers->addHeader($cookie1);
        $headers->addHeader($cookie2);
        $string  = $headers->toString();
        $expected = array(
            'Set-Cookie: ' . $cookie1->getFieldValue(),
            'Set-Cookie: ' . $cookie2->getFieldValue(),
        );
        $expected = implode("\r\n", $expected) . "\r\n";
        $this->assertEquals($expected, $string);
    }

    public function testZeroIsAValidHeaderValue()
    {
        $headers = Headers::fromString('Fake: 0');
        $this->assertSame('0', $headers->get('Fake')->getFieldValue());
    }

}
