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
    protected $arguments = array();
    protected $options = array();
    protected $command = null;
    
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
    
    public function getCommand() {
        return $this->command;
    }
    
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
    
    public function getOpt() {
        $opts = array();
        $options = $this->getOptions();
        foreach ($options as $opt => $option) {
            if ($this->isOption(substr($option['opt'], 1)) || $this->isOption(substr($option['longOpt'], 2))) {
                $opts[$opt] = $option['value'];
            }
        }
        return $opts;
    }
    
    /**
     * Check if argument passed
     * 
     * @param string $option
     * @param int $pos
     */
    public function hasOption($option, $pos = null) {
        if (null === $pos) {
            return (in_array('-' . $option, $this->arguments) || in_array('--' . $option, $this->arguments));
        } else {
            return (isset($this->arguments[$pos]) && ($this->arguments[$pos] == '-' . $option || $this->arguments[$pos] == '--' . $option));
        }
    }
    
    /**
     * Get registered options
     * 
     * @param string $option
     * @param array $default
     */
    public function getOptions($option = null, $default = array()) {
        $val = $default;
        if (!empty($this->options[$this->command]['options'])) {
            if (null === $option) {
                $val = $this->options[$this->command]['options'];
            } else {
                foreach ($this->options[$this->command]['options'] as $opt => $optVal) {
                    if ('-' . $option == $optVal['opt'] || '--' . $option == $optVal['longOpt']) {
                        $val = $optVal;
                        break;
                    }
                }
            }
        }
        return $val;
    }
    
    public function getHelpNote() {
        return isset($this->options[$this->command]['helpNote']) ? $this->options[$this->command]['helpNote'] : null;
    }
    
    /**
     * Get registered option value
     * 
     * @param string $option
     * @param mixed $default
     */
    public function getOptionValue($option, $default = null) {
        return ($optVal = $this->getOptions($option)) ? $optVal['value'] : $default;
    }
    
    /**
     * Check if valid registered option
     * 
     * @param string $option
     */
    public function isValidOption($option) {
        return (false !== $this->getOptions($option, false));
    }
    
    /**
     * Check if option is registered and passed as argument
     * 
     * @param string $option
     * @param int $pos
     */
    public function isOption($option, $pos = null) {
        return ($this->isValidOption($option) && $this->hasOption($option, $pos));
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