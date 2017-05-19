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
    protected $args = array();
    protected $options = array();
    protected $helpNote = '';
    protected $shellHistory = './.history_cli';
    
    public $stdio;
    
    /**
     * Initialize cli
     * @param	array $settings
     */
    public function __construct($settings = array()) {
        if (!StdIO::isCli()) {
            throw new CliException('"' . get_class($this) . '" class only supports Command Line Interface.');
        }
        
        ini_set('html_errors', 0);
        set_time_limit(0);
        
        $this->stdio = new StdIO();
        
        $this->processArgs();
        $this->initialize($settings);
    }
    
    /**
     * Get standard input from console
     * @param	string $prompt_message
     * @param	bool $secure
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
     * @param	string $shell_name
     * @param	array $commands
     * @param	callable $shell_handler
     * @param	string $prompt
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
     * Get passed arguments
     * @param	int $index
     * @param	string $default
     */
    public function getArgs($index = NULL, $default = NULL) {
        if ($index === NULL) {
            return $this->args;
        } elseif (array_key_exists($index, $this->args)) {
            return $this->args[$index];
        } else {
            return $default;
        }
    }
    
    /**
     * Check if argument passed
     * @param	string $option
     * @param	int $pos
     */
    public function hasArg($option, $pos = NULL) {
        if ($pos === NULL) {
            return (in_array('-' . $option, $this->args) or in_array('--' . $option, $this->args));
        } else {
            return (isset($this->args[$pos]) and ( $this->args[$pos] == '-' . $option or $this->args[$pos] == '--' . $option));
        }
    }
    
    /**
     * Get registered options
     * @param	string $option
     * @param	array $default
     */
    public function getOptions($option = NULL, $default = array()) {
        $val = $default;
        if (!empty($this->options)) {
            if ($option === NULL) {
                $_val = array();
                foreach ($this->options as $opt => $optVal) {
                    $_val[] = array(
                        'opt' => $opt,
                        'long_opt' => $optVal['long_opt'],
                        'description' => $optVal['description'],
                        'required' => $optVal['required'],
                        'value' => $optVal['value']
                    );
                }
                $val = $_val;
            } else {
                foreach ($this->options as $opt => $optVal) {
                    if ('-' . $option == $opt or '--' . $option == $optVal['long_opt']) {
                        $val = array(
                            'opt' => $opt,
                            'long_opt' => $optVal['long_opt'],
                            'description' => $optVal['description'],
                            'required' => $optVal['required'],
                            'value' => $optVal['value']
                        );
                        break;
                    }
                }
            }
        }
        return $val;
    }
    
    /**
     * Get registered option value
     * @param	string $option
     * @param	mixed $default
     */
    public function getOptionValue($option, $default = NULL) {
        $val = $default;
        if (!empty($this->options)) {
            foreach ($this->options as $opt => $optVal) {
                if ('-' . $option == $opt or '--' . $option == $optVal['long_opt']) {
                    $val = $optVal['value'];
                    break;
                }
            }
        }
        return $val;
    }
    
    /**
     * Check if valid registered option
     * @param	string $option
     */
    public function isValidOption($option) {
        $ret = false;
        if (!empty($this->options)) {
            foreach ($this->options as $opt => $val) {
                if ('-' . $option == $opt or '--' . $option == $val['long_opt']) {
                    $ret = true;
                    break;
                }
            }
        }
        return $ret;
    }
    
    /**
     * Check if option is registered and passed as argument
     * @param	string $option
     * @param	int $pos
     */
    public function isOption($option, $pos = NULL) {
        return ($this->isValidOption($option) and $this->hasArg($option, $pos));
    }
    
    /**
     * Bind function or method to registered option
     * @param	string $option
     * @param	callable $callback
     * @param	array $params
     */
    public function bindOption($option, $callback, $params = array()) {
        if ($option = $this->getOptions($option)) {
            $opt = substr($option['opt'], 1);
            $long_opt = substr($option['long_opt'], 2);
            if (!is_array($params)) {
                throw new CliException('Not a valid argument "params" to "' . __METHOD__ . '" for option "' . $opt . '"');
            }
            if (!empty($params)) {
                array_unshift($params, $this);
                $_params = $params;
            } else {
                $_params = array($this, $option['value']);
            }
            if ($this->isOption($opt) or $this->isOption($long_opt)) {
                if (!is_callable($callback, false, $callback_name)) {
                    throw new CliException('Not a valid callback to "' . __METHOD__ . '" for option "' . $opt . '"');
                } else {
                    call_user_func_array($callback, $_params);
                }
            }
        }
    }
    
    /**
     * Print Help Content
     */
    public function showHelp() {
        $hw = $this->stdio->getWidth();
        $text = array();
        $text[] = $this->stdio->colorizeText(str_repeat('=', $hw), 'green');
        $text[] = $this->stdio->colorizeText('Help for current command !', 'green');
        $text[] = $this->stdio->colorizeText(str_repeat('-', $hw), 'green');
        if (!empty($this->args)) {
            $text[] = $this->stdio->colorizeText('Given arguments: ', 'purple') . implode(', ', $this->args);
        } else {
            $text[] = $this->stdio->colorizeText('No arguments given !', 'red');
        }
        $text[] = StdIO::EOL;
        if (!empty($this->options)) {
            $text[] = $this->stdio->colorizeText('Registered options:', 'purple');
            $i = 1;
            foreach ($this->options as $opt => $option) {
                $text[] = StdIO::EOL;
                $text[] = $i . ')' . StdIO::TAB . $this->stdio->colorizeText('Option: ', 'light_blue') . StdIO::TAB . $opt;
                $text[] = StdIO::TAB . $this->stdio->colorizeText('Long Option: ', 'light_blue') . StdIO::TAB . $option['long_opt'];
                $text[] = StdIO::TAB . $this->stdio->colorizeText('Description: ', 'light_blue') . StdIO::TAB . $option['description'];
                $text[] = StdIO::TAB . $this->stdio->colorizeText('Required: ', 'light_blue') . StdIO::TAB . (($option['required']) ? $this->stdio->colorizeText('Yes', 'green') : $this->stdio->colorizeText('No', 'red'));
                $text[] = StdIO::TAB . $this->stdio->colorizeText('Given Value: ', 'light_blue') . StdIO::TAB . $option['value'];
                $i++;
            }
        } else {
            $text[] = $this->stdio->colorizeText('No options registered !', 'red');
        }
        if (!empty($this->helpNote)) {
            $text[] = StdIO::EOL;
            $text[] = $this->stdio->colorizeText(str_repeat('-', $hw), 'yellow');
            $text[] = wordwrap($this->helpNote, $hw, StdIO::EOL, true);
            $text[] = $this->stdio->colorizeText(str_repeat('-', $hw), 'yellow');
        }
        $text[] = $this->stdio->colorizeText(str_repeat('=', $hw), 'green');
        $this->stdio->writeln($text);
    }
    
    /**
     * Print and Overwrite line, useful to show current status
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
    
    protected function initialize($settings) {
        if (is_array($settings)) {
            $this->registerOption('h', 'help', 'Shows help for current command.');
            if (isset($settings['options'])) {
                $this->registerOptions($settings['options']);
            }
            if (isset($settings['help_note'])) {
                $this->setHelpNote($settings['help_note']);
            }
            if ($this->isOption('h') or $this->isOption('help')) {
                $this->showHelp();
                $this->stop();
            }
        }
    }
    
    protected function processArgs() {
        $_argc = isset($argc) ? $argc : $_SERVER['argc'];
        $_argv = isset($argv) ? $argv : $_SERVER['argv'];
        if ($_argc > 1) {
            array_shift($_argv);
            $this->args = $_argv;
        }
    }
    
    protected function parseOptions() {
        if (!empty($this->options) and ! empty($this->args)) {
            foreach ($this->options as $opt => $option) {
                $_opt = $opt;
                $_long_option = $option['long_opt'];
                $flipped_args = array_flip($this->args);
                $opt_key = NULL;
                if (array_key_exists($_opt, $flipped_args)) {
                    $opt_key = $flipped_args[$_opt];
                } elseif (array_key_exists($_long_option, $flipped_args)) {
                    $opt_key = $flipped_args[$_long_option];
                }
                if ($opt_key !== NULL) {
                    $optval_key = $opt_key + 1;
                    if (isset($this->args[$optval_key]) and ! preg_match('/^(-|--)/', $this->args[$optval_key])) {
                        $this->options[$opt]['value'] = $this->args[$optval_key];
                    } elseif ($option['required'] === true) {
                        $this->stdio->setColor('red')->write('Error! Given option ' . $this->args[$opt_key] . ' requires a value.', 2);
                        $this->showHelp();
                        $this->stop();
                    }
                }
            }
        }
    }
    
    protected function registerOption($option, $long_option = NULL, $description = NULL, $required = false) {
        if (!empty($option)) {
            $opt = '-' . substr(strval($option), 0, 1);
            $this->options[$opt] = array('long_opt' => NULL, 'description' => NULL, 'required' => false, 'value' => NULL);
            empty($long_option) or $this->options[$opt]['long_opt'] = '--' . strval($long_option);
            empty($description) or $this->options[$opt]['description'] = strval($description);
            $this->options[$opt]['required'] = (bool) $required;
        }
    }
    
    protected function registerOptions($options) {
        if (!empty($options) and is_array($options)) {
            foreach ($options as $option) {
                if (is_array($option)) {
                    call_user_func_array(array($this, 'registerOption'), $option);
                }
            }
        }
        $this->parseOptions();
    }
    
    protected function setHelpNote($helpNote) {
        empty($helpNote) or $this->helpNote = strval($helpNote);
    }
}
