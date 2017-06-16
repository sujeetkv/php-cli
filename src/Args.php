<?php
/**
 * PHP library to build command line tools
 *
 * @author  Sujeet <sujeetkv90@gmail.com>
 * @link    https://github.com/sujeet-kumar/php-cli
 */

namespace SujeetKumar\PhpCli;

/**
 * Args class
 */
class Args
{
    /**
     * Current arguments
     * 
     * @var array
     */
    protected $arguments = array();
    
    /**
     * Parsed options
     * 
     * @var array
     */
    protected $options = array();
    
    /**
     * Current command
     * 
     * @var string
     */
    protected $command = null;
    
    /**
     * Initialize Args class
     * 
     * @param int $argsCount
     * @param array $argsValue
     */
    public function __construct($argsCount = 0, $argsValue = array()) {
        empty($argsCount) && $argsCount = isset($argc) ? $argc : (isset($_SERVER['argc']) ? $_SERVER['argc'] : 0);
        empty($argsValue) && $argsValue = isset($argv) ? $argv : (isset($_SERVER['argv']) ? $_SERVER['argv'] : array());
        
        if ($argsCount > 0 && !empty($argsValue)) {
            $this->command = array_shift($argsValue);
            $this->arguments = $argsValue;
        } else {
            throw new CliException('Could not process arguments.');
        }
    }
    
    /**
     * Register commands
     * 
     * @param array $commands
     */
    public function registerCommands($commands) {
        if (!empty($commands) && is_array($commands)) {
            foreach ($commands as $cmd => $cmdInfo) {
                $this->options[$cmd] = array('options' => array(), 'helpNote' => null);
                if (is_array($cmdInfo)) {
                    if (isset($cmdInfo['options']) && is_array($cmdInfo['options'])) {
                        foreach ($cmdInfo['options'] as $cmdOption) {
                            if (is_array($cmdOption)) {
                                array_unshift($cmdOption, $cmd);
                                call_user_func_array(array($this, 'registerCommand'), $cmdOption);
                            }
                        }
                    }
                    isset($cmdInfo['helpNote']) && $this->options[$cmd]['helpNote'] = $cmdInfo['helpNote'];
                }
                $this->registerCommand($cmd, 'h', 'help', 'Show help for current command.');
            }
        }
        $this->parseOptions();
        return $this;
    }
    
    /**
     * Get current command
     */
    public function getCommand() {
        return $this->command;
    }
    
    /**
     * Get all commands with options
     */
    public function getCommandList() {
        return $this->options;
    }
    
    /**
     * Get current arguments
     * 
     * @param int $index
     * @param string $default
     */
    public function get($index = null, $default = null) {
        if (null === $index) {
            return $this->arguments;
        } elseif (array_key_exists($index, $this->arguments)) {
            return $this->arguments[$index];
        } else {
            return $default;
        }
    }
    
    /**
     * Get key-value pairs of current arguments
     */
    public function getOpt() {
        $opts = array();
        $options = $this->getOption();
        foreach ($options as $opt => $option) {
            if ($this->hasOption($opt)) {
                $opts[$opt] = $option['value'];
            }
        }
        return $opts;
    }
    
    /**
     * Check if argument passed and registered
     * 
     * @param string $option
     * @param int $pos
     */
    public function hasOption($option, $pos = null) {
        if ($opt = $this->getOption($option)) {
            if (null === $pos) {
                return (in_array($opt['opt'], $this->arguments) || in_array($opt['longOpt'], $this->arguments));
            } else {
                return (isset($this->arguments[$pos]) 
                        && ($this->arguments[$pos] == $opt['opt'] || $this->arguments[$pos] == $opt['longOpt']));
            }
        } else {
            return false;
        }
    }
    
    /**
     * Get registered options
     * 
     * @param string $option
     * @param array $default
     */
    public function getOption($option = null, $default = array()) {
        $val = $default;
        if (!empty($this->options[$this->command]['options'])) {
            if (null === $option) {
                $val = $this->options[$this->command]['options'];
            } elseif (isset($this->options[$this->command]['options'][$option])) {
                $val = $this->options[$this->command]['options'][$option];
            }
        }
        return $val;
    }
    
    /**
     * Get registered option value
     * 
     * @param string $option
     * @param mixed $default
     */
    public function getOptionValue($option, $default = null) {
        return ($optVal = $this->getOption($option)) ? $optVal['value'] : $default;
    }
    
    /**
     * Check if valid registered option
     * 
     * @param string $option
     */
    public function isValidOption($option) {
        return (false !== $this->getOption($option, false));
    }
    
    /**
     * Get help note of current command
     */
    public function getHelpNote() {
        return isset($this->options[$this->command]['helpNote']) ? $this->options[$this->command]['helpNote'] : null;
    }
    
    protected function registerCommand($command, $option = null, $longOption = null, $description = null) {
        if (!empty($command) && !empty($option)) {
            $opt = substr(strval($option), 0, 1);
            $this->options[$command]['options'][$opt] = array('opt' => '-' . $opt, 'longOpt' => null, 'description' => null, 'value' => null);
            empty($longOption) || $this->options[$command]['options'][$opt]['longOpt'] = '--' . strval($longOption);
            empty($description) || $this->options[$command]['options'][$opt]['description'] = strval($description);
        }
    }
    
    protected function parseOptions() {
        if (!empty($this->options) && !empty($this->arguments) && isset($this->options[$this->command]['options']) && is_array($this->options[$this->command]['options'])) {
            foreach ($this->options[$this->command]['options'] as $opt => $optInfo) {
                if (($optKey = array_search($optInfo['opt'], $this->arguments)) !== false || ($optKey = array_search($optInfo['longOpt'], $this->arguments)) !== false) {
                    $optValKey = $optKey + 1;
                    if (isset($this->arguments[$optValKey]) && !preg_match('/^(-|--)/', $this->arguments[$optValKey])) {
                        $this->options[$this->command]['options'][$opt]['value'] = $this->arguments[$optValKey];
                    }
                }
            }
        }
    }
}
