<?php
/**
 * PHP library to build command line tools
 *
 * @author  Sujeet <sujeetkv90@gmail.com>
 * @link    https://github.com/sujeet-kumar/php-cli
 */

namespace SujeetKumar\PhpCli\Helper;

use SujeetKumar\PhpCli\StdIO;

/**
 * Table class
 */
class Table
{
    protected $tableLayout;
    protected $tableHeader = '';
    protected $tableData = array();
    protected $renderedTable = '';
    protected $colWidths = array();
    
    public function __construct(StdIO $stdio) {
        $this->tableLayout = new TableLayout($stdio);
        $this->tableLayout->setSeparator('|');
    }
    
    public function setWidth($width) {
        $this->tableLayout->setMaxWidth($width);
        return $this;
    }
    
    public function setHeader($columns, $columnWidths, $columnAligns = array(), $colors = array()) {
        is_array($columnWidths) && $this->tableLayout->setColWidths($columnWidths);
        is_array($columnAligns) && $this->tableLayout->setColAligns($columnAligns);
        is_array($columns) && $this->tableHeader = $this->tableLayout->formatRow(
            $columns, $colors, array(), array(), array_fill(0, count($columnWidths), array('bold'))
        );
        $this->colWidths = $this->tableLayout->getColWidths();
        return $this;
    }
    
    public function setRow($columns, $colors = array(), $columnAligns = array()) {
        $this->tableData[] = $this->tableLayout->formatRow($columns, $colors, array(), $columnAligns);
        return $this;
    }
    
    public function setData($rows, $colors = array(), $columnAligns = array()) {
        foreach ($rows as $row) {
            is_array($row) && $this->setRow($row, $colors, $columnAligns);
        }
        return $this;
    }
    
    public function render() {
        $borderArr = array();
        $border = '';
        foreach ($this->colWidths as $colWidth) {
            $borderArr[] = str_repeat('-', $colWidth);
        }
        empty($borderArr) || $border = $this->tableLayout->setSeparator('+')->formatRow($borderArr);
        
        empty($border) || $this->renderedTable .= $border . StdIO::EOL;
        $this->renderedTable .= $this->tableHeader . StdIO::EOL;
        empty($border) || $this->renderedTable .= $border . StdIO::EOL;
        $this->renderedTable .= implode(StdIO::EOL, $this->tableData) . StdIO::EOL;
        empty($border) || $this->renderedTable .= $border . StdIO::EOL;
        
        return $this->renderedTable;
    }
}
