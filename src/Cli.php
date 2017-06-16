<?php
/**
 * PHP library to build command line tools
 *
 * @author  Sujeet <sujeetkv90@gmail.com>
 * @link    https://github.com/sujeet-kumar/php-cli
 */

namespace SujeetKumar\PhpCli;

use SujeetKumar\PhpCli\Helper\TableLayout;
use SujeetKumar\PhpCli\Helper\Table;
use SujeetKumar\PhpCli\Helper\Figlet;

/**
 * Cli class
 */
class Cli
{
    /**
     * StdIO
     * 
     * @var object
     */
    public $stdio;
    
    /**
     * Args
     * 
     * @var object
     */
    public $args;
    
    /**
     * Prompt
     * 
     * @var object
     */
    public $prompt;
    
    /**
     * Figlet
     * 
     * @var object
     */
    public $figlet;
    
    /**
     * Help note for current script
     * 
     * @var string
     */
    protected $helpNote = '';
    
    /**
     * Initialize Cli class
     * 
     * @param array $settings
     */
    public function __construct($commands = array()) {
        if (!StdIO::isCli()) {
            throw new CliException('This program is only meant for Command Line Interface.');
        }
        
        ini_set('html_errors', 0);
        set_time_limit(0);
        
        $this->stdio = new StdIO();
        $this->args = new Args();
        $this->prompt = new Prompt($this);
        
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
        $text[] = $tableLayout->formatRow(array('Usage:'));
        $optHeading = $tableLayout->formatRow(array('Available options are:'));
        
        $tableLayout->setColWidths(array('5%', '*'));
        if (empty($options)) {
            $text[] = $tableLayout->formatRow(array('', $args->getCommand()));
        } else {
            $text[] = $tableLayout->formatRow(array('', $args->getCommand() . ' [OPTION] [VALUE] ...')) . StdIO::EOL;
            $text[] = $optHeading;
            $tableLayout->setColWidths(array('5%', '40%', '*'));
            foreach ($options as $opt) {
                $text[] = $tableLayout->formatRow(array(
                    '',
                    $opt['opt'] . ', ' . $opt['longOpt'],
                    $opt['description']
                ));
            }
        }
        
        empty($helpNote) && $helpNote = $args->getHelpNote();
        if (!empty($helpNote)) {
            $tableLayout->setColWidths(array('*'));
            $text[] = StdIO::EOL . $tableLayout->formatRow(array($helpNote));
        }
        
        $this->stdio->write($text, 2);
        return $this;
    }
    
    /**
     * Print and Overwrite line, useful to show current status
     * 
     * @param string $msg
     */
    public function showStatus($msg) {
        static $_len = 0;
        $len = strlen($msg);
        if ($len) {
            $_msg = ($len < $_len) ? str_pad($msg, $_len) : $msg;
            $_len = $len;
            $this->stdio->overwrite(' ' . $_msg);
        }
    }
    
    /**
     * Show progress percentage, to be used with loop
     * 
     * @param int $totalStep
     * @param int $currentStep
     * @param string $msg
     */
    public function showProgress($totalStep, $currentStep, $msg = 'Processing...') {
        if ($totalStep > 0) {
            $p = floor((($currentStep / $totalStep) * 100));
            $this->stdio->overwrite(' ' . $msg . ' ' . $p . '% ');
        }
    }
    
    /**
     * Show progress bar, to be used with loop
     * 
     * @param int $totalStep
     * @param int $currentStep
     * @param string $info
     * @param int $maxWidth
     */
    public function showProgressBar($totalStep, $currentStep, $info = ' ', $maxWidth = null) {
        static $width = null;
        $cliWidth = $this->stdio->getWidth();
        is_null($width) && $width = $cliWidth;
        empty($info) && $info = ' ';
        empty($maxWidth) || $width = min($maxWidth, $cliWidth);
        
        if ($totalStep > 0) {
            $p = floor((($currentStep / $totalStep) * 100));
            //$bar = '[' . str_pad(str_repeat('#', intval($p / 2)), 50, '_') . ']';
            $status = str_pad($p, 3, ' ', STR_PAD_LEFT) . '%';
            $remlen = $width - (strlen($status) + strlen($info) + 4);
            $div = (100 / $remlen);
            $blen = min($remlen, ceil($p / $div));
            $bar = '|' . $this->stdio->colorizeText(str_repeat('#', $blen), 'green', 'green') . str_repeat('_', ($remlen - $blen)) . '|';
            $this->stdio->overwrite($status . ' ' . $bar . ' ' . $info);
        }
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
     * @param string $fontFile
     */
    public function createFiglet($text, $fontFile = null, $loadGerman = true) {
        $this->figlet || $this->figlet = new Figlet();
        $fontFile && $this->figlet->loadFont($fontFile, $loadGerman);
        return $this->figlet->render($text);
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
    public function stop($status = 0) {
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
                $this->stop();
            }
        }
    }
}
