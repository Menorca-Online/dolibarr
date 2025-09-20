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
 *	\file       verifactu/verifactuindex.php
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

// Contar facturas totales
$sql = "SELECT COUNT(*) as total FROM ".MAIN_DB_PREFIX."facture WHERE entity IN (".getEntity('invoice').")";
$resql = $db->query($sql);
if ($resql) {
    $obj = $db->fetch_object($resql);
    $total_facturas = $obj->total;
    $db->free($resql);
} else {
    $total_facturas = 0;
}

// Contar facturas con hash
$sql = "SELECT COUNT(DISTINCT f.rowid) as con_hash
        FROM ".MAIN_DB_PREFIX."facture f
        INNER JOIN ".MAIN_DB_PREFIX."facture_extrafields ef ON f.rowid = ef.fk_object
        WHERE ef.hash IS NOT NULL
        AND ef.hash != ''
        AND f.entity IN (".getEntity('invoice').")";
$resql = $db->query($sql);
if ($resql) {
    $obj = $db->fetch_object($resql);
    $facturas_con_hash = $obj->con_hash;
    $db->free($resql);
} else {
    $facturas_con_hash = 0;
}

$facturas_sin_hash = $total_facturas - $facturas_con_hash;

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
        AND (ef.hash IS NULL OR ef.hash = '')
        ORDER BY f.datef DESC
        LIMIT 10";

$resql = $db->query($sql);
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

print '</div></div>';

// End of page
llxFooter();
$db->close();
