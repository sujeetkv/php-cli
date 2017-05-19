<?php
/**
 * PHP library to build command line tools
 *
 * @author  Sujeet <sujeetkv90@gmail.com>
 * @link    https://github.com/sujeet-kumar/php-cli
 */

namespace SujeetKumar\PhpCli;

/**
 * StdIO class
 */
class StdIO
{
    const CR = "\r";
    const LF = "\n";
    const TAB = "\t";
    const EOL = PHP_EOL;
    
    protected $stdout = null;
    protected $stdin = null;
    protected $hasReadline = false;
    protected $hasColorSupport = false;
    protected $colorEnabled = true;
    protected $cliWidth = 80;
    protected static $stty = null;
    
    protected $foregroundColor = null;
    protected $backgroundColor = null;
    
    protected $foregroundColors = array(
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
    
    protected $backgroundColors = array(
        'black'         => '40',
        'red'           => '41',
        'green'         => '42',
        'yellow'        => '43',
        'blue'          => '44',
        'magenta'       => '45',
        'cyan'          => '46',
        'light_gray'    => '47'
    );
    
    public function __construct() {
        $this->stdout = @fopen('php://stdout', 'w');
        $this->stdin = @fopen('php://stdin', 'r');
        
        if (!$this->stdout) {
            throw new CliException('Could not open output stream.');
        }
        
        if (!$this->stdin) {
            throw new CliException('Could not open input stream.');
        }
        
        $this->hasReadline = (extension_loaded('readline') && function_exists('readline'));
        
        if (self::isWindows()) {
            $this->hasColorSupport = ('10.0.10586' === PHP_WINDOWS_VERSION_MAJOR.'.'.PHP_WINDOWS_VERSION_MINOR.'.'.PHP_WINDOWS_VERSION_BUILD
                || false !== getenv('ANSICON')
                || 'ON' === getenv('ConEmuANSI')
                || 'xterm' === getenv('TERM'));
        } else {
            $this->hasColorSupport = (function_exists('posix_isatty') && @posix_isatty($this->stream));
        }
    }
    
    /**
     * Read line from console
     * @param string $prompt
     */
    public function read($prompt = '') {
        if ($this->hasReadline) {
            $line = readline($prompt);
        } else {
            $this->write($prompt);
            $line = fgets($this->stdin);
            $line = (false === $line || '' === $line) ? false : rtrim($line);
        }
        return $line;
    }
    
    /**
     * Write text to console
     * @param mixed $text
     * @param int $newlines
     */
    public function write($text, $newlines = 0) {
        if (is_array($text)) {
            $text = implode(self::EOL, $text);
        }
        if ($this->foregroundColor || $this->backgroundColor) {
            $text = $this->colorizeText($text, $this->foregroundColor, $this->backgroundColor);
            $this->foregroundColor = $this->backgroundColor = null;
        }
        return fwrite($this->stdout, $text . str_repeat(self::EOL, $newlines));
    }
    
    /**
     * Write text to console and add a new line at end
     * @param mixed $text
     */
    public function writeln($text) {
        return $this->write($text, 1);
    }
    
    /**
     * Set color for next write operation
     * @param string $foregroundColor
     * @param string $backgroundColor
     */
    public function setColor($foregroundColor = null, $backgroundColor = null) {
        $this->foregroundColor = $foregroundColor;
        $this->backgroundColor = $backgroundColor;
        return $this;
    }
    
    /**
     * Get colored text
     * @param string $text
     * @param string $foregroundColor
     * @param string $backgroundColor
     */
    public function colorizeText($text = '', $foregroundColor = null, $backgroundColor = null) {
        $str = '';
        $colored = false;
        if ($text !== '' && $this->hasColorSupport && $this->colorEnabled) {
            if (!empty($foregroundColor) && isset($this->foregroundColors[$foregroundColor])) {
                $str .= "\033[" . $this->foregroundColors[$foregroundColor] . "m";
                $colored = true;
            }
            if (!empty($backgroundColor) && isset($this->backgroundColors[$backgroundColor])) {
                $str .= "\033[" . $this->backgroundColors[$backgroundColor] . "m";
                $colored = true;
            }
        }
        $str .= $text;
        if ($colored) {
            $str .= "\033[0m";
        }
        return $str;
    }
    
    public function hasColorSupport() {
        return $this->hasColorSupport;
    }
    
    public function colorEnabled() {
        return $this->colorEnabled;
    }
    
    public function hasReadline() {
        return $this->hasReadline;
    }
    
    /**
     * Get foreground colors
     */
    public function getForegroundColors() {
        return array_keys($this->foregroundColors);
    }
    
    /**
     * Get background colors
     */
    public function getBackgroundColors() {
        return array_keys($this->backgroundColors);
    }
    
    /**
     * Get cli width
     */
    public function getWidth() {
        return $this->cliWidth;
    }
    
    /**
     * Print blank newlines
     * @param int $count
     */
    public function ln($count = 1) {
        $this->write('', $count);
    }
    
    /**
     * Print horizontal rule
     * @param int $size
     * @param string $char
     */
    public function hr($size = 0, $char = '-') {
        $this->writeln(str_repeat($char, ($size ? $size : $this->cliWidth)));
    }
    
    /**
     * Clear non-windows cli
     */
    public function clear() {
        if (!self::isWindows()) {
            passthru('clear');
        }
    }
    
    public static function isCli() {
        return (php_sapi_name() == 'cli' or defined('STDIN'));
    }
    
    public static function hasStty() {
        if (null !== self::$stty) {
            return self::$stty;
        }
        exec('stty 2>&1', $output, $exitcode);
        return (self::$stty = $exitcode === 0);
    }
    
    public static function isWindows() {
        return (DIRECTORY_SEPARATOR === '\\');
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