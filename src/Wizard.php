<?php

declare(strict_types=1);

namespace Webclient\Helper\Form;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class Wizard
{

    /**
     * @var RequestFactoryInterface
     */
    private $requestFactory;

    /**
     * @var StreamFactoryInterface
     */
    private $streamFactory;

    public function __construct(RequestFactoryInterface $requestFactory, StreamFactoryInterface $streamFactory)
    {
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
    }

    public function createForm(string $uri, string $method = 'GET'): Form
    {
        return new Form($this->requestFactory, $this->streamFactory, $uri, $method);
    }
}
