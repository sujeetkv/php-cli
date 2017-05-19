<?php
/**
 * PHP library to build command line tools
 *
 * @author  Sujeet <sujeetkv90@gmail.com>
 * @link    https://github.com/sujeet-kumar/php-cli
 */

namespace SujeetKumar\PhpCli;

/**
 * Table class
 */
class Table {

    /** @var string border between columns */
    protected $border = ' ';

    /** @var int the terminal width */
    protected $maxWidth = 75;

    /** @var StdIO for IO */
    protected $stdio;

    /**
     * TableFormatter constructor.
     *
     * @param StdIO $stdio
     */
    public function __construct(StdIO $stdio) {
        $this->stdio = $stdio;
        $this->maxWidth = $this->stdio->getWidth();
    }

    /**
     * The currently set border (defaults to ' ')
     *
     * @return string
     */
    public function getBorder() {
        return $this->border;
    }

    /**
     * Set the border. The border is set between each column. Its width is
     * added to the column widths.
     *
     * @param string $border
     */
    public function setBorder($border) {
        $this->border = $border;
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
        $border = $this->strlen($this->border);
        $fixed = (count($columnWidths) - 1) * $border; // borders are used already
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
                    throw new Exception('Only one fluid column allowed!');
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
            throw new Exception('Wanted column widths exceed available space');
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
     * Displays text in multiple word wrapped columns
     *
     * @param array $columnWidths list of column widths (in characters, percent or '*')
     * @param array $texts list of texts for each column
     * @param array $colors A list of color names to use for each column. use empty string for default
     */
    public function format($columnWidths, $texts, $colors = array()) {
        $columnWidths = $this->calculateColLengths($columnWidths);

        $wrapped = array();
        $maxlen = 0;

        foreach ($columnWidths as $col => $width) {
            $wrapped[$col] = explode(StdIO::LF, $this->wordwrap($texts[$col], $width, StdIO::LF, true));
            $len = count($wrapped[$col]);
            if ($len > $maxlen) {
                $maxlen = $len;
            }
        }

        $last = count($columnWidths) - 1;
        $out = '';
        for ($i = 0; $i < $maxlen; $i++) {
            foreach ($columnWidths as $col => $width) {
                if (isset($wrapped[$col][$i])) {
                    $val = $wrapped[$col][$i];
                } else {
                    $val = '';
                }
                $chunk = sprintf('%-' . $width . 's', $val);
                if (isset($colors[$col]) && $colors[$col]) {
                    $chunk = $this->stdio->colorizeText($chunk, $colors[$col]);
                }
                $out .= $chunk;

                // border
                if ($col != $last) {
                    $out .= $this->border;
                }
            }
            $out .= StdIO::EOL;
        }
        return $out;
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
