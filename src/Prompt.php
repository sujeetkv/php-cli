<?php
/**
 * PHP library to build command line tools
 *
 * @author  Sujeet <sujeetkv90@gmail.com>
 * @link    https://github.com/sujeet-kumar/php-cli
 */

namespace SujeetKumar\PhpCli;

/**
 * Prompt class
 */
class Prompt
{
    /** @var object Cli */
    protected $cli;
    
    /** @var object Args */
    protected $args;
    
    /** @var bool */
    private static $stty;
    
    /** @var string|bool */
    private static $shell;
    
    /**
     * Initialize Prompt class
     * 
     * @param Cli $cli
     */
    public function __construct($cli) {
        $this->cli = $cli;
    }
    
    /**
     * Get standard input from console
     * 
     * @param string $promptMessage
     * @param bool $hiddenResponse
     * @param bool $hiddenFallback
     */
    public function getInput($promptMessage, $hiddenResponse = false, $hiddenFallback = true) {
        $input = null;
        if (!empty($promptMessage)) {
            if ($hiddenResponse) {
                if (StdIO::isWindows()) {
                    $exe = __DIR__ . '/bin/hiddeninput.exe';
                    // handle code running from a phar
                    if ('phar:' === substr(__FILE__, 0, 5)) {
                        $tmp_exe = sys_get_temp_dir() . '/hiddeninput.exe';
                        copy($exe, $tmp_exe);
                        $exe = $tmp_exe;
                    }
                    $this->cli->stdio->write("$promptMessage: ");
                    $input = rtrim(shell_exec($exe));
                    $this->cli->stdio->ln();
                    if (isset($tmp_exe)) {
                        unlink($tmp_exe);
                    }
                } elseif ($this->hasStty()) {
                    $sttyMode = shell_exec('stty -g');
                    $this->cli->stdio->write("$promptMessage: ");
                    shell_exec('stty -echo');
                    $input = trim(fgets($this->cli->stdio->getInputStream(), 4096));
                    shell_exec(sprintf('stty %s', $sttyMode));
                    $this->cli->stdio->ln();
                } elseif (false !== ($shell = $this->getShell())) {
                    $this->cli->stdio->write("$promptMessage: ");
                    $readCmd = 'csh' === $shell ? 'set mypassword = $<' : 'read -r mypassword';
                    $command = sprintf("/usr/bin/env %s -c 'stty -echo; %s; stty echo; echo \$mypassword'", $shell, $readCmd);
                    $input = rtrim(shell_exec($command));
                    $this->cli->stdio->ln();
                } else {
                    if ($hiddenFallback) {
                        $input = trim($this->cli->stdio->read("$promptMessage: "));
                    } else {
                        throw new CliException('Hidden response not supported.');
                    }
                }
            } else {
                $input = trim($this->cli->stdio->read("$promptMessage: "));
            }
        }
        return $input;
    }
    
    /**
     * Create interactive shell on console
     * 
     * @param string $shellName
     * @param array $commands
     * @param callable $shellHandler
     * @param string $historyPath
     * @param string $prompt
     * @param bool $showBanner
     * @param string $bannerColor
     */
    public function renderShell($shellName, $commands, $shellHandler, $historyPath = './', $prompt = '>', $showBanner = true, $bannerColor = 'light_green') {
        if (empty($commands) || !is_array($commands)) {
            throw new CliException('Invalid variable commands provided.');
        }
        
        if (!is_callable($shellHandler, false, $callable_name)) {
            throw new CliException('Invalid callable shell_handler provided: ' . $callable_name);
        }
        
        if (!is_dir($historyPath)) {
            throw new CliException('Given history path does not exist:' . $historyPath);
        }
        
        $shellHistory = rtrim($historyPath, '/\\') . '/.history_' . $shellName;
        
        $commands['list'] = array('helpNote' => 'Show list of commands.');
        $commands['exit'] = array('helpNote' => 'Exit the shell.');
        
        if ($this->cli->stdio->hasReadline()) {
            readline_read_history($shellHistory);
            readline_completion_function(function () use ($commands) {
                return $this->shellAutoCompleter($commands);
            });
        }
        
        $header = <<<EOF

Welcome to the {$shellName} shell.

At the prompt, type {$this->cli->stdio->colorizeText('list', 'brown')} to get a list of 
available commands.

To exit the shell, type {$this->cli->stdio->colorizeText('^D', 'brown')} or {$this->cli->stdio->colorizeText('exit', 'brown')}.

EOF;
        
        if ($showBanner) {
            $banner = <<<EOF
{$this->cli->createFiglet($shellName, $bannerColor)}

EOF;
            $this->cli->stdio->writeln($banner . $header);
        } else {
            $this->cli->stdio->writeln($header);
        }
        
        do {
            
            $command = $this->cli->stdio->read($prompt . ' ');
            
            if (false === $command) {
                $this->cli->stdio->ln(2)->write('Bye', 2);
                break;
            }
            
            $res = true;
            
            if (!empty($command)) {
                if ($this->cli->stdio->hasReadline()) {
                    readline_add_history($command);
                    readline_write_history($shellHistory);
                }
                
                $args = array_map('trim', explode(' ', $command));
                
                $this->args = new Args(count($args), $args);
                
                $cmd = $this->args->registerCommands($commands)->getCommand();
                
                $cmdList = $this->args->getCommandList();
                $list = array();
                foreach ($cmdList as $c => $copt) {
                    $list[] = ' ' . $this->cli->stdio->colorizeText($c, 'green') . '  ' . $copt['helpNote'];
                }
                
                if ($this->args->hasOption('h')) {
                    $this->cli->showHelp($this->args);
                    continue;
                } elseif ($cmd == 'exit') {
                    $this->cli->stdio->ln()->write('Bye', 2);
                    break;
                } elseif ($cmd == 'list') {
                    $this->cli->stdio->writeln('Available commands:');
                    $this->cli->stdio->write($list, 2);
                    continue;
                } elseif (!in_array($cmd, array_keys($cmdList))) {
                    $this->cli->stdio->writeln(array("No command '$cmd' found.", 'Available commands are:'));
                    $this->cli->stdio->write($list, 2);
                    continue;
                } else {
                    $res = call_user_func($shellHandler, $this->cli, $cmd, $this->args->getOpt());
                }
            }
            
        } while ($res !== false);
        
        $this->cli->exitScript();
    }
    
    private function shellAutoCompleter($rawCommands) {
        $info = readline_info();
        $text = substr($info['line_buffer'], 0, $info['end']);

        if ($info['point'] !== $info['end']) {
            return true;
        }

        if (!$text || false === strpos($text, ' ')) {
            return array_keys($rawCommands);
        }

        $text = trim(substr($text, 0, strpos($text, ' ')));

        $list = array('--help');
        if (isset($rawCommands[$text]['options']) && is_array($rawCommands[$text]['options'])) {
            foreach ($rawCommands[$text]['options'] as $option) {
                isset($option[1]) && $list[] = '--' . $option[1];
            }
        }
        return $list;
    }
    
    /**
     * Returns a valid unix shell.
     */
    private function getShell() {
        if (null !== self::$shell) {
            return self::$shell;
        }
        self::$shell = false;
        if (file_exists('/usr/bin/env')) {
            // handle other OSs with bash/zsh/ksh/csh if available to hide the answer
            $test = '/usr/bin/env %s -c "echo OK" 2> /dev/null';
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
     * Returns whether Stty is available or not.
     */
    private function hasStty() {
        if (null !== self::$stty) {
            return self::$stty;
        }
        exec('stty 2>&1', $output, $exitcode);
        return self::$stty = (0 === $exitcode);
    }
}
