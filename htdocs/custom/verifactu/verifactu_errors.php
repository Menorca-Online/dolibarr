<?php
/* Copyright (C) 2001-2005  Rodolphe Quiedeville    <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012  Regis Houssin           <regis.houssin@inodbox.com>
 * Copyright (C) 2015       Jean-François Ferry     <jfefe@aternatik.fr>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2025		SuperAdmin
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
 *	\file       verifactu/verifactu_errors.php
 *	\ingroup    verifactu
 *	\brief      Página para gestionar errores del módulo Verifactu
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

// Libraries
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once __DIR__.'/class/verifactuerror.class.php';

// Global variables definitions
global $db, $langs, $user;

// Load translation files required by the page
$langs->loadLangs(array("verifactu@verifactu", "admin", "other"));

// Parameters
$action = GETPOST('action', 'aZ09');

// Security check
if (!$user->rights->verifactu->read) {
	accessforbidden();
}

// Initialize objects
$form = new Form($db);

// Filtros
$search_date_start = GETPOST('search_date_start', 'alpha');
$search_date_end = GETPOST('search_date_end', 'alpha');
$search_tipo_error = GETPOST('search_tipo_error', 'alpha');
$search_notificado = GETPOST('search_notificado', 'int');

print llxHeader('', $langs->trans("VerifactuErrors"), '', '', 0, 0, '', '', '', 'mod-verifactu page-errors');

print load_fiche_titre($langs->trans("VerifactuErrors"), '', 'fa-exclamation-triangle');

print '<div class="fichecenter">';

// Formulario de filtros
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre">';
print '<input type="text" class="flat" name="search_date_start" value="'.$search_date_start.'" placeholder="Fecha desde (YYYY-MM-DD)" size="12">';
print '</td>';
print '<td class="liste_titre">';
print '<input type="text" class="flat" name="search_date_end" value="'.$search_date_end.'" placeholder="Fecha hasta (YYYY-MM-DD)" size="12">';
print '</td>';
print '<td class="liste_titre">';
print '<input type="text" class="flat" name="search_tipo_error" value="'.$search_tipo_error.'" placeholder="Tipo error">';
print '</td>';
print '<td class="liste_titre center">';
print '<select class="flat" name="search_notificado">';
print '<option value="">-- Notificado --</option>';
print '<option value="0"'.($search_notificado === '0' ? ' selected' : '').'>No</option>';
print '<option value="1"'.($search_notificado === '1' ? ' selected' : '').'>Sí</option>';
print '</select>';
print '</td>';
print '<td class="liste_titre">';
print '<input type="text" class="flat" name="search_mensaje" value="'.GETPOST('search_mensaje', 'alpha').'" placeholder="Mensaje">';
print '</td>';
print '<td class="liste_titre maxwidthsearch">';
$searchpicto = $form->showFilterButtons();
print $searchpicto;
print '</td>';
print '</tr>';

// Cabeceras de tabla
print '<tr class="liste_titre">';
print '<th>Fecha</th>';
print '<th>Tipo Error</th>';
print '<th>Notificado</th>';
print '<th>Mensaje</th>';
print '<th class="center">Acciones</th>';
print '</tr>';

// Construir consulta con filtros
$sql = "SELECT e.rowid, e.fecha, e.tipo_error, e.mensaje, e.notificado, e.fk_batch, e.fk_registro
        FROM ".MAIN_DB_PREFIX."verifactu_errores e";

$where = array();
if ($search_date_start) {
    $where[] = "e.fecha >= '".$db->escape($search_date_start)." 00:00:00'";
}
if ($search_date_end) {
    $where[] = "e.fecha <= '".$db->escape($search_date_end)." 23:59:59'";
}
if ($search_tipo_error) {
    $where[] = "e.tipo_error LIKE '%".$db->escape($search_tipo_error)."%'";
}
if ($search_notificado !== '') {
    $where[] = "e.notificado = ".intval($search_notificado);
}
$search_mensaje = GETPOST('search_mensaje', 'alpha');
if ($search_mensaje) {
    $where[] = "e.mensaje LIKE '%".$db->escape($search_mensaje)."%'";
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

$sql .= " ORDER BY e.fecha DESC";

$resql = $db->query($sql);
if ($resql) {
    $num = $db->num_rows($resql);
    if ($num > 0) {
        $i = 0;
        while ($i < $num) {
            $obj = $db->fetch_object($resql);
            print '<tr class="oddeven">';
            print '<td>'.dol_print_date($db->jdate($obj->fecha), 'dayhour').'</td>';
            print '<td><strong>'.$obj->tipo_error.'</strong></td>';
            // Notificado
            $notificado_badge = $obj->notificado ? '<span class="badge badge-success">Sí</span>' : '<span class="badge badge-warning">No</span>';
            print '<td class="center">'.$notificado_badge.'</td>';
            print '<td>'.nl2br($obj->mensaje).'</td>';
            print '<td class="center">';
            if ($obj->fk_batch) {
                print '<a href="verifactu_batches.php?action=detail&id='.urlencode($obj->fk_batch).'" class="button_search_x" title="Ver batch">📦</a> ';
            }
            if ($obj->fk_registro) {
                print '<a href="verifactu_batches.php?action=detail&id='.urlencode($obj->fk_batch).'#registro'.$obj->fk_registro.'" class="button_search_x" title="Ver registro">📄</a>';
            }
            print '</td>';
            print '</tr>';
            $i++;
        }
    } else {
        print '<tr class="oddeven"><td colspan="5" class="center">No hay errores que coincidan con los filtros</td></tr>';
    }
    $db->free($resql);
} else {
    print '<tr class="oddeven"><td colspan="5" class="center">Error consultando errores</td></tr>';
}

print '</table>';
print '</div>';
print '</form>';

print '</div>';

// End of page
llxFooter();
$db->close();