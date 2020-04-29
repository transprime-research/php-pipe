<?php

namespace Piper\Tests;

use PHPUnit\Framework\TestCase;
use Transprime\Piper\{Piper, Exceptions\PiperException};

class PiperTest extends TestCase
{
    public function testPiperIsCreated()
    {
        $this->assertIsObject(new Piper());
    }

    public function testPipeToUp()
    {
        $piper = new Piper();

        $result = $piper->pipe('name')
            ->to(fn($name) => strtoupper($name))
            ->up();

        $this->assertSame('NAME', $result);
    }

    public function testClosureCanBeRunOnPipedResult()
    {
        piper('name')
            ->to(fn($name) => strtoupper($name))
            ->up(fn($result) => $this->assertSame('NAME', $result));
    }

    public function testManyPipeTo()
    {
        piper('NAME')
            ->to(fn($name) => strtolower($name))
            ->to(fn($name) => ucfirst($name))
            ->up(fn($result) => $this->assertSame('Name', $result));
    }

    public function testPipeAPipedResult()
    {
        $result = piper('NAME')
            ->to(fn($name) => strtolower($name))
            ->to(fn($name) => ucfirst($name))
            ->up(fn($result) => \piper($result)->to(fn() => [$result])->up());

        $this->assertSame(['Name'], $result);
    }

    public function testPiperAcceptsClosure()
    {
        $result = piper(fn() => 'NAME')
            ->to(fn($name) => strtolower($name))
            ->up();

        $this->assertSame('name', $result);
    }

    public function testPipeMethodCannotBeCalledMoreThanOnce()
    {
        $this->expectException(PiperException::class);
        $this->expectExceptionMessage('on() must be called only once');

        piper(fn() => 'NAME')
            ->pipe('1')
            ->to(fn($name) => strtolower($name))
            ->up();
    }

    public function testPipeAndToMethodMustBeCalledBeforeUp()
    {
        $this->expectException(PiperException::class);
        $this->expectExceptionMessage('on() must be called and to() at least once');

        piper(fn() => 'NAME')
            ->up();
    }

    public function testPipeMethodAcceptsCallable()
    {
        // test global method
        $result = piper('NAME', 'strtolower')
            ->to(fn($name) => ucfirst($name))
            ->up();

        $this->assertSame('Name', $result);

        // test class method
        $result2 = piper('NAME', StrManipulator::class . '::strToLower')
            ->to(fn($name) => ucfirst($name))
            ->up();

        $this->assertSame('Name', $result2);

        // test array class and method
        $result2 = piper('NAME', [StrManipulator::class, 'strToLower'])
            ->to(fn($name) => ucfirst($name))
            ->up();

        $this->assertSame('Name', $result2);

        // test class object
        $result2 = piper('NAME', new StrManipulator())
            ->to(fn($name) => ucfirst($name))
            ->up();

        $this->assertSame('Name', $result2);
    }

    public function testFunctionCanBeRetrievedAtTheNextPipe()
    {
        piper(fn() => fn() => fn() => fn() => 'ade')
            ->to(fn($fn) => $fn()) //Executes the first function
            ->to(fn($fn) => $fn()) //Executes the second function
            ->to(fn($fn) => $fn()) //Executes the third function
            ->to(fn($name) => ucfirst($name)) //Executes the fourth function
            ->up(fn($result) => $this->assertSame('Ade', $result)); //manipulates the result
    }

    public function testAcceptingFunctionAndParametersInToMethod()
    {
        $this->assertSame('ADE', piper('ade')->to('strtoupper')->up());

        $this->assertSame(
            10,
            piper(['ade', 1])
                ->to(fn($data) => array_filter($data, static fn($val): bool => is_int($val)))
                ->to('array_merge', [2, 3, 4])
                ->to('array_values')
                ->to('array_sum')
                ->up()
        );

        $this->assertSame(
            ['ADE'],
            piper(['name' => 'ade', 'hobby' => 'coding'])
                ->to('array_flip')
                ->to('array_keys')
                ->to('array_map', fn($val) => strtoupper($val))
                ->to('array_intersect', [0 => 'ADE'])()
        );
    }

    public function testMakePiperInvokableWithoutCallingUpMethod()
    {
        $result = piper('name')
            ->to(fn($name) => ucfirst($name))();

        $this->assertSame('Name', $result);
    }

    public function testPiperOnStaticCreation()
    {
        $this->assertEquals(
            'n,a,m,e',
            Piper::on('NAME')
                ->to('str_split')
                ->to('implode', ',')
                ->to('strtolower')
                ->up()
        );
    }

    public function testStringPassedIntoFirstPipeShouldBeTreatedAsString()
    {
        $this->assertEquals(
            'STRMANIPULATOR::STRTOLOWER',
            Piper::on('StrManipulator::strToLower')
                ->to('strtoupper')
                ->up()
        );
    }
}

class StrManipulator
{
    public function __invoke(string $value)
    {
        return $this->strToLower($value);
    }

    public static function strToLower(string $value = '')
    {
        return strtolower($value);
    }
}