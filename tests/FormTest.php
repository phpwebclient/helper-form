<?php

declare(strict_types=1);

namespace Tests\Webclient\Helper\Form;

use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Webclient\Helper\Form\Form;

use function dirname;
use function file_get_contents;
use function fopen;
use function fwrite;
use function preg_match;

class FormTest extends TestCase
{

    /**
     * @var ResponseFactoryInterface
     */
    private $responseFactory;

    /**
     * @var StreamFactoryInterface
     */
    private $streamFactory;

    public function setUp()
    {
        parent::setUp();
        $factory = new Psr17Factory();
        $this->responseFactory = $factory;
        $this->streamFactory = $factory;
    }

    /**
     * @param string $method
     * @param string $uri
     * @param string $error
     * @dataProvider provideConstruct
     */
    public function testConstruct(string $method, string $uri, string $error)
    {
        if ($error) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage($error);
        }
        $form = $this->getForm($uri, $method);
        $this->assertInstanceOf(Form::class, $form);
        $request = $form->createRequest();
        $this->assertSame($method, $request->getMethod());
        $this->assertSame($uri, $request->getUri()->__toString());
    }

    public function testAddFieldPost()
    {
        $form = $this->getForm('http://localhost:8000/path?query=yes#fragment', 'POST');
        $form->addField('auth[user]', 'phpunit');
        $form->addField('auth[pass]', 'secret');
        $request = $form->createRequest();
        $this->assertSame('auth%5Buser%5D=phpunit&auth%5Bpass%5D=secret', $request->getBody()->__toString());
        $this->assertSame('http://localhost:8000/path?query=yes#fragment', $request->getUri()->__toString());
    }

    public function testUploadFromString()
    {
        $form = $this->getForm('http://localhost:8000/path?query=yes#fragment', 'POST');
        $content = 'hello, world!';
        $form->uploadFromString('files[]', $content, 'readme.txt', 'text/plain; charset=UTF-8');
        $request = $form->createRequest();
        $pattern = '/multipart\/form-data\s*;\s*boundary=\"(?<boundary>[a-f0-9]{32})\"/ui';
        preg_match($pattern, $request->getHeaderLine('Content-Type'), $matches);
        $this->assertArrayHasKey('boundary', $matches);
        $boundary = $matches['boundary'];
        $body = "\r\n--$boundary\r\nContent-Disposition: form-data; name=\"files[]\"; filename=\"readme.txt\"\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n$content\r\n--$boundary--\r\n";
        $this->assertSame($body, $request->getBody()->__toString());
        $this->assertSame('http://localhost:8000/path?query=yes#fragment', $request->getUri()->__toString());
    }

    public function testUploadFromFile()
    {
        $form = $this->getForm('http://localhost:8000/path?query=yes#fragment', 'POST');
        $file = dirname(__DIR__) . '/LICENSE';
        $content = file_get_contents($file);
        $form->uploadFromFile('files[]', $file, 'text/plain; charset=UTF-8');
        $request = $form->createRequest();
        $pattern = '/multipart\/form-data\s*;\s*boundary=\"(?<boundary>[a-f0-9]{32})\"/ui';
        preg_match($pattern, $request->getHeaderLine('Content-Type'), $matches);
        $this->assertArrayHasKey('boundary', $matches);
        $boundary = $matches['boundary'];
        $body = "\r\n--$boundary\r\nContent-Disposition: form-data; name=\"files[]\"; filename=\"LICENSE\"\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n$content\r\n--$boundary--\r\n";
        $this->assertSame($body, $request->getBody()->__toString());
        $this->assertSame('http://localhost:8000/path?query=yes#fragment', $request->getUri()->__toString());
    }

    public function testUploadFromResource()
    {
        $form = $this->getForm('http://localhost:8000/path?query=yes#fragment', 'POST');
        $content = 'hello, world!';
        $resource = fopen('php://temp', 'rw+');
        $form->uploadFromResource('files[]', $resource, 'readme.txt', 'text/plain; charset=UTF-8');
        fwrite($resource, $content);
        $request = $form->createRequest();
        $pattern = '/multipart\/form-data\s*;\s*boundary=\"(?<boundary>[a-f0-9]{32})\"/ui';
        preg_match($pattern, $request->getHeaderLine('Content-Type'), $matches);
        $this->assertArrayHasKey('boundary', $matches);
        $boundary = $matches['boundary'];
        $body = "\r\n--$boundary\r\nContent-Disposition: form-data; name=\"files[]\"; filename=\"readme.txt\"\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n$content\r\n--$boundary--\r\n";
        $this->assertSame($body, $request->getBody()->__toString());
        $this->assertSame('http://localhost:8000/path?query=yes#fragment', $request->getUri()->__toString());
    }

    public function testAllUploads()
    {
        $form = $this->getForm('http://localhost:8000/path?query=yes#fragment', 'POST');
        $string = 'hello, world! I am created from string!';
        $form->uploadFromString('files[]', $string, 'string.txt', 'text/plain; charset=UTF-8');

        $path = dirname(__DIR__) . '/LICENSE';
        $file = file_get_contents($path);
        $form->uploadFromFile('files[]', $path, 'text/plain; charset=UTF-8', 'file.txt');

        $resource = 'hello, world! I am created from resource!';
        $fh = fopen('php://temp', 'rw+');
        fwrite($fh, $resource);
        $form->uploadFromResource('files[]', $fh, 'resource.txt', 'text/plain; charset=UTF-8');

        $request = $form->createRequest();
        $pattern = '/multipart\/form-data\s*;\s*boundary=\"(?<boundary>[a-f0-9]{32})\"/ui';
        preg_match($pattern, $request->getHeaderLine('Content-Type'), $matches);
        $this->assertArrayHasKey('boundary', $matches);
        $boundary = $matches['boundary'];

        $body = "\r\n--$boundary\r\nContent-Disposition: form-data; name=\"files[]\"; filename=\"string.txt\"\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n$string\r\n";

        $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"files[]\"; filename=\"file.txt\"\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n$file\r\n";

        $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"files[]\"; filename=\"resource.txt\"\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n$resource\r\n";

        $body .= "--$boundary--\r\n";

        $this->assertSame($body, $request->getBody()->__toString());
        $this->assertSame('http://localhost:8000/path?query=yes#fragment', $request->getUri()->__toString());
    }

    public function testAllUploadsAndFieldsPost()
    {
        $form = $this->getForm('http://localhost:8000/path?query=yes#fragment', 'POST');

        $form->addField('auth[user]', 'phpunit');
        $form->addField('auth[pass]', 'secret');

        $string = 'hello, world! I am created from string!';
        $form->uploadFromString('files[]', $string, 'string.txt', 'text/plain; charset=UTF-8');

        $path = dirname(__DIR__) . '/LICENSE';
        $file = file_get_contents($path);
        $form->uploadFromFile('files[]', $path, 'text/plain; charset=UTF-8', 'file.txt');

        $resource = 'hello, world! I am created from resource!';
        $fh = fopen('php://temp', 'rw+');
        fwrite($fh, $resource);
        $form->uploadFromResource('files[]', $fh, 'resource.txt', 'text/plain; charset=UTF-8');

        $request = $form->createRequest();
        $pattern = '/multipart\/form-data\s*;\s*boundary=\"(?<boundary>[a-f0-9]{32})\"/ui';
        preg_match($pattern, $request->getHeaderLine('Content-Type'), $matches);
        $this->assertArrayHasKey('boundary', $matches);
        $boundary = $matches['boundary'];

        $body = "\r\n--$boundary\r\nContent-Disposition: form-data; name=\"auth[user]\"\r\n\r\nphpunit\r\n";
        $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"auth[pass]\"\r\n\r\nsecret\r\n";

        $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"files[]\"; filename=\"string.txt\"\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n$string\r\n";

        $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"files[]\"; filename=\"file.txt\"\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n$file\r\n";

        $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"files[]\"; filename=\"resource.txt\"\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n$resource\r\n";

        $body .= "--$boundary--\r\n";

        $this->assertSame($body, $request->getBody()->__toString());
        $this->assertSame('http://localhost:8000/path?query=yes#fragment', $request->getUri()->__toString());
    }

    public function testAllUploadsAndFieldsGet()
    {
        $form = $this->getForm('http://localhost:8000/path?query=yes#fragment', 'GET');

        $form->addField('auth[user]', 'phpunit');
        $form->addField('auth[pass]', 'secret');

        $string = 'hello, world! I am created from string!';
        $form->uploadFromString('files[]', $string, 'string.txt', 'text/plain; charset=UTF-8');

        $path = dirname(__DIR__) . '/LICENSE';
        $form->uploadFromFile('files[]', $path, 'text/plain; charset=UTF-8', 'file.txt');

        $resource = 'hello, world! I am created from resource!';
        $fh = fopen('php://temp', 'rw+');
        fwrite($fh, $resource);
        $form->uploadFromResource('files[]', $fh, 'resource.txt', 'text/plain; charset=UTF-8');

        $request = $form->createRequest();

        $this->assertSame('', $request->getBody()->__toString());
        $merged = 'http://localhost:8000/path?query=yes&auth%5Buser%5D=phpunit&auth%5Bpass%5D=secret#fragment';
        $this->assertSame($merged, $request->getUri()->__toString());
    }

    public function testAddFieldGet()
    {
        $form = $this->getForm('http://localhost:8000/path?query=yes#fragment', 'GET');
        $form->addField('auth[user]', 'phpunit');
        $form->addField('auth[pass]', 'secret');
        $request = $form->createRequest();
        $this->assertSame('', $request->getBody()->__toString());
        $merged = 'http://localhost:8000/path?query=yes&auth%5Buser%5D=phpunit&auth%5Bpass%5D=secret#fragment';
        $this->assertSame($merged, $request->getUri()->__toString());
    }

    public function testDoubleAddField()
    {
        $form = $this->getForm('http://localhost:8000/path?query=yes#fragment', 'GET');
        $form->addField('auth[user]', 'phpunit');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('field auth[user] already exists');
        $form->addField('auth[user]', 'phpunit');
    }

    public function testDoubleAddFieldArray()
    {
        $form = $this->getForm('http://localhost:8000/path?query=yes#fragment', 'GET');
        $form->addField('auth[user][]', 'phpunit');
        $form->addField('auth[user][]', 'phpunit');
        $this->assertTrue(true);
    }

    public function provideConstruct()
    {
        return [
            ['GET', 'http://localhost:8000/path?query=yes#fragment', ''],
            ['HEAD', 'http://localhost:8000/path?query=yes#fragment', ''],
            ['OPTIONS', 'http://localhost:8000/path?query=yes', ''],
            ['POST', 'http://localhost:8000/path', ''],
            ['PUT', '/path?query=yes#fragment', 'uri must contains scheme and host'],
            ['PATCH', 'http://localhost', ''],
            ['DELETE', 'localhost:8000', 'uri must contains scheme and host'],
            ['TRACE', 'http://', 'uri must contains scheme and host'],
        ];
    }

    private function getForm(string $uri, string $method): Form
    {
        return new Form($this->responseFactory, $this->streamFactory, $uri, $method);
    }
}
