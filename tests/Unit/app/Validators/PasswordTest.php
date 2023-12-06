<?php
/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/

namespace App\Validators;

use App\Validators\Password as PasswordValidator;
use Config;
use Illuminate\Contracts\Translation\Translator;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;
use Illuminate\Validation\Validator;

class PasswordTest extends TestCase
{
    public const LOWERCASE = 'abc';
    public const UPPERCASE = 'YYZ';
    public const DIGITS = '246';
    public const SYMBOLS = '+!#';
    public const UNDERSCORE = '_';

    public function testGetComplexityConfigurationGivenDefault()
    {
        $sut = new PasswordValidator();
        Config::set('cdash.password', []);

        $expected = [
            'complexity' => 0,
            'count' => 0,
        ];
        $actual = $sut->getComplexityConfiguration();
        $this::assertEquals($expected, $actual);
    }

    public function testGetComplexityConfigurationFavorsConfigurationOverDefault()
    {
        $sut = new PasswordValidator();
        $expected = config('cdash.password');
        $actual = $sut->getComplexityConfiguration();
        $this::assertEquals($expected, $actual);

        $complexity = 4;
        $count = 3;

        Config::set(
            'cdash.password',
            ['complexity' => $complexity, 'count' => $count]
        );

        $expected = [
            'complexity' => $complexity,
            'count' => $count,
        ];
        $actual = $sut->getComplexityConfiguration();
        $this::assertEquals($expected, $actual);
    }

    public function testGetComplexityFavorsArgumentsOverConfiguration()
    {
        $sut = new PasswordValidator();
        Config::set(
            'cdash.password',
            ['complexity' => 5, 'count' => 3]
        );

        $expected = [
            'complexity' => 5,
            'count' => 3,
        ];
        $actual = $sut->getComplexityConfiguration();
        $this::assertEquals($expected, $actual);

        $arguments = [
            'complexity' => 3,
            'count' => 2,
        ];

        // important, parameters are passed in without keys, so just send values here
        $actual = $sut->getComplexityConfiguration($arguments);
        $this::assertEquals($arguments, $actual);
    }

    public function testComplexityFailsGivenEmptyPassword()
    {
        $sut = new PasswordValidator();
        $attribute = 'email';
        $value = null;
        // array_values for readability's sake
        $parameters = array_values(['complexity' => 1, 'count' => 1]);

        $validator = $this->getMockValidator();
        $this::assertFalse($sut->complexity($attribute, $value, $parameters, $validator));

        $value = '';
        $this::assertFalse($sut->complexity($attribute, $value, $parameters, $validator));

        $value = 0;
        $this::assertFalse($sut->complexity($attribute, $value, $parameters, $validator));

        $value = '0';
        $this::assertFalse($sut->complexity($attribute, $value, $parameters, $validator));

        $value = false;
        $this::assertFalse($sut->complexity($attribute, $value, $parameters, $validator));

        $value = 'false';
        $this::assertTrue($sut->complexity($attribute, $value, $parameters, $validator));
    }

    public function testComplexityGivenGivenCountOfZeroOrComplexityOfZero()
    {
        $validator = $this->getMockValidator();

        $sut = new PasswordValidator();
        $attribute = 'email';

        /*
         * Given the password is any value
         * When complexity is less than 4 and count = 0
         * Then complexity returns true
         */
        $password = self::LOWERCASE;
        // array_values for readability's sake
        $parameters = array_values(['complexity' => 4, 'count' => 0]);
        $this::assertTrue($sut->complexity($attribute, $password, $parameters, $validator));

        /*
         * Given the password is any value
         * When complexity = 0 and count is any value
         * Then complexity returns true
         */
        $parameters = array_values(['complexity' => 0, 'count' => 10]);
        $this::assertTrue($sut->complexity($attribute, $password, $parameters, $validator));
    }

    public function testComplexityGivenComplexityOfValue1()
    {
        $sut = new PasswordValidator();
        $attribute = 'email';
        $validator = $this->getMockValidator();

        // array_values for readability's sake
        $parameters = array_values(['complexity' => 1, 'count' => 1]);
        /*
         * Given complexity = 1
         * When password a password is any value
         *   and the count is <= the length of the password
         * Then complexity returns true
         */
        $password = self::LOWERCASE;

        $this::assertTrue($sut->complexity($attribute, $password, $parameters, $validator));

        $password = self::UPPERCASE;
        $this::assertTrue($sut->complexity($attribute, $password, $parameters, $validator));

        $password = self::DIGITS;
        $this::assertTrue($sut->complexity($attribute, $password, $parameters, $validator));

        $password = self::SYMBOLS;
        $this::assertTrue($sut->complexity($attribute, $password, $parameters, $validator));

        $password = self::UNDERSCORE;
        $this::assertTrue($sut->complexity($attribute, $password, $parameters, $validator));

        /*
         * Given complexity = 1
         * When password a password is any value
         *   and the count is > the length of the password
         * Then complexity returns false
         */
        $password = self::LOWERCASE;
        $parameters = array_values(['complexity' => 1, 'count' => strlen($password) + 1]);
        $this::assertFalse($sut->complexity($attribute, $password, $parameters, $validator));

        $password = self::UPPERCASE;
        $parameters[1] = strlen($password) + 1;
        $this::assertFalse($sut->complexity($attribute, $password, $parameters, $validator));

        $password = self::DIGITS;
        $parameters[1] = strlen($password) + 1;
        $this::assertFalse($sut->complexity($attribute, $password, $parameters, $validator));

        $password = self::SYMBOLS;
        $parameters[1] = strlen($password) + 1;
        $this::assertFalse($sut->complexity($attribute, $password, $parameters, $validator));

        $password = self::UNDERSCORE;
        $parameters[1] = strlen($password) + 1;
        $this::assertFalse($sut->complexity($attribute, $password, $parameters, $validator));
    }

    public function testComplexityGivenComplexityOfValue2()
    {
        $sut = new PasswordValidator();
        $attribute = 'email';
        $validator = $this->getMockValidator();

        $parameters = array_values(['complexity' => 2, 'count' => 1]); // count is 1 for all tests
        /*
         * Given complexity = 2
         * When password a password contains only one class of character
         * Then complexity returns false
         */
        $password = self::LOWERCASE;
        $this::assertFalse($sut->complexity($attribute, $password, $parameters, $validator));

        $password = self::UPPERCASE;
        $this::assertFalse($sut->complexity($attribute, $password, $parameters, $validator));

        $password = self::DIGITS;
        $this::assertFalse($sut->complexity($attribute, $password, $parameters, $validator));

        $password = self::SYMBOLS;
        $this::assertFalse($sut->complexity($attribute, $password, $parameters, $validator));

        $password = self::UNDERSCORE;
        $this::assertFalse($sut->complexity($attribute, $password, $parameters, $validator));

        /*
         * Given complexity = 2
         * When password a password contains at least two character classes
         *   and the count < the number of characters in each class
         * Then complexity returns true
         */
        $password = self::LOWERCASE . self::DIGITS;
        $this::assertTrue($sut->complexity($attribute, $password, $parameters, $validator));

        $password = self::UPPERCASE . self::SYMBOLS;
        $this::assertTrue($sut->complexity($attribute, $password, $parameters, $validator));

        $password = self::DIGITS . self::UPPERCASE;
        $this::assertTrue($sut->complexity($attribute, $password, $parameters, $validator));

        $password = self::SYMBOLS . self::LOWERCASE;
        $this::assertTrue($sut->complexity($attribute, $password, $parameters, $validator));

        $password = self::UNDERSCORE . self::DIGITS;
        $this::assertTrue($sut->complexity($attribute, $password, $parameters, $validator));
    }

    public function testComplexityGivenComplexityOfValue3()
    {
        $sut = new PasswordValidator();
        $attribute = 'email';
        $validator = $this->getMockValidator();

        // array_values for readability's sake
        $parameters = array_values(['complexity' => 3, 'count' => 1]); // count is 1 for all tests
        /*
         * Given complexity = 3
         * When password a password contains two or less character classes
         * Then complexity returns false
         */
        $password = self::LOWERCASE . self::DIGITS;
        $this::assertFalse($sut->complexity($attribute, $password, $parameters, $validator));

        $password = self::UPPERCASE . self::SYMBOLS;
        $this::assertFalse($sut->complexity($attribute, $password, $parameters, $validator));

        $password = self::SYMBOLS . self::LOWERCASE;
        $this::assertFalse($sut->complexity($attribute, $password, $parameters, $validator));


        $password = self::UNDERSCORE . self::DIGITS;
        $this::assertFalse($sut->complexity($attribute, $password, $parameters, $validator));

        /*
         * Given complexity = 3
         * When a password contains at least two character classes
         *   and the count >= the number of characters in each class
         * Then complexity returns true
         */
        $password = self::LOWERCASE . self::DIGITS . self::UPPERCASE;
        $this::assertTrue($sut->complexity($attribute, $password, $parameters, $validator));

        $password = self::UPPERCASE . self::SYMBOLS . self::DIGITS;
        $this::assertTrue($sut->complexity($attribute, $password, $parameters, $validator));

        $password = self::DIGITS . self::UPPERCASE . self::SYMBOLS;
        $this::assertTrue($sut->complexity($attribute, $password, $parameters, $validator));

        $password = self::SYMBOLS . self::LOWERCASE . self::DIGITS;
        $this::assertTrue($sut->complexity($attribute, $password, $parameters, $validator));

        $password = self::UNDERSCORE . self::DIGITS . self::UPPERCASE;
        $this::assertTrue($sut->complexity($attribute, $password, $parameters, $validator));
    }

    public function testComplexityOfValue4()
    {
        $sut = new PasswordValidator();
        $attribute = 'email';
        $validator = $this->getMockValidator();

        // array_values for readability's sake
        $parameters = array_values(['complexity' => 4, 'count' => 1]); // count is 1 for all tests
        /*
         * Given complexity = 4
         * When a password contains three or less character classes
         * Then complexity returns false
         */
        $password = self::LOWERCASE . self::DIGITS . self::UPPERCASE;
        $this::assertFalse($sut->complexity($attribute, $password, $parameters, $validator));

        $password = self::UPPERCASE . self::SYMBOLS . self::DIGITS;
        $this::assertFalse($sut->complexity($attribute, $password, $parameters, $validator));

        $password = self::DIGITS . self::UPPERCASE . self::SYMBOLS;
        $this::assertFalse($sut->complexity($attribute, $password, $parameters, $validator));

        $password = self::SYMBOLS . self::LOWERCASE . self::DIGITS;
        $this::assertFalse($sut->complexity($attribute, $password, $parameters, $validator));

        $password = self::UNDERSCORE . self::DIGITS . self::UPPERCASE;
        $this::assertFalse($sut->complexity($attribute, $password, $parameters, $validator));

        /*
         * Given complexity = 4
         * When a password contains at least two character classes
         *   and the count >= the number of characters in each class
         * Then complexity returns true
         */
        $password = self::LOWERCASE . self::DIGITS . self::UPPERCASE . self::SYMBOLS;
        $this::assertTrue($sut->complexity($attribute, $password, $parameters, $validator));

        $password = self::UPPERCASE . self::SYMBOLS . self::DIGITS . self::LOWERCASE;
        $this::assertTrue($sut->complexity($attribute, $password, $parameters, $validator));

        $password = self::DIGITS . self::UPPERCASE . self::SYMBOLS . self::LOWERCASE;
        $this::assertTrue($sut->complexity($attribute, $password, $parameters, $validator));

        $password = self::SYMBOLS . self::LOWERCASE . self::DIGITS . self::UPPERCASE;
        $this::assertTrue($sut->complexity($attribute, $password, $parameters, $validator));

        $password = self::UNDERSCORE . self::DIGITS . self::UPPERCASE . self::LOWERCASE;
        $this::assertTrue($sut->complexity($attribute, $password, $parameters, $validator));
    }

    public function testSetErrorOutput()
    {
        $sut = new PasswordValidator();
        $attribute = 'email';
        $validator = $this->getMockValidator();

        // array_values for readability's sake
        $parameters = array_values(['complexity' => 1, 'count' => 1]);
        $password = self::LOWERCASE;
        $sut->complexity($attribute, $password, $parameters, $validator);
        $this::assertEmpty($validator->customMessages);

        $length = strlen($password) + 1;
        $parameters = array_values(['complexity' => 1, 'count' => $length]);
        $sut->complexity($attribute, $password, $parameters, $validator);
        // see comments in Password::setCustomMessages
        $expected = [
            'password.complexity'
            => "Your :attribute must contain at least {$length} characters from 1 of the following types: uppercase, lowercase, numbers, and symbols",
        ];
        $actual = $validator->customMessages;
        $this::assertEquals($expected, $actual);


        $parameters = array_values(['complexity' => 2, 'count' => 1]);
        $sut->complexity($attribute, $password, $parameters, $validator);
        $expected = [
            'password.complexity'
            => "Your :attribute must contain at least 2 of the following types: uppercase, lowercase, numbers, and symbols",
        ];
        $actual = $validator->customMessages;
        $this::assertEquals($expected, $actual);

        $parameters = array_values(['complexity' => 4, 'count' => 2]);
        $sut->complexity($attribute, $password, $parameters, $validator);
        $expected = [
            'password.complexity'
            => "Your :attribute must contain at least 2 characters from each of the following types: uppercase, lowercase, numbers, and symbols",
        ];
        $actual = $validator->customMessages;
        $this::assertEquals($expected, $actual);

        $parameters = array_values(['complexity' => 4, 'count' => 1]);
        $sut->complexity($attribute, $password, $parameters, $validator);

        // extra space between contain and each due to templating
        $expected = [
            'password.complexity'
            => "Your :attribute must contain  each of the following types: uppercase, lowercase, numbers, and symbols",
        ];
        $actual = $validator->customMessages;
        $this::assertEquals($expected, $actual);
    }

    /**
     * @param array $methods
     * @return Validator|MockObject
     */
    private function getMockValidator($methods = [])
    {
        /** @var Translator|MockObject $translator */
        $translator = $this->getMockBuilder(Translator::class)
            ->onlyMethods(['trans', 'transChoice', 'setLocale', 'getLocale'])
            ->getMockForAbstractClass();
        $validator = new Validator($translator, [], []);
        /** @var Validator|MockObject $validator */
        /*
        $validator = $this->getMockBuilder(Validator::class)
            ->setConstructorArgs([$translator, [], []])
            ->setMethods(null)
            ->getMock();
        */
        return $validator;
    }
}
