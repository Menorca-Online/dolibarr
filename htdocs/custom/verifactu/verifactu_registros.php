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

// Filtros
$search_estado = GETPOSTINT('search_estado');
$search_operation = GETPOST('search_operation', 'alpha');
$search_factura = GETPOSTINT('search_factura');

// Construir parámetros URL para mantener filtros en paginación
$param = '';
if (!empty($search_estado)) $param .= '&search_estado=' . $search_estado;
if (!empty($search_operation)) $param .= '&search_operation=' . urlencode($search_operation);
if (!empty($search_factura)) $param .= '&search_factura=' . $search_factura;

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

// Construir WHERE clause para filtros
$where_conditions = array();
if (!empty($search_estado)) {
    $where_conditions[] = 't.estado = ' . ((int) $search_estado);
}
if (!empty($search_operation)) {
    $where_conditions[] = "t.operation = '" . $db->escape($search_operation) . "'";
}
if (!empty($search_factura)) {
    $where_conditions[] = 't.factureid = ' . ((int) $search_factura);
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = ' WHERE ' . implode(' AND ', $where_conditions);
}

// Count total records with filters
$sql_count = 'SELECT COUNT(*) as total FROM ' . MAIN_DB_PREFIX . 'verifactu_factura_registros t' . $where_clause;
$result_count = $db->query($sql_count);
$nbtotalofrecords = 0;
if ($result_count) {
    $obj_count = $db->fetch_object($result_count);
    $nbtotalofrecords = $obj_count->total;
    $db->free($result_count);
}

$sql = 'SELECT t.rowid, t.factureid, t.operation, t.estado, t.fecha';
$sql .= ' FROM ' . MAIN_DB_PREFIX . 'verifactu_factura_registros as t';
$sql .= $where_clause;
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

// DEBUG: Mostrar todos los datos recibidos
if (GETPOST('debug', 'alpha') == '1') {
	echo "<h2>DEBUG MODE</h2>";
	echo "<h3>GET Data:</h3><pre>"; print_r($_GET); echo "</pre>";
	echo "<h3>POST Data:</h3><pre>"; print_r($_POST); echo "</pre>";
	echo "<h3>Action:</h3><pre>"; print_r($action); echo "</pre>";
	echo "<hr>";
}

// Actions

// Acción limpiar filtros
if (GETPOST('button_removefilter', 'alpha')) {
	$search_estado = '';
	$search_operation = '';
	$search_factura = '';
	$page = 0;
}

// Acción masiva - actualizar estados
if ($action == 'mass_update_estado' && $hasWriteRights) {
	$selected_records = GETPOST('selected_records', 'array');
	$new_estado_mass = GETPOSTINT('new_estado_mass');
	
	if (!empty($selected_records) && !empty($new_estado_mass)) {
		$updated_count = 0;
		$error_count = 0;
		
		foreach ($selected_records as $record_id) {
			$record_id = (int) $record_id;
			if ($record_id > 0) {
				// Cargar el registro
				$temp_registro = new VerifactuFacturaRegistro($db);
				if ($temp_registro->fetch($record_id) > 0) {
					// Actualizar solo el estado
					$temp_registro->estado = $new_estado_mass;
					$result = $temp_registro->update($user);
					
					if ($result > 0) {
						$updated_count++;
					} else {
						$error_count++;
					}
				}
			}
		}
		
		// Mostrar mensaje de resultado
		if ($updated_count > 0) {
			setEventMessages(sprintf($langs->trans('MassUpdateSuccess'), $updated_count), null, 'mesgs');
		}
		if ($error_count > 0) {
			setEventMessages(sprintf($langs->trans('MassUpdateErrors'), $error_count), null, 'errors');
		}
		
		dol_syslog("Verifactu: Actualización masiva - $updated_count exitosos, $error_count errores");
	} else {
		setEventMessages($langs->trans('MassUpdateNoSelection'), null, 'warnings');
	}
	
	header('Location: ' . $_SERVER['PHP_SELF'] . ($param ? '?' . substr($param, 1) : ''));
	exit;
}

if ($action == 'update_operation' && $hasWriteRights) {
	$id = GETPOSTINT('record_id');
	$new_operation = GETPOST('new_operation', 'alpha');
	if ($id && $new_operation) {
		// Cargar el registro completo primero
		$verifactufacturaregistro->fetch($id);
		// Cambiar solo el campo que queremos actualizar
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
		// Cargar el registro completo primero
		$verifactufacturaregistro->fetch($id);
		// Cambiar solo el campo que queremos actualizar
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

// Formulario de filtros
print '<form method="GET" action="' . $_SERVER['PHP_SELF'] . '">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td colspan="6">' . $langs->trans('SearchFilters') . '</td>';
print '</tr>';
print '<tr class="oddeven">';

// Filtro por Factura ID
print '<td width="15%"><label>' . $langs->trans('Factura') . '</label></td>';
print '<td width="15%">';
print '<input type="text" name="search_factura" value="' . dol_escape_htmltag($search_factura) . '" placeholder="ID Factura" size="10">';
print '</td>';

// Filtro por Operación
print '<td width="15%"><label>' . $langs->trans('Operation') . '</label></td>';
print '<td width="15%">';
print '<select name="search_operation">';
print '<option value="">' . $langs->trans('All') . '</option>';
foreach ($operations as $op) {
    $selected = ($op->code == $search_operation) ? 'selected' : '';
    print '<option value="' . dol_escape_htmltag($op->code) . '" ' . $selected . '>' . dol_escape_htmltag($op->label) . '</option>';
}
print '</select>';
print '</td>';

// Filtro por Estado
print '<td width="15%"><label>' . $langs->trans('Estado') . '</label></td>';
print '<td width="25%">';
print '<select name="search_estado">';
print '<option value="">' . $langs->trans('All') . '</option>';
foreach ($estados as $est) {
    $selected = ($est->rowid == $search_estado) ? 'selected' : '';
    print '<option value="' . $est->rowid . '" ' . $selected . '>' . dol_escape_htmltag($est->label) . '</option>';
}
print '</select>';
print '</td>';

print '</tr>';
print '<tr class="oddeven">';
print '<td colspan="6" class="center">';
print '<input type="submit" class="button" name="button_search" value="' . $langs->trans('Search') . '">';
print '&nbsp;&nbsp;';
print '<input type="submit" class="button" name="button_removefilter" value="' . $langs->trans('RemoveFilter') . '" onclick="clearFilters();">';
print '</td>';
print '</tr>';
print '</table>';
print '</div>';
print '</form>';

print '<script type="text/javascript">
function clearFilters() {
    document.querySelector(\'input[name="search_factura"]\').value = "";
    document.querySelector(\'select[name="search_operation"]\').selectedIndex = 0;
    document.querySelector(\'select[name="search_estado"]\').selectedIndex = 0;
}

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

function confirmMassAction() {
    var checkedBoxes = document.querySelectorAll(\'.record-checkbox:checked\');
    var newEstado = document.querySelector(\'select[name="new_estado_mass"]\');
    
    console.log("=== DEBUG MASS ACTION ===");
    console.log("Checked boxes found:", checkedBoxes.length);
    console.log("New estado element:", newEstado);
    console.log("New estado value:", newEstado ? newEstado.value : "NULL");
    
    // Mostrar valores de los checkboxes
    var selectedValues = [];
    checkedBoxes.forEach(function(cb, index) {
        selectedValues.push(cb.value);
        console.log("Checkbox", index, "value:", cb.value);
    });
    
    if (checkedBoxes.length === 0) {
        alert(\'' . $langs->trans('PleaseSelectRecords') . '\');
        return false;
    }
    
    if (!newEstado.value) {
        alert(\'' . $langs->trans('PleaseSelectNewEstado') . '\');
        newEstado.focus();
        return false;
    }
    
    var estadoText = newEstado.options[newEstado.selectedIndex].text;
    
    // Mostrar datos que se enviarán
    console.log("Selected IDs:", selectedValues);
    console.log("New estado ID:", newEstado.value);
    console.log("New estado text:", estadoText);
    
    var confirmMsg = "DEBUG: ¿Actualizar " + checkedBoxes.length + " registros al estado \\"" + estadoText + "\\"?\\n\\nIDs seleccionados: " + selectedValues.join(", ");
    return confirm(confirmMsg);
}

// Función para actualizar registros individuales via AJAX
function updateSingleRecord(recordId, field, value) {
    console.log("Updating single record:", recordId, field, value);
    
    var formData = new FormData();
    formData.append("action", "update_" + field);
    formData.append("record_id", recordId);
    formData.append("new_" + field, value);
    formData.append("token", "' . newToken() . '");
    
    fetch("' . $_SERVER['PHP_SELF'] . '", {
        method: "POST",
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        // Recargar la página para mostrar cambios
        window.location.reload();
    })
    .catch(error => {
        console.error("Error:", error);
        alert("Error al actualizar el registro");
    });
}

// Inicializar contador al cargar la página
document.addEventListener(\'DOMContentLoaded\', function() {
    updateSelectedCount();
});
</script>';

// Mostrar información de filtros activos
$filter_info = array();
if (!empty($search_factura)) $filter_info[] = $langs->trans('Factura') . ': ' . $search_factura;
if (!empty($search_operation)) {
    foreach ($operations as $op) {
        if ($op->code == $search_operation) {
            $filter_info[] = $langs->trans('Operation') . ': ' . $op->label;
            break;
        }
    }
}
if (!empty($search_estado)) {
    foreach ($estados as $est) {
        if ($est->rowid == $search_estado) {
            $filter_info[] = $langs->trans('Estado') . ': ' . $est->label;
            break;
        }
    }
}

if (!empty($filter_info)) {
    print '<div class="info">';
    print '<strong>' . $langs->trans('ActiveFilters') . ':</strong> ' . implode(', ', $filter_info);
    print ' (' . $nbtotalofrecords . ' ' . $langs->trans('records') . ')';
    print '</div><br>';
}

// Iniciar formulario para acciones masivas (incluye toda la tabla)
if ($hasWriteRights && $nbtotalofrecords > 0) {
    print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '" id="massActionForm">';
    print '<input type="hidden" name="action" value="mass_update_estado">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    if ($param) {
        $param_parts = explode('&', substr($param, 1));
        foreach ($param_parts as $part) {
            if (strpos($part, '=') !== false) {
                list($key, $value) = explode('=', $part, 2);
                print '<input type="hidden" name="' . dol_escape_htmltag($key) . '" value="' . dol_escape_htmltag(urldecode($value)) . '">';
            }
        }
    }
    
    print '<div class="div-table-responsive-no-min" style="background: #f8f9fa; padding: 10px; margin-bottom: 10px; border: 1px solid #dee2e6; border-radius: 5px;">';
    print '<table class="noborder centpercent">';
    print '<tr>';
    print '<td width="30%"><strong>' . $langs->trans('MassActions') . '</strong></td>';
    print '<td width="25%">';
    print '<select name="new_estado_mass" required>';
    print '<option value="">' . $langs->trans('SelectNewEstado') . '</option>';
    foreach ($estados as $est) {
        print '<option value="' . $est->rowid . '">' . dol_escape_htmltag($est->label) . '</option>';
    }
    print '</select>';
    print '</td>';
    print '<td width="20%">';
    print '<input type="submit" class="button" value="' . $langs->trans('UpdateSelectedRecords') . '" onclick="return confirmMassAction();">';
    print '</td>';
    print '<td width="25%" class="right">';
    print '<span id="selectedCount">0</span> ' . $langs->trans('RecordsSelected');
    print ' | <a href="#" onclick="selectAll(); return false;">' . $langs->trans('SelectAll') . '</a>';
    print ' | <a href="#" onclick="selectNone(); return false;">' . $langs->trans('SelectNone') . '</a>';
    print '</td>';
    print '</tr>';
    print '</table>';
    print '</div>';
} else if ($hasWriteRights) {
    // Si no hay registros pero tienes permisos, mostrar formulario vacío para mantener estructura
    print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '" id="massActionForm" style="display:none;">';
    print '<input type="hidden" name="action" value="mass_update_estado">';
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
print_liste_field_titre('ID', $_SERVER['PHP_SELF'], 't.rowid', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('Factura', $_SERVER['PHP_SELF'], 't.factureid', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('Operation', $_SERVER['PHP_SELF'], 't.operation', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('Estado', $_SERVER['PHP_SELF'], 't.estado', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('DateCreation', $_SERVER['PHP_SELF'], 't.fecha', '', $param, '', $sortfield, $sortorder);
print '<th>' . $langs->trans('Actions') . '</th>';
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

	// Operation select
	print '<td>';
	if ($hasWriteRights) {
		print '<select name="operation_' . $record->rowid . '" onchange="updateSingleRecord(' . $record->rowid . ', \'operation\', this.value);">';
		foreach ($operations as $op) {
			$selected = ($op->code == $record->operation) ? 'selected' : '';
			print '<option value="' . $op->code . '" ' . $selected . '>' . $op->label . '</option>';
		}
		print '</select>';
	} else {
		print $record->operation;
	}
	print '</td>';

	// Estado select  
	print '<td>';
	if ($hasWriteRights) {
		print '<select name="estado_' . $record->rowid . '" onchange="updateSingleRecord(' . $record->rowid . ', \'estado\', this.value);">';
		foreach ($estados as $est) {
			$selected = ($est->rowid == $record->estado) ? 'selected' : '';
			print '<option value="' . $est->rowid . '" ' . $selected . '>' . $est->label . '</option>';
		}
		print '</select>';
	} else {
		// Buscar el label del estado
		$estado_label = $record->estado;
		foreach ($estados as $est) {
			if ($est->rowid == $record->estado) {
				$estado_label = $est->label;
				break;
			}
		}
		print $estado_label;
	}
	print '</td>';

	print '<td>' . dol_print_date($record->fecha, 'dayhour') . '</td>';
	print '<td></td>';
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
