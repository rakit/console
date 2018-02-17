<?php

namespace Rakit\Console;

use Closure;
use InvalidArgumentException;
use Exception;
use RuntimeException;

class App
{
    use Concerns\InputUtils;

    protected static $stty;
    protected static $shell;

    protected $filename;
    protected $command;
    protected $arguments = [];
    protected $options = [];
    protected $optionsAlias = [];
    protected $commands = [];
    protected $resolvedOptions = [];

    protected $foregroundColors = [
        'black' => '0;30',
        'dark_gray' => '1;30',
        'blue' => '0;34',
        'light_blue' => '1;34',
        'green' => '0;32',
        'light_green' => '1;32',
        'cyan' => '0;36',
        'light_cyan' => '1;36',
        'red' => '0;31',
        'light_red' => '1;31',
        'purple' => '0;35',
        'light_purple' => '1;35',
        'brown' => '0;33',
        'yellow' => '1;33',
        'light_gray' => '0;37',
        'white' => '1;37',
    ];

    protected $backgroundColors = [
        'black' => '40',
        'red' => '41',
        'green' => '42',
        'yellow' => '43',
        'blue' => '44',
        'magenta' => '45',
        'cyan' => '46',
        'light_gray' => '47',
    ];

    /**
     * Constructor
     */
    public function __construct(array $argv = null)
    {
        if (is_null($argv)) {
            $argv = $GLOBALS['argv'];
        }

        list(
            $this->filename,
            $this->command,
            $this->arguments,
            $this->options,
            $this->optionsAlias
        ) = $this->parseArgv($argv);

        $this->register(new CommandList);
    }

    /**
     * Register command
     *
     * @param Command $command
     */
    public function register(Command $command)
    {
        list($commandName, $args, $options) = $this->parseCommand($command->getSignature());

        if (!$commandName) {
            $class = get_class($command);
            throw new InvalidArgumentException("Command '{$class}' must have a name defined in signature");
        }

        if (!method_exists($command, 'handle')) {
            $class = get_class($command);
            throw new InvalidArgumentException("Command '{$class}' must have method handle");
        }

        $command->defineApp($this);

        $this->commands[$commandName] = [
            'handler' => [$command, 'handle'],
            'description' => $command->getDescription(),
            'args' => $args,
            'options' => $options
        ];
    }

    /**
     * Register closure command
     *
     * @param string $signature     command signature
     * @param string $description   command description
     * @param Closure $handler      command handler
     */
    public function command($signature, $description, Closure $handler)
    {
        list($commandName, $args, $options) = $this->parseCommand($signature);

        $this->commands[$commandName] = [
            'handler' => $handler,
            'description' => $description,
            'args' => $args,
            'options' => $options
        ];
    }

    /**
     * Get registered commands
     *
     * @return array
     */
    public function getRegisteredCommands()
    {
        return $this->commands;
    }

    /**
     * Get commands like given keyword
     *
     * @param string $keyword
     * @return array
     */
    public function getCommandsLike($keyword)
    {
        $regex = preg_quote($keyword);
        $commands = $this->getRegisteredCommands();
        $matchedCommands = [];
        foreach ($commands as $name => $command) {
            if ((bool) preg_match("/".$regex."/", $name)) {
                $matchedCommands[$name] = $command;
            }
        }
        return $matchedCommands;
    }

    /**
     * Get filename
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * Get options
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Get arguments
     *
     * @return array
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * Run app
     */
    public function run()
    {
        return $this->execute($this->command);
    }

    /**
     * Execute command
     *
     * @param string $command command name
     */
    public function execute($command)
    {
        if (!$command) {
            $command = "list";
        }

        if (!isset($this->commands[$command])) {
            return $this->showCommandsLike($command);
        }

        if (array_key_exists('help', $this->options) OR array_key_exists('h', $this->optionsAlias)) {
            return $this->showHelp($command);
        }

        try {
            $handler = $this->commands[$command]['handler'];
            $arguments = $this->validateAndResolveArguments($command);
            $this->validateAndResolveOptions($command);

            if ($handler instanceof \Closure) {
                $handler = $handler->bindTo($this);
            }

            call_user_func_array($handler, $arguments);
        } catch(Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Get option by given key
     *
     * @param string $key
     * @return mixed
     */
    public function option($key)
    {
        return isset($this->resolvedOptions[$key])? $this->resolvedOptions[$key] : null;
    }

    /**
     * Write text
     *
     * @param string $message
     * @param string $fgColor
     * @param string $bgColor
     */
    public function write($message, $fgColor = null, $bgColor = null)
    {
        if ($fgColor OR $bgColor) {
            $message = $this->color($message, $fgColor, $bgColor);
        }
        print($message);
    }

    /**
     * Write text line
     *
     * @param string $message
     * @param string $fgColor
     * @param string $bgColor
     */
    public function writeln($message, $fgColor = null, $bgColor = null)
    {
        return $this->write($message.PHP_EOL, $fgColor, $bgColor);
    }

    /**
     * Write error message
     *
     * @param string $message
     * @param string $fgColor
     * @param string $bgColor
     */
    public function error($message, $exit = true)
    {
        $this->writeln($message, 'red');
        if ($exit) exit();
    }

    /**
     * Coloring text
     *
     * @param string $text
     * @param string $fgColor
     * @param string $bgColor
     */
    public function color($text, $fgColor, $bgColor = null)
    {
        if ($this->isWindows()) {
            return $text;
        }

        $coloredString = "";
        $colored = false;

        // Check if given foreground color found
        if (isset($this->foregroundColors[$fgColor])) {
            $colored = true;
            $coloredString .= "\033[" . $this->foregroundColors[$fgColor] . "m";
        }
        // Check if given background color found
        if (isset($this->backgroundColors[$bgColor])) {
            $colored = true;
            $coloredString .= "\033[" . $this->backgroundColors[$bgColor] . "m";
        }

        // Add string and end coloring
        $coloredString .=  $text . ($colored? "\033[0m" : "");

        return $coloredString;
    }

    /**
     * Parse Command Definition
     *
     * @param array $command
     * @return array
     */
    protected function parseCommand($command)
    {
        $exp = explode(" ", trim($command), 2);
        $command = trim($exp[0]);
        $args = [];
        $options = [];

        if (isset($exp[1])) {
            preg_match_all("/\{(?<name>\w+)(?<arr>\*)?((=(?<default>[^\}]+))|(?<optional>\?))?(::(?<desc>[^}]+))?\}/i", $exp[1], $matchArgs);
            preg_match_all("/\{--((?<alias>[a-zA-Z])\|)?(?<name>\w+)((?<valuable>=)(?<default>[^\}]+)?)?(::(?<desc>[^}]+))?\}/i", $exp[1], $matchOptions);
            foreach($matchArgs['name'] as $i => $argName) {
                $default = $matchArgs['default'][$i];
                $expDefault = explode('::', $default, 2);
                if (count($expDefault) > 1) {
                    $default = $expDefault[0];
                    $description = $expDefault[1];
                } else {
                    $default = $expDefault[0];
                    $description = $matchArgs['desc'][$i];
                }

                $args[$argName] = [
                    'is_array' => !empty($matchArgs['arr'][$i]),
                    'is_optional' => !empty($matchArgs['optional'][$i]) || !empty($default),
                    'default' => $default ?: null,
                    'description' => $description,
                ];
            }

            foreach($matchOptions['name'] as $i => $optName) {
                $default = $matchOptions['default'][$i];
                $expDefault = explode('::', $default, 2);
                if (count($expDefault) > 1) {
                    $default = $expDefault[0];
                    $description = $expDefault[1];
                } else {
                    $default = $expDefault[0];
                    $description = $matchOptions['desc'][$i];
                }
                $options[$optName] = [
                    'is_valuable' => !empty($matchOptions['valuable'][$i]),
                    'default' => $default ?: null,
                    'description' => $description,
                    'alias' => $matchOptions['alias'][$i] ?: null,
                ];
            }
        }

        return [$command, $args, $options];
    }

    /**
     * Parse PHP argv
     *
     * @param array $argv
     * @return array
     */
    protected function parseArgv(array $argv)
    {
        $filename = array_shift($argv);
        $command = array_shift($argv);
        $arguments = [];
        $options = [];
        $optionsAlias = [];

        while (count($argv)) {
            $arg = array_shift($argv);
            if ($this->isOption($arg)) {
                $optName = ltrim($arg, "-");
                if ($this->isOptionWithValue($arg)) {
                    list($optName, $optvalue) = explode("=", $optName);
                } else {
                    $optvalue = array_shift($argv);
                }

                $options[$optName] = $optvalue;
            } elseif ($this->isOptionAlias($arg)) {
                $alias = ltrim($arg, "-");
                $exp = explode("=", $alias);
                $aliases = str_split($exp[0]);
                if (count($aliases) > 1) {
                    foreach($aliases as $aliasName) {
                        $optionsAlias[$aliasName] = null;
                    }
                } else {
                    $aliasName = $aliases[0];
                    if (count($exp) > 1) {
                        list($aliasName, $aliasValue) = $exp;
                    } else {
                        $aliasValue = array_shift($argv);
                    }

                    $optionsAlias[$aliasName] = $aliasValue;
                }
            } else {
                $arguments[] = $arg;
            }
        }

        return [$filename, $command, $arguments, $options, $optionsAlias];
    }

    /**
     * Check whether OS is windows
     *
     * @return boolean
     */
    private function isWindows()
    {
        return '\\' === DIRECTORY_SEPARATOR;
    }

    /**
     * Check whether Stty is available or not.
     *
     * @return bool
     */
    private function hasSttyAvailable()
    {
        if (null !== self::$stty) {
            return self::$stty;
        }
        exec('stty 2>&1', $output, $exitcode);
        return self::$stty = $exitcode === 0;
    }

    /**
     * Returns a valid unix shell.
     *
     * @return string|bool The valid shell name, false in case no valid shell is found
     */
    private function getShell()
    {
        if (null !== self::$shell) {
            return self::$shell;
        }
        self::$shell = false;
        if (file_exists('/usr/bin/env')) {
            // handle other OSs with bash/zsh/ksh/csh if available to hide the answer
            $test = "/usr/bin/env %s -c 'echo OK' 2> /dev/null";
            foreach (array('bash', 'zsh', 'ksh', 'csh') as $sh) {
                if ('OK' === rtrim(shell_exec(sprintf($test, $sh)))) {
                    self::$shell = $sh;
                    break;
                }
            }
        }
        return self::$shell;
    }

    /**
     * Check whether argument is option or not
     *
     * @param string $arg
     * @return boolean
     */
    protected function isOption($arg)
    {
        return (bool) preg_match("/^--\w+/", $arg);
    }

    /**
     * Check whether argument is option alias or not
     *
     * @param string $arg
     * @return boolean
     */
    protected function isOptionAlias($arg)
    {
        return (bool) preg_match("/^-[a-z]+/i", $arg);
    }

    /**
     * Check whether argument is option with value or not
     *
     * @param string $arg
     * @return boolean
     */
    protected function isOptionWithValue($arg)
    {
        return strpos($arg, "=") !== false;
    }

    /**
     * Validate And Resolve Arguments
     *
     * @param string $command
     * @return array resolved arguments
     */
    protected function validateAndResolveArguments($command)
    {
        $args = $this->arguments;
        $commandArgs = $this->commands[$command]['args'];
        $resolvedArgs = [];
        foreach($commandArgs as $argName => $argOption) {
            if (!$argOption['is_optional'] AND empty($args)) {
                return $this->error("Argument {$argName} is required");
            }
            if ($argOption['is_array']) {
                $value = $args;
            } else {
                $value = array_shift($args) ?: $argOption['default'];
            }

            $resolvedArgs[$argName] = $value;
        }

        return $resolvedArgs;
    }

    /**
     * Validate And Resolve Options
     *
     * @param string $command
     */
    protected function validateAndResolveOptions($command)
    {
        $options = $this->options;
        $optionsAlias = $this->optionsAlias;
        $commandOptions = $this->commands[$command]['options'];
        $resolvedOptions = $options;

        foreach ($commandOptions as $optName => $optionSetting) {
            $alias = $optionSetting['alias'];
            if ($alias AND isset($optionsAlias[$alias])) {
                $value = isset($optionsAlias[$alias])? $optionsAlias[$alias] : $optionSetting['default'];
            } else {
                $value = isset($options[$optName])? $options[$optName] : $optionSetting['default'];
            }

            if (!$optionSetting['is_valuable']) {
                $resolvedOptions[$optName] = !empty($value);
            } else {
                $resolvedOptions[$optName] = $value;
            }
        }

        $this->resolvedOptions = $resolvedOptions;
    }

    /**
     * Show commands like given command
     *
     * @param string $keyword
     */
    protected function showCommandsLike($keyword)
    {
        $commands = $this->getRegisteredCommands();
        $matchedCommands = $this->getCommandsLike($keyword);

        if(count($matchedCommands) === 1) {
            $keys = array_keys($matchedCommands);
            $values = array_values($matchedCommands);
            $name = array_shift($keys);
            $command = array_shift($values);
            $this->writeln(PHP_EOL.$this->color(" Command '{$keyword}' is not available. Did you mean '{$name}'?", 'red').PHP_EOL);
        } else {
            $this->writeln(PHP_EOL.$this->color(" Command '{$keyword}' is not available.", 'red'));
            $commandList = $this->commands["list"]["handler"];
            $commandList(count($matchedCommands)? $keyword : null);
        }
    }


    /**
     * Show command help
     *
     * @param string $commandName
     */
    protected function showHelp($commandName)
    {
        $command = $this->commands[$commandName];
        $maxLen = 0;
        $args = $command['args'];
        $opts = $command['options'];
        $usageArgs = [$commandName];
        $displayArgs = [];
        $displayOpts = [];
        foreach($args as $argName => $argSetting) {
            $usageArgs[] = "<".$argName.">";
            $displayArg = $argName;
            if ($argSetting['is_optional']) {
                $displayArg .= " (optional)";
            }
            if (strlen($displayArg) > $maxLen) {
                $maxLen = strlen($displayArg);
            }
            $displayArgs[$displayArg] = $argSetting['description'];
        }
        $usageArgs[] = "[options]";

        foreach($opts as $optName => $optSetting) {
            $displayOpt = $optSetting['alias']? str_pad('-'.$optSetting['alias'].',', 4) : str_repeat(' ', 4);
            $displayOpt .= "--".$optName;
            if (strlen($displayOpt) > $maxLen) {
                $maxLen = strlen($displayOpt);
            }
            $displayOpts[$displayOpt] = $optSetting['description'];
        }

        $pad = $maxLen + 3;
        $this->writeln(PHP_EOL." ".$command['description'].PHP_EOL);
        $this->writeln($this->color(" Usage:", 'blue'));
        $this->writeln('');
        $this->writeln("  ".implode(" ", $usageArgs));
        $this->writeln("");
        $this->writeln($this->color(" Arguments: ", 'blue').PHP_EOL);
        foreach($displayArgs as $argName => $argDesc) {
            $this->writeln("  ".$this->color($argName, 'green').str_repeat(' ', $pad - strlen($argName)).$argDesc);
        }
        $this->writeln('');
        $this->writeln($this->color(" Options: ", 'blue').PHP_EOL);
        foreach($displayOpts as $optName => $optDesc) {
            $this->writeln("  ".$this->color($optName, 'green').str_repeat(' ', $pad - strlen($optName)).$optDesc);
        }
        $this->writeln('');
    }

    /**
     * Error Handler
     *
     * @param Exception $exception
     */
    public function handleError(Exception $exception)
    {
        $indent = str_repeat(" ", 2);
        $class = get_class($exception);
        $file = $exception->getFile();
        $line = $exception->getLine();
        $filepath = function($file) {
            return str_replace(dirname(__DIR__).DIRECTORY_SEPARATOR, '', $file);
        };
        $message = $exception->getMessage();
        $verbose = (array_key_exists('verbose', $this->options) OR array_key_exists('v', $this->optionsAlias));

        $this->writeln(
            PHP_EOL
            .$indent."Whops! You got an ".$class
            .PHP_EOL
            .$indent.$message
            .PHP_EOL
        , 'red');

        if ($verbose) {
            $this->writeln(
                $indent."File: ".$filepath($file)
                .PHP_EOL
                .$indent."Line: ".$line
                .PHP_EOL
            , 'dark_gray');

            $traces = $exception->getTrace();
            $count = count($traces);
            $traceFunction = function($trace) {
                $args = implode(', ', array_map([$this, 'stringify'], $trace['args']));
                if ($trace['function'] == '{closure}') {
                    return 'Closure('.$args.')';
                } elseif(!isset($trace['class'])) {
                    return $trace['function'].'('.$args.')';
                } else {
                    return $trace['class'].$trace['type'].$trace['function'].'('.$args.')';
                }
            };
            $x = $count > 9? 2 : 1;

            $this->writeln($indent.'Traces:');
            foreach($traces as $i => $trace) {
                $space = str_repeat(" ", $x + 2);
                $no = str_pad($count - $i, $x, ' ', STR_PAD_LEFT);
                $func = $traceFunction($trace);
                $file = isset($trace['file'])? $filepath($trace['file']) : 'unknown';
                $line = isset($trace['line'])? $trace['line'] : 'unknown';
                $this->writeln("{$indent}{$no}) {$func}");
                $this->writeln("{$indent}{$space}File: {$file}", 'dark_gray');
                $this->writeln("{$indent}{$space}Line: {$line}", 'dark_gray');
                $this->writeln('');
            }
        }
    }

    /**
     * Stringify value
     */
    protected function stringify($value)
    {
        if (is_object($value)) {
            return get_class($value);
        } elseif(is_array($value)) {
            if (count($value) > 3) {
                return 'Array';
            } else {
                return implode(", ", array_map([$this, 'stringify'], $value));
            }
        } elseif(is_bool($value)) {
            return $value? 'true' : 'false';
        } elseif(is_string($value)) {
            return '"'.addslashes($value).'"';
        } elseif(is_null($value)) {
            return 'null';
        } else {
            return $value;
        }
    }

}
