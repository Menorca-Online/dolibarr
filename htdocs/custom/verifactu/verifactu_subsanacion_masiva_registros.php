<?php
/*
 * Verifactu - Subsanación masiva de registros
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

// Fetch data - solo registros con errores que pueden ser subsanados
$records = array();

// Count total records (solo registros que pueden ser subsanados: estado 3=Aceptado con errores y que no tengan registros más recientes)
$sql_count = 'SELECT COUNT(*) as total FROM ' . MAIN_DB_PREFIX . 'verifactu_factura_registros t1';
$sql_count .= ' WHERE t1.estado IN (3)';
$sql_count .= ' AND NOT EXISTS (';
$sql_count .= '     SELECT 1 FROM ' . MAIN_DB_PREFIX . 'verifactu_factura_registros t2';
$sql_count .= '     WHERE t2.factureid = t1.factureid';
$sql_count .= '     AND t2.rowid > t1.rowid';
$sql_count .= ' )';
$result_count = $db->query($sql_count);
$nbtotalofrecords = 0;
if ($result_count) {
    $obj_count = $db->fetch_object($result_count);
    $nbtotalofrecords = $obj_count->total;
    $db->free($result_count);
}

// Consulta principal - solo registros subsanables y más recientes por factura
$sql = 'SELECT t.rowid, t.factureid, t.operation, t.estado, t.fecha, f.ref as factura_ref';
$sql .= ' FROM ' . MAIN_DB_PREFIX . 'verifactu_factura_registros as t';
$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'facture f ON f.rowid = t.factureid';
$sql .= ' WHERE t.estado IN (3)'; // Solo registros con errores
$sql .= ' AND NOT EXISTS (';
$sql .= '     SELECT 1 FROM ' . MAIN_DB_PREFIX . 'verifactu_factura_registros t2';
$sql .= '     WHERE t2.factureid = t.factureid';
$sql .= '     AND t2.rowid > t.rowid';
$sql .= ' )';
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

// Acción masiva - subsanar registros seleccionados
if ($action == 'mass_subsanar' && $hasWriteRights) {
	$selected_records = GETPOST('selected_records', 'array');
	
	if (!empty($selected_records)) {
		$processed_count = 0;
		$success_count = 0;
		$error_count = 0;
		
		foreach ($selected_records as $record_id) {
			$record_id = (int) $record_id;
			if ($record_id > 0) {
				// Cargar el registro
				$temp_registro = new VerifactuFacturaRegistro($db);
				if ($temp_registro->fetch($record_id) > 0 && $temp_registro->factureid > 0) {
					// Cargar la factura relacionada
					require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
					$facture = new Facture($db);
					if ($facture->fetch($temp_registro->factureid) > 0) {
						$processed_count++;
						
						// Llamar a la función de subsanación
						require_once DOL_DOCUMENT_ROOT . '/custom/verifactu/lib/verifactu.lib.php';
						$result = verifactu_generar_registro_alta($facture, true);
						
						if ($result >= 0) {
							$success_count++;
						} else {
							$error_count++;
						}
					}
				}
			}
		}
		
		// Mostrar mensaje de resultado
		if ($success_count > 0) {
			setEventMessages(sprintf($langs->trans('MassSubsanacionSuccess'), $success_count), null, 'mesgs');
		}
		if ($error_count > 0) {
			setEventMessages(sprintf($langs->trans('MassSubsanacionErrors'), $error_count), null, 'errors');
		}
		
		dol_syslog("Verifactu: Subsanación masiva - $success_count exitosos, $error_count errores de $processed_count procesados");
	} else {
		setEventMessages($langs->trans('MassUpdateNoSelection'), null, 'warnings');
	}
	
	header('Location: ' . $_SERVER['PHP_SELF']);
	exit;
}

// Page header
llxHeader('', $langs->trans('SubsanarSelectedRecords'), '');

print load_fiche_titre($langs->trans('SubsanarSelectedRecords'), '', 'verifactu@verifactu');

// Información sobre la página
print '<div class="info" style="margin-bottom: 20px;">';
print '<strong>' . $langs->trans('Nota') . ':</strong> ';
print 'Esta página muestra únicamente los registros más recientes que pueden ser subsanados (aceptados con errores). ';
print 'Si para una factura existe un registro más nuevo, el anterior no se mostrará. ';
print 'Seleccione los registros que desea subsanar y haga clic en el botón de subsanación.';
print '</div>';

print '<script type="text/javascript">
// Funciones para acciones masivas
function toggleAll(masterCheckbox) {
    var checkboxes = document.querySelectorAll(\'.record-checkbox\');
    checkboxes.forEach(function(checkbox) {
        checkbox.checked = masterCheckbox.checked;
    });
    updateSelectedCount();
}

function selectAll() {
    var checkboxes = document.querySelectorAll(\'.record-checkbox\');
    var masterCheckbox = document.getElementById(\'checkAll\');
    checkboxes.forEach(function(checkbox) {
        checkbox.checked = true;
    });
    if (masterCheckbox) masterCheckbox.checked = true;
    updateSelectedCount();
}

function selectNone() {
    var checkboxes = document.querySelectorAll(\'.record-checkbox\');
    var masterCheckbox = document.getElementById(\'checkAll\');
    checkboxes.forEach(function(checkbox) {
        checkbox.checked = false;
    });
    if (masterCheckbox) masterCheckbox.checked = false;
    updateSelectedCount();
}

function updateSelectedCount() {
    var checkedBoxes = document.querySelectorAll(\'.record-checkbox:checked\');
    var countElement = document.getElementById(\'selectedCount\');
    if (countElement) {
        countElement.textContent = checkedBoxes.length;
    }
    
    // Actualizar estado del checkbox maestro
    var masterCheckbox = document.getElementById(\'checkAll\');
    var allCheckboxes = document.querySelectorAll(\'.record-checkbox\');
    
    if (masterCheckbox && allCheckboxes.length > 0) {
        if (checkedBoxes.length === 0) {
            masterCheckbox.checked = false;
            masterCheckbox.indeterminate = false;
        } else if (checkedBoxes.length === allCheckboxes.length) {
            masterCheckbox.checked = true;
            masterCheckbox.indeterminate = false;
        } else {
            masterCheckbox.checked = false;
            masterCheckbox.indeterminate = true;
        }
    }
}

// Función para subsanar registros seleccionados
function subsanarSelected() {
    var checkedBoxes = document.querySelectorAll(\'input[name="selected_records[]"]:checked\');
    
    if (checkedBoxes.length === 0) {
        alert(\'' . $langs->trans('PleaseSelectRecords') . '\');
        return false;
    }
    
    var confirmMsg = "¿Generar subsanación para " + checkedBoxes.length + " registros seleccionados?\\n\\nEsto creará nuevos registros de subsanación para las facturas relacionadas.";
    
    if (confirm(confirmMsg)) {
        // Cambiar la acción del formulario y enviarlo
        var form = document.getElementById("massActionForm");
        var actionInput = form.querySelector(\'input[name="action"]\');
        actionInput.value = "mass_subsanar";
        form.submit();
    }
}

// Inicializar contador al cargar la página
document.addEventListener(\'DOMContentLoaded\', function() {
    updateSelectedCount();
});
</script>';

// Formulario para acciones masivas - solo subsanación
if ($hasWriteRights && $nbtotalofrecords > 0) {
    print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '" id="massActionForm">';
    print '<input type="hidden" name="action" value="mass_subsanar">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    
    print '<div class="div-table-responsive-no-min" style="background: #fff3cd; padding: 15px; margin-bottom: 15px; border: 1px solid #ffeaa7; border-radius: 5px;">';
    print '<table class="noborder centpercent">';
    print '<tr>';
    print '<td width="30%"><strong>' . $langs->trans('SubsanarSelectedRecords') . '</strong></td>';
    print '<td width="40%">';
    print '<input type="button" class="button" value="' . $langs->trans('SubsanarSelectedRecords') . '" onclick="subsanarSelected();" style="background: #ff8c00; color: white; padding: 8px 16px;">';
    print '</td>';
    print '<td width="30%" class="right">';
    print '<span id="selectedCount">0</span> ' . $langs->trans('RecordsSelected');
    print ' | <a href="#" onclick="selectAll(); return false;">' . $langs->trans('SelectAll') . '</a>';
    print ' | <a href="#" onclick="selectNone(); return false;">' . $langs->trans('SelectNone') . '</a>';
    print '</td>';
    print '</tr>';
    print '</table>';
    print '</div>';
} else if ($hasWriteRights) {
    // Si no hay registros, mostrar mensaje
    print '<div class="info">No hay registros que puedan ser subsanados en este momento.</div>';
} else {
    // Si no hay registros pero tienes permisos, mostrar formulario vacío para mantener estructura
    print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '" id="massActionForm" style="display:none;">';
    print '<input type="hidden" name="action" value="mass_subsanar">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
}

// Table (ahora dentro del formulario si hay permisos de escritura)
print '<table class="liste">';
print '<thead>';
print '<tr class="liste_titre">';
if ($hasWriteRights) {
    print '<th width="20px" class="center">';
    print '<input type="checkbox" id="checkAll" onclick="toggleAll(this);" title="' . $langs->trans('SelectAll') . '">';
    print '</th>';
}
print_liste_field_titre('ID', $_SERVER['PHP_SELF'], 't.rowid', '', '', '', $sortfield, $sortorder);
print_liste_field_titre('Factura', $_SERVER['PHP_SELF'], 't.factureid', '', '', '', $sortfield, $sortorder);
print_liste_field_titre('Núm. Factura', $_SERVER['PHP_SELF'], 'f.ref', '', '', '', $sortfield, $sortorder);
print_liste_field_titre('Estado', $_SERVER['PHP_SELF'], 't.estado', '', '', '', $sortfield, $sortorder);
print_liste_field_titre('Fecha', $_SERVER['PHP_SELF'], 't.fecha', '', '', '', $sortfield, $sortorder);
print '</tr>';
print '</thead>';
print '<tbody>';

foreach ($records as $record) {
	print '<tr>';
	
	// Checkbox para selección masiva
	if ($hasWriteRights) {
		print '<td class="center">';
		print '<input type="checkbox" name="selected_records[]" value="' . $record->rowid . '" class="record-checkbox" onchange="updateSelectedCount();">';
		print '</td>';
	}
	
	print '<td>' . $record->rowid . '</td>';
	print '<td><a href="' . DOL_URL_ROOT . '/compta/facture/card.php?id=' . $record->factureid . '">' . $record->factureid . '</a></td>';
	print '<td><strong>' . ($record->factura_ref ?: 'N/A') . '</strong></td>';

	// Estado (solo lectura)
	print '<td>';
	$estado_label = 'Estado ' . $record->estado;
	$estado_class = '';
	
	// Determinar el label y color según el estado
	switch($record->estado) {
		case 3:
			$estado_label = 'Aceptado con errores';
			$estado_class = 'badge badge-warning';
			break;
		case 4:
			$estado_label = 'Incorrecto';  
			$estado_class = 'badge badge-danger';
			break;
		default:
			$estado_label = 'Estado ' . $record->estado;
			$estado_class = 'badge badge-info';
	}
	
	print '<span class="' . $estado_class . '">' . $estado_label . '</span>';
	print '</td>';

	print '<td>' . dol_print_date($record->fecha, 'dayhour') . '</td>';
	print '</tr>';
}

print '</tbody>';
print '</table>';

// Cerrar formulario de acciones masivas
if ($hasWriteRights) {
    print '</form>';
}

// Pagination
print_barre_liste('', $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $nbtotalofrecords, 'verifactu@verifactu', 0, '', '', $limit, 1);

// Footer
llxFooter();
$db->close();
?>
