<?php

namespace Pontedilana\PhpWeasyPrint\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pontedilana\PhpWeasyPrint\AbstractGenerator;
use Psr\Log\LoggerInterface;

#[CoversClass(AbstractGenerator::class)]
class AbstractGeneratorTest extends TestCase
{
    public function testAddOption(): void
    {
        $media = $this->createGenerator();

        $this->assertEquals([], $media->getOptions());

        $r = new \ReflectionMethod($media, 'addOption');
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

    public function testAddOptionException(): void
    {
        $media = $this->createGenerator();

        $r = new \ReflectionMethod($media, 'addOption');
        $r->invokeArgs($media, ['foo', 'bar']);

        $this->expectException(\InvalidArgumentException::class);
        $r->invokeArgs($media, ['foo', 'baz']);
    }

    public function testAddOptions(): void
    {
        $media = $this->createGenerator();

        $this->assertEquals([], $media->getOptions());

        $r = new \ReflectionMethod($media, 'addOptions');
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

    public function testAddOptionsException(): void
    {
        $media = $this->createGenerator();

        $r = new \ReflectionMethod($media, 'addOptions');
        $r->invokeArgs($media, [['foo' => 'bar', 'baz' => 'bat']]);

        $this->expectException(\InvalidArgumentException::class);
        $r->invokeArgs($media, [['foo' => 'baz']]);
    }

    public function testSetOption(): void
    {
        $media = $this->createGenerator();

        $logger = $this
            ->getMockBuilder(LoggerInterface::class)
            ->getMock()
        ;
        $media->setLogger($logger);
        $logger->expects($this->once())->method('debug');

        $r = new \ReflectionMethod($media, 'addOption');
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
        } catch (\InvalidArgumentException $e) {
            $this->anything();
        }
    }

    public function testSetOptions(): void
    {
        $media = $this->createGenerator();

        $logger = $this
            ->getMockBuilder(LoggerInterface::class)
            ->getMock()
        ;
        $media->setLogger($logger);
        $logger->expects($this->exactly(4))->method('debug');

        $r = new \ReflectionMethod($media, 'addOptions');
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
        } catch (\InvalidArgumentException $e) {
            $this->anything();
        }
    }

    public function testGenerate(): void
    {
        $media = $this->getMockBuilder(AbstractGenerator::class)
            ->onlyMethods([
                'configure',
                'prepareOutput',
                'mergeOptions',
                'buildCommand',
                'buildCommandArray',
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
            ->method('mergeOptions')
            ->with($this->equalTo(['foo' => 'bar']))
            ->willReturn(['foo' => 'bar'])
        ;
        $media
            ->expects($this->any())
            ->method('buildCommand')
            ->with(
                $this->equalTo('the_binary'),
                $this->equalTo('the_input_file'),
                $this->equalTo('the_output_file'),
                $this->equalTo(['foo' => 'bar'])
            )
            ->willReturn('the command')
        ;
        $media
            ->expects($this->any())
            ->method('buildCommandArray')
            ->with(
                $this->equalTo('the_binary'),
                $this->equalTo('the_input_file'),
                $this->equalTo('the_output_file'),
                $this->equalTo(['foo' => 'bar'])
            )
            ->willReturn(['the_binary', 'the_input_file', 'the_output_file'])
        ;
        $media
            ->expects($this->once())
            ->method('executeCommand')
            ->with($this->equalTo(['the_binary', 'the_input_file', 'the_output_file']))
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

    public function testFailingGenerate(): void
    {
        $media = $this->getMockBuilder(AbstractGenerator::class)
            ->onlyMethods([
                'configure',
                'prepareOutput',
                'mergeOptions',
                'buildCommand',
                'buildCommandArray',
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
            ->method('mergeOptions')
            ->willReturn(['foo' => 'bar'])
        ;
        $media
            ->expects($this->any())
            ->method('buildCommand')
            ->willReturn('the command')
        ;
        $media
            ->expects($this->any())
            ->method('buildCommandArray')
            ->willReturn(['the_binary', 'the_input_file', 'the_output_file'])
        ;
        $media
            ->expects($this->once())
            ->method('executeCommand')
            ->with($this->equalTo(['the_binary', 'the_input_file', 'the_output_file']))
            ->willReturn([1, 'stdout', 'stderr'])
        ;
        $media
            ->expects($this->once())
            ->method('checkProcessStatus')
            ->with(1, 'stdout', 'stderr', 'the command')
            ->willThrowException(new \RuntimeException())
        ;

        $this->expectException(\RuntimeException::class);

        $media->generate('the_input_file', 'the_output_file', ['foo' => 'bar']);
    }

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

    public function testMergeOptions(): void
    {
        $media = $this->createGenerator();

        $originalOptions = ['foo' => 'bar', 'baz' => 'bat'];

        $addOptions = new \ReflectionMethod($media, 'addOptions');
        $addOptions->invokeArgs($media, [$originalOptions]);

        $r = new \ReflectionMethod($media, 'mergeOptions');

        $mergedOptions = $r->invokeArgs($media, [['foo' => 'ban']]);

        $this->assertEquals(
            [
                'foo' => 'ban',
                'baz' => 'bat',
            ],
            $mergedOptions,
            '->mergeOptions() merges an option with the instance options and returns the resulting options array'
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
            '->mergeOptions() merges multiple options with the instance options and returns the resulting options array'
        );
    }

    public function testMergeOptionsException(): void
    {
        $media = $this->createGenerator();
        $originalOptions = ['foo' => 'bar', 'baz' => 'bat'];

        $addOptions = new \ReflectionMethod($media, 'addOptions');
        $addOptions->invokeArgs($media, [$originalOptions]);

        $r = new \ReflectionMethod($media, 'mergeOptions');

        $this->expectException(\InvalidArgumentException::class);
        $mergedOptions = $r->invokeArgs($media, [['bad' => 'ban']]);
    }

    #[DataProvider('dataForBuildCommand')]
    public function testBuildCommand(string $binary, string $url, string $path, array $options, string $expected): void
    {
        $media = $this->createGenerator();

        $r = new \ReflectionMethod($media, 'buildCommand');

        $this->assertEquals($expected, $r->invokeArgs($media, [$binary, $url, $path, $options]));
    }

    public static function dataForBuildCommand(): array
    {
        $theBinary = \PHP_BINARY;
        $escapedBinary = \escapeshellarg($theBinary);

        return [
            [
                $theBinary,
                'https://the.url/',
                '/the/path',
                [],
                $escapedBinary . ' ' . \escapeshellarg('https://the.url/') . ' ' . \escapeshellarg('/the/path'),
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
                $escapedBinary . ' ' . \escapeshellarg('https://the.url/') . ' ' . \escapeshellarg('/the/path'),
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
                $escapedBinary . ' --foo ' . \escapeshellarg('foovalue') . ' --bar ' . \escapeshellarg('barvalue1') . ' --bar ' . \escapeshellarg('barvalue2') . ' --baz ' . \escapeshellarg('https://the.url/') . ' ' . \escapeshellarg('/the/path'),
            ],
            [
                $theBinary,
                'https://the.url/',
                '/the/path',
                [
                    'attachment' => ['/path1', '/path2'],
                ],
                $escapedBinary . ' --attachment ' . \escapeshellarg('/path1') . ' --attachment ' . \escapeshellarg('/path2') . ' ' . \escapeshellarg('https://the.url/') . ' ' . \escapeshellarg('/the/path'),
            ],
        ];
    }

    public function testBuildCommandThrowsOnNonExecutableBinary(): void
    {
        $media = $this->createGenerator();

        $r = new \ReflectionMethod($media, 'buildCommand');

        $maliciousBinary = 'weasyprint; touch /tmp/pwn; #';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(\sprintf("The binary '%s' is not executable.", $maliciousBinary));

        $r->invokeArgs($media, [$maliciousBinary, 'https://the.url/', '/the/path', []]);
    }

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

        $r = new \ReflectionMethod($media, 'checkOutput');

        $message = '->checkOutput() checks both file existence and size';

        try {
            $r->invokeArgs($media, ['the_output_file', 'the command']);
            $this->anything();
        } catch (\RuntimeException $e) {
            $this->fail($message);
        }
    }

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

        $r = new \ReflectionMethod($media, 'checkOutput');

        $message = '->checkOutput() throws an InvalidArgumentException when the file does not exist';

        try {
            $r->invokeArgs($media, ['the_output_file', 'the command']);
            $this->fail($message);
        } catch (\RuntimeException $e) {
            $this->anything();
        }
    }

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

        $r = new \ReflectionMethod($media, 'checkOutput');

        $message = '->checkOutput() throws an InvalidArgumentException when the file is empty';

        try {
            $r->invokeArgs($media, ['the_output_file', 'the command']);
            $this->fail($message);
        } catch (\RuntimeException $e) {
            $this->anything();
        }
    }

    public function testCheckProcessStatus(): void
    {
        $media = $this->createGenerator();

        $r = new \ReflectionMethod($media, 'checkProcessStatus');

        try {
            $r->invokeArgs($media, [0, '', '', 'the command']);
            $this->anything();
        } catch (\RuntimeException $e) {
            $this->fail('0 status means success');
        }

        try {
            $r->invokeArgs($media, [1, '', 'Could not connect to X', 'the command']);
            $this->fail('1 status means failure');
        } catch (\RuntimeException $e) {
            $this->assertEquals(1, $e->getCode(), 'Exception thrown by checkProcessStatus should pass on the error code');
        }
    }

    public function testCheckProcessStatusRejectsFailureWithoutStderr(): void
    {
        $media = $this->createGenerator();
        $method = new \ReflectionMethod($media, 'checkProcessStatus');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1);
        $method->invoke($media, 1, '', '', 'the command');
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
            ->expects($this->once())
            ->method('fileExists')
            ->willReturn(true)
        ;
        $media
            ->expects($this->once())
            ->method('isFile')
            ->willReturn(true)
        ;
        $r = new \ReflectionMethod($media, 'prepareOutput');

        $r->invokeArgs($media, ['', false]);
    }

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

    #[DataProvider('dataForProtocolCheck')]
    public function testIsProtocolAllowed(string $filename, bool $expected): void
    {
        $media = $this->createGenerator();
        $r = new \ReflectionMethod($media, 'isProtocolAllowed');

        $this->assertSame($expected, $r->invokeArgs($media, [$filename]));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function dataForProtocolCheck(): array
    {
        return [
            'relative local path' => ['out.pdf', true],
            'absolute local path' => ['/tmp/out.pdf', true],
            'file scheme' => ['file:///tmp/out.pdf', true],
            'lowercase phar' => ['phar://exploit.phar', false],
            'uppercase phar (bypass)' => ['PHAR://exploit.phar', false],
            'mixed-case phar (bypass)' => ['PhAr://exploit.phar', false],
            'php filter wrapper' => ['php://filter/convert.base64-encode/resource=/etc/passwd', false],
            'remote http' => ['http://example.com/x.pdf', false],
            'remote https' => ['https://example.com/x.pdf', false],
            'ftp' => ['ftp://example.com/x', false],
        ];
    }

    #[DataProvider('dataForDisallowedOutputProtocol')]
    public function testPrepareOutputRejectsDisallowedProtocols(string $filename): void
    {
        $media = $this->createGenerator();
        $r = new \ReflectionMethod($media, 'prepareOutput');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The output file scheme is not supported.');

        $r->invokeArgs($media, [$filename, false]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function dataForDisallowedOutputProtocol(): array
    {
        return [
            'lowercase phar' => ['phar://the_output_file'],
            'uppercase phar (bypass)' => ['PHAR://the_output_file'],
            'php filter wrapper' => ['php://filter/resource=/tmp/x'],
            'remote http' => ['http://example.com/x.pdf'],
        ];
    }

    public function testGetCommandThrowsExceptionWhenBinaryNotSet(): void
    {
        $media = $this->createGenerator();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('You must define a binary prior to conversion.');

        $media->getCommand('input.html', 'output.pdf');
    }

    public function testGetFileContentsThrowsExceptionWhenFileCannotBeRead(): void
    {
        $media = $this->createGenerator();

        $this->expectException(\Pontedilana\PhpWeasyPrint\Exception\CouldNotReadFileContentException::class);
        $this->expectExceptionMessage('Could not read the contents of file \'/nonexistent/path/to/file.txt\'.');

        $r = new \ReflectionMethod($media, 'getFileContents');

        @$r->invokeArgs($media, ['/nonexistent/path/to/file.txt']);
    }

    public function testFilesizeThrowsExceptionWhenSizeCannotBeRead(): void
    {
        $media = $this->createGenerator();

        $this->expectException(\Pontedilana\PhpWeasyPrint\Exception\CouldNotReadFileSizeException::class);
        $this->expectExceptionMessage('Could not read the size of file \'/nonexistent/path/to/file.txt\'.');

        $r = new \ReflectionMethod($media, 'filesize');

        @$r->invokeArgs($media, ['/nonexistent/path/to/file.txt']);
    }

    public function testSetAndGetBinary(): void
    {
        $media = $this->createGenerator();

        $this->assertNull($media->getBinary(), '->getBinary() returns null by default');

        $media->setBinary('/usr/bin/weasyprint');
        $this->assertEquals('/usr/bin/weasyprint', $media->getBinary(), '->setBinary() sets the binary path');

        $media->setBinary('/opt/weasyprint');
        $this->assertEquals('/opt/weasyprint', $media->getBinary(), '->setBinary() updates the binary path');
    }

    public function testSetTimeout(): void
    {
        $media = $this->createGenerator();

        $result = $media->setTimeout(2000);

        $this->assertSame($media, $result, '->setTimeout() returns the generator instance');
    }

    public function testDisableTimeout(): void
    {
        $media = $this->createGenerator();

        $media->setTimeout(5000);
        $result = $media->disableTimeout();

        $this->assertSame($media, $result, '->disableTimeout() returns the generator instance');
    }

    public function testSetAndGetDefaultExtension(): void
    {
        $media = $this->createGenerator();

        $media->setDefaultExtension('pdf');
        $this->assertEquals('pdf', $media->getDefaultExtension(), '->setDefaultExtension() sets the default extension');

        $media->setDefaultExtension('png');
        $this->assertEquals('png', $media->getDefaultExtension(), '->setDefaultExtension() updates the default extension');
    }

    public function testSetAndGetTemporaryFolder(): void
    {
        $media = $this->createGenerator();

        $defaultTempFolder = $media->getTemporaryFolder();
        $this->assertEquals(\sys_get_temp_dir(), $defaultTempFolder, '->getTemporaryFolder() returns system temp dir by default');

        $media->setTemporaryFolder('/custom/temp');
        $this->assertEquals('/custom/temp', $media->getTemporaryFolder(), '->setTemporaryFolder() sets the temporary folder');
    }

    public function testGetOptions(): void
    {
        $media = $this->createGenerator();

        $options = $media->getOptions();
        $this->assertIsArray($options, '->getOptions() returns an array');
    }

    public function testExecuteCommand(): void
    {
        $media = $this->createGenerator();

        $r = new \ReflectionMethod($media, 'executeCommand');

        // Execute a simple command that should succeed
        $result = $r->invokeArgs($media, [[\PHP_BINARY, '-r', 'echo "test";']]);

        $this->assertIsArray($result, '->executeCommand() returns an array');
        $this->assertCount(3, $result, '->executeCommand() returns an array with 3 elements [status, stdout, stderr]');
        $this->assertEquals(0, $result[0], '->executeCommand() returns 0 exit code for successful command');
        $this->assertStringContainsString('test', $result[1], '->executeCommand() returns stdout output');
        $this->assertIsString($result[2], '->executeCommand() returns stderr as string');
    }

    public function testExecuteCommandWithFailure(): void
    {
        $media = $this->createGenerator();

        $r = new \ReflectionMethod($media, 'executeCommand');

        // Execute a command that should fail
        $result = $r->invokeArgs($media, [[\PHP_BINARY, '-r', 'exit(1);']]);

        $this->assertIsArray($result, '->executeCommand() returns an array');
        $this->assertEquals(1, $result[0], '->executeCommand() returns non-zero exit code for failed command');
    }

    private function createGenerator(): AbstractGenerator
    {
        return new class extends AbstractGenerator {
            protected function configure(): void
            {
            }
        };
    }
}
