<?php
/**
 * Latexport Plugin: Exports to latex
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author Jean-Michel Gonet <jmgonet@yahoo.com>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

require_once DOKU_PLUGIN . 'latexport/helpers/archive_helper_zip.php';
require_once DOKU_PLUGIN . 'latexport/renderer/dumper.php';

/**
 * A façade between doku wiki and the actual tex renderer.
 * Actual renderer is dumper.php or one of its extensions.
 */
class renderer_plugin_latexport_tex extends Doku_Renderer {
	const GRAPHICSPATH = 'images/';

	/** 
	 * To create a compressed archive with all TeX resources needed
	 * to download together.
	 */
	private $archive; 

	/**
	 * Flag indicating that an unordered item has open, and it
	 * still has no content.
	 */
	private $bareUnorderedItem;

	/**
	 * Class constructor.
	 */
	function __construct() {
		$this->archive = new ArchiveHelperZip(); 
		$this->bareUnorderedItem = false;
	}

	/**
	 * Returns the mode name produced by this renderer.
	 */
	function getFormat(){
		return "latexport";
	}

	/**
	 * Do not make multiple instances of this class
	 */
	function isSingleton(){
		return true;
	}

	/**
	 * Initialize the rendering
	 */
	function document_start() {
		global $ID;

		// Create HTTP headers
		$output_filename = str_replace(':','-',$ID).'.zip';
		$headers = array(
				'Content-Type' => $this->archive->getContentType(),
				'Content-Disposition' => 'attachment; filename="'.$output_filename.'";',
				);

		// store the content type headers in metadata
		p_set_metadata($ID,array('format' => array('latexport_tex' => $headers) ));

		// Starts the archive:
		$this->archive->startArchive();
		$this->archive->startFile(str_replace(':','-',$ID).'.tex');

		// Starts the document:
		$this->appendCommand('documentclass', 'book');
		$this->appendCommand('usepackage', 'graphicx');
		$this->appendCommand('graphicspath', ' {'.self::GRAPHICSPATH.'} ');
		$this->appendCommand('begin', 'document');
	}

	/**
	 * Headers are transformed in part, chapter, section, subsection and subsubsection.
	 */
	function header($text, $level, $pos) {
		switch($level) {
			case 1:
				$this->appendCommand('part', $text);
				break;
			case 2:
				$this->appendCommand('chapter', $text);
				break;
			case 3:
				$this->appendCommand('section', $text);
				break;
			case 4:
				$this->appendCommand('subsection', $text);
				break;
			default:
				$this->appendCommand('subsubsection', $text);
				break;
		}
	}

	/**
	 * Open a paragraph.
	 */
	function p_open() {
	}

	/**
	 * Renders plain text.
	 */
	function cdata($text) {
		$this->appendContent($text);
	}

	/**
	 * Close a paragraph.
	 */
	function p_close() {
		$this->appendContent("\r\n\r\n");
	}
	/**
	 * Start emphasis (italics) formatting
	 */
	function emphasis_open() {
		$this->appendContent("\\emph{");
	}

	/**
	 * Stop emphasis (italics) formatting
	 */
	function emphasis_close() {
		$this->appendContent("}");
	}
	/**
	 * Start strong (bold) formatting
	 */
	function strong_open() {
		$this->appendContent("\\textbf{");	
	}

	/**
	 * Stop strong (bold) formatting
	 */
	function strong_close() {
		$this->appendContent("}");
	}
	/**
	 * Start underline formatting
	 */ 
	function underline_open() {
		$this->appendContent("\\underline{");
	}

	/**
	 * Stop underline formatting
	 */
	function underline_close() {
		$this->appendContent("}");
	}
	/**
	 * Render a wiki internal link.
	 * Internal links at the very beginning of an unordered item include
	 * the destination page.
	 * @param string       $link  page ID to link to. eg. 'wiki:syntax'
	 * @param string|array $title name for the link, array for media file
	 */
	function internallink($link, $title = null) {
		if ($this->bareUnorderedItem) {
			$this->appendContent("Include --");
		}
		$this->appendContent($title);
	}

	/**
	 * Render an internal media file
	 *
	 * @param string $src     media ID
	 * @param string $title   descriptive text
	 * @param string $align   left|center|right
	 * @param int    $width   width of media in pixel
	 * @param int    $height  height of media in pixel
	 * @param string $cache   cache|recache|nocache
	 * @param string $linking linkonly|detail|nolink
	 */
	function internalmedia($src, $title = null, $align = null, $width = null,
			$height = null, $cache = null, $linking = null) {
		global $ID;
		list($src, $hash) = explode('#', $src, 2);
		resolve_mediaid(getNS($ID), $src, $exists, $this->date_at, true);
		$file   = mediaFN($src);
		$this->appendCommand('begin', 'figure', 'ht');
		$this->appendCommand('includegraphics', $this->insertImage($file), 'width=\textwidth');
		$this->appendCommand('caption', $title);
		$this->appendCommand('end', 'figure');
	}

	/**
	 * Open an unordered list
	 */
	function listu_open() {
		$this->appendCommand('begin', 'itemize');
	}
	/**
	 * Open a list item
	 *
	 * @param int $level the nesting level
	 * @param bool $node true when a node; false when a leaf
	 */
	function listitem_open($level,$node=false) {
		$this->appendContent(str_repeat('   ', $level).'\\item ');
		$this->bareUnorderedItem = true;
	}
	/**
	 * Start the content of a list item
	 */
	function listcontent_open() {
		// Nothing to do.
	}

	/**
	 * Stop the content of a list item
	 */
	function listcontent_close() {
		$this->appendContent("\r\n");
	}

	/**
	 * Close an unordered list
	 */
	function listu_close() {
		$this->appendCommand('end', 'itemize');
	}

	/**
	 * Receives mathematic formula from Mathjax plugin.
	 * As Mathjax already uses $ or $$ as separator, there is no
	 * need to reprocess.
	 */
	function mathjax_content($formula) {
		$this->appendContent("$formula");
	}

	/**
	 * Closes the document
	 */
	function document_end(){
		$this->appendCommand('end', 'document');
		$this->archive->closeFile();
		$this->doc = $this->archive->closeArchive();
	}

	/**
	 * Inserts the specified file.
	 * @param The physical path to the file.
	 * @return The TeX-ified name of the file.
	 */
	function insertImage($filename) {
		$baseFilename = $this->texifyFilename(basename($filename));
		$this->archive->insertContent(self::GRAPHICSPATH.$baseFilename, file_get_contents($filename));
		return $baseFilename;
	}

	/**
	 * Returns a TeX compliant version of the specified file name.
	 * @param filename The filename.
	 * @return A TeX compliant version, with no spaces, and no dot besides the extension.
	 */
	function texifyFilename($filename) {
		$ext = '';
		$extPosition = strrpos($filename, ".");
		if ($extPosition) {
			$ext = substr($filename, $extPosition + 1);
			$filename = substr($filename, 0, -strlen($ext) - 1);
		}
		$texifiedFilename = str_replace(".", "_", $filename);
		$texifiedFilename = str_replace(" ", "_", $texifiedFilename);
		return "$texifiedFilename.$ext";
	}

	/**
	 * Adds a latex command to the document.
	 * @param command  The command
	 * @param scope    The name of the scope, or the mandatory argument, 
	 *                 to be included inside the curly brackets.
	 * @param argument If specified, to be included in square brackets. Depending
	 *                 on the command, square brackets are placed before or after
	 *                 the curly brackets.
	 */
	function appendCommand($command, $scope, $argument = '') {
		if ($argument) {
			switch($command) {
				// Some commands have the optional arguments after the curly brackets:
				case 'begin':
				case 'end':
					$text = '\\'.$command.'{'.$scope.'}['.$argument.']';
					break;

				// Most commands have the optional arguments before the curly brackets:
				default:
					$text = '\\'.$command.'['.$argument.']{'.$scope.'}';
					break;
			}
		} else {
			$text = '\\'.$command.'{'.$scope.'}';
		}
		$this->archive->appendContent("$text\r\n");
	}

	/**
	 * Adds simple content to the document.
	 * @param c The content.
	 */
	function appendContent($c) {
		if ($this->bareUnorderedItem) {
			if (!ctype_space($c)) {
				$this->bareUnorderedItem = false;
			}
		}
		$this->archive->appendContent($c);
	}

}
