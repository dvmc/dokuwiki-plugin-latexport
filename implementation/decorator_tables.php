<?php

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

require_once DOKU_PLUGIN . 'latexport/implementation/decorator.php';

class CellSize {

	public $colspan;

	public $rowspan;

	static function makeRow($maxcols) {
		$row = [];
		for ($n = 0; $n < $maxcols; $n++) {
			$row[] = new CellSize();
		}
		return $row;
	}

	public function __construct($colspan = 1, $rowspan = 0) {
		$this->colspan = $colspan;
		$this->rowspan = $rowspan;
	}

	public function setSize($colspan, $rowspan) {
		$this->colspan = $colspan;
		$this->rowspan = $rowspan;
	}

	public function getCols() {
		return $this->colspan;
	}

	public function getRows() {
		return $this->rowspan;
	}


	public function nextCellSize() {
		if ($this->rowspan > 0) {
			return new CellSize($this->colspan, $this->rowspan - 1);
		} else {
			return new CellSize();
		}
	}

	public function __toString() {
		return "<c=$this->colspan,r=$this->rowspan>";
	}
}

/**
 * Adapts the tables to latex.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author Jean-Michel Gonet <jmgonet@yahoo.com>
 */
class DecoratorTables extends Decorator {

	private $row;

	private $column;

	private $inTable;

	/**
	 * Open a paragraph.
	 */
	function p_open() {
		if ($this->inTable) {
			// No paragraphs allowed in tables
		} else {
			$this->decorator->p_open();
		}
	}

	/**
	 * Close a paragraph.
	 */
	function p_close() {
		if ($this->inTable) {
			$this->decorator->linebreak();
		} else {
			$this->decorator->p_close();
		}
	}

    /**
     * Verbatim is not supported inside makecell (it should go inside
	 * a mini page), so best next option is not to output verbatim,
	 * and hope for the best.
     *
     * @param string $text
     */
    function unformatted($text) {
		if ($this->inTable) {
			$this->decorator->cdata($text);
		} else {
			$this->decorator->unformatted($text);
		}
    }

    /**
     * Start a table
     *
     * @param int $maxcols maximum number of columns
     * @param int $numrows NOT IMPLEMENTED
     * @param int $pos     byte position in the original source
     */
    function table_open($maxcols = null, $numrows = null, $pos = null) {
		$this->row = CellSize::makeRow($maxcols);
		$this->decorator->table_open($maxcols, $numrows, $pos);
		$this->inTable = true;
    }

    function table_close($pos = null) {
		$this->decorator->table_close($pos);
		$this->inTable = false;
	}

    /**
     * Open a table row
     */
    function tablerow_open() {
		$this->column = 0;
		$this->decorator->tablerow_open();
    }

    /**
     * Close a table row
     */
    function tablerow_close() {
		$this->decorator->tablerow_close();
		$this->computeCline();
		$this->computeNextLine();
    }


    /**
     * Open a table header cell
     *
     * @param int    $colspan
     * @param string $align left|center|right
     * @param int    $rowspan
     */
    function tableheader_open($colspan = 1, $align = null, $rowspan = 1) {
		$numberOfPlaceholders = $this->computePlaceholders($colspan, $rowspan);
		for ($n = 0; $n < $numberOfPlaceholders; $n++) {
			$this->decorator->tableheader_open(1, null, 1);
			$this->decorator->tableheader_close(1, null, 1);
		}
		$this->decorator->tableheader_open($colspan, $align, $rowspan);
    }

    /**
     * Open a table cell
     *
     * @param int    $colspan
     * @param string $align left|center|right
     * @param int    $rowspan
     */
    function tablecell_open($colspan = 1, $align = center, $rowspan = 1) {
		$numberOfPlaceholders = $this->computePlaceholders($colspan, $rowspan);
		for ($n = 0; $n < $numberOfPlaceholders; $n++) {
			$this->decorator->tablecell_open(1, null, 1);
			$this->decorator->tablecell_close(1, null, 1);
		}
		$this->decorator->tablecell_open($colspan, $align, $rowspan);
    }

	function computePlaceholders($colspan, $rowspan) {
		$totalNumberOfPlaceholders = 0;
		do {
			$cell = $this->row[$this->column];
			if ($cell->getRows() > 0) {
				$numberOfPlaceholders = $cell->getCols();
			} else {
				$numberOfPlaceholders = 0;
			}
			$this->column += $numberOfPlaceholders;
			$totalNumberOfPlaceholders += $numberOfPlaceholders;
		} while ($numberOfPlaceholders > 0);
		$this->row[$this->column]->setSize($colspan, $rowspan);
		$this->column += $colspan;
		return $totalNumberOfPlaceholders;
	}

	function computeCLine() {
		$lineIsPresent = false;
		$column = 0;

		do {
			$cell = $this->row[$column];

			if ($cell->getRows() == 1 && !$lineIsPresent) {
				$starts = $column + 1;
				$lineIsPresent = true;
			}

			if ($cell->getRows() > 1 && $lineIsPresent) {
				$this->decorator->table_cline($starts, $column);
				$lineIsPresent = false;
			}

			$column += $cell->getCols();
		} while($column < sizeof($this->row));

		if ($lineIsPresent) {
			$this->decorator->table_cline($starts, $column);
		}
	}

	function computeNextLine() {
		$row = [];

		foreach($this->row as $cell) {
			$nextCell = $cell->nextCellSize();
			$row[] = $nextCell;
		}

		$this->row = $row;
		$this->column = 0;
	}

}
