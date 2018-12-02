<?php
/**
 * PHP library to build command line tools
 *
 * @author  Sujeet <sujeetkv90@gmail.com>
 * @link    https://github.com/sujeetkv/php-cli
 */

namespace SujeetKV\PhpCli\Helper;

use SujeetKV\PhpCli\StdIO;

/**
 * ActiveLine class
 */
class ActiveLine
{
    protected $stdio;
    
    protected $msgLength = 0;
    
    protected $progressTotal = 0;
    protected $progressMessage = 0;
    
    protected $progressBarWidth = 0;
    protected $progressBarTotal = 0;
    protected $progressBarColor = 'green';
    protected $progressBarSolid = true;
    protected $progressBarInfo = ' ';
    
    protected static $spinnerIndex = 0;
    protected static $spinnerSymbols = array('   ', '.  ', '.. ', '...');
    
    /**
     * Constructor
     * 
     * @param StdIO $stdio
     */
    public function __construct(StdIO $stdio) {
        $this->stdio = $stdio;
    }
    
    /**
     * Set active message
     * 
     * @param string $msg
     * @param bool $active
     */
    public function setMessage($msg, $active = true) {
        $len = strlen($msg);
        if ($len) {
            $line_text = ($len < $this->msgLength) ? str_pad($msg, $this->msgLength) : $msg;
            $this->msgLength = $len;
            $this->stdio->overwrite(' ' . $line_text);
            $active or $this->stdio->ln();
        }
    }
    
    /**
     * Start active progress
     * 
     * @param int $totalStep
     * @param string $msg
     */
    public function startProgress($totalStep, $msg = 'Processing...') {
        $this->progressTotal = $totalStep;
        $this->progressMessage = $msg;
        $this->updateProgress(0);
    }
    
    /**
     * Update active progress
     * 
     * @param int $currentStep
     * @param string $msg
     */
    public function updateProgress($currentStep, $msg = null) {
        is_null($msg) or $this->progressMessage = $msg;
        if ($this->progressTotal > 0) {
            $p = floor(($currentStep / $this->progressTotal) * 100);
            $this->stdio->overwrite(' ' . $this->progressMessage . ' ' . $p . '% ');
        }
    }
    
    /**
     * Finish active progress
     * 
     * @param string $msg
     */
    public function finishProgress($msg = null) {
        $this->updateProgress($this->progressTotal, $msg);
        $this->stdio->ln();
    }
    
    /**
     * Start active progress bar
     * 
     * @param int $totalStep
     * @param string $info
     * @param int $maxWidth
     * @param bool $solid
     * @param string $color
     */
    public function startProgressBar($totalStep, $info = null, $maxWidth = null, $solid = true, $color = 'green') {
        $this->progressBarTotal = $totalStep;
        is_null($info) or $this->progressBarInfo = $info;
        $this->progressBarColor = $color;
        $this->progressBarSolid = (bool) $solid;
        $this->progressBarWidth = $this->stdio->getWidth();
        empty($maxWidth) or $this->progressBarWidth = min(intval($maxWidth), $this->progressBarWidth);
        $this->updateProgressBar(0);
    }
    
    /**
     * Update active progress bar
     * 
     * @param int $currentStep
     * @param string $info
     */
    public function updateProgressBar($currentStep, $info = null) {
        is_null($info) or $this->progressBarInfo = $info;
        if ($this->progressBarTotal > 0) {
            $p = floor((($currentStep / $this->progressBarTotal) * 100));
            $status = str_pad($p, 3, ' ', STR_PAD_LEFT) . '%';
            $remlen = $this->progressBarWidth - (strlen($status) + strlen($this->progressBarInfo) + 4);
            $div = (100 / $remlen);
            $blen = min($remlen, ceil($p / $div));
            $textColor = empty($this->progressBarColor) ? null : $this->progressBarColor;
            $bgColor = $this->progressBarSolid ? $this->progressBarColor : null;
            $bar = '|' . $this->stdio->colorizeText(str_repeat('#', $blen), $textColor, $bgColor) . str_repeat('_', ($remlen - $blen)) . '|';
            $this->stdio->overwrite($status . ' ' . $bar . ' ' . $this->progressBarInfo);
        }
    }
    
    /**
     * Finish active progress bar
     * 
     * @param string $info
     */
    public function finishProgressBar($info = null) {
        $this->updateProgressBar($this->progressBarTotal, $info);
        $this->stdio->ln();
    }
    
    /**
     * Get active spinner symbol
     * 
     * @param bool $reset
     */
    public static function getSpinner($reset = false) {
        $reset and self::$spinnerIndex = 0;
        $spinnerSymbol = self::$spinnerSymbols[self::$spinnerIndex++];
        $maxIndex = count(self::$spinnerSymbols) - 1;
        self::$spinnerIndex = (self::$spinnerIndex > $maxIndex) ? 0 : self::$spinnerIndex;
        return $spinnerSymbol;
    }
}
