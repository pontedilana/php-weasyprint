<?php

namespace xmarcos\PhpWeasyPrint;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Process\Process;
use xmarcos\PhpWeasyPrint\Exception\CouldNotReadFileContentException;
use xmarcos\PhpWeasyPrint\Exception\CouldNotReadFileSizeException;
use xmarcos\PhpWeasyPrint\Exception\FileAlreadyExistsException;

/**
 *  Base generator class for medias.
 *
 * @author  Matthieu Bontemps <matthieu.bontemps@knplabs.com>
 * @author  Antoine Hérault <antoine.herault@knplabs.com>
 * @author  Manuel Dalla Lana <manuel@pontedilana.it>
 */
abstract class AbstractGenerator implements GeneratorInterface, LoggerAwareInterface
{
    const DEFAULT_TIMEOUT = 10;
    public $temporaryFiles = [];
    protected $temporaryFolder = null;
    private $logger;
    private $defaultExtension;
    private $env;
    private $timeout = null;
    private $options = [];
    private $binary = null;

    public function __construct($binary = null, $options = [], $env = null)
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
    abstract protected function configure();

    /**
     * {@inheritdoc}
     */
    public function generate($input, $output, $options = [], $overwrite = false)
    {
        $this->prepareOutput($output, $overwrite);

        $command = $this->getCommand($input, $output, $options);

        $this->logger->info(\sprintf('Generate from file(s) "%s" to file "%s".', $input, $output), [
            'command' => $command,
            'env' => $this->env,
            'timeout' => $this->timeout,
        ]);

        try {
            list($status, $stdout, $stderr) = $this->executeCommand($command);
            $this->checkProcessStatus($status, $stdout, $stderr, $command);
            $this->checkOutput($output, $command);
        } catch (\Exception $e) {
            $this->logger->error(\sprintf('An error happened while generating "%s".', $output), [
                'command' => $command,
                'status' => isset($status) ? $status : null,
                'stdout' => isset($stdout) ? $stdout : null,
                'stderr' => isset($stderr) ? $stderr : null,
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
    public function generateFromHtml($html, $output, $options = [], $overwrite = false)
    {
        $fileName = $this->createTemporaryFile($html, 'html');

        $this->generate($fileName, $output, $options, $overwrite);
    }

    /**
     * {@inheritdoc}
     */
    public function getOutput($input, $options = [])
    {
        $filename = $this->createTemporaryFile(null, $this->getDefaultExtension());

        $this->generate($input, $filename, $options);

        return $this->getFileContents($filename);
    }

    /**
     * {@inheritdoc}
     */
    public function getOutputFromHtml($html, $options = [])
    {
        $fileName = $this->createTemporaryFile($html, 'html');

        return $this->getOutput($fileName, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Builds the command string.
     *
     * @param string $binary  The binary path/name
     * @param string $input   Url or file location of the page to process
     * @param string $output  File location to the pdf-or-image-to-be
     * @param array  $options An array of options
     */
    protected function buildCommand($binary, $input, $output, $options = [])
    {
        $command = $binary;
        $escapedBinary = \escapeshellarg($binary);
        if (\is_executable($escapedBinary)) {
            $command = $escapedBinary;
        }

        foreach ($options as $key => $option) {
            if (null !== $option && false !== $option) {
                if (true === $option) {
                    $command .= ' --' . $key;
                } elseif (\is_array($option)) {
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
                            $command .= ' --' . $key . ' ' . \escapeshellarg($option);
                            break;
                    }
                }
            }
        }

        return $command . (' ' . \escapeshellarg($input) . ' ' . \escapeshellarg($output));
    }

    /**
     * Executes the given command via shell and returns the complete output as
     * a string.
     *
     * @return array [status, stdout, stderr]
     */
    protected function executeCommand($command)
    {
        $process = new Process($command, null, $this->env);

        if (null !== $this->timeout) {
            $process->setTimeout($this->timeout);
        }

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
    protected function prepareOutput($filename, $overwrite)
    {
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

    public function getDefaultExtension()
    {
        return $this->defaultExtension;
    }

    public function setDefaultExtension($defaultExtension)
    {
        $this->defaultExtension = $defaultExtension;

        return $this;
    }

    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * Defines the binary.
     *
     * @param string|null $binary The path/name of the binary
     */
    public function setBinary($binary)
    {
        $this->binary = $binary;

        return $this;
    }

    /**
     * Returns the binary.
     */
    public function getBinary()
    {
        return $this->binary;
    }

    /**
     * Returns the command for the given input and output files.
     *
     * @param string $input   The input file
     * @param string $output  The ouput file
     * @param array  $options An optional array of options that will be used only for this command
     */
    public function getCommand($input, $output, $options = [])
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
    public function removeTemporaryFiles()
    {
        foreach ($this->temporaryFiles as $file) {
            $this->unlink($file);
        }
    }

    /**
     * Get TemporaryFolder.
     */
    public function getTemporaryFolder()
    {
        return !empty($this->temporaryFolder) ? $this->temporaryFolder : \sys_get_temp_dir();
    }

    /**
     * Set temporaryFolder.
     */
    public function setTemporaryFolder($temporaryFolder)
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
    protected function createTemporaryFile($content = null, $extension = null)
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
     * @param string $name  The option to set
     * @param mixed  $value The value (NULL to unset)
     *
     * @throws \InvalidArgumentException
     */
    public function setOption($name, $value)
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
     * @param $options An associative array of options as name/value
     */
    public function setOptions($options)
    {
        foreach ($options as $name => $value) {
            $this->setOption($name, $value);
        }

        return $this;
    }

    /**
     * Returns all the options.
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Adds an option.
     *
     * @param string $name    The name
     * @param mixed  $default An optional default value
     *
     * @throws \InvalidArgumentException
     */
    protected function addOption($name, $default = null)
    {
        if (\array_key_exists($name, $this->options)) {
            throw new \InvalidArgumentException(\sprintf('The option \'%s\' already exists.', $name));
        }

        $this->options[$name] = $default;

        return $this;
    }

    /**
     * Adds an array of options.
     */
    protected function addOptions($options)
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
     * @throws \InvalidArgumentException
     */
    protected function mergeOptions($options)
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
    public function resetOptions()
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
    protected function checkOutput($output, $command)
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
     * @param int    $status  The exit status code
     * @param string $stdout  The stdout content
     * @param string $stderr  The stderr content
     * @param string $command The run command
     *
     * @throws \RuntimeException if the output file generation failed
     */
    protected function checkProcessStatus($status, $stdout, $stderr, $command)
    {
        if (0 !== $status && '' !== $stderr) {
            throw new \RuntimeException(\sprintf('The exit status code \'%s\' says something went wrong:' . "\n" . 'stderr: "%s"' . "\n" . 'stdout: "%s"' . "\n" . 'command: %s.', $status, $stderr, $stdout, $command), $status);
        }
    }

    /**
     * Wrapper for the "file_get_contents" function.
     */
    protected function getFileContents($filename)
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
    protected function fileExists($filename)
    {
        return \file_exists($filename);
    }

    /**
     * Wrapper for the "is_file" method.
     */
    protected function isFile($filename)
    {
        return \strlen($filename) <= \PHP_MAXPATHLEN && \is_file($filename);
    }

    /**
     * Wrapper for the "filesize" function.
     */
    protected function filesize($filename)
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
    protected function unlink($filename)
    {
        return $this->fileExists($filename) && \unlink($filename);
    }

    /**
     * Wrapper for the "is_dir" function.
     */
    protected function isDir($filename)
    {
        return \is_dir($filename);
    }

    /**
     * Wrapper for the mkdir function.
     */
    protected function mkdir($pathname)
    {
        return \mkdir($pathname, 0777, true);
    }
}
