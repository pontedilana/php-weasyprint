<?php

namespace Pontedilana\PhpWeasyPrint;

use Pontedilana\PhpWeasyPrint\Exception\CouldNotReadFileContentException;
use Pontedilana\PhpWeasyPrint\Exception\CouldNotReadFileSizeException;
use Pontedilana\PhpWeasyPrint\Exception\FileAlreadyExistsException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Process\Process;

/**
 *  Base generator class for medias.
 *
 * @author  Matthieu Bontemps <matthieu.bontemps@knplabs.com>
 * @author  Antoine Hérault <antoine.herault@knplabs.com>
 * @author  Manuel Dalla Lana <manuel@pontedilana.it>
 */
abstract class AbstractGenerator implements GeneratorInterface, LoggerAwareInterface
{
    public const DEFAULT_TIMEOUT = 10;

    /** @var list<string> */
    public array $temporaryFiles = [];
    protected ?string $temporaryFolder = null;
    private LoggerInterface $logger;
    private string $defaultExtension;

    /** @var array<string, mixed>|null */
    private ?array $env;
    private ?int $timeout = null;

    /** @var array<string, bool|string|array|null> */
    private array $options = [];
    private ?string $binary = null;

    /**
     * @param array<string, bool|string|array|null> $options
     * @param array<string, mixed>|null             $env
     */
    public function __construct(string $binary = null, array $options = [], array $env = null)
    {
        $this->configure();

        $this->logger = new NullLogger();
        $this->setBinary($binary);
        $this->setOptions($options);
        $this->setTimeout(self::DEFAULT_TIMEOUT);
        $this->env = empty($env) ? null : $env;

        if (\is_callable([$this, 'removeTemporaryFiles'])) {
            \register_shutdown_function([$this, 'removeTemporaryFiles']);
        }
    }

    public function __destruct()
    {
        $this->removeTemporaryFiles();
    }

    /**
     * This method must configure the media options.
     *
     * @see AbstractGenerator::addOption()
     */
    abstract protected function configure(): void;

    /**
     * {@inheritdoc}
     */
    public function generate(string $input, string $output, array $options = [], bool $overwrite = false): void
    {
        $this->prepareOutput($output, $overwrite);

        $command = $this->getCommand($input, $output, $options);

        $this->logger->info(\sprintf('Generate from file(s) "%s" to file "%s".', $input, $output), [
            'command' => $command,
            'env' => $this->env,
            'timeout' => $this->timeout,
        ]);

        $status = null;
        $stdout = $stderr = '';
        try {
            [$status, $stdout, $stderr] = $this->executeCommand($command);
            $this->checkProcessStatus($status, $stdout, $stderr, $command);
            $this->checkOutput($output, $command);
        } catch (\Exception $e) {
            $this->logger->error(\sprintf('An error happened while generating "%s".', $output), [
                'command' => $command,
                'status' => $status,
                'stdout' => $stdout,
                'stderr' => $stderr,
            ]);

            throw $e;
        }

        $this->logger->info(\sprintf('File "%s" has been successfully generated.', $output), [
            'command' => $command,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function generateFromHtml(string $html, string $output, array $options = [], bool $overwrite = false): void
    {
        $fileName = $this->createTemporaryFile($html, 'html');

        $this->generate($fileName, $output, $options, $overwrite);
    }

    /**
     * {@inheritdoc}
     */
    public function getOutput(string $input, array $options = []): string
    {
        $filename = $this->createTemporaryFile(null, $this->getDefaultExtension());

        $this->generate($input, $filename, $options);

        return $this->getFileContents($filename);
    }

    /**
     * {@inheritdoc}
     */
    public function getOutputFromHtml(string $html, array $options = []): string
    {
        $fileName = $this->createTemporaryFile($html, 'html');

        return $this->getOutput($fileName, $options);
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Builds the command string.
     *
     * @param string                                $binary  The binary path/name
     * @param string                                $input   Url or file location of the page to process
     * @param string                                $output  File location to the pdf-or-image-to-be
     * @param array<string, bool|string|array|null> $options An array of options
     */
    protected function buildCommand(string $binary, string $input, string $output, array $options = []): string
    {
        $escapedBinary = \escapeshellarg($binary);
        $command = \is_executable($escapedBinary) ? $escapedBinary : $binary;

        foreach ($options as $key => $option) {
            if (null === $option || false === $option) {
                continue;
            }

            if (true === $option) {
                $command .= ' --' . $key;
                continue;
            }

            if (\is_array($option)) {
                foreach ($option as $v) {
                    $command .= ' --' . $key . ' ' . \escapeshellarg($v);
                }
            } else {
                switch ($key) {
                    case 'format':
                        $command .= ' --' . $key . ' ' . $option;
                        break;
                    case 'resolution':
                        $command .= ' --' . $key . ' ' . (int)$option;
                        break;
                    default:
                        $command .= ' --' . $key . ' ' . \escapeshellarg((string)$option);
                        break;
                }
            }
        }

        return $command . (' ' . \escapeshellarg($input) . ' ' . \escapeshellarg($output));
    }

    /**
     * Executes the given command via shell and returns the complete output as
     * a string.
     *
     * @return array{int|null, string, string} [status, stdout, stderr]
     */
    protected function executeCommand(string $command): array
    {
        $process = Process::fromShellCommandline($command, null, $this->env, null, $this->timeout);
        $process->run();

        return [
            $process->getExitCode(),
            $process->getOutput(),
            $process->getErrorOutput(),
        ];
    }

    /**
     * Prepares the specified output.
     *
     * @param string $filename  The output filename
     * @param bool   $overwrite Whether to overwrite the file if it already exists
     *
     * @throws FileAlreadyExistsException
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    protected function prepareOutput(string $filename, bool $overwrite): void
    {
        if (0 === \strpos($filename, 'phar://')) {
            throw new \InvalidArgumentException('The output file cannot be a phar archive.');
        }

        $directory = \dirname($filename);

        if ($this->fileExists($filename)) {
            if (!$this->isFile($filename)) {
                throw new \InvalidArgumentException(\sprintf('The output file \'%s\' already exists and it is a %s.', $filename, $this->isDir($filename) ? 'directory' : 'link'));
            }
            if (false === $overwrite) {
                throw new FileAlreadyExistsException(\sprintf('The output file \'%s\' already exists.', $filename));
            }
            if (!$this->unlink($filename)) {
                throw new \RuntimeException(\sprintf('Could not delete already existing output file \'%s\'.', $filename));
            }
        } elseif (!$this->isDir($directory) && !$this->mkdir($directory)) {
            throw new \RuntimeException(\sprintf('The output file\'s directory \'%s\' could not be created.', $directory));
        }
    }

    public function getDefaultExtension(): string
    {
        return $this->defaultExtension;
    }

    public function setDefaultExtension(string $defaultExtension): self
    {
        $this->defaultExtension = $defaultExtension;

        return $this;
    }

    public function setTimeout(?int $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * Defines the binary.
     *
     * @param string|null $binary The path/name of the binary
     */
    public function setBinary(?string $binary): self
    {
        $this->binary = $binary;

        return $this;
    }

    /**
     * Returns the binary.
     */
    public function getBinary(): ?string
    {
        return $this->binary;
    }

    /**
     * Returns the command for the given input and output files.
     *
     * @param string                                $input   The input file
     * @param string                                $output  The ouput file
     * @param array<string, bool|string|array|null> $options An optional array of options that will be used only for this command
     */
    public function getCommand(string $input, string $output, array $options = []): string
    {
        if (null === $this->binary) {
            throw new \LogicException('You must define a binary prior to conversion.');
        }

        $options = $this->mergeOptions($options);

        return $this->buildCommand($this->binary, $input, $output, $options);
    }

    /**
     * Removes all temporary files.
     */
    public function removeTemporaryFiles(): void
    {
        foreach ($this->temporaryFiles as $file) {
            $this->unlink($file);
        }
    }

    /**
     * Get TemporaryFolder.
     */
    public function getTemporaryFolder(): string
    {
        return $this->temporaryFolder ?? \sys_get_temp_dir();
    }

    /**
     * Set temporaryFolder.
     */
    public function setTemporaryFolder(string $temporaryFolder): self
    {
        $this->temporaryFolder = $temporaryFolder;

        return $this;
    }

    /**
     * Creates a temporary file.
     * The file is not created if the $content argument is null.
     *
     * @param string|null $content   Optional content for the temporary file
     * @param string|null $extension An optional extension for the filename
     *
     * @return string The filename
     */
    protected function createTemporaryFile(?string $content = null, ?string $extension = null): string
    {
        $dir = \rtrim($this->getTemporaryFolder(), \DIRECTORY_SEPARATOR);

        if (!\is_dir($dir)) {
            if (false === @\mkdir($dir, 0777, true) && !\is_dir($dir)) {
                throw new \RuntimeException(\sprintf("Unable to create directory: %s\n", $dir));
            }
        } elseif (!\is_writable($dir)) {
            throw new \RuntimeException(\sprintf("Unable to write in directory: %s\n", $dir));
        }

        $filename = $dir . \DIRECTORY_SEPARATOR . \uniqid('php_weasyprint', true);

        if (null !== $extension) {
            $filename .= '.' . $extension;
        }

        if (null !== $content) {
            \file_put_contents($filename, $content);
        }

        $this->temporaryFiles[] = $filename;

        return $filename;
    }

    /**
     * Sets an option. Be aware that option values are NOT validated and that
     * it is your responsibility to validate user inputs.
     *
     * @param string                 $name  The option to set
     * @param bool|string|array|null $value The value (NULL to unset)
     *
     * @throws \InvalidArgumentException
     */
    public function setOption(string $name, $value): self
    {
        if (!\array_key_exists($name, $this->options)) {
            throw new \InvalidArgumentException(\sprintf('The option \'%s\' does not exist.', $name));
        }

        $this->options[$name] = $value;

        $this->logger->debug(\sprintf('Set option "%s".', $name), ['value' => $value]);

        return $this;
    }

    /**
     * Sets an array of options.
     *
     * @param array<string, bool|string|array|null> $options An associative array of options as name/value
     */
    public function setOptions(array $options): self
    {
        foreach ($options as $name => $value) {
            $this->setOption($name, $value);
        }

        return $this;
    }

    /**
     * Returns all the options.
     *
     * @return array<string, bool|string|array|null>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Adds an option.
     *
     * @param string                 $name    The name
     * @param bool|string|array|null $default An optional default value
     *
     * @throws \InvalidArgumentException
     */
    protected function addOption(string $name, $default = null): self
    {
        if (\array_key_exists($name, $this->options)) {
            throw new \InvalidArgumentException(\sprintf('The option \'%s\' already exists.', $name));
        }

        $this->options[$name] = $default;

        return $this;
    }

    /**
     * Adds an array of options.
     *
     * @param array<string, bool|string|array|null> $options
     */
    protected function addOptions(array $options): self
    {
        foreach ($options as $name => $default) {
            $this->addOption($name, $default);
        }

        return $this;
    }

    /**
     * Merges the given array of options to the instance options and returns
     * the result options array. It does NOT change the instance options.
     *
     * @param array<string, bool|string|array|null> $options
     *
     * @return array<string, bool|string|array|null>
     *
     * @throws \InvalidArgumentException
     */
    protected function mergeOptions(array $options): array
    {
        $mergedOptions = $this->options;

        foreach ($options as $name => $value) {
            if (!\array_key_exists($name, $mergedOptions)) {
                throw new \InvalidArgumentException(\sprintf('The option \'%s\' does not exist.', $name));
            }

            $mergedOptions[$name] = $value;
        }

        return $mergedOptions;
    }

    /**
     * Reset all options to their initial values.
     */
    public function resetOptions(): void
    {
        $this->options = [];
        $this->configure();
    }

    /**
     * Checks the specified output.
     *
     * @param string $output  The output filename
     * @param string $command The generation command
     *
     * @throws \RuntimeException if the output file generation failed
     */
    protected function checkOutput(string $output, string $command): void
    {
        // the output file must exist
        if (!$this->fileExists($output)) {
            throw new \RuntimeException(\sprintf('The file \'%s\' was not created (command: %s).', $output, $command));
        }

        // the output file must not be empty
        if (0 === $this->filesize($output)) {
            throw new \RuntimeException(\sprintf('The file \'%s\' was created but is empty (command: %s).', $output, $command));
        }
    }

    /**
     * Checks the process return status.
     *
     * @param ?int   $status  The exit status code
     * @param string $stdout  The stdout content
     * @param string $stderr  The stderr content
     * @param string $command The run command
     *
     * @throws \RuntimeException if the output file generation failed
     */
    protected function checkProcessStatus(?int $status, string $stdout, string $stderr, string $command): void
    {
        if (null === $status) {
            throw new \RuntimeException(\sprintf('The command is not terminated.' . "\n" . 'stderr: "%s"' . "\n" . 'stdout: "%s"' . "\n" . 'command: %s', $stderr, $stdout, $command));
        }

        if (0 !== $status && '' !== $stderr) {
            throw new \RuntimeException(\sprintf('The exit status code \'%s\' says something went wrong:' . "\n" . 'stderr: "%s"' . "\n" . 'stdout: "%s"' . "\n" . 'command: %s', $status, $stderr, $stdout, $command), $status);
        }
    }

    /**
     * Wrapper for the "file_get_contents" function.
     */
    protected function getFileContents(string $filename): string
    {
        $fileContent = \file_get_contents($filename);

        if (false === $fileContent) {
            throw new CouldNotReadFileContentException(\sprintf('Could not read file \'%s\' content.', $filename));
        }

        return $fileContent;
    }

    /**
     * Wrapper for the "file_exists" function.
     */
    protected function fileExists(string $filename): bool
    {
        return \file_exists($filename);
    }

    /**
     * Wrapper for the "is_file" method.
     */
    protected function isFile(string $filename): bool
    {
        return \strlen($filename) <= \PHP_MAXPATHLEN && \is_file($filename);
    }

    /**
     * Wrapper for the "filesize" function.
     */
    protected function filesize(string $filename): int
    {
        $filesize = \filesize($filename);

        if (false === $filesize) {
            throw new CouldNotReadFileSizeException(\sprintf('Could not read file \'%s\' size.', $filename));
        }

        return $filesize;
    }

    /**
     * Wrapper for the "unlink" function.
     */
    protected function unlink(string $filename): bool
    {
        return $this->fileExists($filename) && \unlink($filename);
    }

    /**
     * Wrapper for the "is_dir" function.
     */
    protected function isDir(string $filename): bool
    {
        return \is_dir($filename);
    }

    /**
     * Wrapper for the mkdir function.
     */
    protected function mkdir(string $pathname): bool
    {
        return \mkdir($pathname, 0777, true);
    }
}
