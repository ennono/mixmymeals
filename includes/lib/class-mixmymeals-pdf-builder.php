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

		write_to_log( $full_html );

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

		$styles = <<<CSS
<style>
/* Seitenlayout */
@page {
    margin-top: 3.5cm;
    margin-right: 2.5cm;
    margin-bottom: 2.5cm;
    margin-left: 2.5cm;
}

body, body * {
    font-family: "DejaVu Sans", sans-serif !important; /* global für alles */
    color: #222;
    line-height: 1.5;
    background: #fff;
    box-sizing: border-box;
}

/* Header / Footer */
.mmm-pdf-header {
    position: fixed;
    top: -2.5cm;
    left: 0;
    right: 0;
    text-align: center;
    font-weight: 700;
    font-size: 16pt;
    color: #111;
}

.mmm-pdf-footer {
    position: fixed;
    bottom: -2cm;
    left: 0;
    right: 0;
    text-align: center;
    font-size: 9pt;
    color: #666;
}

/* Page break */
.mmm-pdf-page { page-break-after: always; }
.mmm-pdf-page:last-child { page-break-after: auto; }
.mmm-page-break { page-break-before: always; }

/* Überschriften */
h1 { font-size: 18pt; margin: 0 0 0.6cm; color: #111; border-bottom: 2px solid #444; padding-bottom: 0.2cm; }
h2 { font-size: 12pt; margin: 0.8cm 0 0.4cm; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 0.15cm; }
h3 { font-size: 10pt; margin: 0.5cm 0 0.2cm; color: #444; }
h4 { font-size: 8pt; margin: 0.4cm 0 0.2cm; color: #222; font-weight: 600; }

/* Textausrichtung */
.align-left { text-align: left; }
.align-right { text-align: right; }

/* Tabellen für Mahlzeiten und Einkaufsliste */
table {
    border-collapse: collapse;
    width: 100%;
    margin-bottom: 0.5cm;
    font-size: 8pt;
}

table th, table td {
    border: 1px solid #ccc;
    padding: 0.1cm 0.2cm;
    vertical-align: top;
    font-size: 8pt;
}

table th {
    background: #f0f0f0;
    font-weight: bold;
    font-size: 8pt;
}

/* Allgemeine Listen */
ul { padding-left: 1.2em; margin: 0.2cm 0; }
li { margin: 0.15cm 0; }

/* Meal Plan Overview */

.mmm-meal-plan-overview-table {
    table-layout: auto;
    width: 100%;
}

.mmm-meal-plan-overview-table th:nth-child(1),
.mmm-meal-plan-overview-table td:nth-child(1) { width: 20%; }

.mmm-meal-plan-overview-table th:nth-child(2),
.mmm-meal-plan-overview-table td:nth-child(2) { width: 70%; }

.mmm-meal-plan-overview-table th:nth-child(3),
.mmm-meal-plan-overview-table td:nth-child(3) { width: 10%; }

/* Shopping List */

.mmm-meal-plan-shopping-list-table {
    table-layout: fixed;
    width: 100%;
    border-collapse: collapse;
}

.mmm-meal-plan-shopping-list-table th,
.mmm-meal-plan-shopping-list-table td {
    border: 1px solid #ccc;
    padding: 0.15cm 0.2cm;
    font-size: 8pt;
    vertical-align: top;
}

.mmm-meal-plan-shopping-list-table th:nth-child(1),
.mmm-meal-plan-shopping-list-table td:nth-child(1) { width: 5%; text-align: center; }  /* Checkbox oder Typ */
.mmm-meal-plan-shopping-list-table th:nth-child(2),
.mmm-meal-plan-shopping-list-table td:nth-child(2) { width: 75%; text-align: left; }   /* Mahlzeit / Zutat */
.mmm-meal-plan-shopping-list-table th:nth-child(3),
.mmm-meal-plan-shopping-list-table td:nth-child(3) { width: 20%; text-align: right; }  /* Portion / Menge */

/* Recipes */

.mmm-meal-ingredients-overview {
    font-size: 8pt;
    line-height: 1.35;
}

.mmm-recipe-meta {
    font-size: 8pt;
    line-height: 1.35;
}

.mmm-recipe-steps {
    font-size: 8pt;
    line-height: 1.35;
}

</style>

CSS;

		return <<<HTML
<html>
<head>
<meta charset="UTF-8">
{$styles}
</head>
<body>

<div class="mmm-pdf-header">
{$header_html}
</div>

{$pages_html}

<div class="mmm-pdf-footer">
{$this->footer_html}
</div>

</body>
</html>
HTML;
	}
}
