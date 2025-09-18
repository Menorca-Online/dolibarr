<?php
/* Copyright (C) 2025 SuperAdmin
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       htdocs/custom/verifactu/core/modules/verifactu/doc/pdf_verifactu.modules.php
 *	\ingroup    verifactu
 *	\brief      File of class to generate customers invoices from verifactu model
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/facture/modules_facture.php';
require_once DOL_DOCUMENT_ROOT.'/core/modules/facture/doc/pdf_crabe.modules.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';

/**
 *	Class to generate the customer invoice PDF with template Verifactu
 */
class pdf_verifactu extends ModelePDFFactures
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string model name
	 */
	public $name;

	/**
	 * @var string model description (short text)
	 */
	public $description;

	/**
	 * @var int 	Save the name of generated file as the main doc when generating a doc with this template
	 */
	public $update_main_doc_field;

	/**
	 * @var string document type
	 */
	public $type;

	/**
	 * Dolibarr version of the loaded document
	 * @var string Version, possible values are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated' or a version string like 'x.y.z'''|'development'|'dolibarr'|'experimental'
	 */
	public $version = 'dolibarr';

	/**
	 * @var bool Situation invoice type
	 */
	public $situationinvoice;

	/**
	 * @var float X position for the situation progress column
	 */
	public $posxprogress;

	/**
	 * @var int Category of operation
	 */
	public $categoryOfOperation = -1; // unknown by default

	/**
	 *	Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */

	public $verifactuQR = '' ;

	public function __construct($db)
	{
		global $langs, $mysoc, $conf;

		// Translations
		$langs->loadLangs(array("main", "bills", "verifactu@verifactu"));

		$this->db = $db;
		$this->name = "verifactu";
		$this->description = $langs->trans('PDFVerifactuDescription');
		$this->update_main_doc_field = 1; // Save the name of generated file as the main doc when generating a doc with this template

		// Obtener la URL del endpoint desde la configuración del módulo
		$verifactuEndpoint = getDolGlobalString('VERIFACTU_URL_ENDPOINT', '');
		$verifactuUrlComprobar = getDolGlobalString('VERIFACTU_URL_COMPROBAR_FACTURA', '');
		
		// Construir la URL del QR usando la configuración
		$this->verifactuQR = !empty($verifactuUrlComprobar) ? $verifactuUrlComprobar : $verifactuEndpoint;
		

		// Si no hay configuración, usar una URL por defecto
		if (empty($this->verifactuQR)) {
			$this->verifactuQR = 'https://verifactu.com/api/v1/qr';
		}

		// Dimension page
		$this->type = 'pdf';
		$formatarray = pdf_getFormat();
		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur, $this->page_hauteur);
		$this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
		$this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
		$this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
		$this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);
		$this->corner_radius = getDolGlobalInt('MAIN_PDF_FRAME_CORNER_RADIUS', 0);
		$this->option_logo = 1; // Display logo
		$this->option_tva = 1; // Manage the vat option FACTURE_TVAOPTION
		$this->option_modereg = 1; // Display payment mode
		$this->option_condreg = 1; // Display payment terms
		$this->option_multilang = 1; // Available in several languages
		$this->option_escompte = 1; // Displays if there has been a discount
		$this->option_credit_note = 1; // Support credit notes
		$this->option_freetext = 1; // Support add of a personalised text
		$this->option_draft_watermark = 1; // Support add of a watermark on drafts
		$this->watermark = '';

		// Define position of columns
		$this->posxdesc = $this->marge_gauche + 1;
		if (getDolGlobalInt('PRODUCT_USE_UNITS')) {
			$this->posxtva = 101;
			$this->posxup = 118;
			$this->posxqty = 135;
			$this->posxunit = 151;
		} else {
			$this->posxtva = 106;
			$this->posxup = 122;
			$this->posxqty = 145;
			$this->posxunit = 162;
		}
		$this->posxprogress = 151; // Only displayed for situation invoices
		$this->posxdiscount = 162;
		$this->posxprogress = 174;
		$this->postotalht = 174;
		if (getDolGlobalString('MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT') || getDolGlobalString('MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT_COLUMN')) {
			$this->posxtva = $this->posxup;
		}
		$this->posxpicture = $this->posxtva - getDolGlobalInt('MAIN_DOCUMENTS_WITH_PICTURE_WIDTH', 20); // width of images
		if ($this->page_largeur < 210) { // To work with US executive format
			$this->posxpicture -= 20;
			$this->posxtva -= 20;
			$this->posxup -= 20;
			$this->posxqty -= 20;
			$this->posxunit -= 20;
			$this->posxdiscount -= 20;
			$this->posxprogress -= 20;
			$this->postotalht -= 20;
		}

		$this->tva = array();
		$this->tva_array = array();
		$this->localtax1 = array();
		$this->localtax2 = array();
		$this->atleastoneratenotnull = 0;
		$this->atleastonediscount = 0;
		$this->situationinvoice = false;

		if ($mysoc === null) {
			dol_syslog(get_class($this).'::__construct() Global $mysoc should not be null.'. getCallerInfoString(), LOG_ERR);
			return;
		}

		// Get source company
		$this->emetteur = $mysoc;
		if (empty($this->emetteur->country_code)) {
			$this->emetteur->country_code = substr($langs->defaultlang, -2); // By default, if was not defined
		}
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Function to build pdf onto disk
	 *
	 *  @param		Facture			$object				Object to generate
	 *  @param		Translate		$outputlangs		Lang output object
	 *  @param		string			$srctemplatepath	Full path of source filename for generator using a template file
	 *  @param		int<0,1>		$hidedetails		Do not show line details
	 *  @param		int<0,1>		$hidedesc			Do not show desc
	 *  @param		int<0,1>		$hideref			Do not show ref
	 *  @return		int<-1,1>							1=OK, <=0=KO
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		global $conf, $hookmanager, $langs, $user;

		// Incluir librerías necesarias para QR
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
		require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
		
		// Generar la URL del QR con los datos de la factura
		$qrData = $this->generateQRData($object);
		
		// Crear una instancia de la plantilla crabe modificada
		$crabeTemplate = new pdf_crabe_verifactu($this->db, $qrData);
		
		// Copiar nuestras propiedades específicas
		$crabeTemplate->name = $this->name;
		$crabeTemplate->description = $this->description;
		
		// Generar el PDF usando la plantilla modificada
		$result = $crabeTemplate->write_file($object, $outputlangs, $srctemplatepath, $hidedetails, $hidedesc, $hideref);
		
		return $result;
	}

	/**
	 * Generar los datos para el código QR
	 */
	private function generateQRData($object)
	{
		global $conf;
		
		// Obtener configuraciones
		$urlComprobar = getDolGlobalString('VERIFACTU_URL_COMPROBAR_FACTURA', 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR');
		$nif = $this->emetteur->idprof1 ? $this->emetteur->idprof1 : 'TESTNIF';
		$num = $object->ref ? $object->ref : 'TESTREF';
		$fecha = $object->datef ? dol_print_date($object->datef, '%d-%m-%Y') : date('d-m-Y');
		$importe = $object->total_ttc ? number_format($object->total_ttc, 2, '.', '') : '0.00';
		
		// Construir URL con parámetros de la factura
		$params = array(
			'nif' => $nif,
			'numserie' => $num,
			'fecha' => $fecha, //dd-mm-aaaa
			'importe' => $importe
		);
		
		$qrUrl = $urlComprobar . '?' . http_build_query($params);

		// Log para debug
		dol_syslog("QR Data: " . $qrUrl, LOG_DEBUG);
		
		return $qrUrl;
	}
}

/**
 * Clase extendida de pdf_crabe que incluye código QR
 */
class pdf_crabe_verifactu extends pdf_crabe
{
	public $qrData = '';
	
	public function __construct($db, $qrData = '')
	{
		parent::__construct($db);
		$this->qrData = $qrData;
	}
	
	/**
	 * Sobrescribir el método para agregar QR en la cabecera
	 */
	protected function _pagehead(&$pdf, $object, $showaddress, $outputlangs, $outputlangsbis = null, $titlekey = "PdfInvoiceTitle")
	{
		// Llamar a la cabecera original
		$result = parent::_pagehead($pdf, $object, $showaddress, $outputlangs, $outputlangsbis, $titlekey);
		
		// Agregar código QR si tenemos datos (después de la cabecera)
		if (!empty($this->qrData)) {
			$this->addQRCodeToHeader($pdf, $object);
		}
		
		return $result;
	}

	/**
	 * Sobrescribir el método del pie de página (ya no usamos para QR)
	 */
	protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0, $heightforqrinvoice = 0)
	{
		// Solo llamar al pie de página original, sin QR
		return parent::_pagefoot($pdf, $object, $outputlangs, $hidefreetext, $heightforqrinvoice);
	}
	
	/**
	 * Agregar código QR en la cabecera (entre empresa y datos de factura)
	 */
	private function addQRCodeToHeader(&$pdf, $object)
	{
		// Incluir librería QR
		require_once DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/tcpdf_barcodes_2d.php';
		
		// Verificar si la librería QR está disponible
		if (class_exists('TCPDF2DBarcode')) {
			// Posición del QR en la cabecera
			$qrSize = 30; // mm - más grande para ser visible en cabecera
			
			// Posición: lado derecho, después del logo/empresa (aprox. línea 60-80)
			$x = $this->page_largeur - $this->marge_droite - $qrSize - 5; // Lado derecho
			$y = 50; // Posición vertical entre empresa y datos de factura
			
			// Generar y agregar el QR
			$pdf->write2DBarcode($this->qrData, 'QRCODE,L', $x, $y, $qrSize, $qrSize, array(), false);
			
			// Agregar texto explicativo debajo del QR
			$pdf->SetFont('helvetica', '', 8);
			$pdf->SetXY($x, $y + $qrSize + 2);
			$pdf->Cell($qrSize, 4, 'Verificar en AEAT', 0, 0, 'C');
			
			// Debug: agregar un rectángulo para ver la posición (quitar después)
			$pdf->SetDrawColor(0, 255, 0); // Color verde para diferenciarlo
			$pdf->Rect($x, $y, $qrSize, $qrSize);
			
		} else {
			// Si no hay QR, agregar texto de debug en cabecera
			$pdf->SetFont('helvetica', '', 8);
			$pdf->SetXY(120, 60);
			$pdf->Cell(70, 4, 'QR no disponible: ' . substr($this->qrData, 0, 50), 0, 0, 'L');
		}
	}

	/**
	 * Método anterior del QR (ya no se usa)
	 */
	private function addQRCode(&$pdf)
	{
		// Este método ya no se usa, mantenido por compatibilidad
		return;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *	Return description of a module
	 *
	 *	@param	Translate	$langs      Lang object to use for output
	 *	@return string       			Description
	 */
	public function info($langs)
	{
		global $conf, $langs;

		// Load translation files required by the page
		$langs->loadLangs(array("bills", "admin"));

		$form = new Form($this->db);

		$texte = $this->description.".<br>\n";
		$texte .= '<form action="'.$_SERVER["PHP_SELF"].'" method="POST" enctype="multipart/form-data">';
		$texte .= '<input type="hidden" name="token" value="'.newToken().'">';
		$texte .= '<input type="hidden" name="page_y" value="">';
		$texte .= '<input type="hidden" name="action" value="setModuleOptions">';
		$texte .= '<input type="hidden" name="param1" value="INVOICE_MODEL_VERIFACTU">';
		$texte .= '<table class="nobordernopadding" width="100%">';

		// Show logo
		$texte .= '<tr><td>';
		$texthelpofparam = $langs->transnoentitiesnoconv("UseCompanyLogoHelp");
		$texte .= $form->textwithpicto($langs->trans("UseCompanyLogo"), $texthelpofparam, 1, 'help', '', 1);
		$texte .= '</td>';
		$texte .= '<td>';
		$texte .= $form->selectyesno('INVOICE_LOGO', getDolGlobalString('INVOICE_LOGO'), 1);
		$texte .= '</td></tr>';

		$texte .= '</table>';
		$texte .= '<div class="center">';
		$texte .= '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
		$texte .= '</div>';
		$texte .= '</form>';
		return $texte;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Function to show outputlangs array (used for debugging)
	 *
	 *  @return     void
	 */
	public function write_file_to_screen()
	{
		// No implementado por ahora
		return;
	}
}