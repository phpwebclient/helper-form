[![Latest Stable Version](https://img.shields.io/packagist/v/webclient/helper-form.svg?style=flat-square)](https://packagist.org/packages/webclient/helper-form)
[![Total Downloads](https://img.shields.io/packagist/dt/webclient/helper-form.svg?style=flat-square)](https://packagist.org/packages/webclient/helper-form/stats)
[![License](https://img.shields.io/packagist/l/webclient/helper-form.svg?style=flat-square)](https://github.com/phpwebclient/helper-form/blob/master/LICENSE)
[![PHP](https://img.shields.io/packagist/php-v/webclient/helper-form.svg?style=flat-square)](https://php.net)

# webclient/helper-form

Helper for creating PSR-7 request with files.

# Install

Install this package and your favorite [psr-17 implementation](https://packagist.org/providers/psr/http-factory-implementation).

```bash
composer require webclient/helper-form:^1.0
```

> Now it’s very easy to create file requests for PSR-18 HTTP Clients!

# Using

```php
<?php

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Webclient\Helper\Form\Wizard;

/**
 * @var RequestFactoryInterface $requestFactory
 * @var StreamFactoryInterface $streamFactory
 */
$wizard = new Wizard($requestFactory, $streamFactory);

$fh = fopen('/home/user42/.ssh/id_psa.pub', 'r+');

/** @var RequestInterface $request */
$request = $wizard
    ->createForm('http://localhost:8080/path?query=webclient#fragment', 'POST')
    ->addField('sign_up[login]', 'user42')
    ->addField('sign_up[password]', '$ecr3t')
    ->uploadFromString('about', 'hi!', 'about.txt', 'text/plain; charset=UTF-8')
    ->uploadFromFile('photo', '/home/user42/images/DCIM_4564.JPEG', 'image/jpeg', 'avatar.jpg')
    ->uploadFromResource('public_ssh_key', $fh, 'id_sra.pub', 'text/plain')
    ->createRequest()
;
```
