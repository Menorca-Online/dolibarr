<?php
/*
 * Verifactu registers list page
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

require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactufacturaregistro.class.php';

$langs->loadLangs(array('verifactu@verifactu'));

$hasReadRights = $user->hasRight("verifactu", "myobject", "read");
$hasWriteRights = $user->hasRight("verifactu", "myobject", "write");
if (!$hasWriteRights) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$id = GETPOSTINT('id');
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if (empty($page) || $page == -1) {
	$page = 0;
}
$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$offset = $limit * $page;
if (!$sortfield) {
	$sortfield = 't.rowid';
}
if (!$sortorder) {
	$sortorder = 'ASC';
}

// Initialize objects
$verifactufacturaregistro = new VerifactuFacturaRegistro($db);

// Fetch operations
$sql_op = 'SELECT code, label FROM ' . MAIN_DB_PREFIX . 'c_verifactu_registro_operaciones WHERE active=1';
$result_op = $db->query($sql_op);
$operations = array();
if ($result_op) {
    while ($obj = $db->fetch_object($result_op)) {
        $operations[] = $obj;
    }
    $db->free($result_op);
}

// Fetch estados
$sql_est = 'SELECT rowid, label FROM ' . MAIN_DB_PREFIX . 'c_verifactu_registro_estados WHERE active=1';
$result_est = $db->query($sql_est);
$estados = array();
if ($result_est) {
    while ($obj = $db->fetch_object($result_est)) {
        $estados[] = $obj;
    }
    $db->free($result_est);
}

// Fetch data
$records = array();

// Count total records
$sql_count = 'SELECT COUNT(*) as total FROM ' . MAIN_DB_PREFIX . 'verifactu_factura_registros';
$result_count = $db->query($sql_count);
$nbtotalofrecords = 0;
if ($result_count) {
    $obj_count = $db->fetch_object($result_count);
    $nbtotalofrecords = $obj_count->total;
    $db->free($result_count);
}

$sql = 'SELECT t.rowid, t.factureid, t.operation, t.estado, t.fecha';
$sql .= ' FROM ' . MAIN_DB_PREFIX . 'verifactu_factura_registros as t';
$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);

$result = $db->query($sql);
if ($result) {
	$num = $db->num_rows($result);
	$i = 0;
	while ($i < min($num, $limit)) {
		$obj = $db->fetch_object($result);
		$records[$i] = $obj;
		$i++;
	}
	$db->free($result);
} else {
	dol_print_error($db);
}

// Actions
if ($action == 'update_operation' && $hasWriteRights) {
	$id = GETPOSTINT('record_id');
	$new_operation = GETPOST('new_operation', 'alpha');
	if ($id && $new_operation) {
		$verifactufacturaregistro->id = $id;
		$verifactufacturaregistro->operation = $new_operation;
		$result = $verifactufacturaregistro->update($user);
		if ($result > 0) {
			setEventMessages($langs->trans('RecordUpdated'), null, 'mesgs');
		} else {
			setEventMessages($langs->trans('ErrorUpdatingRecord'), null, 'errors');
		}
	}
	header('Location: ' . $_SERVER['PHP_SELF']);
	exit;
}

if ($action == 'update_estado' && $hasWriteRights) {
	$id = GETPOSTINT('record_id');
	$new_estado = GETPOSTINT('new_estado');
	if ($id && $new_estado) {
		$verifactufacturaregistro->id = $id;
		$verifactufacturaregistro->estado = $new_estado;
		$result = $verifactufacturaregistro->update($user);
		if ($result > 0) {
			setEventMessages($langs->trans('RecordUpdated'), null, 'mesgs');
		} else {
			setEventMessages($langs->trans('ErrorUpdatingRecord'), null, 'errors');
		}
	}
	header('Location: ' . $_SERVER['PHP_SELF']);
	exit;
}

// Page header
llxHeader('', $langs->trans('VerifactuRegisters'), '');

print load_fiche_titre($langs->trans('VerifactuRegisters'), '', 'verifactu@verifactu');

// Table
print '<table class="liste">';
print '<thead>';
print '<tr class="liste_titre">';
print_liste_field_titre('ID', $_SERVER['PHP_SELF'], 't.rowid', '', '', '', $sortfield, $sortorder);
print_liste_field_titre('Factura', $_SERVER['PHP_SELF'], 't.factureid', '', '', '', $sortfield, $sortorder);
print_liste_field_titre('Operation', $_SERVER['PHP_SELF'], 't.operation', '', '', '', $sortfield, $sortorder);
print_liste_field_titre('Estado', $_SERVER['PHP_SELF'], 't.estado', '', '', '', $sortfield, $sortorder);
print_liste_field_titre('DateCreation', $_SERVER['PHP_SELF'], 't.fecha', '', '', '', $sortfield, $sortorder);
print '<th>' . $langs->trans('Actions') . '</th>';
print '</tr>';
print '</thead>';
print '<tbody>';

foreach ($records as $record) {
	print '<tr>';
	print '<td>' . $record->rowid . '</td>';
	print '<td><a href="' . DOL_URL_ROOT . '/compta/facture/card.php?id=' . $record->factureid . '">' . $record->factureid . '</a></td>';
	
	// Operation select
	print '<td>';
	if ($hasWriteRights) {
		print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
		print '<input type="hidden" name="action" value="update_operation">';
		print '<input type="hidden" name="record_id" value="' . $record->rowid . '">';
		print '<input type="hidden" name="token" value="' . newToken() . '">';
		print '<select name="new_operation" onchange="this.form.submit();">';
		foreach ($operations as $op) {
			$selected = ($op->code == $record->operation) ? 'selected' : '';
			print '<option value="' . $op->code . '" ' . $selected . '>' . $op->label . '</option>';
		}
		print '</select>';
		print '</form>';
	} else {
		print $record->operation;
	}
	print '</td>';
	
	// Estado select
	print '<td>';
	if ($hasWriteRights) {
		print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
		print '<input type="hidden" name="action" value="update_estado">';
		print '<input type="hidden" name="record_id" value="' . $record->rowid . '">';
		print '<input type="hidden" name="token" value="' . newToken() . '">';
		print '<select name="new_estado" onchange="this.form.submit();">';
		foreach ($estados as $est) {
			$selected = ($est->rowid == $record->estado) ? 'selected' : '';
			print '<option value="' . $est->rowid . '" ' . $selected . '>' . $est->label . '</option>';
		}
		print '</select>';
		print '</form>';
	} else {
		print $record->estado;
	}
	print '</td>';
	
	print '<td>' . dol_print_date($record->fecha, 'dayhour') . '</td>';
	print '<td></td>';
	print '</tr>';
}

print '</tbody>';
print '</table>';

// Pagination
print_barre_liste('', $page, $_SERVER['PHP_SELF'], '', $sortfield, $sortorder, '', $num, $nbtotalofrecords, 'verifactu@verifactu', 0, '', '', $limit, 1);

// Footer
llxFooter();
$db->close();
?>