<?php
/* Copyright (C) 2025 SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       htdocs/custom/verifactu/xml_preview.php
 * \ingroup    verifactu
 * \brief      Vista previa del XML de Verifactu para una factura
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/verifactu/class/verifactuxml.class.php';

// Load translation files required by the page
$langs->loadLangs(array("verifactu@verifactu", "bills"));

// Get parameters
$id = GETPOST('id', 'int');
$action = GETPOST('action', 'aZ09');

// Security check
if (empty($id)) {
	accessforbidden('ID de factura requerido');
}

// Check if user can read invoices
if (!$user->hasRight('facture', 'lire')) {
	accessforbidden();
}

// Load the invoice
$object = new Facture($db);
$result = $object->fetch($id);
if ($result <= 0) {
	dol_print_error($db, 'Factura no encontrada');
	exit;
}

// Check if invoice belongs to current entity
if ($object->entity != $conf->entity) {
	accessforbidden();
}

/*
 * Actions
 */

$xml_content = '';
$error_message = '';

try {
	// Generate XML using our VerifactuXML class
	$xmlGenerator = new VerifactuXML($db);
	$xml_content = $xmlGenerator->generateRegistroAlta($object);

	// Pretty format the XML for display
	$dom = new DOMDocument('1.0', 'UTF-8');
	$dom->preserveWhiteSpace = false;
	$dom->formatOutput = true;
	$dom->loadXML($xml_content);
	$xml_formatted = $dom->saveXML();

} catch (Exception $e) {
	$error_message = 'Error al generar XML: ' . $e->getMessage();
	dol_syslog("Verifactu XML Preview Error: " . $e->getMessage(), LOG_ERR);
}

// Download action
if ($action == 'download' && !empty($xml_content)) {
	$filename = 'verifactu_' . $object->ref . '_' . date('Y-m-d_H-i-s') . '.xml';

	header('Content-Type: application/xml');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	header('Content-Length: ' . strlen($xml_content));

	echo $xml_content;
	exit;
}

/*
 * View
 */

llxHeader("", "XML Verifactu - " . $object->ref, '');

// Simple header
print '<div class="fiche">';
print '<div class="fichetitle">';
print '<div class="titre inline-block">';
print img_picto('', 'bill', 'class="pictofixedwidth"');
print 'XML Verifactu - Factura ' . $object->ref;
print '</div>';
print '<div class="refidno">';
print '<a href="' . DOL_URL_ROOT . '/compta/facture/card.php?facid=' . $object->id . '" class="butAction">';
print '<i class="fa fa-arrow-left"></i> Volver a la factura</a>';
print '</div>';
print '</div>';

print '<div class="fichecenter">';

// Show error if any
if (!empty($error_message)) {
	print '<div class="error">' . $error_message . '</div>';
}

// Show XML content
if (!empty($xml_formatted)) {
	print '<div class="div-table-responsive-no-min">';

	// Action buttons
	print '<div style="margin-bottom: 15px;">';
	print '<a class="butAction" href="' . $_SERVER['PHP_SELF'] . '?id=' . $id . '&action=download">';
	print '<i class="fa fa-download"></i> Descargar XML</a>';
	print '<a class="butAction" href="javascript:void(0)" onclick="copyXmlToClipboard()">';
	print '<i class="fa fa-copy"></i> Copiar al portapapeles</a>';
	print '</div>';

	// XML content with syntax highlighting
	print '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; padding: 15px; margin: 10px 0;">';
	print '<h3 style="margin-top: 0;"><i class="fa fa-code"></i> XML Verifactu - ' . $object->ref . '</h3>';

	print '<pre id="xml-content" style="background: #fff; border: 1px solid #ccc; padding: 15px; border-radius: 3px; overflow: auto; max-height: 600px; font-family: monospace; font-size: 12px; line-height: 1.4;">';
	print htmlspecialchars($xml_formatted, ENT_QUOTES, 'UTF-8');
	print '</pre>';

	print '</div>';

	// Information about the invoice
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<th colspan="2">Información de la factura</th>';
	print '</tr>';

	print '<tr class="oddeven">';
	print '<td>Referencia</td>';
	print '<td>' . $object->ref . '</td>';
	print '</tr>';

	print '<tr class="oddeven">';
	print '<td>Fecha</td>';
	print '<td>' . dol_print_date($object->date, 'day') . '</td>';
	print '</tr>';

	// Load thirdparty for display
	$object->fetch_thirdparty();

	print '<tr class="oddeven">';
	print '<td>Cliente</td>';
	print '<td>' . ($object->thirdparty->name ?: $object->thirdparty->nom) . '</td>';
	print '</tr>';

	print '<tr class="oddeven">';
	print '<td>Total sin IVA</td>';
	print '<td>' . price($object->total_ht) . '</td>';
	print '</tr>';

	print '<tr class="oddeven">';
	print '<td>Total IVA</td>';
	print '<td>' . price($object->total_tva) . '</td>';
	print '</tr>';

	print '<tr class="oddeven">';
	print '<td>Total con IVA</td>';
	print '<td>' . price($object->total_ttc) . '</td>';
	print '</tr>';

	// Show hash information if available
	$sql = "SELECT hash, hash_anterior, fechaHoraHusoGenRegistro
	        FROM " . MAIN_DB_PREFIX . "facture_extrafields
	        WHERE fk_object = " . ((int) $object->id);
	$resql = $db->query($sql);
	if ($resql && $db->num_rows($resql) > 0) {
		$obj = $db->fetch_object($resql);

		if (!empty($obj->hash)) {
			print '<tr class="oddeven">';
			print '<td>Hash Verifactu</td>';
			print '<td style="font-family: monospace; font-size: 11px; word-break: break-all;">' . $obj->hash . '</td>';
			print '</tr>';
		}

		if (!empty($obj->hash_anterior)) {
			print '<tr class="oddeven">';
			print '<td>Hash Anterior</td>';
			print '<td style="font-family: monospace; font-size: 11px; word-break: break-all;">' . $obj->hash_anterior . '</td>';
			print '</tr>';
		}

		if (!empty($obj->fechaHoraHusoGenRegistro)) {
			print '<tr class="oddeven">';
			print '<td>Fecha/Hora Generación</td>';
			print '<td>' . $obj->fechaHoraHusoGenRegistro . '</td>';
			print '</tr>';
		}
	}

	print '</table>';
	print '</div>';

} elseif (empty($error_message)) {
	print '<div class="info">No se pudo generar el XML para esta factura.</div>';
}

print '</div>';
print '</div>';

// JavaScript for copy to clipboard
print '<script type="text/javascript">
function copyXmlToClipboard() {
	var xmlContent = document.getElementById("xml-content");
	if (xmlContent) {
		var textArea = document.createElement("textarea");
		textArea.value = xmlContent.textContent;
		document.body.appendChild(textArea);
		textArea.select();

		try {
			document.execCommand("copy");
			alert("XML copiado al portapapeles");
		} catch (err) {
			alert("Error al copiar: " + err);
		}

		document.body.removeChild(textArea);
	}
}

// Simple XML syntax highlighting
document.addEventListener("DOMContentLoaded", function() {
	var xmlContent = document.getElementById("xml-content");
	if (xmlContent) {
		var html = xmlContent.innerHTML;

		// Basic XML syntax highlighting
		html = html.replace(/(&lt;\/?[^&gt;]+&gt;)/g, \'<span style="color: #0066cc; font-weight: bold;">$1</span>\');
		html = html.replace(/(&lt;!--.*?--&gt;)/g, \'<span style="color: #008000; font-style: italic;">$1</span>\');
		html = html.replace(/(&quot;[^&quot;]*&quot;)/g, \'<span style="color: #cc6600;">$1</span>\');

		xmlContent.innerHTML = html;
	}
});
</script>';

// CSS for better styling
print '<style>
.xml-element { color: #0066cc; font-weight: bold; }
.xml-comment { color: #008000; font-style: italic; }
.xml-attribute { color: #cc6600; }
.xml-text { color: #000; }

#xml-content {
	tab-size: 2;
	-moz-tab-size: 2;
}

/* Responsive design for small screens */
@media (max-width: 768px) {
	#xml-content {
		font-size: 10px;
		max-height: 400px;
	}
}
</style>';

llxFooter();
$db->close();

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/invoice.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/verifactu/class/verifactuxml.class.php';

// Load translation files required by the page
$langs->loadLangs(array("verifactu@verifactu", "bills"));

// Get parameters
$id = GETPOST('id', 'int');
$action = GETPOST('action', 'aZ09');

// Security check
if (empty($id)) {
	accessforbidden('ID de factura requerido');
}

// Check if user can read invoices
if (!$user->hasRight('facture', 'lire')) {
	accessforbidden();
}

// Load the invoice
$object = new Facture($db);
$result = $object->fetch($id);
if ($result <= 0) {
	dol_print_error($db, 'Factura no encontrada');
	exit;
}

// Check if invoice belongs to current entity
if ($object->entity != $conf->entity) {
	accessforbidden();
}

/*
 * Actions
 */

$xml_content = '';
$error_message = '';

try {
	// Generate XML using our VerifactuXML class
	$xmlGenerator = new VerifactuXML($db);
	$xml_content = $xmlGenerator->generateRegistroAlta($object);

	// Pretty format the XML for display
	$dom = new DOMDocument('1.0', 'UTF-8');
	$dom->preserveWhiteSpace = false;
	$dom->formatOutput = true;
	$dom->loadXML($xml_content);
	$xml_formatted = $dom->saveXML();

} catch (Exception $e) {
	$error_message = 'Error al generar XML: ' . $e->getMessage();
	dol_syslog("Verifactu XML Preview Error: " . $e->getMessage(), LOG_ERR);
}

// Download action
if ($action == 'download' && !empty($xml_content)) {
	$filename = 'verifactu_' . $object->ref . '_' . date('Y-m-d_H-i-s') . '.xml';

	header('Content-Type: application/xml');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	header('Content-Length: ' . strlen($xml_content));

	echo $xml_content;
	exit;
}

/*
 * View
 */

llxHeader("", "XML Verifactu - " . $object->ref, '');

// Simple header for XML preview
print '<div class="fiche">';
print '<div class="fichetitle">';
print '<div class="titre inline-block">';
print img_picto('', 'bill', 'class="pictofixedwidth"');
print 'XML Verifactu - Factura ' . $object->ref;
print '</div>';
print '<div class="refidno">';
print '<a href="' . DOL_URL_ROOT . '/compta/facture/card.php?facid=' . $object->id . '">';
print '<i class="fa fa-arrow-left"></i> Volver a la factura</a>';
print '</div>';
print '</div>';

// Invoice card
$linkback = '<a href="' . DOL_URL_ROOT . '/compta/facture/list.php?restore_lastsearch_values=1' . (!empty($socid) ? '&socid=' . $socid : '') . '">' . $langs->trans("BackToList") . '</a>';

$morehtmlref = '<div class="refidno">';
// Ref customer
$morehtmlref .= $form->editfieldkey("RefCustomer", 'ref_client', $object->ref_client, $object, 0, 'string', '', 0, 1);
$morehtmlref .= $form->editfieldval("RefCustomer", 'ref_client', $object->ref_client, $object, 0, 'string', '', null, null, '', 1);
// Thirdparty
$morehtmlref .= '<br>' . $object->thirdparty->getNomUrl(1, 'customer');
// Project
if (isModEnabled('project')) {
	$langs->load("projects");
	$morehtmlref .= '<br>';
	if ($user->hasRight('projet', 'lire')) {
		$morehtmlref .= img_picto($langs->trans("Project"), 'project', 'class="pictofixedwidth"');
		if ($object->fk_project > 0) {
			$proj = new Project($db);
			$proj->fetch($object->fk_project);
			$morehtmlref .= $proj->getNomUrl(1);
		}
	}
}
$morehtmlref .= '</div>';

dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';

// Show error if any
if (!empty($error_message)) {
	print '<div class="error">' . $error_message . '</div>';
}

// Show XML content
if (!empty($xml_formatted)) {
	print '<div class="div-table-responsive-no-min">';

	// Action buttons
	print '<div style="margin-bottom: 15px;">';
	print '<a class="butAction" href="' . $_SERVER['PHP_SELF'] . '?id=' . $id . '&action=download">';
	print '<i class="fa fa-download"></i> Descargar XML</a>';
	print '<a class="butAction" href="javascript:void(0)" onclick="copyXmlToClipboard()">';
	print '<i class="fa fa-copy"></i> Copiar al portapapeles</a>';
	print '</div>';

	// XML content with syntax highlighting
	print '<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; padding: 15px; margin: 10px 0;">';
	print '<h3 style="margin-top: 0;"><i class="fa fa-code"></i> XML Verifactu - ' . $object->ref . '</h3>';

	print '<pre id="xml-content" style="background: #fff; border: 1px solid #ccc; padding: 15px; border-radius: 3px; overflow: auto; max-height: 600px; font-family: monospace; font-size: 12px; line-height: 1.4;">';
	print htmlspecialchars($xml_formatted, ENT_QUOTES, 'UTF-8');
	print '</pre>';

	print '</div>';

	// Information about the invoice
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<th colspan="2">Información de la factura</th>';
	print '</tr>';

	print '<tr class="oddeven">';
	print '<td>Referencia</td>';
	print '<td>' . $object->ref . '</td>';
	print '</tr>';

	print '<tr class="oddeven">';
	print '<td>Fecha</td>';
	print '<td>' . dol_print_date($object->date, 'day') . '</td>';
	print '</tr>';

	print '<tr class="oddeven">';
	print '<td>Cliente</td>';
	print '<td>' . $object->thirdparty->name . '</td>';
	print '</tr>';

	print '<tr class="oddeven">';
	print '<td>Total sin IVA</td>';
	print '<td>' . price($object->total_ht) . '</td>';
	print '</tr>';

	print '<tr class="oddeven">';
	print '<td>Total IVA</td>';
	print '<td>' . price($object->total_tva) . '</td>';
	print '</tr>';

	print '<tr class="oddeven">';
	print '<td>Total con IVA</td>';
	print '<td>' . price($object->total_ttc) . '</td>';
	print '</tr>';

	// Show hash information if available
	$sql = "SELECT hash, hash_anterior, fechaHoraHusoGenRegistro
	        FROM " . MAIN_DB_PREFIX . "facture_extrafields
	        WHERE fk_object = " . ((int) $object->id);
	$resql = $db->query($sql);
	if ($resql && $db->num_rows($resql) > 0) {
		$obj = $db->fetch_object($resql);

		if (!empty($obj->hash)) {
			print '<tr class="oddeven">';
			print '<td>Hash Verifactu</td>';
			print '<td style="font-family: monospace; font-size: 11px; word-break: break-all;">' . $obj->hash . '</td>';
			print '</tr>';
		}

		if (!empty($obj->hash_anterior)) {
			print '<tr class="oddeven">';
			print '<td>Hash Anterior</td>';
			print '<td style="font-family: monospace; font-size: 11px; word-break: break-all;">' . $obj->hash_anterior . '</td>';
			print '</tr>';
		}

		if (!empty($obj->fechaHoraHusoGenRegistro)) {
			print '<tr class="oddeven">';
			print '<td>Fecha/Hora Generación</td>';
			print '<td>' . $obj->fechaHoraHusoGenRegistro . '</td>';
			print '</tr>';
		}
	}

	print '</table>';
	print '</div>';

} elseif (empty($error_message)) {
	print '<div class="info">No se pudo generar el XML para esta factura.</div>';
}

print '</div>';

print dol_get_fiche_end();

// JavaScript for copy to clipboard
print '<script type="text/javascript">
function copyXmlToClipboard() {
	var xmlContent = document.getElementById("xml-content");
	if (xmlContent) {
		var textArea = document.createElement("textarea");
		textArea.value = xmlContent.textContent;
		document.body.appendChild(textArea);
		textArea.select();

		try {
			document.execCommand("copy");
			alert("XML copiado al portapapeles");
		} catch (err) {
			alert("Error al copiar: " + err);
		}

		document.body.removeChild(textArea);
	}
}

// Syntax highlighting for XML (simple)
document.addEventListener("DOMContentLoaded", function() {
	var xmlContent = document.getElementById("xml-content");
	if (xmlContent) {
		var html = xmlContent.innerHTML;

		// Simple XML syntax highlighting
		html = html.replace(/(&lt;\/?[^&gt;]+&gt;)/g, \'<span style="color: #0066cc; font-weight: bold;">$1</span>\');
		html = html.replace(/(&lt;!--.*?--&gt;)/g, \'<span style="color: #008000; font-style: italic;">$1</span>\');
		html = html.replace(/(&quot;[^&quot;]*&quot;)/g, \'<span style="color: #cc6600;">$1</span>\');

		xmlContent.innerHTML = html;
	}
});
</script>';

// CSS for better styling
print '<style>
.xml-element { color: #0066cc; font-weight: bold; }
.xml-comment { color: #008000; font-style: italic; }
.xml-attribute { color: #cc6600; }
.xml-text { color: #000; }

#xml-content {
	tab-size: 2;
	-moz-tab-size: 2;
}

/* Responsive design for small screens */
@media (max-width: 768px) {
	#xml-content {
		font-size: 10px;
		max-height: 400px;
	}
}
</style>';

llxFooter();
$db->close();
