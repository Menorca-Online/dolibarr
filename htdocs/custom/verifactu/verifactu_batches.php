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

if (!$sortfield) $sortfield = 'f.datef';
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
//if (!isModEnabled('verifactu')) {
//	accessforbidden('Module not enabled');
//}
//if (! $user->hasRight('verifactu', 'myobject', 'read')) {
//	accessforbidden();
//}
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
$batch = new VerifactuBatch($db); 
$batch->fecha = $now;
$batch->estado = 1; // Pendiente
$batch->msg_error = '';
$batch->num_records = 0;
$batch->csv = '';
$batch->create($user);
  

/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

llxHeader("", $langs->trans("VerifactuArea"), '', '', 0, 0, '', '', '', 'mod-verifactu page-index');

print load_fiche_titre($langs->trans("Verifactu Batches"), '', 'verifactu.png@verifactu');

print '<div class="fichecenter">';

// Lista de batches
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th colspan="5">Batches de Envío</th>';
print "</tr>\n";

$sql = "SELECT b.rowid, b.fecha, b.estado, b.msg_error, b.num_records, eb.label as estado_label
        FROM ".MAIN_DB_PREFIX."verifactu_batches b
        LEFT JOIN ".MAIN_DB_PREFIX."c_verifactu_estado_batch eb ON b.estado = eb.rowid
        ORDER BY b.fecha DESC";

$resql = $db->query($sql);
if ($resql) {
    $num = $db->num_rows($resql);
    if ($num > 0) {
        print '<tr class="liste_titre">';
        print '<th>Fecha</th>';
        print '<th class="center">Estado</th>';
        print '<th class="center">Num. Registros</th>';
        print '<th>Mensaje Error</th>';
        print '<th class="center">Acciones</th>';
        print '</tr>';

        $i = 0;
        while ($i < $num) {
            $obj = $db->fetch_object($resql);
            print '<tr class="oddeven">';
            print '<td>'.dol_print_date($db->jdate($obj->fecha), 'dayhour').'</td>';
            // Estado
            $estado_badge = '';
            switch ($obj->estado) {
                case 1: $estado_badge = '<span class="badge badge-warning">Pendiente</span>'; break;
                case 2: $estado_badge = '<span class="badge badge-success">Enviado</span>'; break;
                case 3: $estado_badge = '<span class="badge badge-danger">Error</span>'; break;
                default: $estado_badge = '<span class="badge badge-secondary">'.$obj->estado_label.'</span>';
            }
            print '<td class="center">'.$estado_badge.'</td>';
            print '<td class="center"><span class="badge badge-info">'.$obj->num_records.'</span></td>';
            print '<td>'.($obj->msg_error ? $obj->msg_error : '-').'</td>';
            print '<td class="center"><a href="'.$_SERVER["PHP_SELF"].'?action=detail&id='.urlencode($obj->rowid).'" class="button_search_x" title="Ver detalle">👁️</a></td>';
            print '</tr>';
            $i++;
        }
    } else {
        print '<tr class="oddeven"><td colspan="5" class="center">No hay batches creados aún</td></tr>';
    }
    $db->free($resql);
} else {
    print '<tr class="oddeven"><td colspan="5" class="center">Error consultando batches</td></tr>';
}

print '</table>';
print '</div>';

print '</div>';

// Si se solicita detalle de un batch
if ($action == 'detail' && GETPOST('id', 'int')) {
    $id = GETPOST('id', 'int');
    print '<div class="fichecenter" style="margin-top: 20px;">';
    print load_fiche_titre('Detalle del Batch ID ' . $id, '', '');

    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<th>Factura</th>';
    print '<th>Fecha</th>';
    print '<th>Estado</th>';
    print '<th>Mensaje Error</th>';
    print '</tr>';

    $sql_detail = "SELECT r.rowid, r.factureid, r.fecha, r.estado, r.msg_error, f.ref
                   FROM ".MAIN_DB_PREFIX."verifactu_factura_registros r
                   LEFT JOIN ".MAIN_DB_PREFIX."facture f ON r.factureid = f.rowid
                   WHERE r.fk_batch = ".intval($id)."
                   ORDER BY r.fecha DESC";

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
                    case 1: $estado_label = '<span class="badge badge-warning">Pendiente</span>'; break;
                    case 2: $estado_label = '<span class="badge badge-success">Correcto</span>'; break;
                    case 3: $estado_label = '<span class="badge badge-warning">Aceptado con Errores</span>'; break;
                    case 4: $estado_label = '<span class="badge badge-danger">Rechazado</span>'; break;
                    case 5: $estado_label = '<span class="badge badge-danger">No Enviado</span>'; break;
                    default: $estado_label = '<span class="badge badge-secondary">Desconocido</span>';
                }
                print '<td class="center">'.$estado_label.'</td>';
                print '<td>'.($obj_detail->msg_error ? $obj_detail->msg_error : '-').'</td>';
                print '</tr>';
                $i++;
            }
        } else {
            print '<tr class="oddeven"><td colspan="4" class="center">No hay registros en este batch</td></tr>';
        }
        $db->free($resql_detail);
    } else {
        print '<tr class="oddeven"><td colspan="4" class="center">Error consultando detalle</td></tr>';
    }

    print '</table>';
    print '</div>';

    print '<div class="center" style="margin-top: 10px;">';
    print '<a href="'.$_SERVER["PHP_SELF"].'" class="butAction">Volver a Batches</a>';
    print '</div>';

    print '</div>';
}

// Si se solicita detalle de un batch
if ($action == 'detail' && GETPOST('id', 'int')) {
    $id = GETPOST('id', 'int');
    print '<div class="fichecenter" style="margin-top: 20px;">';
    print load_fiche_titre('Detalle del Batch ID ' . $id, '', '');

    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<th>Factura</th>';
    print '<th>Fecha</th>';
    print '<th>Estado</th>';
    print '<th>Mensaje Error</th>';
    print '</tr>';

    $sql_detail = "SELECT r.rowid, r.factureid, r.fecha, r.estado, r.msg_error, f.ref
                   FROM ".MAIN_DB_PREFIX."verifactu_factura_registros r
                   LEFT JOIN ".MAIN_DB_PREFIX."facture f ON r.factureid = f.rowid
                   WHERE r.fk_batch = ".intval($id)."
                   ORDER BY r.fecha DESC";

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
                    case 1: $estado_label = '<span class="badge badge-warning">Pendiente</span>'; break;
                    case 2: $estado_label = '<span class="badge badge-success">Correcto</span>'; break;
                    case 3: $estado_label = '<span class="badge badge-warning">Aceptado con Errores</span>'; break;
                    case 4: $estado_label = '<span class="badge badge-danger">Rechazado</span>'; break;
                    case 5: $estado_label = '<span class="badge badge-danger">No Enviado</span>'; break;
                    default: $estado_label = '<span class="badge badge-secondary">Desconocido</span>';
                }
                print '<td class="center">'.$estado_label.'</td>';
                print '<td>'.($obj_detail->msg_error ? $obj_detail->msg_error : '-').'</td>';
                print '</tr>';
                $i++;
            }
        } else {
            print '<tr class="oddeven"><td colspan="4" class="center">No hay registros en este batch</td></tr>';
        }
        $db->free($resql_detail);
    } else {
        print '<tr class="oddeven"><td colspan="4" class="center">Error consultando detalle</td></tr>';
    }

    print '</table>';
    print '</div>';

    print '<div class="center" style="margin-top: 10px;">';
    print '<a href="'.$_SERVER["PHP_SELF"].'" class="butAction">Volver a Batches</a>';
    print '</div>';

    print '</div>';
}

// End of page
llxFooter();
$db->close();
