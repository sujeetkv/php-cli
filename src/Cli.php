<?php
namespace SujeetKumar\PhpCli;

/**
 * Cli utility class for PHP Command Line Interface
 * @author	Sujeet <sujeetkv90@gmail.com>
 * @link	https://github.com/sujeet-kumar/php-cli
 */
class Cli
{
    const LF = PHP_EOL;
    const CR = "\r";
    const TAB = "\t";
    
    protected $stdout = NULL;
    protected $stdin = NULL;
    protected $readline_supported = false;
    protected $color_supported = false;
    protected $args = array();
    protected $options = array();
    protected $help_note = '';
    protected $cli_width = 60;
    
    protected $foreground_colors = array(
        'black'         => '0;30',
        'dark_gray'     => '1;30',
        'blue'          => '0;34',
        'light_blue'    => '1;34',
        'green'         => '0;32',
        'light_green'   => '1;32',
        'cyan'          => '0;36',
        'light_cyan'    => '1;36',
        'red'           => '0;31',
        'light_red'     => '1;31',
        'purple'        => '0;35',
        'light_purple'  => '1;35',
        'brown'         => '0;33',
        'yellow'        => '1;33',
        'light_gray'    => '0;37',
        'white'         => '1;37'
    );
    
    protected $background_colors = array(
        'black'         => '40',
        'red'           => '41',
        'green'         => '42',
        'yellow'        => '43',
        'blue'          => '44',
        'magenta'       => '45',
        'cyan'          => '46',
        'light_gray'    => '47'
    );
    
    /**
     * Initialize cli
     * @param	array $settings
     */
    public function __construct($settings = array()) {
        if (!$this->_isCli()) {
            throw new CliException('"' . get_class($this) . '" class only supports Command Line Interface.');
        }
        
        ini_set('html_errors', 0);
        set_time_limit(0);
        
        $this->stdout = @fopen('php://stdout', 'w');
        $this->stdin = @fopen('php://stdin', 'r');
        
        if (!$this->stdout) {
            throw new CliException('"' . get_class($this) . '" could not open STDOUT.');
        }
        
        if (!$this->stdin) {
            throw new CliException('"' . get_class($this) . '" could not open STDIN.');
        }
        
        $this->readline_supported = (extension_loaded('readline') && function_exists('readline')) ? true : false;
        
        $this->colorMode();
        $this->_processArgs();
        $this->_initialize($settings);
    }
    
    /**
     * Read line from console
     */
    public function read() {
        if ($this->readline_supported) {
            $line = readline('');
            if (!empty($line)) {
                readline_add_history($line);
            }
            return $line;
        }
        return fgets($this->stdin);
    }
    
    /**
     * Write text to console
     * @param	mixed $text
     * @param	int $newlines
     */
    public function write($text, $newlines = 1) {
        if (is_array($text)) {
            $text = implode(self::LF, $text);
        }
        return fwrite($this->stdout, $text . str_repeat(self::LF, $newlines));
    }
    
    /**
     * Write colored text to console
     * @param	string $text
     * @param	string $foreground_color
     * @param	string $background_color
     */
    public function printText($text = '', $foreground_color = NULL, $background_color = NULL) {
        return $this->write($this->coloredText($text, $foreground_color, $background_color));
    }
    
    /**
     * Get standard input from console
     * @param	string $prompt_message
     * @param	bool $secure
     */
    public function promptInput($prompt_message, $secure = false) {
        $input = NULL;
        if (!empty($prompt_message)) {
            $this->write("$prompt_message: ", 0);
            
            if ($secure and $this->color_supported) {
                $this->write("\033[0;30m\033[40m", 0);
            }
            
            $input = trim($this->read());
            
            if ($secure and $this->color_supported) {
                $this->write("\033[0m", 0);
            }
        }
        return $input;
    }
    
    /**
     * Get colored text
     * @param	string $text
     * @param	string $foreground_color
     * @param	string $background_color
     */
    public function coloredText($text = '', $foreground_color = NULL, $background_color = NULL) {
        $str = '';
        $colored = false;
        if (!empty($text) and $this->color_supported) {
            if (!empty($foreground_color) and isset($this->foreground_colors[$foreground_color])) {
                $str .= "\033[" . $this->foreground_colors[$foreground_color] . "m";
                $colored = true;
            }
            if (!empty($background_color) and isset($this->background_colors[$background_color])) {
                $str .= "\033[" . $this->background_colors[$background_color] . "m";
                $colored = true;
            }
        }
        $str .= strval($text);
        if ($colored)
            $str .= "\033[0m";
        return $str;
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
        $hw = $this->cli_width;
        $text = array();
        $text[] = $this->coloredText(str_repeat('=', $hw), 'green');
        $text[] = $this->coloredText('Help for current command !', 'green');
        $text[] = $this->coloredText(str_repeat('-', $hw), 'green');
        if (!empty($this->args)) {
            $text[] = $this->coloredText('Given arguments: ', 'purple') . implode(', ', $this->args);
        } else {
            $text[] = $this->coloredText('No arguments given !', 'red');
        }
        $text[] = self::LF;
        if (!empty($this->options)) {
            $text[] = $this->coloredText('Registered options:', 'purple');
            $i = 1;
            foreach ($this->options as $opt => $option) {
                $text[] = self::LF;
                $text[] = $this->coloredText($i . ')', 'red') . self::TAB . $this->coloredText('Option: ', 'light_blue') . self::TAB . $opt;
                $text[] = self::TAB . $this->coloredText('Long Option: ', 'light_blue') . self::TAB . $option['long_opt'];
                $text[] = self::TAB . $this->coloredText('Description: ', 'light_blue') . self::TAB . $option['description'];
                $text[] = self::TAB . $this->coloredText('Required: ', 'light_blue') . self::TAB . (($option['required']) ? $this->coloredText('Yes', 'green') : $this->coloredText('No', 'red'));
                $text[] = self::TAB . $this->coloredText('Given Value: ', 'light_blue') . self::TAB . $option['value'];
                $i++;
            }
        } else {
            $text[] = $this->coloredText('No options registered !', 'red');
        }
        if (!empty($this->help_note)) {
            $text[] = self::LF;
            $text[] = $this->coloredText(str_repeat('-', $hw), 'yellow');
            $text[] = wordwrap($this->help_note, $hw, self::LF, true);
            $text[] = $this->coloredText(str_repeat('-', $hw), 'yellow');
        }
        $text[] = $this->coloredText(str_repeat('=', $hw), 'green');
        $this->write($text);
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
            $this->write($_msg . (($passive) ? self::LF : self::CR), 0);
        }
    }
    
    /**
     * Show progress percentage, to be used with loop
     * @param	int $totalCount
     * @param	int $currCount
     * @param	string $msg
     */
    public function showProgress($totalCount, $currCount, $msg = 'Processing...') {
        if ($totalCount > 0) {
            $p = floor((($currCount / $totalCount) * 100));
            $this->write($msg . ' ' . $p . '%' . (($p == 100) ? " Complete !" . self::LF : self::CR), 0);
        }
    }
    
    /**
     * Show progress bar, to be used with loop
     * @param	int $totalCount
     * @param	int $currCount
     */
    public function showProgressBar($totalCount, $currCount) {
        if ($totalCount > 0) {
            $p = floor((($currCount / $totalCount) * 100));
            $_b = '[' . str_pad(str_repeat('|', intval($p / 2)), 50) . ']';
            $this->write(' ' . $p . '% ' . self::TAB . $_b . (($p == 100) ? self::LF : self::CR), 0);
        }
    }
    
    /**
     * Enable/Disable colored output
     * @param	bool $colored
     */
    public function colorMode($colored = true) {
        $this->color_supported = (!$this->_isWindows() and $colored) ? true : false;
    }
    
    /**
     * Get foreground colors
     */
    public function getForegroundColors() {
        return array_keys($this->foreground_colors);
    }
    
    /**
     * Get background colors
     */
    public function getBackgroundColors() {
        return array_keys($this->background_colors);
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
     * Get cli width
     */
    public function getWidth() {
        return $this->cli_width;
    }
    
    /**
     * Print blank newlines
     * @param	int $count
     */
    public function nl($count = 1) {
        $this->write('', $count);
    }
    
    /**
     * Print horizontal rule
     * @param	int $size
     * @param	string $char
     */
    public function hr($size = 0, $char = '-') {
        $this->write(str_repeat($char, ($size ? $size : $this->cli_width)));
    }
    
    /**
     * Clear non-windows cli
     */
    public function clear() {
        if (!$this->_isWindows()) {
            passthru('clear');
        }
    }
    
    /**
     * Stop cli
     */
    public function stop($status = 0) {
        exit($status);
    }
    
    protected function _initialize($settings) {
        if (is_array($settings)) {
            $this->_registerOption('h', 'help', 'Shows help for current command.');
            if (isset($settings['options'])) {
                $this->_registerOptions($settings['options']);
            }
            if (isset($settings['help_note'])) {
                $this->_setHelpNote($settings['help_note']);
            }
            if (isset($settings['cli_width'])) {
                $this->cli_width = (int) $settings['cli_width'];
            }
            if ($this->isOption('h') or $this->isOption('help')) {
                $this->showHelp();
                $this->stop();
            }
        }
    }
    
    protected function _isCli() {
        return (php_sapi_name() == 'cli' or defined('STDIN'));
    }
    
    protected function _isWindows() {
        return (strtoupper(substr(php_uname('s'), 0, 3)) == 'WIN');
    }
    
    protected function _processArgs() {
        $_argc = isset($argc) ? $argc : $_SERVER['argc'];
        $_argv = isset($argv) ? $argv : $_SERVER['argv'];
        if ($_argc > 1) {
            array_shift($_argv);
            $this->args = $_argv;
        }
    }
    
    protected function _parseOptions() {
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
                        $this->printText('Error! Given option ' . $this->args[$opt_key] . ' requires a value.', 'red');
                        $this->nl();
                        $this->showHelp();
                        $this->stop();
                    }
                }
            }
        }
    }
    
    protected function _registerOption($option, $long_option = NULL, $description = NULL, $required = false) {
        if (!empty($option)) {
            $opt = '-' . substr(strval($option), 0, 1);
            $this->options[$opt] = array('long_opt' => NULL, 'description' => NULL, 'required' => false, 'value' => NULL);
            empty($long_option) or $this->options[$opt]['long_opt'] = '--' . strval($long_option);
            empty($description) or $this->options[$opt]['description'] = strval($description);
            $this->options[$opt]['required'] = (bool) $required;
        }
    }
    
    protected function _registerOptions($options) {
        if (!empty($options) and is_array($options)) {
            foreach ($options as $option) {
                if (is_array($option)) {
                    call_user_func_array(array($this, '_registerOption'), $option);
                }
            }
        }
        $this->_parseOptions();
    }
    
    protected function _setHelpNote($help_note) {
        empty($help_note) or $this->help_note = strval($help_note);
    }
    
    public function __destruct() {
        if (is_resource($this->stdout)) {
            fclose($this->stdout);
        }
        if (is_resource($this->stdin)) {
            fclose($this->stdin);
        }
    }
    
}

/* Cli Exception */
class CliException extends \Exception {
    
}
