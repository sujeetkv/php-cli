<?php
/**
 * PHP library to build command line tools
 *
 * @author  Sujeet <sujeetkv90@gmail.com>
 * @link    https://github.com/sujeet-kumar/php-cli
 */

namespace SujeetKumar\PhpCli;

use SujeetKumar\PhpCli\Helper\ActiveLine;
use SujeetKumar\PhpCli\Helper\TableLayout;
use SujeetKumar\PhpCli\Helper\Table;
use SujeetKumar\PhpCli\Helper\Figlet;

/**
 * Cli class
 */
class Cli
{
    /**
     * @var object StdIO
     */
    public $stdio;
    
    /**
     * @var object Args
     */
    public $args;
    
    /**
     * @var object Prompt
     */
    public $prompt;
    
    /**
     * @var object ActiveLine
     */
    public $activeLine;
    
    /**
     * @var object Figlet
     */
    public $figlet;
    
    /**
     * @var string Help note for current script
     */
    protected $helpNote = '';
    
    /**
     * Constructor
     * 
     * @param array $commands
     * @param int $argsCount
     * @param array $argsValue
     */
    public function __construct($commands = array(), $argsCount = 0, $argsValue = array()) {
        if (!StdIO::isCli()) {
            throw new CliException('This program is only meant for Command Line Interface.');
        }
        
        ini_set('html_errors', 0);
        set_time_limit(0);
        
        $this->stdio = new StdIO();
        $this->args = new Args($argsCount, $argsValue);
        $this->prompt = new Prompt($this);
        $this->activeLine = new ActiveLine($this->stdio);
        
        $this->initialize($commands);
    }
    
    /**
     * Bind function or method to registered option
     * 
     * @param string $option
     * @param callable $callback
     * @param array $params
     */
    public function bindOption($option, $callback, $params = array()) {
        if ($this->args->hasOption($option)) {
            $opt = $this->args->getOption($option);
            if (!empty($params)) {
                array_unshift($params, $this);
                $_params = $params;
            } else {
                $_params = array($this, $opt['value']);
            }
            if (!is_callable($callback, false, $callbackName)) {
                throw new CliException('Invalid callback ' . $callbackName . ' to ' . __METHOD__ . ' for option "' . $option . '"');
            } else {
                call_user_func_array($callback, $_params);
            }
        }
        return $this;
    }
    
    /**
     * Print Help Content
     * 
     * @param Args $args
     * @param string $helpNote
     */
    public function showHelp($args, $helpNote = null) {
        $text = array();
        $tableLayout = new TableLayout($this->stdio);
        $tableLayout->setMaxWidth(min(75, $this->stdio->getWidth()));
        $options = $args->getOption();
        $text[] = $tableLayout->formatRow(array('Usage:'), array('brown'));
        $optHeading = $tableLayout->formatRow(array('Options:'), array('brown'));
        
        $tableLayout->setColWidths(array('5%', '*'));
        if (empty($options)) {
            $text[] = $tableLayout->formatRow(array('', $args->getCommand()));
        } else {
            $text[] = $tableLayout->formatRow(array('', $args->getCommand() . ' [OPTION] [VALUE] ...'));
            $text[] = $tableLayout->formatRow(array('', ''));
            $text[] = $optHeading;
            $tableLayout->setColWidths(array('5%', '40%', '*'));
            foreach ($options as $opt) {
                $text[] = $tableLayout->formatRow(array(
                    '',
                    $opt['opt'] . ', ' . $opt['longOpt'],
                    $opt['description']
                ), array(
                    '',
                    'green'
                ));
            }
        }
        
        empty($helpNote) && $helpNote = $args->getHelpNote();
        if (!empty($helpNote)) {
            $tableLayout->setColWidths(array('*'));
            $text[] = $tableLayout->formatRow(array(''));
            $text[] = $tableLayout->formatRow(array($helpNote));
        }
        
        $this->stdio->write($text, 2);
        return $this;
    }
    
    /**
     * Get Table object
     * 
     * @param int $width
     */
    public function getTable($width = null) {
        $table = new Table($this->stdio);
        empty($width) || $table->setWidth($width);
        return $table;
    }
    
    /**
     * Get figlet of text
     * 
     * @param string $text
     * @param string $color
     * @param string $fontFile
     * @param bool $loadGerman
     */
    public function createFiglet($text, $color = null, $fontFile = null, $loadGerman = true) {
        $this->figlet || $this->figlet = new Figlet();
        $fontFile && $this->figlet->loadFont($fontFile, $loadGerman);
        return $this->stdio->colorizeText($this->figlet->render($text), $color);
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
     * Exit the execution
     * 
     * @param int $status exit code
     */
    public function exitScript($status = 0) {
        exit($status);
    }
    
    /**
     * Set help note
     * 
     * @param string $helpNote
     */
    public function setHelpNote($helpNote) {
        empty($helpNote) || $this->helpNote = strval($helpNote);
        return $this;
    }
    
    protected function initialize($commands) {
        if (is_array($commands)) {
            $this->args->registerCommands($commands);
            if ($this->args->hasOption('h')) {
                $this->showHelp($this->args);
                $this->exitScript();
            }
        }
    }
}
