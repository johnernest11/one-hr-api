<?php

namespace Tests\Unit;

use Tests\TestCase;
use TheIconic\NameParser\Name;
use TheIconic\NameParser\Parser;

class CustomNameParserTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app = $this->createApplication();
    }

    public static function provider()
    {
        return [
            [
                'Maria Teresa Cruz',
                [
                    'firstname' => 'Maria',
                    'middlename' => 'Teresa',
                    'lastname' => 'Cruz',
                ],
            ],
            [
                'Juan Carlos Dela Peña',
                [
                    'firstname' => 'Juan',
                    'middlename' => 'Carlos',
                    'lastname' => 'dela Peña',
                ],
            ],
            [
                'Angela Marie Delos Santos De Vera',
                [
                    'firstname' => 'Angela Marie',
                    'middlename' => 'delos Santos',
                    'lastname' => 'de Vera',
                ],
            ],
            [
                'Roberto Antonio Castillo Jr.',
                [
                    'firstname' => 'Roberto',
                    'middlename' => 'Antonio',
                    'lastname' => 'Castillo',
                    'suffix' => 'Jr',
                ],
            ],
            [
                'Miguel Andres C. San Jose IV',
                [
                    'firstname' => 'Miguel Andres',
                    'initials' => 'C.',
                    'lastname' => 'san Jose',
                    'suffix' => 'IV',
                ],
            ],
        ];
    }

    /**
     * @dataProvider provider
     */
    public function testParse($input, $expectation)
    {
        $parser = $this->app->make(Parser::class);
        $name = $parser->parse($input);

        $this->assertInstanceOf(Name::class, $name);
        $this->assertEquals($expectation, $name->getAll());
    }
}
