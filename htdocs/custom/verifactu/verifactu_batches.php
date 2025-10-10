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
 *	\file       verifactu/verifactu_batches.php
 *	\ingroup    verifactu
 *	\brief      Home page of verifactu top menu
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

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

// Load translation files required by the page
$langs->loadLangs(array("verifactu@verifactu"));

$action = GETPOST('action', 'aZ09');

$now = dol_now();
$max = getDolGlobalInt('MAIN_SIZE_SHORTLIST_LIMIT', 5);

// Parámetros de paginación
$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTINT('page');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
    $page = 0;
}
$offset = $limit * $page;

// Filtros
$search_ref = GETPOST('search_ref', 'alpha');
$search_hash = GETPOST('search_hash', 'alpha');

// Filtros específicos para batches
$search_date_start = GETPOST('search_date_start', 'alpha');
$search_date_end = GETPOST('search_date_end', 'alpha');
$search_estado = GETPOST('search_estado', 'int');
$search_msg_error = GETPOST('search_msg_error', 'alpha');

if (!$sortfield) $sortfield = 'b.fecha';
if (!$sortorder) $sortorder = 'DESC';

// Security check - Protection if external user
$socid = GETPOSTINT('socid');
if (!empty($user->socid) && $user->socid > 0) {
	$action = '';
	$socid = $user->socid;
}

// Initialize a technical object to manage hooks. Note that conf->hooks_modules contains array
//$hookmanager->initHooks(array($object->element.'index'));

// Security check (enable the most restrictive one)
//if ($user->socid > 0) accessforbidden();
//if ($user->socid > 0) $socid = $user->socid;
if (!isModEnabled('verifactu')) {
	accessforbidden('Module not enabled');
}
// Temporarily allow access for testing - uncomment when permissions are properly set
// if (! $user->hasRight('verifactu', 'myobject', 'read')) {
//	accessforbidden();
// }
//restrictedArea($user, 'verifactu', 0, 'verifactu_myobject', 'myobject', '', 'rowid');
//if (empty($user->admin)) {
//	accessforbidden('Must be admin');
//}


/*
 * Actions
 */

// None


//crear un batch fake para ver que funciona
include_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactubatch.class.php';
include_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactufacturaregistro.class.php';
include_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifacturegistroestado.class.php';
include_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactuestadobatch.class.php';
include_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactubatch.class.php';
include_once DOL_DOCUMENT_ROOT . '/custom/verifactu/lib/verifactu.lib.php';

//ESTADOS REGISTRO
const VERIFACTU_ESTADO_REGISTRO_PENDIENTE_ENVIO = 1;
const VERIFACTU_ESTADO_REGISTRO_CORRECTO = 2;
const VERIFACTU_ESTADO_REGISTRO_ACEPTADO_CON_ERRORES = 3;
const VERIFACTU_ESTADO_REGISTRO_INCORRECTO = 4;
const VERIFACTU_ESTADO_REGISTRO_NO_ENVIADO = 5;
const VERIFACTU_ESTADO_REGISTRO_ENVIANDO = 6;

//OPERACIONES
const VERIFACTU_OPERACION_REGISTRO_ALTA = 1;
const VERIFACTU_OPERACION_REGISTRO_ALTA_SUBSANACION = 2;
const VERIFACTU_OPERACION_REGISTRO_ALTA_SUBSANACION_RECHAZADA = 3;

//ESTADOS BATCH
const VERIFACTU_ESTADO_BATCH_PENDIENTE = 1;
const VERIFACTU_ESTADO_BATCH_CORRECTO = 2;
const VERIFACTU_ESTADO_BATCH_INCORRECTO = 3;
const VERIFACTU_ESTADO_BATCH_PARCIALMENTE_CORRECTO = 4;


/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

llxHeader("", $langs->trans("VerifactuArea"), '', '', 0, 0, '', '', '', 'mod-verifactu page-index');

print load_fiche_titre($langs->trans("Verifactu Batches"), '', 'verifactu.png@verifactu');

print '<div class="fichecenter">';

// Formulario de filtros
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="page" value="'.$page.'">';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre">';
print '<input type="text" class="flat" name="search_date_start" value="'.$search_date_start.'" placeholder="Fecha desde (YYYY-MM-DD)" size="12">';
print '</td>';
print '<td class="liste_titre">';
print '<input type="text" class="flat" name="search_date_end" value="'.$search_date_end.'" placeholder="Fecha hasta (YYYY-MM-DD)" size="12">';
print '</td>';
print '<td class="liste_titre center">';
print '<select class="flat" name="search_estado">';
print '<option value="">-- Estado --</option>';
print '<option value="'.VERIFACTU_ESTADO_BATCH_PENDIENTE.'"'.($search_estado == VERIFACTU_ESTADO_BATCH_PENDIENTE ? ' selected' : '').'>Pendiente</option>';
print '<option value="'.VERIFACTU_ESTADO_BATCH_CORRECTO.'"'.($search_estado == VERIFACTU_ESTADO_BATCH_CORRECTO ? ' selected' : '').'>Enviado</option>';
print '<option value="'.VERIFACTU_ESTADO_BATCH_INCORRECTO.'"'.($search_estado == VERIFACTU_ESTADO_BATCH_INCORRECTO ? ' selected' : '').'>Error</option>';
print '<option value="'.VERIFACTU_ESTADO_BATCH_PARCIALMENTE_CORRECTO.'"'.($search_estado == VERIFACTU_ESTADO_BATCH_PARCIALMENTE_CORRECTO ? ' selected' : '').'>Parcialmente correcto</option>';
print '</select>';
print '</td>';
print '<td class="liste_titre">';
print '<input type="text" class="flat" name="search_msg_error" value="'.$search_msg_error.'" placeholder="Mensaje error">';
print '</td>';
print '<td class="liste_titre maxwidthsearch">';
$searchpicto = $form->showFilterButtons();
print $searchpicto;
print '</td>';
print '</tr>';

// Cabeceras de tabla
print '<tr class="liste_titre">';
print_liste_field_titre('Fecha', $_SERVER["PHP_SELF"], 'b.fecha', '', '', '', $sortfield, $sortorder);
print_liste_field_titre('Estado', $_SERVER["PHP_SELF"], 'b.estado', '', '', 'center', $sortfield, $sortorder);
print_liste_field_titre('Num. Registros', $_SERVER["PHP_SELF"], '', '', '', 'center', $sortfield, $sortorder);
print_liste_field_titre('Mensaje Error', $_SERVER["PHP_SELF"], 'b.msg_error', '', '', '', $sortfield, $sortorder);
print_liste_field_titre('', $_SERVER["PHP_SELF"], '', '', '', 'center');
print '</tr>';

// Construir consulta con filtros
$sql = "SELECT b.rowid, b.fecha, b.estado, b.msg_error, COUNT(r.rowid) as num_records, eb.label as estado_label";
$sql .= " FROM ".MAIN_DB_PREFIX."verifactu_batches b";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."c_verifactu_estado_batch eb ON b.estado = eb.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."verifactu_factura_registros r ON r.fk_batch = b.rowid";
$sql .= " WHERE 1=1";

// Aplicar filtros
if ($search_date_start) {
    $sql .= " AND b.fecha >= '".$db->escape($search_date_start)." 00:00:00'";
}
if ($search_date_end) {
    $sql .= " AND b.fecha <= '".$db->escape($search_date_end)." 23:59:59'";
}
if ($search_estado) {
    $sql .= " AND b.estado = ".intval($search_estado);
}
if ($search_msg_error) {
    $sql .= " AND b.msg_error LIKE '%".$db->escape($search_msg_error)."%'";
}

$sql .= " GROUP BY b.rowid, b.fecha, b.estado, b.msg_error, eb.label";

// Contar total para paginación - para consultas con GROUP BY necesitamos un enfoque diferente
$sqlcount = "SELECT COUNT(DISTINCT b.rowid) as nb";
$sqlcount .= " FROM ".MAIN_DB_PREFIX."verifactu_batches b";
$sqlcount .= " LEFT JOIN ".MAIN_DB_PREFIX."c_verifactu_estado_batch eb ON b.estado = eb.rowid";
$sqlcount .= " LEFT JOIN ".MAIN_DB_PREFIX."verifactu_factura_registros r ON r.fk_batch = b.rowid";
$sqlcount .= " WHERE 1=1";

// Aplicar los mismos filtros para el conteo
if ($search_date_start) {
    $sqlcount .= " AND b.fecha >= '".$db->escape($search_date_start)." 00:00:00'";
}
if ($search_date_end) {
    $sqlcount .= " AND b.fecha <= '".$db->escape($search_date_end)." 23:59:59'";
}
if ($search_estado) {
    $sqlcount .= " AND b.estado = ".intval($search_estado);
}
if ($search_msg_error) {
    $sqlcount .= " AND b.msg_error LIKE '%".$db->escape($search_msg_error)."%'";
}

$resqlcount = $db->query($sqlcount);
if ($resqlcount) {
    $objcount = $db->fetch_object($resqlcount);
    $nbtotalofrecords = $objcount->nb;
    $db->free($resqlcount);
} else {
    $nbtotalofrecords = 0;
}

// Ordenamiento
$sql .= $db->order($sortfield, $sortorder);

// Aplicar límite y offset
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if ($resql) {
    $num = $db->num_rows($resql);

    // Mostrar información de paginación
    $param = '';
    if ($search_date_start) $param .= '&search_date_start='.urlencode($search_date_start);
    if ($search_date_end) $param .= '&search_date_end='.urlencode($search_date_end);
    if ($search_estado) $param .= '&search_estado='.$search_estado;
    if ($search_msg_error) $param .= '&search_msg_error='.urlencode($search_msg_error);

    print '<tr><td colspan="5">';
    print_barre_liste('', $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $nbtotalofrecords, 'batch', 0, '', '', $limit, 0, 0, 1);
    print '</td></tr>';

    $i = 0;
    while ($i < min($num, $limit)) {
        $obj = $db->fetch_object($resql);
        print '<tr class="oddeven">';
        print '<td>'.dol_print_date($db->jdate($obj->fecha), 'dayhour').'</td>';
        // Estado
        $estado_badge = '';
        switch ($obj->estado) {
            case VERIFACTU_ESTADO_BATCH_PENDIENTE: $estado_badge = '<span class="badge badge-warning">Pendiente</span>'; break;
            case VERIFACTU_ESTADO_BATCH_CORRECTO: $estado_badge = '<span class="badge badge-success">Correcto</span>'; break;
            case VERIFACTU_ESTADO_BATCH_INCORRECTO: $estado_badge = '<span class="badge badge-danger">Incorrecto</span>'; break;
            case VERIFACTU_ESTADO_BATCH_PARCIALMENTE_CORRECTO: $estado_badge = '<span class="badge badge-info">Parcialmente correcto</span>'; break;
            default: $estado_badge = '<span class="badge badge-secondary">'.$obj->estado_label.'</span>';
        }
        print '<td class="center">'.$estado_badge.'</td>';
        print '<td class="center"><span class="badge badge-info">'.$obj->num_records.'</span></td>';
        print '<td>'.($obj->msg_error ? $obj->msg_error : '-').'</td>';
        print '<td class="center"><a href="'.$_SERVER["PHP_SELF"].'?action=detail&id='.urlencode($obj->rowid).'" class="button_search_x" title="Ver detalle">👁️</a></td>';
        print '</tr>';
        $i++;
    }

    if ($num == 0) {
        print '<tr class="oddeven"><td colspan="5" class="center">No hay batches que coincidan con los filtros</td></tr>';
    }

    $db->free($resql);
} else {
    print '<tr class="oddeven"><td colspan="5" class="center">Error consultando batches</td></tr>';
}

print '</table>';
print '</div>';
print '</form>';

print '</div>';

// Si se solicita detalle de un batch
if ($action == 'detail' && GETPOST('id', 'int')) {
    $id = GETPOST('id', 'int');

    print '<div class="fichecenter" style="margin-top: 20px;">';
    print load_fiche_titre('Detalle del Batch ID ' . $id, '', '');

    // Filtros para detalle
    $search_detail_estado = GETPOST('search_detail_estado', 'int');
    $search_detail_msg_error = GETPOST('search_detail_msg_error', 'alpha');

    print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="detail">';
    print '<input type="hidden" name="id" value="'.$id.'">';

    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre_filter">';
    print '<td class="liste_titre"></td>'; // Factura (sin filtro)
    print '<td class="liste_titre"></td>'; // Fecha (sin filtro)
    print '<td class="liste_titre center">';
    print '<select class="flat" name="search_detail_estado">';
    print '<option value="">-- Estado Registro --</option>';
    print '<option value="'.VERIFACTU_ESTADO_REGISTRO_PENDIENTE_ENVIO.'"'.($search_detail_estado == VERIFACTU_ESTADO_REGISTRO_PENDIENTE_ENVIO ? ' selected' : '').'>Pendiente</option>';
    print '<option value="'.VERIFACTU_ESTADO_REGISTRO_CORRECTO.'"'.($search_detail_estado == VERIFACTU_ESTADO_REGISTRO_CORRECTO ? ' selected' : '').'>Correcto</option>';
    print '<option value="'.VERIFACTU_ESTADO_REGISTRO_ACEPTADO_CON_ERRORES.'"'.($search_detail_estado == VERIFACTU_ESTADO_REGISTRO_ACEPTADO_CON_ERRORES ? ' selected' : '').'>Aceptado con Errores</option>';
    print '<option value="'.VERIFACTU_ESTADO_REGISTRO_INCORRECTO.'"'.($search_detail_estado == VERIFACTU_ESTADO_REGISTRO_INCORRECTO ? ' selected' : '').'>Incorrecto</option>';
    print '<option value="'.VERIFACTU_ESTADO_REGISTRO_NO_ENVIADO.'"'.($search_detail_estado == VERIFACTU_ESTADO_REGISTRO_NO_ENVIADO ? ' selected' : '').'>No Enviado</option>';
    print '<option value="'.VERIFACTU_ESTADO_REGISTRO_ENVIANDO.'"'.($search_detail_estado == VERIFACTU_ESTADO_REGISTRO_ENVIANDO ? ' selected' : '').'>Enviando</option>';
    print '</select>';
    print '</td>';
    print '<td class="liste_titre">';
    print '<input type="text" class="flat" name="search_detail_msg_error" value="'.$search_detail_msg_error.'" placeholder="Mensaje error registro">';
    print '</td>';
    print '<td class="liste_titre maxwidthsearch">';
    $searchpicto = $form->showFilterButtons();
    print $searchpicto;
    print '</td>';
    print '</tr>';

    print '<tr class="liste_titre">';
    print '<th>Factura</th>';
    print '<th>Fecha</th>';
    print '<th>Estado</th>';
    print '<th>Mensaje Error</th>';
    print '<th></th>'; // Para botones de filtros
    print '</tr>';

    // Consulta con filtros
    $sql_detail = "SELECT r.rowid, r.factureid, r.fecha, r.estado, r.msg_error, f.ref";
    $sql_detail .= " FROM ".MAIN_DB_PREFIX."verifactu_factura_registros r";
    $sql_detail .= " LEFT JOIN ".MAIN_DB_PREFIX."facture f ON r.factureid = f.rowid";
    $sql_detail .= " WHERE r.fk_batch = ".intval($id);

    if ($search_detail_estado) {
        $sql_detail .= " AND r.estado = ".intval($search_detail_estado);
    }
    if ($search_detail_msg_error) {
        $sql_detail .= " AND r.msg_error LIKE '%".$db->escape($search_detail_msg_error)."%'";
    }

    $sql_detail .= " ORDER BY r.fecha DESC";

    $resql_detail = $db->query($sql_detail);
    if ($resql_detail) {
        $num_detail = $db->num_rows($resql_detail);
        if ($num_detail > 0) {
            $i = 0;
            while ($i < $num_detail) {
                $obj_detail = $db->fetch_object($resql_detail);
                print '<tr class="oddeven">';
                print '<td><a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$obj_detail->factureid.'">'.$obj_detail->ref.'</a></td>';
                print '<td>'.dol_print_date($db->jdate($obj_detail->fecha), 'dayhour').'</td>';
                // Estado
                $estado_label = '';
                switch ($obj_detail->estado) {
                    case VERIFACTU_ESTADO_REGISTRO_PENDIENTE_ENVIO: $estado_label = '<span class="badge badge-warning">Pendiente</span>'; break;
                    case VERIFACTU_ESTADO_REGISTRO_CORRECTO: $estado_label = '<span class="badge badge-success">Correcto</span>'; break;
                    case VERIFACTU_ESTADO_REGISTRO_ACEPTADO_CON_ERRORES: $estado_label = '<span class="badge badge-warning">Aceptado con Errores</span>'; break;
                    case VERIFACTU_ESTADO_REGISTRO_INCORRECTO: $estado_label = '<span class="badge badge-danger">Rechazado</span>'; break;
                    case VERIFACTU_ESTADO_REGISTRO_NO_ENVIADO: $estado_label = '<span class="badge badge-danger">No Enviado</span>'; break;
                    case VERIFACTU_ESTADO_REGISTRO_ENVIANDO: $estado_label = '<span class="badge badge-info">Enviando</span>'; break;
                    default: $estado_label = '<span class="badge badge-secondary">Desconocido</span>';
                }
                print '<td class="center">'.$estado_label.'</td>';
                print '<td>'.($obj_detail->msg_error ? $obj_detail->msg_error : '-').'</td>';
                print '<td></td>'; // Para alinear con filtros
                print '</tr>';
                $i++;
            }
        } else {
            print '<tr class="oddeven"><td colspan="5" class="center">No hay registros que coincidan con los filtros</td></tr>';
        }
        $db->free($resql_detail);
    } else {
        print '<tr class="oddeven"><td colspan="5" class="center">Error consultando detalle</td></tr>';
    }

    print '</table>';
    print '</div>';
    print '</form>';

    print '<div class="center" style="margin-top: 10px;">';
    print '<a href="'.$_SERVER["PHP_SELF"].'" class="butAction">Volver a Batches</a>';
    print '</div>';

    print '</div>';
}

// End of page
llxFooter();
$db->close();

