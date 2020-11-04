<?php

declare(strict_types=1);

namespace Tests\Webclient\Helper\Form;

use PHPUnit\Framework\TestCase;
use Stuff\Webclient\Helper\Form\HttpFactory;
use Webclient\Helper\Form\Form;
use Webclient\Helper\Form\Wizard;

class WizardTest extends TestCase
{

    /**
     * @param string $method
     * @param string $uri
     * @dataProvider provideCreateForm
     */
    public function testCreateForm(string $method, string $uri)
    {
        $factory = new HttpFactory();
        $wizard = new Wizard($factory, $factory);
        $form = $wizard->createForm($uri, $method);
        $this->assertInstanceOf(Form::class, $form);
        $request = $form->createRequest();
        $this->assertSame($method, $request->getMethod());
        $this->assertSame($uri, $request->getUri()->__toString());
    }

    public function provideCreateForm()
    {
        return [
            ['GET', 'http://localhost:8000/path?query=yes#fragment'],
            ['HEAD', 'http://localhost:8000/path?query=yes#fragment'],
            ['OPTIONS', 'http://localhost:8000/path?query=yes#fragment'],
            ['POST', 'http://localhost:8000/path?query=yes#fragment'],
            ['PUT', 'http://localhost:8000/path?query=yes'],
            ['PATCH', 'http://localhost:8000/path'],
            ['DELETE', 'http://localhost:8000'],
            ['TRACE', 'http://localhost'],
        ];
    }
}
