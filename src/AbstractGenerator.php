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
 *  Base class for media generators.
 *
 * @author  Matthieu Bontemps <matthieu.bontemps@knplabs.com>
 * @author  Antoine Hérault <antoine.herault@knplabs.com>
 * @author  Manuel Dalla Lana <manuel@pontedilana.it>
 */
abstract class AbstractGenerator implements GeneratorInterface, LoggerAwareInterface
{
    public const DEFAULT_TIMEOUT = 10;

    /** @var list<string> */
    protected const ALLOWED_PROTOCOLS = ['file'];

    protected const WINDOWS_LOCAL_FILENAME_REGEX = '/^[a-z]:(?:[\\\\\/]?(?:[\w\s!#()-]+|[\.]{1,2})+)*[\\\\\/]?/i';

    /** @var array<string, string> File paths mapped to their original directory. */
    private array $temporaryFiles = [];

    /** @var \WeakMap<self, true> */
    private static \WeakMap $instances;
    protected ?string $temporaryFolder = null;
    private LoggerInterface $logger;
    private string $defaultExtension;

    /** @var array<string, mixed>|null */
    private ?array $env;
    private ?int $timeout = null;

    /** @var array<string, bool|int|string|array|null> */
    private array $options = [];
    private ?string $binary = null;

    /**
     * @param array<string, bool|int|string|array|\BackedEnum|null> $options
     * @param array<string, mixed>|null                             $env
     *
     * @note
     *  This class sets a default timeout on the process to prevent
     *  orphaned or hanging processes. This is a defensive measure that applies
     *  in most cases.
     *
     *  If you run this inside a queue worker, job runner, or any environment
     *  that already handles timeouts (e.g. Symfony Messenger, Laravel Queue),
     *  you can disable the internal timeout using:
     *
     *      $generator->disableTimeout();
     *  or
     *      $generator->setTimeout(null);
     *
     *  This ensures no conflicts with higher-level timeout strategies.
     */
    public function __construct(?string $binary = null, array $options = [], ?array $env = null)
    {
        $this->configure();

        $this->logger = new NullLogger();
        $this->setBinary($binary);
        $this->setTimeout(self::DEFAULT_TIMEOUT);
        $this->setOptions($options);
        $this->env = empty($env) ? null : $env;

        if (!isset(self::$instances)) {
            self::$instances = new \WeakMap();
            \register_shutdown_function(static function(): void {
                foreach (self::$instances as $generator => $registered) {
                    $generator->removeTemporaryFiles();
                }
            });
        }
        self::$instances[$this] = true;
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

        if (null === $this->binary) {
            throw new \LogicException('You must define a binary prior to conversion.');
        }

        $temporaryOutput = null;
        try {
            if ($overwrite && $this->fileExists($output) && !isset($this->temporaryFiles[$output])) {
                $temporaryOutput = $this->createOutputTemporaryFile($output);
            }
            $renderOutput = $temporaryOutput ?? $output;

            $options = $this->mergeOptions($options);
            $commandArray = $this->buildCommandArray($this->binary, $input, $renderOutput, $options);
            // String form is kept for logging and exception messages only; the
            // process is executed from the argument array, never through a shell.
            $command = $this->buildCommand($this->binary, $input, $renderOutput, $options);

            $this->logger->info(\sprintf('Generate from file(s) "%s" to file "%s".', $input, $output), [
                'command' => $command,
                'env' => $this->env,
                'timeout' => $this->timeout,
            ]);

            $status = null;
            $stdout = $stderr = '';
            try {
                [$status, $stdout, $stderr] = $this->executeCommand($commandArray);
                $this->checkProcessStatus($status, $stdout, $stderr, $command);
                $this->checkOutput($renderOutput, $command);
                if (null !== $temporaryOutput && !@\rename($temporaryOutput, $output)) {
                    throw new \RuntimeException(\sprintf("Could not replace the output file '%s'.", $output));
                }
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
        } finally {
            if (null !== $temporaryOutput && $this->fileExists($temporaryOutput)) {
                $this->unlink($temporaryOutput);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function generateFromHtml(string $html, string $output, array $options = [], bool $overwrite = false): void
    {
        $existingFiles = $this->getTemporaryFiles();
        try {
            $fileName = $this->createTemporaryFile($html, 'html');

            $this->generate($fileName, $output, $options, $overwrite);
        } finally {
            $this->removeTemporaryFilesAfter($existingFiles);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getOutput(string $input, array $options = []): string
    {
        $existingFiles = $this->getTemporaryFiles();
        try {
            $filename = $this->createTemporaryFile(null, $this->getDefaultExtension());

            $this->generate($input, $filename, $options, true);

            return $this->getFileContents($filename);
        } finally {
            $this->removeTemporaryFilesAfter($existingFiles);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getOutputFromHtml(string $html, array $options = []): string
    {
        $existingFiles = $this->getTemporaryFiles();
        try {
            $fileName = $this->createTemporaryFile($html, 'html');

            return $this->getOutput($fileName, $options);
        } finally {
            $this->removeTemporaryFilesAfter($existingFiles);
        }
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Builds the command string.
     *
     * @param string                                    $binary  The binary path/name
     * @param string                                    $input   URL or path of the input document
     * @param string                                    $output  Path to the output file
     * @param array<string, bool|int|string|array|null> $options An array of options
     */
    protected function buildCommand(string $binary, string $input, string $output, array $options = []): string
    {
        $command = $this->getEscapedBinary($binary);

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
                $command .= ' --' . $key . ' ' . \escapeshellarg((string)$option);
            }
        }

        return $command . (' ' . \escapeshellarg($input) . ' ' . \escapeshellarg($output));
    }

    /**
     * Builds the command as a list of arguments, ready to be passed to
     * Symfony Process without going through a shell. This is the form used for
     * actual execution: arguments reach the binary verbatim, so no shell
     * metacharacter can be interpreted and no escaping is required.
     *
     * @param string                                    $binary  The binary path/name
     * @param string                                    $input   URL or path of the input document
     * @param string                                    $output  Path to the output file
     * @param array<string, bool|int|string|array|null> $options An array of options
     *
     * @return list<string>
     */
    protected function buildCommandArray(string $binary, string $input, string $output, array $options = []): array
    {
        $this->checkBinary($binary);

        $command = [$binary];

        foreach ($options as $key => $option) {
            if (null === $option || false === $option) {
                continue;
            }

            if (true === $option) {
                $command[] = '--' . $key;
                continue;
            }

            if (\is_array($option)) {
                foreach ($option as $v) {
                    $command[] = '--' . $key;
                    $command[] = (string)$v;
                }
            } else {
                $command[] = '--' . $key;
                $command[] = (string)$option;
            }
        }

        $command[] = $input;
        $command[] = $output;

        return $command;
    }

    /**
     * Verifies the binary points to a real executable file, then returns it shell-escaped.
     * Validating before escaping prevents shell-command injection through an
     * attacker-controlled binary string.
     *
     * @throws \RuntimeException if the binary is not an executable file
     */
    protected function getEscapedBinary(string $binary): string
    {
        $this->checkBinary($binary);

        return \escapeshellarg($binary);
    }

    /**
     * @throws \RuntimeException if the binary is not an executable file
     */
    protected function checkBinary(string $binary): void
    {
        if (!\is_executable($binary)) {
            throw new \RuntimeException(\sprintf("The binary '%s' is not executable.", $binary));
        }
    }

    /**
     * Executes the command without a shell and returns the exit code,
     * standard output, and standard error.
     *
     * @param list<string> $command
     *
     * @return array{int|null, string, string} [status, stdout, stderr]
     */
    protected function executeCommand(array $command): array
    {
        $process = new Process($command, null, $this->env, null, $this->timeout);
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
        if (!$this->isProtocolAllowed($filename)) {
            throw new \InvalidArgumentException(\sprintf("The output file scheme is not supported. Expected one of ['%s'].", \implode("', '", self::ALLOWED_PROTOCOLS)));
        }

        $directory = \dirname($filename);

        if ($this->fileExists($filename)) {
            if (!$this->isFile($filename)) {
                throw new \InvalidArgumentException(\sprintf('The output file \'%s\' already exists and it is a %s.', $filename, $this->isDir($filename) ? 'directory' : 'link'));
            }
            if (false === $overwrite) {
                throw new FileAlreadyExistsException(\sprintf('The output file \'%s\' already exists.', $filename));
            }
        } elseif (!$this->isDir($directory) && !$this->mkdir($directory)) {
            throw new \RuntimeException(\sprintf('The output file\'s directory \'%s\' could not be created.', $directory));
        }
    }

    /**
     * Reserves a sibling file so failed generation cannot damage an existing output.
     */
    private function createOutputTemporaryFile(string $output): string
    {
        $filename = \dirname($output) . \DIRECTORY_SEPARATOR . 'php_weasyprint_output_' . \bin2hex(\random_bytes(16)) . '.pdf';
        $handle = @\fopen($filename, 'xb');
        if (false === $handle) {
            throw new \RuntimeException(\sprintf("Could not create a temporary output file in '%s'.", \dirname($output)));
        }
        \fclose($handle);

        return $filename;
    }

    /**
     * Verifies that the given filename uses an allowed protocol.
     *
     * A scheme allow-list (instead of a `phar://` blacklist) closes the
     * case-insensitive wrapper bypass that would otherwise reach file_exists()
     * with e.g. `PHAR://`, enabling PHAR deserialization on PHP < 8.
     *
     * @throws \InvalidArgumentException if the filename is not valid
     */
    protected function isProtocolAllowed(string $filename): bool
    {
        if (false === $parsedFilename = \parse_url($filename)) {
            throw new \InvalidArgumentException('The filename is not valid.');
        }

        $protocol = isset($parsedFilename['scheme']) ? \strtolower($parsedFilename['scheme']) : 'file';

        if (
            'Windows' === \PHP_OS_FAMILY
            && 1 === \strlen($protocol)
            && 1 === \preg_match(self::WINDOWS_LOCAL_FILENAME_REGEX, $filename)
        ) {
            $protocol = 'file';
        }

        return \in_array($protocol, self::ALLOWED_PROTOCOLS, true);
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

    public function disableTimeout(): self
    {
        return $this->setTimeout(null);
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
     * @param string                                                $input   The input file
     * @param string                                                $output  The output file
     * @param array<string, bool|int|string|array|\BackedEnum|null> $options An optional array of options that will be used only for this command
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
     * Returns a snapshot of the files currently owned by this generator.
     *
     * @return list<string>
     */
    public function getTemporaryFiles(): array
    {
        return \array_keys($this->temporaryFiles);
    }

    /**
     * Removes owned temporary files. Failed deletions remain registered for retry.
     */
    public function removeTemporaryFiles(): void
    {
        $this->removeTemporaryFilesAfter([]);
    }

    /**
     * Cleans up files created since the supplied snapshot, preserving outer calls.
     *
     * @param list<string> $existingFiles
     */
    protected function removeTemporaryFilesAfter(array $existingFiles): void
    {
        foreach (\array_diff(\array_keys($this->temporaryFiles), $existingFiles) as $file) {
            // Do not follow a parent directory that has been replaced by a symlink.
            \clearstatcache(true);
            if (\realpath(\dirname($file)) !== $this->temporaryFiles[$file]) {
                continue;
            }
            if ((!$this->fileExists($file) && !\is_link($file)) || @$this->unlink($file)) {
                unset($this->temporaryFiles[$file]);
            }
        }
    }

    /**
     * Gets the temporary folder.
     */
    public function getTemporaryFolder(): string
    {
        return $this->temporaryFolder ?? \sys_get_temp_dir();
    }

    /**
     * Sets the temporary folder.
     */
    public function setTemporaryFolder(string $temporaryFolder): self
    {
        $this->temporaryFolder = $temporaryFolder;

        return $this;
    }

    /**
     * Reserves a temporary file and optionally writes content to it.
     * Null content creates an empty file reserved for generated output.
     */
    protected function createTemporaryFile(?string $content = null, ?string $extension = null): string
    {
        if (null !== $extension && (\str_contains($extension, '/') || \str_contains($extension, '\\') || \str_contains($extension, "\0"))) {
            throw new \InvalidArgumentException('The temporary file extension must not contain path separators or null bytes.');
        }
        $dir = $this->getTemporaryFolder();
        if (!\is_dir($dir)) {
            if (false === @\mkdir($dir, 0777, true) && !\is_dir($dir)) {
                throw new \RuntimeException(\sprintf("Unable to create directory: %s\n", $dir));
            }
        }
        $directory = \realpath($dir);
        if (false === $directory || !\is_writable($directory)) {
            throw new \RuntimeException(\sprintf("Unable to write in directory: %s\n", $dir));
        }

        $filename = \rtrim($directory, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR . 'php_weasyprint' . \bin2hex(\random_bytes(16));
        if (null !== $extension && '' !== $extension) {
            $filename .= '.' . $extension;
        }
        $handle = @\fopen($filename, 'xb');
        if (false === $handle) {
            throw new \RuntimeException(\sprintf("Unable to create temporary file: %s\n", $filename));
        }
        $existingFiles = $this->getTemporaryFiles();
        $this->temporaryFiles[$filename] = $directory;
        try {
            try {
                if (null !== $content && \strlen($content) !== @\fwrite($handle, $content)) {
                    throw new \RuntimeException(\sprintf("Unable to write temporary file: %s\n", $filename));
                }
            } finally {
                \fclose($handle);
            }
        } catch (\Throwable $exception) {
            $this->removeTemporaryFilesAfter($existingFiles);
            throw $exception;
        }

        return $filename;
    }

    /**
     * Sets an option. Value validation is delegated to the concrete generator
     * through validateOptionValue(); the default implementation does nothing.
     * Callers remain responsible for validating user input.
     *
     * @param string                                 $name  The option to set
     * @param bool|int|string|array|\BackedEnum|null $value The value (NULL to unset). Backed enums (e.g. PdfVariant, MediaType) are accepted and converted to their scalar value.
     *
     * @throws \InvalidArgumentException
     */
    public function setOption(string $name, $value): self
    {
        if (!\array_key_exists($name, $this->options)) {
            throw new \InvalidArgumentException(\sprintf('The option \'%s\' does not exist.', $name));
        }

        $value = $this->normalizeOptionValue($value);

        $this->validateOptionValue($name, $value);

        $this->options[$name] = $value;

        $this->logger->debug(\sprintf('Set option "%s".', $name), ['value' => $value]);

        return $this;
    }

    /**
     * Sets an array of options.
     *
     * @param array<string, bool|int|string|array|\BackedEnum|null> $options An associative array of options as name/value
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
     * @return array<string, bool|int|string|array|null>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Adds an option.
     *
     * @param string                     $name    The option name
     * @param bool|int|string|array|null $default An optional default value
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
     * @param array<string, bool|int|string|array|null> $options
     */
    protected function addOptions(array $options): self
    {
        foreach ($options as $name => $default) {
            $this->addOption($name, $default);
        }

        return $this;
    }

    /**
     * Merges the given array of options with the instance options and returns
     * the resulting options array. It does NOT change the instance options.
     *
     * @param array<string, bool|int|string|array|\BackedEnum|null> $options
     *
     * @return array<string, bool|int|string|array|null>
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

            $value = $this->normalizeOptionValue($value);

            $this->validateOptionValue($name, $value);

            $mergedOptions[$name] = $value;
        }

        return $mergedOptions;
    }

    /**
     * Converts backed enum option values (and arrays of them) to their scalar
     * value, so the rest of the pipeline only ever deals with scalars.
     *
     * @param bool|int|string|array|\BackedEnum|null $value
     *
     * @return bool|int|string|array|null
     */
    private function normalizeOptionValue($value)
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if (\is_array($value)) {
            return \array_map(
                static fn($item) => $item instanceof \BackedEnum ? $item->value : $item,
                $value
            );
        }

        return $value;
    }

    /**
     * Hook for concrete generators to validate an option value before it is
     * stored or merged. No-op by default; override to constrain values.
     *
     * @param bool|int|string|array|null $value
     *
     * @throws \InvalidArgumentException if the value is not allowed for the option
     */
    protected function validateOptionValue(string $name, $value): void
    {
    }

    /**
     * Resets all options to their initial values.
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
        \clearstatcache(true, $output);
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
            throw new \RuntimeException(\sprintf('The process exit code is unavailable.' . "\n" . 'stderr: "%s"' . "\n" . 'stdout: "%s"' . "\n" . 'command: %s', $stderr, $stdout, $command));
        }

        if (0 !== $status) {
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
            throw new CouldNotReadFileContentException(\sprintf('Could not read the contents of file \'%s\'.', $filename));
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
            throw new CouldNotReadFileSizeException(\sprintf('Could not read the size of file \'%s\'.', $filename));
        }

        return $filesize;
    }

    /**
     * Wrapper for the "unlink" function.
     */
    protected function unlink(string $filename): bool
    {
        return ($this->fileExists($filename) || \is_link($filename)) && \unlink($filename);
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
