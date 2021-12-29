<?php

namespace Tests\Unit\Pontedilana\PhpWeasyPrint;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Pontedilana\PhpWeasyPrint\AbstractGenerator;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;

/**
 * @covers \Pontedilana\PhpWeasyPrint\AbstractGenerator
 */
class AbstractGeneratorTest extends TestCase
{
    /**
     * @covers \Pontedilana\PhpWeasyPrint\AbstractGenerator::addOption
     */
    public function testAddOption(): void
    {
        $media = $this->getMockForAbstractClass(AbstractGenerator::class, [], '', false);

        $this->assertEquals([], $media->getOptions());

        $r = new ReflectionMethod($media, 'addOption');
        $r->setAccessible(true);
        $r->invokeArgs($media, ['foo', 'bar']);

        $this->assertEquals(['foo' => 'bar'], $media->getOptions(), '->addOption() adds an option');

        $r->invokeArgs($media, ['baz', 'bat']);

        $this->assertEquals(
            [
                'foo' => 'bar',
                'baz' => 'bat',
            ],
            $media->getOptions(),
            '->addOption() appends the option to the existing ones'
        );
    }

    /**
     * @covers \Pontedilana\PhpWeasyPrint\AbstractGenerator::addOption
     */
    public function testAddOptionException(): void
    {
        $media = $this->getMockForAbstractClass(AbstractGenerator::class, [], '', false);

        $r = new ReflectionMethod($media, 'addOption');
        $r->setAccessible(true);
        $r->invokeArgs($media, ['foo', 'bar']);

        $this->expectException(InvalidArgumentException::class);
        $r->invokeArgs($media, ['foo', 'baz']);
    }

    /**
     * @covers \Pontedilana\PhpWeasyPrint\AbstractGenerator::addOptions
     */
    public function testAddOptions(): void
    {
        $media = $this->getMockForAbstractClass(AbstractGenerator::class, [], '', false);

        $this->assertEquals([], $media->getOptions());

        $r = new ReflectionMethod($media, 'addOptions');
        $r->setAccessible(true);
        $r->invokeArgs($media, [['foo' => 'bar', 'baz' => 'bat']]);

        $this->assertEquals(
            [
                'foo' => 'bar',
                'baz' => 'bat',
            ],
            $media->getOptions(),
            '->addOptions() adds all the given options'
        );

        $r->invokeArgs($media, [['ban' => 'bag', 'bal' => 'bac']]);

        $this->assertEquals(
            [
                'foo' => 'bar',
                'baz' => 'bat',
                'ban' => 'bag',
                'bal' => 'bac',
            ],
            $media->getOptions(),
            '->addOptions() adds the given options to the existing ones'
        );
    }

    /**
     * @covers \Pontedilana\PhpWeasyPrint\AbstractGenerator::addOptions
     */
    public function testAddOptionsException(): void
    {
        $media = $this->getMockForAbstractClass(AbstractGenerator::class, [], '', false);

        $r = new ReflectionMethod($media, 'addOptions');
        $r->setAccessible(true);
        $r->invokeArgs($media, [['foo' => 'bar', 'baz' => 'bat']]);

        $this->expectException(InvalidArgumentException::class);
        $r->invokeArgs($media, [['foo' => 'baz']]);
    }

    /**
     * @covers \Pontedilana\PhpWeasyPrint\AbstractGenerator::setOption
     */
    public function testSetOption(): void
    {
        $media = $this
            ->getMockBuilder(AbstractGenerator::class)
            ->setConstructorArgs(['/usr/local/bin/weasyprint'])
            ->getMockForAbstractClass()
        ;

        $logger = $this
            ->getMockBuilder(LoggerInterface::class)
            ->getMock()
        ;
        $media->setLogger($logger);
        $logger->expects($this->once())->method('debug');

        $r = new ReflectionMethod($media, 'addOption');
        $r->setAccessible(true);
        $r->invokeArgs($media, ['foo', 'bar']);

        $media->setOption('foo', 'abc');

        $this->assertEquals(
            [
                'foo' => 'abc',
            ],
            $media->getOptions(),
            '->setOption() defines the value of an option'
        );

        $message = '->setOption() raises an exception when the specified option does not exist';

        try {
            $media->setOption('bad', 'def');
            $this->fail($message);
        } catch (InvalidArgumentException $e) {
            $this->anything();
        }
    }

    /**
     * @covers \Pontedilana\PhpWeasyPrint\AbstractGenerator::setOptions
     */
    public function testSetOptions(): void
    {
        $media = $this
            ->getMockBuilder(AbstractGenerator::class)
            ->setConstructorArgs(['/usr/local/bin/weasyprint'])
            ->getMockForAbstractClass()
        ;

        $logger = $this
            ->getMockBuilder(LoggerInterface::class)
            ->getMock()
        ;
        $media->setLogger($logger);
        $logger->expects($this->exactly(4))->method('debug');

        $r = new ReflectionMethod($media, 'addOptions');
        $r->setAccessible(true);
        $r->invokeArgs($media, [['foo' => 'bar', 'baz' => 'bat']]);

        $media->setOptions(['foo' => 'abc', 'baz' => 'def']);

        $this->assertEquals(
            [
                'foo' => 'abc',
                'baz' => 'def',
            ],
            $media->getOptions(),
            '->setOptions() defines the values of all the specified options'
        );

        $message = '->setOptions() raises an exception when one of the specified options does not exist';

        try {
            $media->setOptions(['foo' => 'abc', 'baz' => 'def', 'bad' => 'ghi']);
            $this->fail($message);
        } catch (InvalidArgumentException $e) {
            $this->anything();
        }
    }

    /**
     * @covers \Pontedilana\PhpWeasyPrint\AbstractGenerator::generate
     */
    public function testGenerate(): void
    {
        $media = $this->getMockBuilder(AbstractGenerator::class)
            ->onlyMethods([
                'configure',
                'prepareOutput',
                'getCommand',
                'executeCommand',
                'checkOutput',
                'checkProcessStatus',
            ])
            ->setConstructorArgs(['the_binary', []])
            ->getMock()
        ;

        $logger = $this
            ->getMockBuilder(LoggerInterface::class)
            ->getMock()
        ;
        $media->setLogger($logger);
        $logger
            ->expects($this->exactly(2))
            ->method('info')
            ->with(
                $this->logicalOr(
                    'Generate from file(s) "the_input_file" to file "the_output_file".',
                    'File "the_output_file" has been successfully generated.'
                ),
                $this->logicalOr(
                    ['command' => 'the command', 'env' => null, 'timeout' => AbstractGenerator::DEFAULT_TIMEOUT],
                    ['command' => 'the command', 'stdout' => 'stdout', 'stderr' => 'stderr']
                )
            )
        ;

        $media
            ->expects($this->once())
            ->method('prepareOutput')
            ->with($this->equalTo('the_output_file'))
        ;
        $media
            ->expects($this->any())
            ->method('getCommand')
            ->with(
                $this->equalTo('the_input_file'),
                $this->equalTo('the_output_file'),
                $this->equalTo(['foo' => 'bar'])
            )
            ->willReturn('the command')
        ;
        $media
            ->expects($this->once())
            ->method('executeCommand')
            ->with($this->equalTo('the command'))
            ->willReturn([0, 'stdout', 'stderr'])
        ;
        $media
            ->expects($this->once())
            ->method('checkProcessStatus')
            ->with(0, 'stdout', 'stderr', 'the command')
        ;
        $media
            ->expects($this->once())
            ->method('checkOutput')
            ->with(
                $this->equalTo('the_output_file'),
                $this->equalTo('the command')
            )
        ;

        $media->generate('the_input_file', 'the_output_file', ['foo' => 'bar']);
    }

    /**
     * @covers \Pontedilana\PhpWeasyPrint\AbstractGenerator::generate
     */
    public function testFailingGenerate(): void
    {
        $media = $this->getMockBuilder(AbstractGenerator::class)
            ->onlyMethods([
                'configure',
                'prepareOutput',
                'getCommand',
                'executeCommand',
                'checkOutput',
                'checkProcessStatus',
            ])
            ->setConstructorArgs(['the_binary', [], ['PATH' => '/usr/bin']])
            ->getMock()
        ;

        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();
        $media->setLogger($logger);
        $media->setTimeout(2000);

        $logger
            ->expects($this->once())
            ->method('info')
            ->with(
                $this->equalTo('Generate from file(s) "the_input_file" to file "the_output_file".'),
                $this->equalTo(['command' => 'the command', 'env' => ['PATH' => '/usr/bin'], 'timeout' => 2000])
            )
        ;

        $logger
            ->expects($this->once())
            ->method('error')
            ->with(
                $this->equalTo('An error happened while generating "the_output_file".'),
                $this->equalTo(['command' => 'the command', 'status' => 1, 'stdout' => 'stdout', 'stderr' => 'stderr'])
            )
        ;

        $media
            ->expects($this->once())
            ->method('prepareOutput')
            ->with($this->equalTo('the_output_file'))
        ;
        $media
            ->expects($this->any())
            ->method('getCommand')
            ->with(
                $this->equalTo('the_input_file'),
                $this->equalTo('the_output_file')
            )
            ->willReturn('the command')
        ;
        $media
            ->expects($this->once())
            ->method('executeCommand')
            ->with($this->equalTo('the command'))
            ->willReturn([1, 'stdout', 'stderr'])
        ;
        $media
            ->expects($this->once())
            ->method('checkProcessStatus')
            ->with(1, 'stdout', 'stderr', 'the command')
            ->willThrowException(new RuntimeException())
        ;

        $this->expectException(RuntimeException::class);

        $media->generate('the_input_file', 'the_output_file', ['foo' => 'bar']);
    }

    /**
     * @covers \Pontedilana\PhpWeasyPrint\AbstractGenerator::generateFromHtml
     */
    public function testGenerateFromHtml(): void
    {
        $media = $this->getMockBuilder(AbstractGenerator::class)
            ->onlyMethods([
                'configure',
                'generate',
                'createTemporaryFile',
            ])
            ->setConstructorArgs(['the_binary'])
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $media
            ->expects($this->once())
            ->method('createTemporaryFile')
            ->with(
                $this->equalTo('<html>foo</html>'),
                $this->equalTo('html')
            )
            ->willReturn('the_temporary_file')
        ;
        $media
            ->expects($this->once())
            ->method('generate')
            ->with(
                $this->equalTo('the_temporary_file'),
                $this->equalTo('the_output_file'),
                $this->equalTo(['foo' => 'bar'])
            )
        ;

        $media->generateFromHtml('<html>foo</html>', 'the_output_file', ['foo' => 'bar']);
    }

    /**
     * @covers \Pontedilana\PhpWeasyPrint\AbstractGenerator::getOutput
     */
    public function testGetOutput(): void
    {
        $media = $this->getMockBuilder(AbstractGenerator::class)
            ->onlyMethods([
                'configure',
                'getDefaultExtension',
                'createTemporaryFile',
                'generate',
                'getFileContents',
                'unlink',
            ])
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $media
            ->expects($this->any())
            ->method('getDefaultExtension')
            ->willReturn('ext')
        ;
        $media
            ->expects($this->any())
            ->method('createTemporaryFile')
            ->with(
                $this->equalTo(null),
                $this->equalTo('ext')
            )
            ->willReturn('the_temporary_file')
        ;
        $media
            ->expects($this->once())
            ->method('generate')
            ->with(
                $this->equalTo('the_input_file'),
                $this->equalTo('the_temporary_file'),
                $this->equalTo(['foo' => 'bar'])
            )
        ;
        $media
            ->expects($this->once())
            ->method('getFileContents')
            ->willReturn('the file contents')
        ;

        $media
            ->expects($this->any())
            ->method('unlink')
            ->with($this->equalTo('the_temporary_file'))
            ->willReturn(true)
        ;

        $this->assertEquals('the file contents', $media->getOutput('the_input_file', ['foo' => 'bar']));
    }

    /**
     * @covers \Pontedilana\PhpWeasyPrint\AbstractGenerator::getOutputFromHtml
     */
    public function testGetOutputFromHtml(): void
    {
        $media = $this->getMockBuilder(AbstractGenerator::class)
            ->onlyMethods([
                'configure',
                'getOutput',
                'createTemporaryFile',
            ])
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $media
            ->expects($this->once())
            ->method('createTemporaryFile')
            ->with(
                $this->equalTo('<html>foo</html>'),
                $this->equalTo('html')
            )
            ->willReturn('the_temporary_file')
        ;
        $media
            ->expects($this->once())
            ->method('getOutput')
            ->with(
                $this->equalTo('the_temporary_file'),
                $this->equalTo(['foo' => 'bar'])
            )
            ->willReturn('the output')
        ;

        $this->assertEquals('the output', $media->getOutputFromHtml('<html>foo</html>', ['foo' => 'bar']));
    }

    /**
     * @covers \Pontedilana\PhpWeasyPrint\AbstractGenerator::mergeOptions
     */
    public function testMergeOptions(): void
    {
        $media = $this->getMockForAbstractClass(AbstractGenerator::class, [], '', false);

        $originalOptions = ['foo' => 'bar', 'baz' => 'bat'];

        $addOptions = new ReflectionMethod($media, 'addOptions');
        $addOptions->setAccessible(true);
        $addOptions->invokeArgs($media, [$originalOptions]);

        $r = new ReflectionMethod($media, 'mergeOptions');
        $r->setAccessible(true);

        $mergedOptions = $r->invokeArgs($media, [['foo' => 'ban']]);

        $this->assertEquals(
            [
                'foo' => 'ban',
                'baz' => 'bat',
            ],
            $mergedOptions,
            '->mergeOptions() merges an option to the instance ones and returns the result options array'
        );

        $this->assertEquals(
            $originalOptions,
            $media->getOptions(),
            '->mergeOptions() does NOT change the instance options'
        );

        $mergedOptions = $r->invokeArgs($media, [['foo' => 'ban', 'baz' => 'bag']]);

        $this->assertEquals(
            [
                'foo' => 'ban',
                'baz' => 'bag',
            ],
            $mergedOptions,
            '->mergeOptions() merges many options to the instance ones and returns the result options array'
        );
    }

    /**
     * @covers \Pontedilana\PhpWeasyPrint\AbstractGenerator::mergeOptions
     */
    public function testMergeOptionsException(): void
    {
        $media = $this->getMockForAbstractClass(AbstractGenerator::class, [], '', false);
        $originalOptions = ['foo' => 'bar', 'baz' => 'bat'];

        $addOptions = new ReflectionMethod($media, 'addOptions');
        $addOptions->setAccessible(true);
        $addOptions->invokeArgs($media, [$originalOptions]);

        $r = new ReflectionMethod($media, 'mergeOptions');
        $r->setAccessible(true);

        $this->expectException(InvalidArgumentException::class);
        $mergedOptions = $r->invokeArgs($media, [['bad' => 'ban']]);
    }

    /**
     * @dataProvider dataForBuildCommand
     */
    public function testBuildCommand(string $binary, string $url, string $path, array $options, string $expected): void
    {
        $media = $this->getMockForAbstractClass(AbstractGenerator::class, [], '', false);

        $r = new ReflectionMethod($media, 'buildCommand');
        $r->setAccessible(true);

        $this->assertEquals($expected, $r->invokeArgs($media, [$binary, $url, $path, $options]));
    }

    public function dataForBuildCommand(): array
    {
        $theBinary = $this->getPHPExecutableFromPath() . ' -v'; // i.e.: '/usr/bin/php -v'

        return [
            [
                $theBinary,
                'https://the.url/',
                '/the/path',
                [],
                $theBinary . ' ' . \escapeshellarg('https://the.url/') . ' ' . \escapeshellarg('/the/path'),
            ],
            [
                $theBinary,
                'https://the.url/',
                '/the/path',
                [
                    'foo' => null,
                    'bar' => false,
                    'baz' => [],
                ],
                $theBinary . ' ' . \escapeshellarg('https://the.url/') . ' ' . \escapeshellarg('/the/path'),
            ],
            [
                $theBinary,
                'https://the.url/',
                '/the/path',
                [
                    'foo' => 'foovalue',
                    'bar' => ['barvalue1', 'barvalue2'],
                    'baz' => true,
                ],
                $theBinary . ' --foo ' . \escapeshellarg('foovalue') . ' --bar ' . \escapeshellarg('barvalue1') . ' --bar ' . \escapeshellarg('barvalue2') . ' --baz ' . \escapeshellarg('https://the.url/') . ' ' . \escapeshellarg('/the/path'),
            ],
            [
                $theBinary,
                'https://the.url/',
                '/the/path',
                [
                    'attachment' => ['/path1', '/path2'],
                ],
                $theBinary . ' --attachment ' . \escapeshellarg('/path1') . ' --attachment ' . \escapeshellarg('/path2') . ' ' . \escapeshellarg('https://the.url/') . ' ' . \escapeshellarg('/the/path'),
            ],
            [
                $theBinary,
                'https://the.url/',
                '/the/path',
                [
                    'resolution' => 100,
                ],
                $theBinary . ' ' . '--resolution 100 ' . \escapeshellarg('https://the.url/') . ' ' . \escapeshellarg('/the/path'),
            ],
        ];
    }

    /**
     * @covers \Pontedilana\PhpWeasyPrint\AbstractGenerator::checkOutput
     */
    public function testCheckOutput(): void
    {
        $media = $this->getMockBuilder(AbstractGenerator::class)
            ->onlyMethods([
                'configure',
                'fileExists',
                'filesize',
            ])
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $media
            ->expects($this->once())
            ->method('fileExists')
            ->with($this->equalTo('the_output_file'))
            ->willReturn(true)
        ;
        $media
            ->expects($this->once())
            ->method('filesize')
            ->with($this->equalTo('the_output_file'))
            ->willReturn(123)
        ;

        $r = new ReflectionMethod($media, 'checkOutput');
        $r->setAccessible(true);

        $message = '->checkOutput() checks both file existence and size';

        try {
            $r->invokeArgs($media, ['the_output_file', 'the command']);
            $this->anything();
        } catch (RuntimeException $e) {
            $this->fail($message);
        }
    }

    /**
     * @covers \Pontedilana\PhpWeasyPrint\AbstractGenerator::checkOutput
     */
    public function testCheckOutputWhenTheFileDoesNotExist(): void
    {
        $media = $this->getMockBuilder(AbstractGenerator::class)
            ->onlyMethods([
                'configure',
                'fileExists',
                'filesize',
            ])
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $media
            ->expects($this->once())
            ->method('fileExists')
            ->with($this->equalTo('the_output_file'))
            ->willReturn(false)
        ;

        $r = new ReflectionMethod($media, 'checkOutput');
        $r->setAccessible(true);

        $message = '->checkOutput() throws an InvalidArgumentException when the file does not exist';

        try {
            $r->invokeArgs($media, ['the_output_file', 'the command']);
            $this->fail($message);
        } catch (RuntimeException $e) {
            $this->anything();
        }
    }

    /**
     * @covers \Pontedilana\PhpWeasyPrint\AbstractGenerator::checkOutput
     */
    public function testCheckOutputWhenTheFileIsEmpty(): void
    {
        $media = $this->getMockBuilder(AbstractGenerator::class)
            ->onlyMethods([
                'configure',
                'fileExists',
                'filesize',
            ])
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $media
            ->expects($this->once())
            ->method('fileExists')
            ->with($this->equalTo('the_output_file'))
            ->willReturn(true)
        ;
        $media
            ->expects($this->once())
            ->method('filesize')
            ->with($this->equalTo('the_output_file'))
            ->willReturn(0)
        ;

        $r = new ReflectionMethod($media, 'checkOutput');
        $r->setAccessible(true);

        $message = '->checkOutput() throws an InvalidArgumentException when the file is empty';

        try {
            $r->invokeArgs($media, ['the_output_file', 'the command']);
            $this->fail($message);
        } catch (RuntimeException $e) {
            $this->anything();
        }
    }

    /**
     * @covers \Pontedilana\PhpWeasyPrint\AbstractGenerator::checkProcessStatus
     */
    public function testCheckProcessStatus(): void
    {
        $media = $this->getMockBuilder(AbstractGenerator::class)
            ->onlyMethods(['configure'])
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $r = new ReflectionMethod($media, 'checkProcessStatus');
        $r->setAccessible(true);

        try {
            $r->invokeArgs($media, [0, '', '', 'the command']);
            $this->anything();
        } catch (RuntimeException $e) {
            $this->fail('0 status means success');
        }

        try {
            $r->invokeArgs($media, [1, '', '', 'the command']);
            $this->anything();
        } catch (RuntimeException $e) {
            $this->fail('1 status means failure, but no stderr content');
        }

        try {
            $r->invokeArgs($media, [1, '', 'Could not connect to X', 'the command']);
            $this->fail('1 status means failure');
        } catch (RuntimeException $e) {
            $this->assertEquals(1, $e->getCode(), 'Exception thrown by checkProcessStatus should pass on the error code');
        }
    }

    public function testItThrowsTheProperExceptionWhenFileExistsAndNotOverwritting(): void
    {
        $this->expectException(\Pontedilana\PhpWeasyPrint\Exception\FileAlreadyExistsException::class);
        $media = $this->getMockBuilder(AbstractGenerator::class)
            ->onlyMethods([
                'configure',
                'fileExists',
                'isFile',
            ])
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $media
            ->expects($this->any())
            ->method('fileExists')
            ->willReturn(true)
        ;
        $media
            ->expects($this->any())
            ->method('isFile')
            ->willReturn(true)
        ;
        $r = new ReflectionMethod($media, 'prepareOutput');
        $r->setAccessible(true);

        $r->invokeArgs($media, ['', false]);
    }

    /**
     * @covers \Pontedilana\PhpWeasyPrint\AbstractGenerator::removeTemporaryFiles
     */
    public function testCleanupEmptyTemporaryFiles(): void
    {
        $generator = $this->getMockBuilder(AbstractGenerator::class)
            ->onlyMethods([
                'configure',
                'unlink',
            ])
            ->setConstructorArgs(['the_binary'])
            ->getMock()
        ;

        $generator
            ->expects($this->once())
            ->method('unlink')
        ;

        $create = new ReflectionMethod($generator, 'createTemporaryFile');
        $create->setAccessible(true);
        $create->invoke($generator, null, null);

        $files = new ReflectionProperty($generator, 'temporaryFiles');
        $files->setAccessible(true);
        $this->assertCount(1, $files->getValue($generator));

        $remove = new ReflectionMethod($generator, 'removeTemporaryFiles');
        $remove->setAccessible(true);
        $remove->invoke($generator);
    }

    /**
     * @covers \Pontedilana\PhpWeasyPrint\AbstractGenerator::removeTemporaryFiles
     */
    public function testCleanupTemporaryFiles(): void
    {
        $generator = $this->getMockBuilder(AbstractGenerator::class)
            ->onlyMethods([
                'configure',
                'unlink',
            ])
            ->setConstructorArgs(['the_binary'])
            ->getMock()
        ;

        $generator
            ->expects($this->once())
            ->method('unlink')
        ;

        $create = new ReflectionMethod($generator, 'createTemporaryFile');
        $create->setAccessible(true);
        $create->invoke($generator, '<html/>', 'html');

        $files = new ReflectionProperty($generator, 'temporaryFiles');
        $files->setAccessible(true);
        $this->assertCount(1, $files->getValue($generator));

        $remove = new ReflectionMethod($generator, 'removeTemporaryFiles');
        $remove->setAccessible(true);
        $remove->invoke($generator);
    }

    /**
     * @covers \Pontedilana\PhpWeasyPrint\AbstractGenerator::resetOptions
     */
    public function testResetOptions(): void
    {
        $media = new class('/usr/local/bin/weasyprint') extends AbstractGenerator {
            protected function configure(): void
            {
                $this->addOptions([
                    'optionA' => null,
                    'optionB' => 'abc',
                ]);
            }
        };

        $media->setOption('optionA', 'bar');

        $this->assertEquals(
            [
                'optionA' => 'bar',
                'optionB' => 'abc',
            ],
            $media->getOptions()
        );

        $media->resetOptions();

        $this->assertEquals(
            [
                'optionA' => null,
                'optionB' => 'abc',
            ],
            $media->getOptions()
        );
    }

    private function getPHPExecutableFromPath(): ?string
    {
        if (isset($_SERVER['_'])) {
            return $_SERVER['_'];
        }

        if (@\defined(\PHP_BINARY)) {
            return \PHP_BINARY;
        }

        if (false === \getenv('PATH')) {
            return null;
        }

        $paths = \explode(\PATH_SEPARATOR, \getenv('PATH'));
        foreach ($paths as $path) {
            // we need this for XAMPP (Windows)
            if (false !== \strpos($path, 'php.exe') && isset($_SERVER['WINDIR']) && \file_exists($path) && \is_file($path)) {
                return $path;
            }
            $php_executable = $path . \DIRECTORY_SEPARATOR . 'php' . (isset($_SERVER['WINDIR']) ? '.exe' : '');
            if (\file_exists($php_executable) && \is_file($php_executable)) {
                return $php_executable;
            }
        }

        return null; // not found
    }
}
