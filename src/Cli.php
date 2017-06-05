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
    public $prompt;
    protected $helpNote = '';
    
    /**
     * Initialize cli
     * 
     * @param array $settings
     */
    public function __construct($commands = array()) {
        if (!StdIO::isCli()) {
            throw new CliException('"This program is only meant for Command Line Interface.');
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
        return $this;
    }
    
    /**
     * Print Help Content
     * 
     * @param Args $args
     * @param string $helpNote
     */
    public function showHelp($args, $helpNote = null) {
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
        
        empty($helpNote) && $helpNote = $args->getHelpNote();
        empty($helpNote) || $text[] = StdIO::EOL . $tableLayout->formatRow(array($helpNote));
        
        $this->stdio->write($text, 2);
        return $this;
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
            //$b = '[' . str_pad(str_repeat('#', intval($p / 2)), 50, '_') . ']';
            $blen = intval($p / 2);
            $b = '[' . $this->stdio->colorizeText(str_repeat('#', $blen), 'green', 'green') . str_repeat('_', (50 - $blen)) . ']';
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
     * Stop cli
     */
    public function stop($status = 0) {
        exit($status);
    }
    
    public function setHelpNote($helpNote) {
        empty($helpNote) || $this->helpNote = strval($helpNote);
        return $this;
    }
    
    protected function initialize($commands) {
        if (is_array($commands)) {
            $this->args->registerCommands($commands);
            if ($this->args->isOption('h') || $this->args->isOption('help')) {
                $this->showHelp($this->args);
                $this->stop();
            }
        }
    }
}
