<?php
/**
 * PHP library to build command line tools
 *
 * @author  Sujeet <sujeetkv90@gmail.com>
 * @link    https://github.com/sujeet-kumar/php-cli
 */

namespace SujeetKumar\PhpCli;

/**
 * Cli class
 */
class Cli
{
    public $stdio;
    public $args;
    protected $helpNote = '';
    protected $shellHistory = './.history_cli';
    
    /**
     * Initialize cli
     * 
     * @param array $settings
     */
    public function __construct($settings = array()) {
        if (!StdIO::isCli()) {
            throw new CliException('"This program is only meant for Command Line Interface.');
        }
        
        ini_set('html_errors', 0);
        set_time_limit(0);
        
        $this->args = new Args();
        $this->stdio = new StdIO();
        
        $this->initialize($settings);
    }
    
    public function initialize($settings) {
        if (is_array($settings)) {
            if (isset($settings['commands'])) {
                $this->args->registerCommands($settings['commands']);
            }
            if (isset($settings['helpNote'])) {
                $this->setHelpNote($settings['helpNote']);
            }
            if ($this->args->isOption('h') || $this->args->isOption('help')) {
                $this->showHelp($this->args);
                $this->stop();
            }
        }
    }
    
    /**
     * Get standard input from console
     * 
     * @param string $prompt_message
     * @param bool $secure
     */
    public function promptInput($prompt_message, $secure = false) {
        $input = null;
        if (!empty($prompt_message)) {
            $this->stdio->write("$prompt_message: ");
            if ($secure) {
                if (StdIO::isWindows()) {
                    $exe = __DIR__ . '/bin/hiddeninput.exe';
                    // handle code running from a phar
                    if ('phar:' === substr(__FILE__, 0, 5)) {
                        $tmp_exe = sys_get_temp_dir() . '/hiddeninput.exe';
                        copy($exe, $tmp_exe);
                        $exe = $tmp_exe;
                    }
                    $input = rtrim(shell_exec($exe));
                    $this->stdio->ln();
                    if (isset($tmp_exe)) {
                        unlink($tmp_exe);
                    }
                } elseif (StdIO::hasStty()) {
                    $stty_mode = shell_exec('stty -g');
                    shell_exec('stty -echo');
                    $input = trim($this->stdio->read());
                    shell_exec(sprintf('stty %s', $stty_mode));
                    $this->stdio->ln();
                } elseif ($this->stdio->hasColorSupport()) {
                    $this->stdio->write("\033[0;30m\033[40m");
                    $input = trim($this->stdio->read());
                    $this->stdio->write("\033[0m");
                } else {
                    throw new CliException('Secure input not supported.');
                }
            } else {
                $input = trim($this->stdio->read());
            }
        }
        return $input;
    }
    
    /**
     * Create interactive shell on console
     * 
     * @param string $shell_name
     * @param array $commands
     * @param callable $shell_handler
     * @param string $prompt
     */
    public function promptShell($shell_name, $commands, $shell_handler, $prompt = '>') {
        if (empty($commands) or !is_array($commands)) {
            
            throw new CliException('Invalid variable commands provided.');
            
        } else {
            $commands['list'] = array();
            
            $list = array_keys($commands);
            $parsed_commands = array();
            
            foreach ($commands as $cmd => $cmd_info) {
                $parsed_commands[$cmd] = array();
                
                if (is_array($cmd_info)) {
                    foreach ($cmd_info as $info) {
                        if (!empty($info[0])) {
                            $opt = substr(strval($info[0]), 0, 1);
                            $parsed_commands[$cmd][$opt] = array('opt' => null, 'long_opt' => null, 'description' => null);
                            $parsed_commands[$cmd][$opt]['opt'] = '-' . $opt;
                            empty($info[1]) or $parsed_commands[$cmd][$opt]['long_opt'] = '--' . strval($info[1]);
                            empty($info[2]) or $parsed_commands[$cmd][$opt]['description'] = strval($info[2]);
                        }
                    }
                }
            }
        }
        
        if (!is_callable($shell_handler, false, $callable_name)) {
            throw new CliException('Invalid callable shell_handler provided: ' . $callable_name);
        }
        
        $this->shellHistory = './.history_' . $shell_name;
        
        if ($this->stdio->hasReadline()) {
            readline_read_history($this->shellHistory);
            readline_completion_function(function () use ($list) {
                return $list;
            });
        }
        
        $header = <<<EOF

Welcome to the {$shell_name} shell.

At the prompt, type list to get a list of 
available commands.

To exit the shell, type ^D or exit.

EOF;
        
        $this->stdio->writeln($header);
        
        do {
            
            $command = $this->stdio->read($prompt . ' ');
            
            if ($command === false or $command == 'exit') {
                $this->stdio->ln();
                $this->stdio->write('bye', 2);
                break;
            }
            
            $res = true;
            
            if (!empty($command)) {
                if ($this->stdio->hasReadline()) {
                    readline_add_history($command);
                    readline_write_history($this->shellHistory);
                }
                
                $args = array_map('trim', explode(' ', $command));
                $cmd = array_shift($args);
                
                if ($cmd == 'list') {
                    $this->stdio->writeln('List of valid commands:');
                    $this->stdio->write($list, 2);
                    continue;
                } elseif (!in_array($cmd, $list)) {
                    $this->stdio->writeln(array("No command '$cmd' found.", 'Available commands are:'));
                    $this->stdio->write($list, 2);
                    continue;
                } else {
                    $opts = array();
                    $opt_help = array();
                    foreach ($parsed_commands[$cmd] as $opt => $opt_info) {
                        if (($opt_key = array_search($opt_info['opt'], $args)) !== false or ($opt_key = array_search($opt_info['long_opt'], $args)) !== false) {
                            $opts[$opt] = NULL;
                            $optval_key = $opt_key + 1;
                            if (isset($args[$optval_key]) and !preg_match('/^(-|--)/', $args[$optval_key])) {
                                $opts[$opt] = $args[$optval_key];
                            }
                        }
                        $opt_help[] = $opt_info['opt'] . ', ' . $opt_info['long_opt'] . StdIO::TAB . $opt_info['description'];
                    }

                    if (in_array('-h', $args) or in_array('--help', $args)) {
                        $help = array();
                        $help[] = "Usage: $cmd [OPTION] [OPTION VALUE] ...";
                        if (!empty($opt_help)) {
                            $help[] = 'Available options are:';
                            $help = array_merge($help, $opt_help);
                        }
                        $this->stdio->write($help, 2);
                        continue;
                    }

                    $res = call_user_func($shell_handler, $this, $cmd, $opts);
                }
            }
            
        } while ($res !== false);
    }
    
    /**
     * Bind function or method to registered option
     * 
     * @param string $option
     * @param callable $callback
     * @param array $params
     */
    public function bindOption($option, $callback, $params = array()) {
        if ($option = $this->args->getOptions($option)) {
            $opt = substr($option['opt'], 1);
            $longOpt = substr($option['longOpt'], 2);
            if (!is_array($params)) {
                throw new CliException('Invalid argument "params" to ' . __METHOD__ . ' for option "' . $opt . '"');
            }
            if (!empty($params)) {
                array_unshift($params, $this);
                $_params = $params;
            } else {
                $_params = array($this, $option['value']);
            }
            if ($this->args->isOption($opt) || $this->args->isOption($longOpt)) {
                if (!is_callable($callback, false, $callbackName)) {
                    throw new CliException('Invalid callback ' . $callbackName . ' to ' . __METHOD__ . ' for option "' . $opt . '"');
                } else {
                    call_user_func_array($callback, $_params);
                }
            }
        }
    }
    
    /**
     * Print Help Content
     * 
     * @param Args $args
     */
    public function showHelp($args) {
        $hw = $this->stdio->getWidth();
        $text = array();
        $tableLayout = new TableLayout($this->stdio);
        $options = $args->getOptions();
        if (empty($options)) {
            $text[] = $tableLayout->formatRow(array('Usage: ' . $args->getCommand()));
        } else {
            $text[] = $tableLayout->formatRow(array('Usage: ' . $args->getCommand() . ' [OPTION] [VALUE] ...')) . StdIO::EOL;
            $text[] = $tableLayout->formatRow(array('Available options are:'));
            $tableLayout->setColWidths(array('5%', '20%', '*'));
            foreach ($options as $opt) {
                $text[] = $tableLayout->formatRow(array(
                    '',
                    $opt['opt'] . ', ' . $opt['longOpt'],
                    $opt['description']
                ));
            }
            $tableLayout->setColWidths(array('*'));
        }
        empty($this->helpNote) || $text[] = StdIO::EOL . $tableLayout->formatRow(array($this->helpNote));
        $this->stdio->writeln($text);
    }
    
    /**
     * Print and Overwrite line, useful to show current status
     * 
     * @param	string $msg
     * @param	bool $passive
     */
    public function showStatus($msg, $passive = false) {
        static $_len = 0;
        $len = strlen($msg);
        if ($len) {
            $_msg = ($len < $_len) ? str_pad($msg, $_len) : $msg;
            $_len = $len;
            $this->stdio->write(' ' . $_msg . (($passive) ? StdIO::EOL : StdIO::CR));
        }
    }
    
    /**
     * Show progress percentage, to be used with loop
     * 
     * @param	int $totalStep
     * @param	int $currentStep
     * @param	string $msg
     */
    public function showProgress($totalStep, $currentStep, $msg = 'Processing...') {
        if ($totalStep > 0) {
            $p = floor((($currentStep / $totalStep) * 100));
            $this->stdio->write(' ' . $msg . ' ' . $p . '%' . (($p == 100) ? " Complete !" . StdIO::EOL : StdIO::CR));
        }
    }
    
    /**
     * Show progress bar, to be used with loop
     * 
     * @param	int $totalStep
     * @param	int $currentStep
     */
    public function showProgressBar($totalStep, $currentStep) {
        if ($totalStep > 0) {
            $p = floor((($currentStep / $totalStep) * 100));
            $b = '[' . str_pad(str_repeat('|', intval($p / 2)), 50, '_') . ']';
            $this->stdio->write(' ' . $p . '% ' . StdIO::TAB . $b . (($p == 100) ? StdIO::EOL : StdIO::CR));
        }
    }
    
    /**
     * Get memory usage
     */
    public function getMemoryUsage() {
        $size = memory_get_usage(true);
        $unit = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
        return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
    }
    
    /**
     * Clear non-windows cli
     */
    public function clear() {
        if (!StdIO::isWindows()) {
            passthru('clear');
        }
    }
    
    /**
     * Stop cli
     */
    public function stop($status = 0) {
        exit($status);
    }
    
    public function setHelpNote($helpNote) {
        empty($helpNote) || $this->helpNote = strval($helpNote);
    }
}
