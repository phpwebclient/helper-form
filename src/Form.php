<?php

declare(strict_types=1);

namespace Webclient\Helper\Form;

use InvalidArgumentException;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

use function addslashes;
use function array_key_exists;
use function array_replace;
use function array_shift;
use function fclose;
use function feof;
use function fopen;
use function fread;
use function fwrite;
use function http_build_query;
use function is_resource;
use function md5;
use function parse_str;
use function parse_url;
use function pathinfo;
use function preg_replace;
use function rand;
use function rewind;
use function strlen;
use function strpos;
use function substr;
use function urlencode;

final class Form
{

    /**
     * @var RequestFactoryInterface
     */
    private $requestFactory;

    /**
     * @var StreamFactoryInterface
     */
    private $streamFactory;

    /**
     * @var string
     */
    private $method;

    /**
     * @var string
     */
    private $uri;

    private $idx;

    /**
     * @var string[][]
     */
    private $fields = [];

    /**
     * @var array[][]
     */
    private $files = [];

    /**
     * @var string
     */
    private $boundary;

    /**
     * @param RequestFactoryInterface $requestFactory
     * @param StreamFactoryInterface $streamFactory
     * @param string $uri
     * @param string $method
     */
    public function __construct(
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        string $uri,
        string $method = 'GET'
    ) {
        $this->checkUri($uri);
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->uri = $uri;
        $this->method = $method;
        $this->idx = [
            'fields' => [],
            'files' => [],
        ];
    }

    /**
     * @param string $field
     * @param string $value
     * @return $this
     */
    public function addField(string $field, string $value): self
    {
        $field = $this->prepareField($field, 'field');
        $this->fields[$field][] = $value;
        return $this;
    }

    /**
     * @param string $field
     * @param string $content
     * @param string $filename
     * @param string $mime
     * @return $this
     */
    public function uploadFromString(string $field, string $content, string $filename, string $mime = ''): self
    {
        if (!$mime) {
            $mime = mime($content);
        }
        $resource = fopen('php://temp', 'rw+');
        fwrite($resource, $content);
        return $this->uploadFromResource($field, $resource, $filename, $mime);
    }

    /**
     * @param string $field
     * @param string $path
     * @param string $mime
     * @param string $filename
     * @return $this
     */
    public function uploadFromFile(string $field, string $path, string $mime = '', string $filename = ''): self
    {
        if (!$filename) {
            $filename = pathinfo($path, PATHINFO_BASENAME);
        }
        if (!$mime) {
            $mime = mime($path);
        }
        return $this->uploadFromResource($field, fopen($path, 'rb+'), $filename, $mime);
    }

    /**
     * @param string $field
     * @param resource $resource
     * @param string $filename
     * @param string $mime
     * @return $this
     */
    public function uploadFromResource(string $field, $resource, string $filename, string $mime = ''): self
    {
        if (!is_resource($resource)) {
            throw new InvalidArgumentException('parameter $resource must be a resource');
        }
        if (!$mime) {
            rewind($resource);
            $mime = mime(fread($resource, 4096));
            rewind($resource);
        }
        if ($mime === 'application/octet-stream') {
            $mime = mime($filename);
        }
        $field = $this->prepareField($field, 'field');
        $this->files[$field][] = [
            'name' => $filename,
            'mime' => $mime,
            'resource' => $resource,
        ];
        return $this;
    }

    /**
     * @return RequestInterface
     */
    public function createRequest(): RequestInterface
    {
        $resource = fopen('php://temp', 'rw+');
        $queryMethods = ['GET', 'HEAD', 'OPTIONS'];
        if (empty($this->files) || in_array($this->method, $queryMethods)) {
            $contentType = 'application/x-www-form-urlencoded';
            $this->fillSimple($resource);
        } else {
            $this->prepareBoundary();
            $contentType = 'multipart/form-data; boundary="' . $this->boundary . '"';
            $this->fillMultipart($resource);
        }
        $body = null;
        $uri = $this->uri;
        if (in_array($this->method, $queryMethods)) {
            $contentType = null;
            rewind($resource);
            $line = '';
            while (!feof($resource)) {
                $line .= fread($resource, 1024);
            }
            $query = [];
            parse_str($line, $query);
            list($uri, $fragment) = array_replace(['', ''], explode('#', $uri));
            list($uri, $temp) = array_replace(['', ''], explode('?', $uri));
            $get = [];
            parse_str($temp, $get);
            $query = array_replace($get, $query);
            if ($query) {
                $uri .= '?' . http_build_query($query);
            }
            if ($fragment) {
                $uri .= '#' . $fragment;
            }
            fclose($resource);
        } else {
            $body = $this->streamFactory->createStreamFromResource($resource);
            $body->rewind();
        }
        $request = $this->requestFactory->createRequest($this->method, $uri);
        if ($contentType) {
            $request = $request->withHeader('Content-Type', [$contentType]);
        }
        if ($body) {
            $request = $request
                ->withHeader('Content-Length', [(string)$body->getSize()])
                ->withBody($body)
            ;
        }
        return $request;
    }

    /**
     * @param string $field
     * @param string $type
     * @return string
     */
    private function prepareField(string $field, string $type): string
    {
        $field = preg_replace('/\[\s+/ui', '[', $field);
        $field = preg_replace('/\s+]/ui', ']', $field);
        $key = $type . 's';
        if (strpos($field, '[]') === false) {
            if (array_key_exists($field, $this->idx[$key])) {
                throw new InvalidArgumentException($type . ' ' . $field . ' already exists');
            }
            $this->idx[$key][$field] = true;
        }
        return $field;
    }

    /**
     * @param resource $body
     */
    private function fillMultipart($body)
    {
        $eol = "\r\n";
        fwrite($body, $eol);
        foreach ($this->fields as $field => $values) {
            foreach ($values as $value) {
                fwrite($body, '--' . $this->boundary . $eol);
                fwrite($body, 'Content-Disposition: form-data; name="' . addslashes($field) . '"' . $eol);
                fwrite($body, $eol . $value . $eol);
            }
        }

        foreach ($this->files as $field => $values) {
            foreach ($values as $value) {
                $disposition = 'Content-Disposition: form-data; name="' . addslashes($field) . '"; ';
                $disposition .= 'filename="' . addslashes($value['name']) . '"';

                fwrite($body, '--' . $this->boundary . $eol);
                fwrite($body, $disposition . $eol);
                fwrite($body, 'Content-Type: ' . $value['mime'] . $eol . $eol);
                rewind($value['resource']);
                while (!feof($value['resource'])) {
                    fwrite($body, fread($value['resource'], 1024));
                }
                fwrite($body, $eol);
            }
        }
        fwrite($body, '--' . $this->boundary . '--' . $eol);
    }

    /**
     * @param resource $body
     */
    private function fillSimple($body)
    {
        $result = '';
        foreach ($this->fields as $field => $values) {
            foreach ($values as $value) {
                $result .= urlencode($field) . '=' . urlencode($value) . '&';
            }
        }
        if (!$result) {
            return;
        }
        fwrite($body, substr($result, 0, -1));
    }

    private function prepareBoundary()
    {
        $generate = false;
        do {
            $boundary = md5((string)rand(0, PHP_INT_MAX));
            foreach ($this->fields as $field => $values) {
                if ($generate) {
                    continue;
                }
                if ($this->isContainBoundary($field, $boundary)) {
                    $generate = true;
                    continue;
                }
                foreach ($values as $value) {
                    if ($generate) {
                        continue;
                    }
                    if ($this->isContainBoundary($value, $boundary)) {
                        $generate = true;
                        continue;
                    }
                }
            }
            foreach ($this->files as $field => $values) {
                if ($generate) {
                    continue;
                }
                if ($this->isContainBoundary($field, $boundary)) {
                    $generate = true;
                }
                foreach ($values as $value) {
                    if ($generate) {
                        continue;
                    }
                    if ($this->isContainBoundary($value['name'], $boundary)) {
                        $generate = true;
                        continue;
                    }
                    if ($this->isContainBoundary($value['mime'], $boundary)) {
                        $generate = true;
                        continue;
                    }
                    $resource = $value['resource'];
                    $lines = [''];
                    rewind($resource);
                    while (!feof($resource) && !$generate) {
                        $lines[] = fread($resource, 512);
                        $data = implode('', $lines);
                        if ($this->isContainBoundary($data, $boundary)) {
                            $generate = true;
                        }
                        array_shift($lines);
                    }
                    rewind($resource);
                }
            }
        } while ($generate);
        $this->boundary = $boundary;
    }

    private function isContainBoundary(string $data, string $boundary): bool
    {
        return strlen($data) > 33 && strpos($data, '--' . $boundary) !== false;
    }

    private function checkUri(string $uri)
    {
        $arr = parse_url($uri);
        if (
            !is_array($arr)
            || !array_key_exists('scheme', $arr)
            || !$arr['scheme']
            || !array_key_exists('host', $arr)
            || !$arr['host']
        ) {
            throw new InvalidArgumentException('uri must contains scheme and host');
        }
    }
}
