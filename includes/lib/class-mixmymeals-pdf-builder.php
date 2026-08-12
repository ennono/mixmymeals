<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . '/../lib/dompdf/3.1.4/autoload.inc.php';

use Dompdf\Dompdf;

class Mixmymeals_PDF_Builder {

	protected string $logo_url = '';
	protected string $header_html = '';
	protected string $footer_html = '';
	protected string $paper_size = 'A4';
	protected string $orientation = 'portrait';

	/** @var string[] */
	protected array $pages = [];

	public function __construct( array $config = [] ) {

		$this->logo_url     = $config['logo_url']     ?? '';
		$this->header_html  = $config['header_html']  ?? '';
		$this->footer_html  = $config['footer_html']  ?? '';
		$this->paper_size  = $config['paper_size']   ?? 'A4';
		$this->orientation = $config['orientation']  ?? 'portrait';
	}

	/**
	 * Fügt eine neue PDF-Seite hinzu
	 */
	public function add_page( string $html ): self {
		$this->pages[] = $html;
		return $this;
	}

	/**
	 * Rendert das PDF
	 */
	public function render(
		string $filename = 'document.pdf',
		bool $download = true
	): void {

		if ( empty( $this->pages ) ) {
			wp_die( 'No PDF pages added.' );
		}

		$dompdf = new Dompdf();

		$full_html = $this->build_document_html();

		$dompdf->loadHtml( $full_html );
		$dompdf->setPaper( $this->paper_size, $this->orientation );
		$dompdf->render();

		$dompdf->stream( $filename, [
			'Attachment' => $download,
		]);

		exit;
	}

	/**
	 * Baut das komplette HTML-Dokument mit Seitenumbrüchen
	 */
	protected function build_document_html(): string {

		$logo_html = $this->logo_url
			? '<img src="' . esc_url( $this->logo_url ) . '" style="max-height:80px;">'
			: '';

		$header_html = $this->header_html
			? $this->header_html
			: $logo_html;

		$pages_html = '';
		foreach ( $this->pages as $index => $page_html ) {
			$pages_html .= '<div class="mmm-pdf-page">';
			$pages_html .= $page_html;
			$pages_html .= '</div>';

			// Seitenumbruch außer nach letzter Seite
			if ( $index < count( $this->pages ) - 1 ) {
				$pages_html .= '<div class="mmm-page-break"></div>';
			}
		}

		/*
		 * The stylesheet lives in assets/css/pdf.css rather than in a heredoc
		 * here: it is 130 lines of static CSS with nothing to interpolate, and as
		 * a real .css file it is editable like every other stylesheet in the
		 * plugin. Heredoc syntax is also rejected by Plugin Check.
		 */
		$css_file = plugin_dir_path( __FILE__ ) . '../../assets/css/pdf.css';
		$css      = is_readable( $css_file ) ? (string) file_get_contents( $css_file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin asset, not a remote request.
		$styles   = '<style>' . $css . '</style>';

		return '<html>' .
			'<head>' .
			'<meta charset="UTF-8">' .
			$styles .
			'</head>' .
			'<body>' .
			'<div class="mmm-pdf-header">' . $header_html . '</div>' .
			$pages_html .
			'<div class="mmm-pdf-footer">' . $this->footer_html . '</div>' .
			'</body>' .
			'</html>';
	}
}
