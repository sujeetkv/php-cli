<?php
/**
 * PHP library to build command line tools
 *
 * @author  Sujeet <sujeetkv90@gmail.com>
 * @link    https://github.com/sujeet-kumar/php-cli
 */

namespace SujeetKumar\PhpCli\Helper;

use SujeetKumar\PhpCli\StdIO;
use SujeetKumar\PhpCli\CliException;

/**
 * TableLayout class
 */
class TableLayout
{
    /** @var string separator between columns */
    protected $separator = ' ';

    /** @var int the terminal width */
    protected $maxWidth = 75;
    
    /** @var array default column widths */
    protected $columnWidths = array();
    
    /** @var array default column alignments */
    protected $columnAligns = array();
    
    /** @var array default column attributes */
    protected $columnAttrs = array();

    /** @var StdIO class object */
    protected $stdio;

    /**
     * TableFormatter constructor.
     *
     * @param StdIO $stdio
     */
    public function __construct(StdIO $stdio) {
        $this->stdio = $stdio;
        $this->maxWidth = $this->stdio->getWidth();
        $this->columnWidths = $this->calculateColLengths(array('*'));
    }

    /**
     * The currently set separator (defaults to ' ')
     *
     * @return string
     */
    public function getSeparator() {
        return $this->separator;
    }

    /**
     * Set the border. The border is set between each column. Its width is
     * added to the column widths.
     *
     * @param string $separator
     */
    public function setSeparator($separator) {
        $this->separator = $separator;
        return $this;
    }

    /**
     * Width of the terminal in characters
     *
     * @return int
     */
    public function getMaxWidth() {
        return $this->maxWidth;
    }

    /**
     * Set the width of the terminal to assume
     *
     * @param int $max
     */
    public function setMaxWidth($max) {
        $this->maxWidth = $max;
        return $this;
    }
    
    /**
     * Set default width of columns
     *
     * @param array $columnWidths list of column widths (in characters, percent or '*')
     */
    public function setColWidths($columnWidths) {
        $this->columnWidths = $this->calculateColLengths($columnWidths);
        return $this;
    }
    
    /**
     * Get calculated column widths
     */
    public function getColWidths() {
        return $this->columnWidths;
    }
    
    /**
     * Set default alignment of columns
     *
     * @param array $columnAligns list of column alignments (left or right)
     */
    public function setColAligns($columnAligns = array()) {
        $this->columnAligns = $columnAligns;
        return $this;
    }
    
    /**
     * Set default attributes of columns
     *
     * @param array $columnAttrs list of column attributes (bold, underline, etc.)
     */
    public function setColAttrs($columnAttrs = array()) {
        $this->columnAttrs = $columnAttrs;
        return $this;
    }
    
    /**
     * Displays text in multiple word wrapped columns
     *
     * @param array $texts list of texts for each column
     * @param array $colors A list of color names to use for each column. use empty string for default
     * @param array $columnWidths list of column widths (in characters, percentage or '*')
     * @param array $columnAligns list of column alignments (left or right)
     * @param array $columnAttrs list of column attributes (bold, underline, etc.)
     */
    public function formatRow($texts, $colors = array(), $columnWidths = array(), $columnAligns = array(), $columnAttrs = array()) {
        $columnWidths = empty($columnWidths) ? $this->columnWidths : $this->calculateColLengths($columnWidths);
        empty($columnAligns) && $columnAligns = $this->columnAligns;
        empty($columnAttrs) && $columnAttrs = $this->columnAttrs;
        
        $wrapped = array();
        $maxlen = 0;
        
        foreach ($columnWidths as $col => $width) {
            //isset($texts[$col]) || $texts[$col] = '';
            $wrapped[$col] = array_map('ltrim', explode(StdIO::LF, $this->wordwrap($texts[$col], $width, StdIO::LF, true)));
            $len = count($wrapped[$col]);
            if ($len > $maxlen) {
                $maxlen = $len;
            }
        }
        
        $last = count($columnWidths) - 1;
        $out = array();
        for ($i = 0; $i < $maxlen; $i++) {
            $chunks = array();
            foreach ($columnWidths as $col => $width) {
                $val = isset($wrapped[$col][$i]) ? $wrapped[$col][$i] : '';
                $align = (isset($columnAligns[$col]) && $columnAligns[$col] == 'right') ? '' : '-';
                $chunk = sprintf('%' . $align . $width . 's', $val);
                
                $textColor = (isset($colors[$col]) && $colors[$col]) ? $colors[$col] : null;
                $textAttr = (isset($columnAttrs[$col]) && is_array($columnAttrs[$col])) ? $columnAttrs[$col] : array();
                if ($textColor || $textAttr) {
                    $chunk = $this->stdio->colorizeText($chunk, $textColor, null, $textAttr);
                }
                
                $chunks[] = $chunk;
            }
            $out[] = (empty($this->separator) || $this->separator == ' ') 
                     ? implode($this->separator, $chunks) 
                     : $this->separator . implode($this->separator, $chunks) . $this->separator;
        }
        
        return implode(StdIO::LF, $out);
    }

    /**
     * Takes an array with dynamic column width and calculates the correct width
     *
     * Column width can be given as fixed char widths, percentages and a single * width can be given
     * for taking the remaining available space. When mixing percentages and fixed widths, percentages
     * refer to the remaining space after allocating the fixed width
     *
     * @param array $columnWidths
     */
    protected function calculateColLengths($columnWidths) {
        $idx = 0;
        // separator are used already
        $fixed = (empty($this->separator) || $this->separator == ' ') 
                 ? (count($columnWidths) - 1) * $this->strlen($this->separator) 
                 : (count($columnWidths) + 1) * $this->strlen($this->separator);
        $fluid = -1;

        // first pass for format check and fixed columns
        foreach ($columnWidths as $idx => $col) {
            // handle fixed columns
            if ((string) intval($col) === (string) $col) {
                $fixed += $col;
                continue;
            }
            // check if other colums are using proper units
            if (substr($col, -1) == '%') {
                continue;
            }
            if ($col == '*') {
                // only one fluid
                if ($fluid < 0) {
                    $fluid = $idx;
                    continue;
                } else {
                    throw new CliException('Only one fluid column allowed!');
                }
            }
            throw new CliException("Unknown column format $col");
        }

        $alloc = $fixed;
        $remain = $this->maxWidth - $alloc;

        // second pass to handle percentages
        foreach ($columnWidths as $idx => $col) {
            if (substr($col, -1) != '%') {
                continue;
            }
            $perc = floatval($col);

            $real = (int) floor(($perc * $remain) / 100);

            $columnWidths[$idx] = $real;
            $alloc += $real;
        }

        $remain = $this->maxWidth - $alloc;
        if ($remain < 0) {
            throw new CliException('Wanted column widths exceed available space');
        }

        // assign remaining space
        if ($fluid < 0) {
            $columnWidths[$idx] += ($remain); // add to last column
        } else {
            $columnWidths[$fluid] = $remain;
        }

        return $columnWidths;
    }

    /**
     * Measures char length in UTF-8 when possible
     *
     * @param string $string
     */
    protected function strlen($string) {
        if (function_exists('mb_strlen')) {
            return mb_strlen($string, 'utf-8');
        }

        return strlen($string);
    }

    /**
     * @param string $string
     * @param int $start
     * @param int|null $length
     */
    protected function substr($string, $start = 0, $length = null) {
        if (function_exists('mb_substr')) {
            return mb_substr($string, $start, $length);
        } else {
            return substr($string, $start, $length);
        }
    }

    /**
     * wordwrap with multibyte support
     * @param string $str
     * @param int $width
     * @param string $break
     * @param bool $cut
     * @return string
     * @link http://stackoverflow.com/a/4988494
     */
    protected function wordwrap($str, $width = 75, $break = "\n", $cut = false) {
        $lines = explode($break, $str);
        foreach ($lines as &$line) {
            $line = rtrim($line);
            if ($this->strlen($line) <= $width) {
                continue;
            }
            $words = explode(' ', $line);
            $line = '';
            $actual = '';
            foreach ($words as $word) {
                if ($this->strlen($actual . $word) <= $width) {
                    $actual .= $word . ' ';
                } else {
                    if ($actual != '') {
                        $line .= rtrim($actual) . $break;
                    }
                    $actual = $word;
                    if ($cut) {
                        while ($this->strlen($actual) > $width) {
                            $line .= $this->substr($actual, 0, $width) . $break;
                            $actual = $this->substr($actual, $width);
                        }
                    }
                    $actual .= ' ';
                }
            }
            $line .= trim($actual);
        }
        return implode($break, $lines);
    }
}
