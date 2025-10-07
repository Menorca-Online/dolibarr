<?php
/*
 * Verifactu invoice registers tab
 */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
	$res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'] . '/main.inc.php';
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . '/main.inc.php')) {
	$res = @include substr($tmp, 0, ($i + 1)) . '/main.inc.php';
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . '/main.inc.php')) {
	$res = @include dirname(substr($tmp, 0, ($i + 1))) . '/main.inc.php';
}
if (!$res && file_exists('../main.inc.php')) {
	$res = @include '../main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
	$res = @include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = @include '../../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/invoice.lib.php';
require_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactufacturaregistro.class.php';

$langs->loadLangs(array('bills', 'verifactu@verifactu'));

$user->loadRights();
if (!$user->hasRight("verifactu", "myobject", "read")) {
	var_dump($user);
	die();
	accessforbidden();
}

$invoiceId = GETPOSTINT('id');
if (!$invoiceId) {
	$invoiceId = GETPOSTINT('facid');
}

if (!$invoiceId) {
	accessforbidden('Missing invoice id');
}

$invoice = new Facture($db);
if ($invoice->fetch($invoiceId) <= 0) {
	accessforbidden();
}

if (!empty($invoice->socid) && empty($invoice->thirdparty)) {
	$invoice->fetch_thirdparty();
}

$title = $langs->trans('VerifactuTabRegisters');

llxHeader('', $title);

$head = facture_prepare_head($invoice);
dol_fiche_head($head, 'verifacturegisters', $langs->trans('InvoiceCard'), 0, 'bill');

dol_banner_tab($invoice, 'ref');

print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<div class="underbanner clearboth"></div>';
print '<table class="border centpercent">';
print '<tr><td class="titlefield">' . $langs->trans('Ref') . '</td><td>' . $invoice->getNomUrl(1, 'ref') . '</td></tr>';
if (!empty($invoice->thirdparty)) {
	print '<tr><td>' . $langs->trans('ThirdParty') . '</td><td>' . $invoice->thirdparty->getNomUrl(1) . '</td></tr>';
}
print '<tr><td>' . $langs->trans('Date') . '</td><td>' . dol_print_date($invoice->date, 'day') . '</td></tr>';
print '</table>';
print '</div>';
print '<div style="clear:both"></div>';
print '</div>';

$sql = "SELECT r.rowid, r.hash, r.hash_data, r.fecha, r.estado, r.msg_error, r.csv_line, r.operation, r.fk_batch,"
	. " op.code AS operation_code, op.label AS operation_label,"
	. " est.code AS estado_code, est.label AS estado_label"
	. " FROM " . MAIN_DB_PREFIX . "verifactu_factura_registros AS r"
	. " LEFT JOIN " . MAIN_DB_PREFIX . "c_verifactu_registro_operaciones AS op ON op.rowid = r.operation"
	. " LEFT JOIN " . MAIN_DB_PREFIX . "c_verifactu_registro_estados AS est ON est.rowid = r.estado"
	. " WHERE r.factureid = " . ((int) $invoiceId)
	. " ORDER BY r.rowid DESC";

$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
} else {
	print '<div class="fichecenter">';
	print '<div class="div-table-responsive">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<th class="nowrap">' . $langs->trans('Id') . '</th>';
	print '<th>' . $langs->trans('Date') . '</th>';
	print '<th>' . $langs->trans('Status') . '</th>';
	print '<th>' . $langs->trans('Operation') . '</th>';
	print '<th>' . $langs->trans('Hash') . '</th>';
	print '<th>' . $langs->trans('Error') . '</th>';
	print '<th>' . $langs->trans('Batch') . '</th>';
	print '</tr>';

	if ($db->num_rows($resql) > 0) {
		while ($obj = $db->fetch_object($resql)) {
			$estadoLabel = $obj->estado_label ?: $obj->estado_code;
			$operationLabel = trim(($obj->operation_code ? $obj->operation_code . ' - ' : '') . $obj->operation_label);
			$batchLink = '';
			if (!empty($obj->fk_batch)) {
				$urlBatch = dol_buildpath('/custom/verifactu/verifactu_batches.php', 1) . '?action=detail&id=' . ((int) $obj->fk_batch);
				$batchLink = '<a href="' . $urlBatch . '">' . $obj->fk_batch . '</a>';
			}
			print '<tr class="oddeven">';
			print '<td>' . (int) $obj->rowid . '</td>';
			print '<td>' . dol_print_date($db->jdate($obj->fecha), 'dayhour') . '</td>';
			print '<td>' . dol_escape_htmltag($estadoLabel) . '</td>';
			print '<td>' . dol_escape_htmltag($operationLabel) . '</td>';
			print '<td>' . dol_escape_htmltag($obj->hash) . '</td>';
			print '<td>' . dol_escape_htmltag($obj->msg_error) . '</td>';
			print '<td class="center">' . $batchLink . '</td>';
			print '</tr>';
		}
	} else {
		print '<tr class="oddeven"><td colspan="7" class="center">' . $langs->trans('None') . '</td></tr>';
	}

	print '</table>';
	print '</div>';
	print '</div>';

	$db->free($resql);
}
print '<div class="inline-block divButAction">'
    . '<a id="verifactu-xml-btn" class="butAction" '
    . 'href="' . dol_buildpath('/custom/verifactu/generar_subsanacion.php?id=' . $invoiceId, 1) . '">'
    . '<i class="fa fa-code"></i> ' . $langs->trans("GenerarSubsanacion") . '</a>'
    . '</div>';
// print '<div class="inline-block divButAction">'
//     . '<a id="verifactu-xml-btn" class="butAction" target="_blank" '
//     . 'href="' . dol_buildpath('/custom/verifactu/xml_preview.php?id=' . $invoiceId, 1) . '">'
//     . '<i class="fa fa-code"></i> ' . $langs->trans("VerXMLVerifactu") . '</a>'
//     . '</div>';    
dol_fiche_end();

llxFooter();
$db->close();
