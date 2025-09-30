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


/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

llxHeader("", $langs->trans("VerifactuArea"), '', '', 0, 0, '', '', '', 'mod-verifactu page-index');

print load_fiche_titre($langs->trans("VerifactuArea"), '', 'verifactu.png@verifactu');

print '<div class="fichecenter"><div class="fichethirdleft">';

// Estadísticas de facturas con hash
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th colspan="2">Estadísticas Verifactu</th>';
print "</tr>\n";

// Contar facturas totales (excluyendo borradores para estadísticas de hash)
$sql = "SELECT COUNT(*) as total FROM ".MAIN_DB_PREFIX."facture WHERE entity IN (".getEntity('invoice').")
        AND fk_statut > 0"; // Excluir borradores
$resql = $db->query($sql);
if ($resql) {
    $obj = $db->fetch_object($resql);
    $total_facturas = $obj->total;
    $db->free($resql);
} else {
    $total_facturas = 0;
}

// Contar facturas con hash (solo facturas validadas/no borrador)
$sql = "SELECT COUNT(DISTINCT f.rowid) as con_hash
        FROM ".MAIN_DB_PREFIX."facture f
        INNER JOIN ".MAIN_DB_PREFIX."facture_extrafields ef ON f.rowid = ef.fk_object
        WHERE ef.hash IS NOT NULL
        AND ef.hash != ''
        AND f.fk_statut > 0
        AND f.entity IN (".getEntity('invoice').")";
$resql = $db->query($sql);
if ($resql) {
    $obj = $db->fetch_object($resql);
    $facturas_con_hash = $obj->con_hash;
    $db->free($resql);
} else {
    $facturas_con_hash = 0;
}$facturas_sin_hash = $total_facturas - $facturas_con_hash;

print '<tr class="oddeven">';
print '<td>Facturas Totales</td>';
print '<td class="right"><span class="badge badge-info">'.$total_facturas.'</span></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>Facturas con Hash</td>';
print '<td class="right"><span class="badge badge-success">'.$facturas_con_hash.'</span></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>Facturas sin Hash</td>';
print '<td class="right"><span class="badge badge-warning">'.$facturas_sin_hash.'</span></td>';
print '</tr>';

if ($total_facturas > 0) {
    $porcentaje = round(($facturas_con_hash / $total_facturas) * 100, 1);
    print '<tr class="oddeven">';
    print '<td><strong>Porcentaje Completo</strong></td>';
    print '<td class="right"><strong><span class="badge badge-primary">'.$porcentaje.'%</span></strong></td>';
    print '</tr>';
}

print '</table>';
print '</div>';

// Último hash generado
print '<div class="div-table-responsive-no-min" style="margin-top: 20px;">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th colspan="2">Último Hash Generado</th>';
print "</tr>\n";

$sql_last_hash = "SELECT hash FROM " . MAIN_DB_PREFIX . "verifactu_last_hash ORDER BY rowid DESC LIMIT 1";
$resql_last = $db->query($sql_last_hash);
if ($resql_last) {
    $obj_last = $db->fetch_object($resql_last);
    $last_hash = $obj_last ? $obj_last->hash : 'No disponible';
    $db->free($resql_last);
} else {
    $last_hash = 'Error: ' . $db->lasterror();
}

print '<tr class="oddeven">';
print '<td>Hash SHA-256</td>';
print '<td class="center"><span style="font-family: monospace; font-size: 12px; word-break: break-all;">'.$last_hash.'</span></td>';
print '</tr>';

print '</table>';
print '</div>';

print '</div><div class="fichetwothirdright">';

// Lista de facturas recientes sin hash
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th colspan="4">Facturas Recientes sin Hash</th>';
print "</tr>\n";

$sql = "SELECT f.rowid, f.ref, f.datef, f.total_ttc, s.nom as client
        FROM ".MAIN_DB_PREFIX."facture f
        LEFT JOIN ".MAIN_DB_PREFIX."societe s ON f.fk_soc = s.rowid
        LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields ef ON f.rowid = ef.fk_object
        WHERE f.entity IN (".getEntity('invoice').")
        AND f.fk_statut > 0
        AND (ef.hash IS NULL OR ef.hash = '')
        ORDER BY f.datef DESC
        LIMIT 10";$resql = $db->query($sql);
if ($resql) {
    $num = $db->num_rows($resql);
    if ($num > 0) {
        print '<tr class="liste_titre">';
        print '<th>Referencia</th>';
        print '<th>Fecha</th>';
        print '<th>Cliente</th>';
        print '<th class="right">Total</th>';
        print '</tr>';

        $i = 0;
        while ($i < $num) {
            $obj = $db->fetch_object($resql);
            print '<tr class="oddeven">';
            print '<td><a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$obj->rowid.'">'.$obj->ref.'</a></td>';
            print '<td>'.dol_print_date($db->jdate($obj->datef), 'day').'</td>';
            print '<td>'.$obj->client.'</td>';
            print '<td class="right">'.price($obj->total_ttc).'</td>';
            print '</tr>';
            $i++;
        }
    } else {
        print '<tr class="oddeven"><td colspan="4" class="center">¡Todas las facturas tienen hash!</td></tr>';
    }
    $db->free($resql);
} else {
    print '<tr class="oddeven"><td colspan="4" class="center">Error consultando datos</td></tr>';
}

print '</table>';
print '</div>';

print '</div></div>';

// Acciones rápidas
print '<div class="fichecenter">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th colspan="2">Acciones Rápidas</th>';
print "</tr>\n";

print '<tr class="oddeven">';
print '<td><a href="'.dol_buildpath('/verifactu/admin/setup.php', 1).'" class="butAction">Configurar Módulo</a></td>';
print '<td>Configurar parámetros del módulo Verifactu</td>';
print '</tr>';



if ($facturas_sin_hash > 0) {
    print '<tr class="oddeven">';
    print '<td><a href="#" onclick="regenerarHashMasivo(); return false;" class="butAction">Regenerar Hash</a></td>';
    print '<td>Regenerar hash para facturas que no lo tienen ('.$facturas_sin_hash.' pendientes)</td>';
    print '</tr>';
}

print '</table>';
print '</div>';
print '</div>';

// JavaScript para acciones
print '<script type="text/javascript">
function regenerarHashMasivo() {
    if (confirm("¿Está seguro de que desea regenerar el hash para todas las facturas que no lo tienen?")) {
        alert("Funcionalidad en desarrollo. Se procesarían '.$facturas_sin_hash.' facturas.");
    }
}
</script>';

// Cerrar el layout de dos columnas antes de la tabla completa


// Tabla completa de facturas con paginación
print '<div class="fichecenter">';
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="page" value="'.$page.'">';

// Título y botones de navegación
print load_fiche_titre('Lista Completa de Facturas', '', '');

// Formulario de búsqueda
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre"><input type="text" class="flat maxwidth75" name="search_ref" value="'.$search_ref.'" placeholder="Referencia"></td>';
print '<td class="liste_titre center"></td>';
print '<td class="liste_titre center"></td>';
print '<td class="liste_titre center"></td>';
print '<td class="liste_titre center">';
print '<select class="flat" name="search_hash">';
print '<option value="">-- Hash --</option>';
print '<option value="1"'.($search_hash == '1' ? ' selected' : '').'>Con Hash</option>';
print '<option value="0"'.($search_hash == '0' ? ' selected' : '').'>Sin Hash</option>';
print '</select>';
print '</td>';
print '<td class="liste_titre maxwidthsearch">';
$searchpicto = $form->showFilterButtons();
print $searchpicto;
print '</td>';
print '</tr>';

// Cabeceras de tabla
print '<tr class="liste_titre">';
print_liste_field_titre('Referencia', $_SERVER["PHP_SELF"], 'f.ref', '', '', '', $sortfield, $sortorder);
print_liste_field_titre('Fecha', $_SERVER["PHP_SELF"], 'f.datef', '', '', 'center', $sortfield, $sortorder);
print_liste_field_titre('Última Modif.', $_SERVER["PHP_SELF"], 'f.tms', '', '', 'center', $sortfield, $sortorder);
print_liste_field_titre('Total', $_SERVER["PHP_SELF"], 'f.total_ttc', '', '', 'right', $sortfield, $sortorder);
print_liste_field_titre('Hash Verifactu', $_SERVER["PHP_SELF"], 'ef.hash', '', '', 'center', $sortfield, $sortorder);
print_liste_field_titre('Hash Anterior', $_SERVER["PHP_SELF"], 'ef.hash_anterior', '', '', 'center', $sortfield, $sortorder);
print_liste_field_titre('', $_SERVER["PHP_SELF"], '', '', '', 'center');
print '</tr>';

// Construir consulta con filtros
$sql = "SELECT f.rowid, f.ref, f.datef, f.tms, f.total_ttc, f.fk_statut, s.nom as client, ef.hash, ef.hash_anterior";
$sql .= " FROM ".MAIN_DB_PREFIX."facture f";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON f.fk_soc = s.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields ef ON f.rowid = ef.fk_object";
$sql .= " WHERE f.entity IN (".getEntity('invoice').")";
$sql .= " AND f.fk_statut > 0"; // Excluir facturas borrador por defecto

// Aplicar filtros
if ($search_ref) {
    $sql .= " AND f.ref LIKE '%".$db->escape($search_ref)."%'";
}
if ($search_hash !== '') {
    if ($search_hash == '1') {
        $sql .= " AND ef.hash IS NOT NULL AND ef.hash != ''";
    } else {
        $sql .= " AND (ef.hash IS NULL OR ef.hash = '')";
    }
}

// Ordenamiento
$sql .= $db->order($sortfield, $sortorder);

// Contar total para paginación
$sqlcount = str_replace('SELECT f.rowid, f.ref, f.datef, f.tms, f.total_ttc, f.fk_statut, s.nom as client, ef.hash, ef.hash_anterior', 'SELECT COUNT(f.rowid) as nb', $sql);
$resqlcount = $db->query($sqlcount);
if ($resqlcount) {
    $objcount = $db->fetch_object($resqlcount);
    $nbtotalofrecords = $objcount->nb;
    $db->free($resqlcount);
} else {
    $nbtotalofrecords = 0;
}

// Aplicar límite y offset
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if ($resql) {
    $num = $db->num_rows($resql);

    // Mostrar información de paginación
    $param = '';
    if ($search_ref) $param .= '&search_ref='.urlencode($search_ref);
    if ($search_hash !== '') $param .= '&search_hash='.urlencode($search_hash);

    print '<tr><td colspan="6">';
    print_barre_liste('', $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $nbtotalofrecords, 'bill', 0, '', '', $limit, 0, 0, 1);
    print '</td></tr>';

    $i = 0;
    while ($i < min($num, $limit)) {
        $obj = $db->fetch_object($resql);

        print '<tr class="oddeven">';

        // Referencia
        print '<td><a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$obj->rowid.'">'.$obj->ref.'</a></td>';

        // Fecha
        print '<td class="center">'.dol_print_date($db->jdate($obj->datef), 'day').'</td>';

        // Última Modificación (timestamp)
        print '<td class="center">'.dol_print_date($db->jdate($obj->tms), 'dayhour').'</td>';

        // Total
        print '<td class="right">'.price($obj->total_ttc).'</td>';

        // Hash
        print '<td class="center">';
        if (!empty($obj->hash)) {
            print '<span style="font-family: monospace; font-size: 11px;">'.$obj->hash.'</span>';
        } else {
            print '<span class="badge badge-warning">Sin Hash</span>';
        }
        print '</td>';

        // Hash Anterior con validación
        print '<td class="center">';
        if (!empty($obj->hash_anterior)) {
            // Obtener el hash de la factura anterior para validar
            // En Verifactu, el hash_anterior debe ser el hash de la factura inmediatamente anterior
            $sql_prev = "SELECT ef.hash FROM ".MAIN_DB_PREFIX."facture f
                        LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields ef ON f.rowid = ef.fk_object
                        WHERE f.rowid < ".$obj->rowid." AND f.fk_statut > 0 AND f.entity IN (".getEntity('invoice').")
                        ORDER BY f.rowid DESC LIMIT 1";
            $res_prev = $db->query($sql_prev);
            $hash_prev = null;
            if ($res_prev && $db->num_rows($res_prev) > 0) {
                $obj_prev = $db->fetch_object($res_prev);
                $hash_prev = $obj_prev->hash;
                $db->free($res_prev);
            }

            $is_valid = ($hash_prev && $obj->hash_anterior === $hash_prev);
            $color = $is_valid ? 'green' : 'red';
            print '<span style="font-family: monospace; font-size: 11px; color: '.$color.';">'.$obj->hash_anterior.'</span>';
            if (!$is_valid) {
                // Debug temporal
                $debug_info = "Factura: ".$obj->ref." | Hash anterior: '".trim($obj->hash_anterior)."' | Hash prev: '".trim($hash_prev)."' | Longitud ant: ".strlen($obj->hash_anterior)." | Longitud prev: ".strlen($hash_prev);
                print '<br><small style="color: red; font-size: 9px;" title="'.$debug_info.'">⚠ No coincide</small>';
            }
        } else {
            print '<span class="badge badge-secondary">Sin Hash Anterior</span>';
        }
        print '</td>';

        // Acciones
        print '<td class="center">';
        print '<a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$obj->rowid.'" class="button_search_x" title="Ver factura">👁️</a>';
        print '</td>';

        print '</tr>';
        $i++;
    }

    if ($num == 0) {
        print '<tr class="oddeven"><td colspan="6" class="center">No se encontraron facturas</td></tr>';
    }

    $db->free($resql);
} else {
    print '<tr class="oddeven"><td colspan="6" class="center">Error en la consulta</td></tr>';
}

print '</table>';
print '</div>';
print '</form>';
print '</div>';
print '</div></div>'; // Cierra fichetwothirdright y fichecenter
// End of page
llxFooter();
$db->close();
