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
    /**
     * TableLayout
     * 
     * @var object
     */
    protected $tableLayout;
    
    /**
     * Formatted table header
     * 
     * @var string
     */
    protected $tableHeader = '';
    
    /**
     * Table rows
     * 
     * @var array
     */
    protected $tableData = array();
    
    /**
     * Final rendered table
     * 
     * @var string
     */
    protected $renderedTable = '';
    
    /**
     * Column widths
     * 
     * @var array
     */
    protected $colWidths = array();
    
    /**
     * Initialize Table class
     * 
     * @param StdIO $stdio
     */
    public function __construct(StdIO $stdio) {
        $this->tableLayout = new TableLayout($stdio);
        $this->tableLayout->setSeparator('|');
    }
    
    /**
     * Set table width
     * 
     * @param int $width
     */
    public function setWidth($width) {
        $this->tableLayout->setMaxWidth($width);
        return $this;
    }
    
    /**
     * Set table header
     * 
     * @param array $columns
     * @param array $columnWidths
     * @param array $columnAligns
     * @param array $colors
     */
    public function setHeader($columns, $columnWidths, $columnAligns = array(), $colors = array()) {
        is_array($columnWidths) && $this->tableLayout->setColWidths($columnWidths);
        is_array($columnAligns) && $this->tableLayout->setColAligns($columnAligns);
        is_array($columns) && $this->tableHeader = $this->tableLayout->formatRow(
            $columns, $colors, array(), array(), array_fill(0, count($columnWidths), array('bold'))
        );
        $this->colWidths = $this->tableLayout->getColWidths();
        return $this;
    }
    
    /**
     * Set table row
     * 
     * @param array $columns
     * @param array $colors
     * @param array $columnAligns
     */
    public function setRow($columns, $colors = array(), $columnAligns = array()) {
        $this->tableData[] = $this->tableLayout->formatRow($columns, $colors, array(), $columnAligns);
        return $this;
    }
    
    /**
     * Set table rows
     * 
     * @param array $rows
     * @param array $colors
     * @param array $columnAligns
     */
    public function setData($rows, $colors = array(), $columnAligns = array()) {
        foreach ($rows as $row) {
            is_array($row) && $this->setRow($row, $colors, $columnAligns);
        }
        return $this;
    }
    
    /**
     * Create final table
     */
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
    
    public function __toString() {
        return $this->render();
    }
}
