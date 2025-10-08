<?php
/* Copyright (C) 2004-2018	Laurent Destailleur			<eldy@users.sourceforge.net>
 * Copyright (C) 2018-2019	Nicolas ZABOURI				<info@inovea-conseil.com>
 * Copyright (C) 2019-2024	Frédéric France				<frederic.france@free.fr>
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
 * 	\defgroup   verifactu     Module Verifactu
 *  \brief      Verifactu module descriptor.
 *
 *  \file       htdocs/verifactu/core/modules/modVerifactu.class.php
 *  \ingroup    verifactu
 *  \brief      Description and activation file for module Verifactu
 */

use Luracast\Restler\Data\Arr;

include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';
include_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactufacturetype.class.php';
include_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactuclaveregimen.class.php';
include_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactuclaveoperacion.class.php';
include_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactuclaveexencion.class.php';
include_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifacturegistroestado.class.php';
include_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifacturegistrooperacion.class.php';
include_once DOL_DOCUMENT_ROOT . '/custom/verifactu/class/verifactuestadobatch.class.php';

/**
 *  Description and activation class for module Verifactu
 */
class modVerifactu extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs;

		$this->db = $db;

		// Id for module (must be unique).
		// Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
		$this->numero = 500000; // TODO Go on page https://wiki.dolibarr.org/index.php/List_of_modules_id to reserve an id number for your module

		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'verifactu';

		// Family can be 'base' (core modules),'crm','financial','hr','projects','products','ecm','technic' (transverse modules),'interface' (link with external tools),'other','...'
		// It is used to group modules by family in module setup page
		$this->family = "other";

		// Module position in the family on 2 digits ('01', '10', '20', ...)
		$this->module_position = '90';

		// Gives the possibility for the module, to provide his own family info and position of this family (Overwrite $this->family and $this->module_position. Avoid this)
		//$this->familyinfo = array('myownfamily' => array('position' => '01', 'label' => $langs->trans("MyOwnFamily")));
		// Module label (no space allowed), used if translation string 'ModuleVerifactuName' not found (Verifactu is name of module).
		$this->name = preg_replace('/^mod/i', '', get_class($this));

		// DESCRIPTION_FLAG
		// Module description, used if translation string 'ModuleVerifactuDesc' not found (Verifactu is name of module).
		$this->description = "VerifactuDescription";
		// Used only if file README.md and README-LL.md not found.
		$this->descriptionlong = "VerifactuDescription";

		// Author
		$this->editor_name = 'Menorca Online S.L';
		$this->editor_url = '';		// Must be an external online web site
		$this->editor_squarred_logo = '';					// Must be image filename into the module/img directory followed with @modulename. Example: 'myimage.png@verifactu'

		// Possible values for version are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated', 'experimental_deprecated' or a version string like 'x.y.z'
		$this->version = '1.0';
		// Url to the file with your last numberversion of this module
		//$this->url_last_version = 'http://www.example.com/versionmodule.txt';

		// Key used in llx_const table to save module status enabled/disabled (where VERIFACTU is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);

		// Name of image file used for this module.
		// If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
		// If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
		// To use a supported fa-xxx css style of font awesome, use this->picto='xxx'
		$this->picto = 'fa-file';

		// Define some features supported by module (triggers, login, substitutions, menus, css, etc...)
		$this->module_parts = array(
			// Set this to 1 if module has its own trigger directory (core/triggers)
			'triggers' => 1,
			// Set this to 1 if module has its own login method file (core/login)
			'login' => 0,
			// Set this to 1 if module has its own substitution function file (core/substitutions)
			'substitutions' => 0,
			// Set this to 1 if module has its own menus handler directory (core/menus)
			'menus' => 0,
			// Set this to 1 if module overwrite template dir (core/tpl)
			'tpl' => 0,
			// Set this to 1 if module has its own barcode directory (core/modules/barcode)
			'barcode' => 0,
			// Set this to 1 if module has its own models directory (core/modules/xxx)
			'models' => 1,
			// Set this to 1 if module has its own printing directory (core/modules/printing)
			'printing' => 0,
			// Set this to 1 if module has its own theme directory (theme)
			'theme' => 0,
			// Set this to relative path of css file if module has its own css file
			'css' => array(
				'/verifactu/css/verifactu.css',
			),
			// Set this to relative path of js file if module must load a js on all pages
			'js' => array(
				'/verifactu/js/verifactu.js',
			),
			// Set here all hooks context managed by module. To find available hook context, make a "grep -r '>initHooks(' *" on source code. You can also set hook context to 'all'
			/* BEGIN MODULEBUILDER HOOKSCONTEXTS */
			'hooks' => array(
				'invoicecard',
				'invoicelist',
				'loadTablesExtraFields',
				'invoicereccard',
				'globalcard',
				'thirdpartycard',
				'contactcard',
				'contact',
				'api',
			),
			/* END MODULEBUILDER HOOKSCONTEXTS */
			// Set this to 1 if features of module are opened to external users
			'moduleforexternal' => 0,
			// Set this to 1 if the module provides a website template into doctemplates/websites/website_template-mytemplate
			'websitetemplates' => 0,
			// Set this to 1 if the module provides a captcha driver
			'captcha' => 0
		);

		// Data directories to create when module is enabled.
		// Example: this->dirs = array("/verifactu/temp","/verifactu/subdir");
		$this->dirs = array("/verifactu/temp");

		// Config pages. Put here list of php page, stored into verifactu/admin directory, to use to setup module.
		$this->config_page_url = array("setup.php@verifactu");

		// Dependencies
		// A condition to hide module
		$this->hidden = getDolGlobalInt('MODULE_VERIFACTU_DISABLED'); // A condition to disable module;
		// List of module class names that must be enabled if this module is enabled. Example: array('always'=>array('modModuleToEnable1','modModuleToEnable2'), 'FR'=>array('modModuleToEnableFR')...)
		$this->depends = array('modFacture');
		// List of module class names to disable if this one is disabled. Example: array('modModuleToDisable1', ...)
		$this->requiredby = array();
		// List of module class names this module is in conflict with. Example: array('modModuleToDisable1', ...)
		$this->conflictwith = array();

		// The language file dedicated to your module
		$this->langfiles = array("verifactu@verifactu");

		// Prerequisites
		$this->phpmin = array(8, 0); // Minimum version of PHP required by module
		// $this->phpmax = array(8, 0); // Maximum version of PHP required by module
		$this->need_dolibarr_version = array(19, -3); // Minimum version of Dolibarr required by module
		// $this->max_dolibarr_version = array(19, -3); // Maximum version of Dolibarr required by module
		$this->need_javascript_ajax = 0;

		// Messages at activation
		$this->warnings_activation = array(); // Warning to show when we activate module. array('always'='text') or array('FR'='textfr','MX'='textmx'...)
		$this->warnings_activation_ext = array(); // Warning to show when we activate an external module. array('always'='text') or array('FR'='textfr','MX'='textmx'...)
		//$this->automatic_activation = array('FR'=>'VerifactuWasAutomaticallyActivatedBecauseOfYourCountryChoice');
		//$this->always_enabled = true;								// If true, can't be disabled

		// Constants
		// List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
		// Example: $this->const=array(1 => array('VERIFACTU_MYNEWCONST1', 'chaine', 'myvalue', 'This is a constant to add', 1),
		//                             2 => array('VERIFACTU_MYNEWCONST2', 'chaine', 'myvalue', 'This is another constant to add', 0, 'current', 1)
		// );
		$this->const = array();

		// Some keys to add into the overwriting translation tables
		/*$this->overwrite_translation = array(
			'en_US:ParentCompany'=>'Parent company or reseller',
			'fr_FR:ParentCompany'=>'Maison mère ou revendeur'
		)*/

		if (!isModEnabled("verifactu")) {
			$conf->verifactu = new stdClass();
			$conf->verifactu->enabled = 0;
		}

		// Array to add new pages in new tabs
		/* BEGIN MODULEBUILDER TABS */
		$this->tabs = array();
		$this->tabs[] = array('data' => 'invoice:+verifacturegisters:Registros Verifact:$user->rights->verifactu->read:/verifactu/verifactu_factura_registros.php?id=__ID__');
		/* END MODULEBUILDER TABS */
		// Example:
		// To add a new tab identified by code tabname1
		// $this->tabs[] = array('data' => 'objecttype:+tabname1:Title1:mylangfile@verifactu:$user->hasRight(\'verifactu\', \'read\'):/verifactu/mynewtab1.php?id=__ID__');
		// To add another new tab identified by code tabname2. Label will be result of calling all substitution functions on 'Title2' key.
		// $this->tabs[] = array('data' => 'objecttype:+tabname2:SUBSTITUTION_Title2:mylangfile@verifactu:$user->hasRight(\'othermodule\', \'read\'):/verifactu/mynewtab2.php?id=__ID__',
		// To remove an existing tab identified by code tabname
		// $this->tabs[] = array('data' => 'objecttype:-tabname:NU:conditiontoremove');
		//
		// Where objecttype can be
		// 'categories_x'	  to add a tab in category view (replace 'x' by type of category (0=product, 1=supplier, 2=customer, 3=member)
		// 'contact'          to add a tab in contact view
		// 'contract'         to add a tab in contract view
		// 'delivery'         to add a tab in delivery view
		// 'group'            to add a tab in group view
		// 'intervention'     to add a tab in intervention view
		// 'invoice'          to add a tab in customer invoice view
		// 'invoice_supplier' to add a tab in supplier invoice view
		// 'member'           to add a tab in foundation member view
		// 'opensurveypoll'	  to add a tab in opensurvey poll view
		// 'order'            to add a tab in sale order view
		// 'order_supplier'   to add a tab in supplier order view
		// 'payment'		  to add a tab in payment view
		// 'payment_supplier' to add a tab in supplier payment view
		// 'product'          to add a tab in product view
		// 'propal'           to add a tab in propal view
		// 'project'          to add a tab in project view
		// 'stock'            to add a tab in stock view
		// 'thirdparty'       to add a tab in third party view
		// 'user'             to add a tab in user view


		// Dictionaries
		/* Example:
		 $this->dictionaries=array(
		 'langs' => 'verifactu@verifactu',
		 // List of tables we want to see into dictionary editor
		 'tabname' => array("table1", "table2", "table3"),
		 // Label of tables
		 'tablib' => array("Table1", "Table2", "Table3"),
		 // Request to select fields
		 'tabsql' => array('SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.$this->db->prefix().'table1 as f', 'SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.$this->db->prefix().'table2 as f', 'SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.$this->db->prefix().'table3 as f'),
		 // Sort order
		 'tabsqlsort' => array("label ASC", "label ASC", "label ASC"),
		 // List of fields (result of select to show dictionary)
		 'tabfield' => array("code,label", "code,label", "code,label"),
		 // List of fields (list of fields to edit a record)
		 'tabfieldvalue' => array("code,label", "code,label", "code,label"),
		 // List of fields (list of fields for insert)
		 'tabfieldinsert' => array("code,label", "code,label", "code,label"),
		 // Name of columns with primary key (try to always name it 'rowid')
		 'tabrowid' => array("rowid", "rowid", "rowid"),
		 // Condition to show each dictionary
		 'tabcond' => array(isModEnabled('verifactu'), isModEnabled('verifactu'), isModEnabled('verifactu')),
		 // Tooltip for every fields of dictionaries: DO NOT PUT AN EMPTY ARRAY
		 'tabhelp' => array(array('code' => $langs->trans('CodeTooltipHelp'), 'field2' => 'field2tooltip'), array('code' => $langs->trans('CodeTooltipHelp'), 'field2' => 'field2tooltip'), ...),
		 );
		 */
		/* BEGIN MODULEBUILDER DICTIONARIES */
		/* BEGIN MODULEBUILDER DICTIONARIES */
		$this->dictionaries = array(
			'langs' => 'verifactu@verifactu',
			'tabname' => array(
				MAIN_DB_PREFIX . "c_verifactu_facture_types",
				MAIN_DB_PREFIX . "c_verifactu_clave_regimenes",
				MAIN_DB_PREFIX . "c_verifactu_clave_operaciones",
				MAIN_DB_PREFIX . "c_verifactu_clave_exenciones",
				MAIN_DB_PREFIX . "c_verifactu_registro_estados",
				MAIN_DB_PREFIX . "c_verifactu_registro_operaciones",
				MAIN_DB_PREFIX . "c_verifactu_estado_batch",
			),
			'tablib' => array(
				"Tipos de Factura Verifactu",
				"Claves de Régimen Verifactu",
				"Claves de Operación Verifactu",
				"Claves de Exención Verifactu",
				"Estados de Registros Verifactu",
				"Registros de Operaciones Verifactu",
				"Estados de Batch Verifactu",
			),
			'tabsql' => array(
				'SELECT f.rowid as rowid, f.code, f.label, f.active FROM ' . MAIN_DB_PREFIX . 'c_verifactu_facture_types as f',
				'SELECT f.rowid as rowid, f.code, f.label, f.active FROM ' . MAIN_DB_PREFIX . 'c_verifactu_clave_regimenes as f',
				'SELECT f.rowid as rowid, f.code, f.label, f.active FROM ' . MAIN_DB_PREFIX . 'c_verifactu_clave_operaciones as f',
				'SELECT f.rowid as rowid, f.code, f.label, f.active FROM ' . MAIN_DB_PREFIX . 'c_verifactu_clave_exenciones as f',
				'SELECT f.rowid as rowid, f.code, f.label, f.active FROM ' . MAIN_DB_PREFIX . 'c_verifactu_registro_estados as f',
				'SELECT f.rowid as rowid, f.code, f.label, f.active FROM ' . MAIN_DB_PREFIX . 'c_verifactu_registro_operaciones as f',
				'SELECT f.rowid as rowid, f.code, f.label, f.active FROM ' . MAIN_DB_PREFIX . 'c_verifactu_estado_batch as f',
			),
			'tabsqlsort' => array(
				"code ASC",
				"code ASC",
				"code ASC",
				"code ASC",
				"code ASC",
				"code ASC",
				"code ASC"
			),
			'tabfield' => array(
				"code,label",
				"code,label",
				"code,label",
				"code,label",
				"code,label",
				"code,label",
				"code,label",
			),
			'tabfieldvalue' => array(
				"code,label",
				"code,label",
				"code,label",
				"code,label",
				"code,label",
				"code,label",
				"code,label",
			),
			'tabfieldinsert' => array(
				"code,label",
				"code,label",
				"code,label",
				"code,label",
				"code,label",
				"code,label",
				"code,label",
			),
			'tabrowid' => array(
				"rowid",
				"rowid",
				"rowid",
				"rowid",
				"rowid",
				"rowid",
			),
			'tabcond' => array(
				isModEnabled('verifactu'),
				isModEnabled('verifactu'),
				isModEnabled('verifactu'),
				isModEnabled('verifactu'),
				isModEnabled('verifactu'),
				isModEnabled('verifactu'),
				isModEnabled('verifactu'),
			),
			'tabhelp' => array(
				array('code' => $langs->trans('Código de factura'), 'label' => $langs->trans('Descripción'), 'active' => $langs->trans('Estado')),
				array('code' => $langs->trans('Código régimen'), 'label' => $langs->trans('Descripción'), 'active' => $langs->trans('Estado')),
				array('code' => $langs->trans('Código operación'), 'label' => $langs->trans('Descripción'), 'active' => $langs->trans('Estado')),
				array('code' => $langs->trans('Código exención'), 'label' => $langs->trans('Descripción'), 'active' => $langs->trans('Estado')),
				array('code' => $langs->trans('Código estado'), 'label' => $langs->trans('Descripción'), 'active' => $langs->trans('Estado')),
				array('code' => $langs->trans('Código registro operacion'), 'label' => $langs->trans('Descripción'), 'active' => $langs->trans('Estado')),
				array('code' => $langs->trans('Código estado batch'), 'label' => $langs->trans('Descripción'), 'active' => $langs->trans('Estado')),
			)
		);


		/* END MODULEBUILDER DICTIONARIES */

		// Boxes/Widgets
		// Add here list of php file(s) stored in verifactu/core/boxes that contains a class to show a widget.
		/* BEGIN MODULEBUILDER WIDGETS */
		$this->boxes = array(
			//  0 => array(
			//      'file' => 'verifactuwidget1.php@verifactu',
			//      'note' => 'Widget provided by Verifactu',
			//      'enabledbydefaulton' => 'Home',
			//  ),
			//  ...
		);
		/* END MODULEBUILDER WIDGETS */

		// Cronjobs (List of cron jobs entries to add when module is enabled)
		// unit_frequency must be 60 for minute, 3600 for hour, 86400 for day, 604800 for week
		/* BEGIN MODULEBUILDER CRON */
		$this->cronjobs = array(
			0 => array(
				'label' => 'Enviar registros pendientes a Verifactu',
				'jobtype' => 'method',
				'class' => '/verifactu/class/verifactucron.class.php',
				'objectname' => 'VerifactuCron',
				'method' => 'doScheduledJob',
				'parameters' => '',
				'comment' => 'Procesa y envía batches de hasta 1000 registros pendientes al webservice de Verifactu',
				'frequency' => 1,
				'unitfrequency' => 1,  // Cada minuto
				'status' => 0,
				'test' => 'isModEnabled("verifactu")',
				'priority' => 50,
			),
			1 => array(
				'label' => 'Enviar notificaciones de errores por email',
				'jobtype' => 'method',
				'class' => '/verifactu/class/verifactucron.class.php',
				'objectname' => 'VerifactuCron',
				'method' => 'doScheduledJobErrors',
				'parameters' => '',
				'comment' => 'Envía notificaciones por email de errores del módulo Verifactu cada 4 horas y marca como notificados',
				'frequency' => 4,
				'unitfrequency' => 3600,  // Cada 4 horas
				'status' => 0,
				'test' => 'isModEnabled("verifactu")',
				'priority' => 40,
			),
		);
		/* END MODULEBUILDER CRON */
		// Example: $this->cronjobs=array(
		//    0=>array('label'=>'My label', 'jobtype'=>'method', 'class'=>'/dir/class/file.class.php', 'objectname'=>'MyClass', 'method'=>'myMethod', 'parameters'=>'param1, param2', 'comment'=>'Comment', 'frequency'=>2, 'unitfrequency'=>3600, 'status'=>0, 'test'=>'isModEnabled("verifactu")', 'priority'=>50),
		//    1=>array('label'=>'My label', 'jobtype'=>'command', 'command'=>'', 'parameters'=>'param1, param2', 'comment'=>'Comment', 'frequency'=>1, 'unitfrequency'=>3600*24, 'status'=>0, 'test'=>'isModEnabled("verifactu")', 'priority'=>50)
		// );

		// Permissions provided by this module
		$this->rights = array();
		$r = 0;
		// Add here entries to declare new permissions
		/* BEGIN MODULEBUILDER PERMISSIONS */

		// General permission to access the module
		$this->rights[$r][0] = $this->numero . sprintf("%02d", 1); // Permission id (must not be already used)
		$this->rights[$r][1] = 'Acceder al módulo Verifactu'; // Permission label
		$this->rights[$r][3] = 0; // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'read'; // In php code, permission will be checked by test if ($user->rights->verifactu->read)
		$this->rights[$r][5] = ''; // In php code, permission will be checked by test if ($user->rights->verifactu->read)
		$r++;

		$o = 1;
		$this->rights[$r][0] = $this->numero . sprintf("%02d", ($o * 10) + 1); // Permission id (must not be already used)
		$this->rights[$r][1] = 'Read objects of Verifactu'; // Permission label
		$this->rights[$r][4] = 'myobject';
		$this->rights[$r][5] = 'read'; // In php code, permission will be checked by test if ($user->hasRight('verifactu', 'myobject', 'read'))
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf("%02d", ($o * 10) + 2); // Permission id (must not be already used)
		$this->rights[$r][1] = 'Create/Update objects of Verifactu'; // Permission label
		$this->rights[$r][4] = 'myobject';
		$this->rights[$r][5] = 'write'; // In php code, permission will be checked by test if ($user->hasRight('verifactu', 'myobject', 'write'))
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf("%02d", ($o * 10) + 3); // Permission id (must not be already used)
		$this->rights[$r][1] = 'Delete objects of Verifactu'; // Permission label
		$this->rights[$r][4] = 'myobject';
		$this->rights[$r][5] = 'delete'; // In php code, permission will be checked by test if ($user->hasRight('verifactu', 'myobject', 'delete'))
		$r++;
		/* END MODULEBUILDER PERMISSIONS */


		// Main menu entries to add
		$this->menu = array();
		$r = 0;
		// Add here entries to declare new menus
		/* BEGIN MODULEBUILDER TOPMENU */
		$this->menu[$r++] = array(
			'fk_menu' => '', // Will be stored into mainmenu + leftmenu. Use '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'top', // This is a Top menu entry
			'titre' => 'ModuleVerifactuName',
			'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'verifactu',
			'leftmenu' => '',
			'url' => '/verifactu/verifactuindex.php',
			'langs' => 'verifactu@verifactu', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("verifactu")', // Define condition to show or hide menu entry. Use 'isModEnabled("verifactu")' if entry must be visible if module is enabled.
			'perms' => '$user->hasRight("verifactu", "myobject", "read")', // Use 'perms'=>'$user->hasRight("verifactu", "myobject", "read")' if you want your menu with a permission rules
			'target' => '',
			'user' => 2, // 0=Menu for internal users, 1=external users, 2=both
		);
		/* END MODULEBUILDER TOPMENU */

		/* BEGIN MODULEBUILDER LEFTMENU MYOBJECT */
		/*
		$this->menu[$r++]=array(
			'fk_menu' => 'fk_mainmenu=verifactu',      // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',                          // This is a Left menu entry
			'titre' => 'MyObject',
			'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle paddingright"'),
			'mainmenu' => 'verifactu',
			'leftmenu' => 'myobject',
			'url' => '/verifactu/verifactuindex.php',
			'langs' => 'verifactu@verifactu',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("verifactu")', // Define condition to show or hide menu entry. Use 'isModEnabled("verifactu")' if entry must be visible if module is enabled.
			'perms' => '$user->hasRight("verifactu", "myobject", "read")',
			'target' => '',
			'user' => 2,				                // 0=Menu for internal users, 1=external users, 2=both
			'object' => 'MyObject'
		);
		$this->menu[$r++]=array(
			'fk_menu' => 'fk_mainmenu=verifactu,fk_leftmenu=myobject',	    // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',			                // This is a Left menu entry
			'titre' => 'New_MyObject',
			'mainmenu' => 'verifactu',
			'leftmenu' => 'verifactu_myobject_new',
			'url' => '/verifactu/myobject_card.php?action=create',
			'langs' => 'verifactu@verifactu',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("verifactu")', // Define condition to show or hide menu entry. Use 'isModEnabled("verifactu")' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms' => '$user->hasRight("verifactu", "myobject", "write")'
			'target' => '',
			'user' => 2,				                // 0=Menu for internal users, 1=external users, 2=both
			'object' => 'MyObject'
		);
		$this->menu[$r++]=array(
			'fk_menu' => 'fk_mainmenu=verifactu,fk_leftmenu=myobject',	    // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'left',			                // This is a Left menu entry
			'titre' => 'List_MyObject',
			'mainmenu' => 'verifactu',
			'leftmenu' => 'verifactu_myobject_list',
			'url' => '/verifactu/myobject_list.php',
			'langs' => 'verifactu@verifactu',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("verifactu")', // Define condition to show or hide menu entry. Use 'isModEnabled("verifactu")' if entry must be visible if module is enabled.
			'perms' => '$user->hasRight("verifactu", "myobject", "read")'
			'target' => '',
			'user' => 2,				                // 0=Menu for internal users, 1=external users, 2=both
			'object' => 'MyObject'
		);
		*/
		/* END MODULEBUILDER LEFTMENU MYOBJECT */

		// Agregar menú para enlace directo a la AEAT
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=verifactu', // Será un submenu del menu principal Verifactu
			'type' => 'left', // Menu de la izquierda
			'titre' => 'Consultar AEAT',
			'prefix' => img_picto('', 'globe', 'class="pictofixedwidth valignmiddle paddingright"'),
			'mainmenu' => 'verifactu',
			'leftmenu' => 'verifactu_aeat_consulta',
			'url' => ($conf->global->VERIFACTU_PRODUCCION ?? "0") == "1" ?
					($conf->global->VERIFACTU_URL_ENDPOINT_PROD ?? '') :
					($conf->global->VERIFACTU_URL_ENDPOINT_DEV ?? '') . '/wlpl/TIKE-CONT/SvTikeEmitidasQuery',
			#'url' => 'https://www1.agenciatributaria.gob.es/wlpl/TIKE-CONT/SvTikeEmitidasQuery',
			'langs' => 'verifactu@verifactu',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("verifactu")',
			'perms' => '1', // Accesible para todos los usuarios con acceso al módulo
			'target' => '_blank', // Abrir en nueva ventana
			'user' => 2, // Para usuarios internos y externos
		);


		//quiero crear una pagina de batches para ver todos los batches enviados, su estado
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=verifactu', // Será un submenu del menu principal Verifactu
			'type' => 'left', // Menu de la izquierda
			'titre' => 'Batches Verifactu',
			'prefix' => img_picto('', 'fa-tasks', 'class="pictofixedwidth valignmiddle paddingright"'),
			'mainmenu' => 'verifactu',
			'leftmenu' => 'verifactu_batches',
			'url' => '/verifactu/verifactu_batches.php',
			'langs' => 'verifactu@verifactu',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("verifactu")',
			'perms' => '1',
			'target' => '',
			'user' => 2, // Para usuarios internos y externos
		);

		// Página de errores del módulo
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=verifactu', // Será un submenu del menu principal Verifactu
			'type' => 'left', // Menu de la izquierda
			'titre' => 'Errores Verifactu',
			'prefix' => img_picto('', 'fa-exclamation-triangle', 'class="pictofixedwidth valignmiddle paddingright"'),
			'mainmenu' => 'verifactu',
			'leftmenu' => 'verifactu_errors',
			'url' => '/verifactu/verifactu_errors.php',
			'langs' => 'verifactu@verifactu',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("verifactu")',
			'perms' => '$user->hasRight("verifactu", "myobject", "read")',
			'target' => '',
			'user' => 2, // Para usuarios internos y externos
		);


		// Exports profiles provided by this module
		$r = 0;
		/* BEGIN MODULEBUILDER EXPORT MYOBJECT */
		/*
		$langs->load("verifactu@verifactu");
		$this->export_code[$r] = $this->rights_class.'_'.$r;
		$this->export_label[$r] = 'MyObjectLines';	// Translation key (used only if key ExportDataset_xxx_z not found)
		$this->export_icon[$r] = $this->picto;
		// Define $this->export_fields_array, $this->export_TypeFields_array and $this->export_entities_array
		$keyforclass = 'MyObject'; $keyforclassfile='/verifactu/class/myobject.class.php'; $keyforelement='myobject@verifactu';
		include DOL_DOCUMENT_ROOT.'/core/commonfieldsinexport.inc.php';
		//$this->export_fields_array[$r]['t.fieldtoadd']='FieldToAdd'; $this->export_TypeFields_array[$r]['t.fieldtoadd']='Text';
		//unset($this->export_fields_array[$r]['t.fieldtoremove']);
		//$keyforclass = 'MyObjectLine'; $keyforclassfile='/verifactu/class/myobject.class.php'; $keyforelement='myobjectline@verifactu'; $keyforalias='tl';
		//include DOL_DOCUMENT_ROOT.'/core/commonfieldsinexport.inc.php';
		$keyforselect='myobject'; $keyforaliasextra='extra'; $keyforelement='myobject@verifactu';
		include DOL_DOCUMENT_ROOT.'/core/extrafieldsinexport.inc.php';
		//$keyforselect='myobjectline'; $keyforaliasextra='extraline'; $keyforelement='myobjectline@verifactu';
		//include DOL_DOCUMENT_ROOT.'/core/extrafieldsinexport.inc.php';
		//$this->export_dependencies_array[$r] = array('myobjectline' => array('tl.rowid','tl.ref')); // To force to activate one or several fields if we select some fields that need same (like to select a unique key if we ask a field of a child to avoid the DISTINCT to discard them, or for computed field than need several other fields)
		//$this->export_special_array[$r] = array('t.field' => '...');
		//$this->export_examplevalues_array[$r] = array('t.field' => 'Example');
		//$this->export_help_array[$r] = array('t.field' => 'FieldDescHelp');
		$this->export_sql_start[$r]='SELECT DISTINCT ';
		$this->export_sql_end[$r]  =' FROM '.$this->db->prefix().'verifactu_myobject as t';
		//$this->export_sql_end[$r]  .=' LEFT JOIN '.$this->db->prefix().'verifactu_myobject_line as tl ON tl.fk_myobject = t.rowid';
		$this->export_sql_end[$r] .=' WHERE 1 = 1';
		$this->export_sql_end[$r] .=' AND t.entity IN ('.getEntity('myobject').')';
		$r++; */
		/* END MODULEBUILDER EXPORT MYOBJECT */

		// Imports profiles provided by this module
		$r = 0;
		/* BEGIN MODULEBUILDER IMPORT MYOBJECT */
		/*
		$langs->load("verifactu@verifactu");
		$this->import_code[$r] = $this->rights_class.'_'.$r;
		$this->import_label[$r] = 'MyObjectLines';	// Translation key (used only if key ExportDataset_xxx_z not found)
		$this->import_icon[$r] = $this->picto;
		$this->import_tables_array[$r] = array('t' => $this->db->prefix().'verifactu_myobject', 'extra' => $this->db->prefix().'verifactu_myobject_extrafields');
		$this->import_tables_creator_array[$r] = array('t' => 'fk_user_author'); // Fields to store import user id
		$import_sample = array();
		$keyforclass = 'MyObject'; $keyforclassfile='/verifactu/class/myobject.class.php'; $keyforelement='myobject@verifactu';
		include DOL_DOCUMENT_ROOT.'/core/commonfieldsinimport.inc.php';
		$import_extrafield_sample = array();
		$keyforselect='myobject'; $keyforaliasextra='extra'; $keyforelement='myobject@verifactu';
		include DOL_DOCUMENT_ROOT.'/core/extrafieldsinimport.inc.php';
		$this->import_fieldshidden_array[$r] = array('extra.fk_object' => 'lastrowid-'.$this->db->prefix().'verifactu_myobject');
		$this->import_regex_array[$r] = array();
		$this->import_examplevalues_array[$r] = array_merge($import_sample, $import_extrafield_sample);
		$this->import_updatekeys_array[$r] = array('t.ref' => 'Ref');
		$this->import_convertvalue_array[$r] = array(
			't.ref' => array(
				'rule'=>'getrefifauto',
				'class'=!getDolGlobalString('VERIFACTU_MYOBJECT_ADDON') ? 'mod_myobject_standard' : getDolGlobalString('VERIFACTU_MYOBJECT_ADDON'),
				'path'=>"/core/modules/verifactu/".(!getDolGlobalString('VERIFACTU_MYOBJECT_ADDON') ? 'mod_myobject_standard' : getDolGlobalString('VERIFACTU_MYOBJECT_ADDON')).'.php',
				'classobject'=>'MyObject',
				'pathobject'=>'/verifactu/class/myobject.class.php',
			),
			't.fk_soc' => array('rule' => 'fetchidfromref', 'file' => '/societe/class/societe.class.php', 'class' => 'Societe', 'method' => 'fetch', 'element' => 'ThirdParty'),
			't.fk_user_valid' => array('rule' => 'fetchidfromref', 'file' => '/user/class/user.class.php', 'class' => 'User', 'method' => 'fetch', 'element' => 'user'),
			't.fk_mode_reglement' => array('rule' => 'fetchidfromcodeorlabel', 'file' => '/compta/paiement/class/cpaiement.class.php', 'class' => 'Cpaiement', 'method' => 'fetch', 'element' => 'cpayment'),
		);
		$this->import_run_sql_after_array[$r] = array();
		$r++; */
		/* END MODULEBUILDER IMPORT MYOBJECT */
	}

	/**
	 *  Function called when module is enabled.
	 *  The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *  It also creates data directories
	 *
	 *  @param      string  $options    Options when enabling module ('', 'noboxes')
	 *  @return     int<-1,1>          	1 if OK, <=0 if KO
	 */
	public function init($options = '')
	{
		global $conf, $langs;

		// Create tables of module at module activation
		//$result = $this->_load_tables('/install/mysql/', 'verifactu');
		$result = $this->_load_tables('/verifactu/sql/');
		if ($result < 0) {
			return -1; // Do not activate module if error 'not allowed' returned when loading module SQL queries (the _load_table run sql with run_sql with the error allowed parameter set to 'default')
		}

		//crear maestros
		$result = $this->_create_maestros();
		if ($result < 0) {
			return -1;
		}

		$result = $this->_add_extra_fields();
		if ($result < 0) {
			return -1;
		}

		// Forzar actualización de posiciones de extrafields
		$this->_update_extrafields_positions();

		// Registrar la plantilla de factura Verifactu automáticamente
		$result = $this->_register_pdf_template();
		if ($result < 0) {
			return -1;
		}

		$sql = array();

		// Document templates
		$moduledir = dol_sanitizeFileName('verifactu');
		$myTmpObjects = array();
		$myTmpObjects['MyObject'] = array('includerefgeneration' => 0, 'includedocgeneration' => 0);

		foreach ($myTmpObjects as $myTmpObjectKey => $myTmpObjectArray) {
			if ($myTmpObjectArray['includerefgeneration']) {
				$src = DOL_DOCUMENT_ROOT . '/install/doctemplates/' . $moduledir . '/template_myobjects.odt';
				$dirodt = DOL_DATA_ROOT . ($conf->entity > 1 ? '/' . $conf->entity : '') . '/doctemplates/' . $moduledir;
				$dest = $dirodt . '/template_myobjects.odt';

				if (file_exists($src) && !file_exists($dest)) {
					require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
					dol_mkdir($dirodt);
					$result = dol_copy($src, $dest, '0', 0);
					if ($result < 0) {
						$langs->load("errors");
						$this->error = $langs->trans('ErrorFailToCopyFile', $src, $dest);
						return 0;
					}
				}

				$sql = array_merge($sql, array(
					"DELETE FROM " . $this->db->prefix() . "document_model WHERE nom = 'standard_" . strtolower($myTmpObjectKey) . "' AND type = '" . $this->db->escape(strtolower($myTmpObjectKey)) . "' AND entity = " . ((int) $conf->entity),
					"INSERT INTO " . $this->db->prefix() . "document_model (nom, type, entity) VALUES('standard_" . strtolower($myTmpObjectKey) . "', '" . $this->db->escape(strtolower($myTmpObjectKey)) . "', " . ((int) $conf->entity) . ")",
					"DELETE FROM " . $this->db->prefix() . "document_model WHERE nom = 'generic_" . strtolower($myTmpObjectKey) . "_odt' AND type = '" . $this->db->escape(strtolower($myTmpObjectKey)) . "' AND entity = " . ((int) $conf->entity),
					"INSERT INTO " . $this->db->prefix() . "document_model (nom, type, entity) VALUES('generic_" . strtolower($myTmpObjectKey) . "_odt', '" . $this->db->escape(strtolower($myTmpObjectKey)) . "', " . ((int) $conf->entity) . ")"
				));
			}
		}

		return $this->_init($sql, $options);
	}

	/**
	 *	Function called when module is disabled.
	 *	Remove from database constants, boxes and permissions from Dolibarr database.
	 *	Data directories are not deleted
	 *
	 *	@param	string		$options	Options when enabling module ('', 'noboxes')
	 *	@return	int<-1,1>				1 if OK, <=0 if KO
	 */
	public function remove($options = '')
	{

		//COMENTAMOS DE MOMENTO PARA NO PERDER LOS HASHES.
		$this->_remove_maestros();
		$this->_remove_extra_fields();

		$this->_remove_pdf_template(); // Eliminar plantilla PDF
		$sql = array();
		return $this->_remove($sql, $options);
	}

	public function _create_maestros()
	{
		global $user;

		// Incluir las clases necesarias
		require_once __DIR__ . '/../../class/verifactufacturetype.class.php';
		require_once __DIR__ . '/../../class/verifactuclaveregimen.class.php';

		$sql = "CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . "c_verifactu_facture_types (
			rowid integer AUTO_INCREMENT PRIMARY KEY,
			code varchar(50) NOT NULL,
			label varchar(255) NOT NULL,
			active tinyint(1) DEFAULT 1
		) ENGINE=innodb;";

		$resql = $this->db->query($sql);
		if (! $resql) {
			dol_print_error($this->db);
			return -1;
		}

		//Clave que identificará el tipo de régimen del impuesto o una operación con trascendencia tributaria.
		$sql = "CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . "c_verifactu_clave_regimenes (
			rowid integer AUTO_INCREMENT PRIMARY KEY,
			code varchar(50) NOT NULL,
			label varchar(255) NOT NULL,
			active tinyint(1) DEFAULT 1
		) ENGINE=innodb;";
		$resql = $this->db->query($sql);
		if (! $resql) {
			dol_print_error($this->db);
			return -1;
		}

		//Clave de la operación sujeta y no exenta o de la operación no sujeta.
		$sql = "CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . "c_verifactu_clave_operaciones (
			rowid integer AUTO_INCREMENT PRIMARY KEY,
			code varchar(50) NOT NULL,
			label varchar(255) NOT NULL,
			active tinyint(1) DEFAULT 1
		) ENGINE=innodb;";

		$resql = $this->db->query($sql);
		if (! $resql) {
			dol_print_error($this->db);
			return -1;
		}

		//Campo que especifica la causa de exención.
		$sql = "CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . "c_verifactu_clave_exenciones (
			rowid integer AUTO_INCREMENT PRIMARY KEY,
			code varchar(50) NOT NULL,
			label varchar(255) NOT NULL,
			active tinyint(1) DEFAULT 1
		) ENGINE=innodb;";
		$resql = $this->db->query($sql);
		if (! $resql) {
			dol_print_error($this->db);
			return -1;
		}
		//Campo que especifica los estados de registros.
		$sql = "CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . "c_verifactu_registro_estados (
			rowid integer AUTO_INCREMENT PRIMARY KEY,
			code varchar(50) NOT NULL,
			label varchar(255) NOT NULL,
			active tinyint(1) DEFAULT 1
		) ENGINE=innodb;";
		$resql = $this->db->query($sql);
		if (! $resql) {
			dol_print_error($this->db);
			return -1;
		}
		//Campo que especifica los estados de registros.
		$sql = "CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . "c_verifactu_registro_operaciones (
			rowid integer AUTO_INCREMENT PRIMARY KEY,
			code varchar(50) NOT NULL,
			label varchar(255) NOT NULL,
			active tinyint(1) DEFAULT 1
		) ENGINE=innodb;";
		$resql = $this->db->query($sql);
		if (! $resql) {
			dol_print_error($this->db);
			return -1;
		}

		$sql = "CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . "c_verifactu_estado_batch (
			rowid integer AUTO_INCREMENT PRIMARY KEY,
			code varchar(50) NOT NULL,
			label varchar(255) NOT NULL,
			active tinyint(1) DEFAULT 1
		) ENGINE=innodb;";
		$resql = $this->db->query($sql);
		if (! $resql) {
			dol_print_error($this->db);
			return -1;
		}



		//CREAMOS UNA TABLA DE BATCH DE REGISTROS PARA ENVIAR A VERIFACTU
		//UN BATCH TENDRA MUCHOS REGISTROS DE FACTURAS Y SE ENVIARAN DE 1000 EN 1000

		$sql = "CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . "verifactu_batches (
			rowid integer AUTO_INCREMENT PRIMARY KEY,
			fecha timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			estado integer DEFAULT 1,
			msg_error text DEFAULT NULL,
			num_records integer DEFAULT 0,
			csv nvarchar(255) DEFAULT NULL
		) ENGINE=innodb;";

		$resql = $this->db->query($sql);
		if (! $resql) {
			dol_print_error($this->db);
			return -1;
		}

		// Crear tabla de errores
		$sql = "CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . "verifactu_errores (
			rowid integer AUTO_INCREMENT PRIMARY KEY,
			fecha timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			tipo_error varchar(50) NOT NULL,
			mensaje text NOT NULL,
			notificado tinyint(1) DEFAULT 0,
			fk_batch integer DEFAULT NULL,
			fk_registro integer DEFAULT NULL,
			datos_adicionales text DEFAULT NULL
		) ENGINE=innodb;";

		$resql = $this->db->query($sql);
		if (! $resql) {
			dol_print_error($this->db);
			return -1;
		}




		$sql = "CREATE TABLE IF NOT EXISTS " . MAIN_DB_PREFIX . "verifactu_factura_registros (
			rowid integer AUTO_INCREMENT PRIMARY KEY,
			factureid integer NOT NULL,
			hash varchar(64) DEFAULT NULL,
			hash_data text DEFAULT NULL,
			fecha timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			estado integer DEFAULT 1,
			msg_error text DEFAULT NULL,
			csv_line text DEFAULT NULL,
			operation integer DEFAULT 1,
			fk_batch integer DEFAULT NULL
		) ENGINE=innodb;";

		$resql = $this->db->query($sql);
		if (! $resql) {
			dol_print_error($this->db);
			return -1;
		}

		//añadimos este trigger a la tabla


		// Verificar si el trigger ya existe antes de crearlo
		$sql_check = "SELECT TRIGGER_NAME FROM information_schema.TRIGGERS 
					  WHERE TRIGGER_SCHEMA = DATABASE() 
					  AND TRIGGER_NAME = '" . MAIN_DB_PREFIX . "_verifactu_factura_registros_AFTER_INSERT'";
		$resql_check = $this->db->query($sql_check);
		if ($resql_check && $this->db->num_rows($resql_check) == 0) {
			$sql = "CREATE TRIGGER " . MAIN_DB_PREFIX . "_verifactu_factura_registros_AFTER_INSERT
			AFTER INSERT ON " . MAIN_DB_PREFIX . "verifactu_factura_registros
			FOR EACH ROW
			BEGIN
				UPDATE " . MAIN_DB_PREFIX . "facture_extrafields
				SET fk_verifactu_registro_estado = NEW.estado
				WHERE " . MAIN_DB_PREFIX . "facture_extrafields.fk_object = NEW.factureid;
			END";
			$resql = $this->db->query($sql);
			if (! $resql) {
				dol_print_error($this->db);
				return -1;
			}
		}

		// Verificar si el trigger de UPDATE ya existe antes de crearlo
		$sql_check = "SELECT TRIGGER_NAME FROM information_schema.TRIGGERS 
					  WHERE TRIGGER_SCHEMA = DATABASE() 
					  AND TRIGGER_NAME = '" . MAIN_DB_PREFIX . "_verifactu_factura_registros_AFTER_UPDATE'";
		$resql_check = $this->db->query($sql_check);
		if ($resql_check && $this->db->num_rows($resql_check) == 0) {
			$sql = "CREATE TRIGGER " . MAIN_DB_PREFIX . "_verifactu_factura_registros_AFTER_UPDATE
			AFTER UPDATE ON " . MAIN_DB_PREFIX . "verifactu_factura_registros
			FOR EACH ROW
			BEGIN
				UPDATE " . MAIN_DB_PREFIX . "facture_extrafields
				SET fk_verifactu_registro_estado = NEW.estado
				WHERE " . MAIN_DB_PREFIX . "facture_extrafields.fk_object = NEW.factureid;
			END";
			$resql = $this->db->query($sql);
			if (! $resql) {
				dol_print_error($this->db);
				return -1;
			}
		}

		//L10
		$claveExencion = array(
			array('code' => 'E1', 'label' => 'E1: Exenta por el artículo 20'),
			array('code' => 'E2', 'label' => 'E2: Exenta por el artículo 21'),
			array('code' => 'E3', 'label' => 'E3: Exenta por el artículo 22'),
			array('code' => 'E4', 'label' => 'E4: Exenta por el artículo 23 y 24'),
			array('code' => 'E5', 'label' => 'E5: Exenta por el artículo 25'),
			array('code' => 'E6', 'label' => 'E6: Exenta por otros'),
		);

		//L9
		$claveOperacion = array(
			array('code' => 'S1', 'label' => 'S1: Operación Sujeta y No exenta - Sin inversión del sujeto pasivo.'),
			array('code' => 'S2', 'label' => 'S2: Operación Sujeta y No exenta - Con Inversión del sujeto pasivo.'),
			array('code' => 'N1', 'label' => 'N1: Operación No Sujeta artículo 7, 14, otros.'),
			array('code' => 'N2', 'label' => 'N2: Operación No Sujeta por Reglas de localización.')
		);

		//L8
		$regimens = array(
			array('code' => '01', 'label' => '01: Operación de régimen general.'),
			array('code' => '02', 'label' => '02: Exportación.'),
			array('code' => '03', 'label' => '03: Operaciones a las que se aplique el régimen especial de bienes usados, objetos de arte, antigüedades y objetos de colección.'),
			array('code' => '04', 'label' => '04: Régimen especial del oro de inversión.'),
			array('code' => '05', 'label' => '05: Régimen especial de las agencias de viajes.'),
			array('code' => '06', 'label' => '06: Régimen especial grupo de entidades en IVA (Nivel Avanzado)'),
			array('code' => '07', 'label' => '07: Régimen especial del criterio de caja.'),
			array('code' => '08', 'label' => '08: Operaciones sujetas al IPSI  / IGIC (Impuesto sobre la Producción, los Servicios y la Importación  / Impuesto General Indirecto Canario).'),
			array('code' => '09', 'label' => '09: Facturación de las prestaciones de servicios de agencias de viaje que actúan como mediadoras en nombre y por cuenta ajena (D.A.4ª RD1619/2012)'),
			array('code' => '10', 'label' => '10: Cobros por cuenta de terceros de honorarios profesionales o de derechos derivados de la propiedad industrial,...'),
			array('code' => '11', 'label' => '11: Operaciones de arrendamiento de local de negocio.'),
			array('code' => '14', 'label' => '14: Factura con IVA pendiente de devengo en certificaciones de obra cuyo destinatario sea una Administración Pública.'),
			array('code' => '15', 'label' => '15: Factura con IVA pendiente de devengo en operaciones de tracto sucesivo.'),
			array('code' => '17', 'label' => '17: Operación acogida a alguno de los regímenes previstos en el Capítulo XI del Título IX (OSS e IOSS)'),
			array('code' => '18', 'label' => '18: Recargo de equivalencia.'),
			array('code' => '19', 'label' => '19: Operaciones de actividades incluidas en el Régimen Especial de Agricultura, Ganadería y Pesca (REAGYP)'),
			array('code' => '20', 'label' => '20: Régimen simplificado'),
		);


		$types = array(
			array('code' => 'F1', 'label' => 'F1 - Factura Estandar', 'api' => 1),
			array('code' => 'F2', 'label' => 'F2 - Factura Simplificada', 'api' => 1),
			//array('code' => 'F3', 'label' => 'F3 - Factura Recapitulativa', 'api' => 1),
			array('code' => 'R1', 'label' => 'R1 - Rectificativa Estandar', 'api' => 1),
			array('code' => 'R2', 'label' => 'R2 - Modificación de la base imponible del IVA por concurso de acreedores (art. 80 Tres LIVA)', 'api' => 1),
			array('code' => 'R3', 'label' => 'R3 - Modificación de la base imponible por crédito incobrable (art. 80 Cuatro LIVA)', 'api' => 1),
			array('code' => 'R4', 'label' => 'R4 - Factura rectificativa por otras causas distintas de las anteriores, o datos no monetarios erróneamente consignados', 'api' => 1),
			array('code' => 'R5', 'label' => 'R5 - Rectificativa Simplificada', 'api' => 1),
		);

		$registroEstados = array(
			array('code' => '1', 'label' => '1 - Sin enviar'), //1
			array('code' => '2', 'label' => '2 - Correcto'), //
			array('code' => '3', 'label' => '3 - AceptadoConErrores'),
			array('code' => '4', 'label' => '4 - Incorrecto'),
			array('code' => '5', 'label' => '5 - Error de sistema, no enviado'),
			array('code' => '6', 'label' => '6 - Enviando, generando batch de envio'),
		);

		$registroOperaciones = array(
			array('code' => '1', 'label' => '1 - Registro alta'),
			array('code' => '2', 'label' => '2 - Registro alta subsanación'),
			array('code' => '3', 'label' => '3 - Registro alta subsanación rechazada'),
		);

		$estadosBatch = array(
			array('code' => '1', 'label' => '1 - Pendiente de envío'), //1
			array('code' => '2', 'label' => '2 - Correcto'), //
			array('code' => '3', 'label' => '3 - Incorrecto'),
			array('code' => '4', 'label' => '4 - Parcialmente correcto'),
		);


		foreach ($estadosBatch as $estado) {
			// Verificar si ya existe
			$sql_check = "SELECT COUNT(*) as count FROM " . MAIN_DB_PREFIX . "c_verifactu_estado_batch WHERE code = '" . $this->db->escape($estado['code']) . "'";
			$resql_check = $this->db->query($sql_check);
			if ($resql_check) {
				$obj = $this->db->fetch_array($resql_check);
				$count = ($obj && isset($obj['count'])) ? $obj['count'] : 0;

				if ($count == 0) { // Solo crear si no existe
					$estadoObj = new VerifactuEstadoBatch($this->db);
					$estadoObj->code = $estado['code'];
					$estadoObj->label = $estado['label'];
					$estadoObj->active = 1;
					$estadoObj->create($user);
				}
			}
		}


		foreach ($types as $type) {
			// Verificar si ya existe
			$sql_check = "SELECT COUNT(*) FROM " . MAIN_DB_PREFIX . "c_verifactu_facture_types WHERE code = '" . $this->db->escape($type['code']) . "'";
			$resql_check = $this->db->query($sql_check);
			$obj = $this->db->fetch_row($resql_check);
			if ($obj[0] == 0) { // Solo crear si no existe
				$typeObj = new VerifactuFactureType($this->db);
				$typeObj->code = $type['code'];
				$typeObj->label = $type['label'];
				$typeObj->active = 1;
				$typeObj->create($user);
			}
		}

		foreach ($regimens as $regimen) {
			// Verificar si ya existe
			$sql_check = "SELECT COUNT(*) as count FROM " . MAIN_DB_PREFIX . "c_verifactu_clave_regimenes WHERE code = '" . $this->db->escape($regimen['code']) . "'";
			$resql_check = $this->db->query($sql_check);
			if ($resql_check) {
				$obj = $this->db->fetch_array($resql_check);
				$count = ($obj && isset($obj['count'])) ? $obj['count'] : 0;

				if ($count == 0) { // Solo crear si no existe
					$regimenObj = new VerifactuClaveRegimen($this->db);
					$regimenObj->code = $regimen['code'];
					$regimenObj->label = $regimen['label'];
					$regimenObj->active = 1;
					$regimenObj->create($user);
				}
			}
		}

		foreach ($claveOperacion as $operacion) {
			// Verificar si ya existe
			$sql_check = "SELECT COUNT(*) as count FROM " . MAIN_DB_PREFIX . "c_verifactu_clave_operaciones WHERE code = '" . $this->db->escape($operacion['code']) . "'";
			$resql_check = $this->db->query($sql_check);
			if ($resql_check) {
				$obj = $this->db->fetch_array($resql_check);
				$count = ($obj && isset($obj['count'])) ? $obj['count'] : 0;

				if ($count == 0) { // Solo crear si no existe
					$operacionObj = new VerifactuClaveOperacion($this->db);
					$operacionObj->code = $operacion['code'];
					$operacionObj->label = $operacion['label'];
					$operacionObj->active = 1;
					$operacionObj->create($user);
				}
			}
		}

		foreach ($claveExencion as $exencion) {
			// Verificar si ya existe
			$sql_check = "SELECT COUNT(*) as count FROM " . MAIN_DB_PREFIX . "c_verifactu_clave_exenciones WHERE code = '" . $this->db->escape($exencion['code']) . "'";
			$resql_check = $this->db->query($sql_check);
			if ($resql_check) {
				$obj = $this->db->fetch_array($resql_check);
				$count = ($obj && isset($obj['count'])) ? $obj['count'] : 0;

				if ($count == 0) { // Solo crear si no existe
					$exencionObj = new VerifactuClaveExencion($this->db);
					$exencionObj->code = $exencion['code'];
					$exencionObj->label = $exencion['label'];
					$exencionObj->active = 1;
					$exencionObj->create($user);
				}
			}
		}

		foreach ($registroEstados as $estado) {
			// Verificar si ya existe
			$sql_check = "SELECT COUNT(*) as count FROM " . MAIN_DB_PREFIX . "c_verifactu_registro_estados WHERE code = '" . $this->db->escape($estado['code']) . "'";
			$resql_check = $this->db->query($sql_check);
			if ($resql_check) {
				$obj = $this->db->fetch_array($resql_check);
				$count = ($obj && isset($obj['count'])) ? $obj['count'] : 0;

				if ($count == 0) { // Solo crear si no existe
					$estadoObj = new VerifactuRegistroEstado($this->db);
					$estadoObj->code = $estado['code'];
					$estadoObj->label = $estado['label'];
					$estadoObj->active = 1;
					$estadoObj->create($user);
				}
			}
		}

		foreach ($registroOperaciones as $operacion) {
			// Verificar si ya existe
			$sql_check = "SELECT COUNT(*) as count FROM " . MAIN_DB_PREFIX . "c_verifactu_registro_operaciones WHERE code = '" . $this->db->escape($operacion['code']) . "'";
			$resql_check = $this->db->query($sql_check);
			if ($resql_check) {
				$obj = $this->db->fetch_array($resql_check);
				$count = ($obj && isset($obj['count'])) ? $obj['count'] : 0;

				if ($count == 0) { // Solo crear si no existe
					$operacionObj = new VerifactuRegistroOperacion($this->db);
					$operacionObj->code = $operacion['code'];
					$operacionObj->label = $operacion['label'];
					$operacionObj->active = 1;
					$operacionObj->create($user);
				}
			}
		}

		//mirar si en la tabla llx_const existen las variables VERIFACTU_URL_ENDPOINT_PROD y VERIFACTU_URL_ENDPOINT_DEV y sino crearlas, tipo chain created at ahora
		$sql = "SELECT COUNT(*) as count FROM " . MAIN_DB_PREFIX . "const WHERE name IN ('VERIFACTU_URL_ENDPOINT_PROD', 'VERIFACTU_URL_ENDPOINT_DEV')";
		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_array($resql);
			$count = ($obj && isset($obj['count'])) ? $obj['count']
				: 0;
			if ($count < 2) {
				$sql = "INSERT INTO " . MAIN_DB_PREFIX . "const (name, value, type, entity, note, visible, tms) VALUES ";
				$values = array();
				if (
					strpos($sql, 'VERIFACTU_URL_ENDPOINT_PROD') === false
				) {
					$values[] = "('VERIFACTU_URL_ENDPOINT_PROD', 'https://www1.agenciatributaria.gob.es', 'chaine', 1, 'Endpoint de producción de Verifactu', 0, NOW())";
				}
				if (
					strpos($sql, 'VERIFACTU_URL_ENDPOINT_DEV') === false
				) {
					$values[] = "('VERIFACTU_URL_ENDPOINT_DEV', 'https://prewww1.aeat.es', 'chaine', 1, 'Endpoint de desarrollo de Verifactu', 0, NOW())";
				}
				if (count($values) > 0) {
					$sql .= implode(", ", $values);
					$resql = $this->db->query($sql);
					if (! $resql) {
						dol_print_error($this->db);
						return -1;
					}
				}
			}
		} else {
			dol_print_error($this->db);
			return -1;
		}

		return 1;
	}

	public function _remove_maestros()
	{
		// $sql = "DROP TABLE IF EXISTS " . MAIN_DB_PREFIX . "c_verifactu_facture_types";

		// $resql = $this->db->query($sql);
		// if (! $resql) {
		// 	dol_print_error($this->db);
		// 	return -1;
		// }

		// $sql = "DROP TABLE IF EXISTS " . MAIN_DB_PREFIX . "verifactu_last_hash";
		// $resql = $this->db->query($sql);
		// if (! $resql) {
		// 	dol_print_error($this->db);
		// 	return -1;
		// }

		// $sql = "DROP TABLE IF EXISTS " . MAIN_DB_PREFIX . "c_verifactu_clave_regimenes";
		// $resql = $this->db->query($sql);
		// if (! $resql) {
		// 	dol_print_error($this->db);
		// 	return -1;
		// }
		// $sql = "DROP TABLE IF EXISTS " . MAIN_DB_PREFIX . "c_verifactu_clave_operaciones";
		// $resql = $this->db->query($sql);
		// if (! $resql) {
		// 	dol_print_error($this->db);
		// 	return -1;
		// }
		// $sql = "DROP TABLE IF EXISTS " . MAIN_DB_PREFIX . "c_verifactu_clave_exenciones";
		// $resql = $this->db->query($sql);
		// if (! $resql) {
		// 	dol_print_error($this->db);
		// 	return -1;
		// }

		// $sql = "DROP TABLE IF EXISTS " . MAIN_DB_PREFIX . "c_verifactu_registro_estados";
		// $resql = $this->db->query($sql);
		// if (! $resql) {
		// 	dol_print_error($this->db);
		// 	return -1;
		// }

		// $sql = "DROP TABLE IF EXISTS " . MAIN_DB_PREFIX . "verifactu_factura_registros";
		// $resql = $this->db->query($sql);
		// if (! $resql) {
		// 	dol_print_error($this->db);
		// 	return -1;
		// }

		// $sql = "DROP TABLE IF EXISTS " . MAIN_DB_PREFIX . "c_verifactu_registro_operaciones";
		// $resql = $this->db->query($sql);
		// if (! $resql) {
		// 	dol_print_error($this->db);
		// 	return -1;
		// }

		// $sql = "DROP TABLE IF EXISTS " . MAIN_DB_PREFIX . "c_verifactu_estado_batch";
		// $resql = $this->db->query($sql);
		// if (! $resql) {
		// 	dol_print_error($this->db);
		// 	return -1;
		// }

		// $sql = "DROP TABLE IF EXISTS " . MAIN_DB_PREFIX . "verifactu_batches";
		// $resql = $this->db->query($sql);
		// if (! $resql) {
		// 	dol_print_error($this->db);
		// 	return -1;
		// }



		return 1;
	}

	public function _add_extra_fields()
	{
		include_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
		$extrafields = new ExtraFields($this->db);
		$db = $this->db;

		// Buscar ID del régimen con código '01'
		$sql_regimen = "SELECT rowid FROM " . MAIN_DB_PREFIX . "c_verifactu_clave_regimenes WHERE code = '01' AND active = 1 LIMIT 1";
		$res_regimen = $this->db->query($sql_regimen);
		$default_regimen = '';
		if ($res_regimen && $this->db->num_rows($res_regimen) > 0) {
			$obj_regimen = $this->db->fetch_object($res_regimen);
			$default_regimen = $obj_regimen->rowid;
			$this->db->free($res_regimen);
		}

		// Buscar ID de la operación con código 'S1'
		$sql_operacion = "SELECT rowid FROM " . MAIN_DB_PREFIX . "c_verifactu_clave_operaciones WHERE code = 'S1' AND active = 1 LIMIT 1";
		$res_operacion = $this->db->query($sql_operacion);
		$default_operacion = '';
		if ($res_operacion && $this->db->num_rows($res_operacion) > 0) {
			$obj_operacion = $this->db->fetch_object($res_operacion);
			$default_operacion = $obj_operacion->rowid;
			$this->db->free($res_operacion);
		}

		// Verificar si ya existe antes de crear
		$existing = $extrafields->fetch_name_optionals_label('facture');
		if (!isset($existing['fk_facture_type'])) {
			// CREAR extrafield nuevo
			$result1 = $extrafields->addExtraField(
				'fk_facture_type',                     // $attrname
				'Tipo de factura',                     // $label
				'sellist',                             // $type
				-10,                                   // $pos (posición negativa para aparecer al inicio)
				'',                                    // $size
				'facture',                             // $elementtype
				0,                                     // $unique
				1,                                     // $required (obligatorio)
				'',                                    // $default_value
				serialize([                            // $param
					"options" => [
						"c_verifactu_facture_types:label:rowid::(active:=:1)" => null
					]
				]),
				0,                                     // $alwayseditable
				'',                                    // $perms
				1,                                     // $list
				'Seleccione el tipo de factura según Verifactu', // $help
				'',                                    // $computed
				'',                                    // $entity
				'',                                    // $langfile
				'1',                                   // $enabled
				0,                                     // $totalizable
				1                                      // $printable
			);
		} else {
			// ACTUALIZAR extrafield existente para cambiar la posición
			$sql = "UPDATE " . MAIN_DB_PREFIX . "extrafields
					SET pos = -10,
						label = 'Tipo de factura',
						required = 1
					WHERE name = 'fk_facture_type'
					AND elementtype = 'facture'";
			$this->db->query($sql);
			dol_syslog("Verifactu: Actualizada posición del extrafield fk_facture_type a -10");
		}

		$existing = $extrafields->fetch_name_optionals_label('facture');
		if (!isset($existing['fk_verifactu__registro_estado'])) {
			// CREAR extrafield nuevo
			$result1 = $extrafields->addExtraField(
				'fk_verifactu_registro_estado',                     // $attrname
				'Estado de Verifactu',                     // $label
				'sellist',                             // $type
				-11,                                   // $pos (posición negativa para aparecer al inicio)
				'',                                    // $size
				'facture',                             // $elementtype
				0,                                     // $unique
				0,                                     // $required (obligatorio)
				'',                                    // $default_value
				serialize([                            // $param
					"options" => [
						"c_verifactu_registro_estados:label:rowid::(active:=:1)" => null
					]
				]),
				0,                                     // $alwayseditable (0 = no siempre editable)
				'',                                    // $perms
				1,                                     // $list
				'Seleccione el estado de factura según el último registro Verifactu', // $help
				'',                                    // $computed
				'',                                    // $entity
				'',                                    // $langfile
				'-1',                                  // no editable
				0,                                     // $totalizable
				0                                      // $printable
			);
		} else {
			// ACTUALIZAR extrafield existente para cambiar la posición
			$sql = "UPDATE " . MAIN_DB_PREFIX . "extrafields
					SET pos = -11,
						label = 'Estado de Verifactu',
						required = 1,
						enabled = '-1'
					WHERE name = 'fk_verifactu_registro_estado'
					AND elementtype = 'facture'";
			$this->db->query($sql);
			dol_syslog("Verifactu: Actualizada posición del extrafield fk_verifactu_registro_estado a -11 y configurado como solo lectura en facturas validadas");
		}

		// Crear campos extra para almacenar los datos inmutables del cliente en cabecera de factura
		$existing = $extrafields->fetch_name_optionals_label('facture');
		$customerSnapshotFields = array(
			'verifactu_client_name' => array(
				'label' => 'Nombre cliente Verifactu',
				'pos' => -30,
				'size' => 255,
				'help' => 'Nombre legal del cliente en el momento de la validación.',
			),
			'verifactu_client_vat' => array(
				'label' => 'CIF/NIF cliente Verifactu',
				'pos' => -29,
				'size' => 50,
				'help' => 'Documento fiscal del cliente capturado durante la validación.',
			),
			'verifactu_client_address' => array(
				'label' => 'Dirección cliente Verifactu',
				'pos' => -28,
				'size' => 255,
				'help' => 'Dirección fiscal del cliente en la fecha de emisión.',
			),
			'verifactu_client_zip' => array(
				'label' => 'CP cliente Verifactu',
				'pos' => -27,
				'size' => 20,
				'help' => 'Código postal del cliente en la fecha de emisión.',
			),
			'verifactu_client_town' => array(
				'label' => 'Población cliente Verifactu',
				'pos' => -26,
				'size' => 150,
				'help' => 'Municipio del cliente en la fecha de emisión.',
			),
			'verifactu_client_state' => array(
				'label' => 'Provincia cliente Verifactu',
				'pos' => -25,
				'size' => 150,
				'help' => 'Provincia o estado del cliente en la fecha de emisión.',
			),
			'verifactu_client_country' => array(
				'label' => 'País cliente Verifactu',
				'pos' => -24,
				'size' => 150,
				'help' => 'País del cliente en la fecha de emisión.',
			),
			'verifactu_client_country_code' => array(
				'label' => 'Código país cliente Verifactu',
				'pos' => -23,
				'size' => 10,
				'help' => 'Código ISO del país del cliente capturado durante la validación.',
			),
		);

		foreach ($customerSnapshotFields as $fieldName => $meta) {
			if (!isset($existing[$fieldName])) {
				$resCreate = $extrafields->addExtraField(
					$fieldName,
					$meta['label'],
					'varchar',
					$meta['pos'],
					$meta['size'],
					'facture',
					0,
					0,
					'',
					'',
					0,
					'',
					0,
					$meta['help'],
					'',
					'',
					'',
					'-1',
					0,
					0
				);
				if ($resCreate < 0) {
					return -1;
				}
			} else {
				$labelSql = $db->escape($meta['label']);
				$helpSql = $db->escape($meta['help']);
				$fieldSql = $db->escape($fieldName);
				$sql = "UPDATE " . $db->prefix() . "extrafields SET label = '" . $labelSql . "', type = 'varchar', size = " . ((int) $meta['size']) . ", pos = " . ((int) $meta['pos']) . ", help = '" . $helpSql . "', enabled = '-1', printable = 1 WHERE name = '" . $fieldSql . "' AND elementtype = 'facture'";
				$resUpdate = $db->query($sql);
				if (!$resUpdate) {
					return -1;
				}
			}
		}



		// Verificar los resultados de la creación de los nuevos campos
		if (isset($result1) && $result1 < 0) {
			return -1;
		}


		// Añadir campos extra para líneas de facturas (facturedet)
		$existing_det = $extrafields->fetch_name_optionals_label('facturedet');

		// Campo fk_clave_regimen
		if (!isset($existing_det['fk_clave_regimen'])) {
			$result6 = $extrafields->addExtraField(
				'fk_clave_regimen',                    // $attrname
				'Clave Régimen',                      // $label
				'sellist',                            // $type
				10,                                   // $pos
				'',                                   // $size
				'facturedet',                         // $elementtype
				0,                                    // $unique
				1,                                    // $required
				$default_regimen,  // ← Usar el ID encontrado dinámicamente
				serialize([                           // $param
					"options" => [
						"c_verifactu_clave_regimenes:label:rowid::(active:=:1)" => null
					]
				]),
				1,                                    // $alwayseditable
				'',                                   // $perms
				1,                                    // $list
				'Seleccione la clave de régimen según Verifactu', // $help
				'',                                   // $computed
				'',                                   // $entity
				'',                                   // $langfile
				'1',                                  // $enabled
				0,                                    // $totalizable
				1                                     // $printable
			);
		}

		// Campo fk_clave_operacion
		if (!isset($existing_det['fk_clave_operacion'])) {
			$result7 = $extrafields->addExtraField(
				'fk_clave_operacion',                  // $attrname
				'Clave Operación',                    // $label
				'sellist',                            // $type
				20,                                   // $pos
				'',                                   // $size
				'facturedet',                         // $elementtype
				0,                                    // $unique
				0,                                    // $required
				$default_operacion,  // ← Usar el ID encontrado dinámicamente
				serialize([                           // $param
					"options" => [
						"c_verifactu_clave_operaciones:label:rowid::(active:=:1)" => null
					]
				]),
				1,                                    // $alwayseditable
				'',                                   // $perms
				1,                                    // $list
				'Seleccione la clave de operación según Verifactu', // $help
				'',                                   // $computed
				'',                                   // $entity
				'',                                   // $langfile
				'1',                                  // $enabled
				0,                                    // $totalizable
				1                                     // $printable
			);
		}

		// Campo fk_clave_exencion
		if (!isset($existing_det['fk_clave_exencion'])) {
			$result8 = $extrafields->addExtraField(
				'fk_clave_exencion',                   // $attrname

				'Clave Exención',                     // $label
				'sellist',                            // $type
				30,                                   // $pos
				'',                                   // $size
				'facturedet',                         // $elementtype
				0,                                    // $unique
				0,                                    // $required
				'',                                   // $default_value
				serialize([                           // $param
					"options" => [
						"c_verifactu_clave_exenciones:label:rowid::(active:=:1)" => null
					]
				]),
				1,                                    // $alwayseditable
				'',                                   // $perms
				1,                                    // $list
				'Seleccione la clave de exención según Verifactu', // $help
				'',                                   // $computed
				'',                                   // $entity
				'',                                   // $langfile
				'1',                                  // $enabled
				0,                                    // $totalizable
				1                                     // $printable
			);
		}

		// Verificar los resultados de la creación de los nuevos campos de líneas
		if (isset($result6) && $result6 < 0) {
			return -1;
		}

		if (isset($result7) && $result7 < 0) {
			return -1;
		}

		if (isset($result8) && $result8 < 0) {
			return -1;
		}

		return 1;
	}

	/**
	 * Actualizar posiciones de extrafields para asegurar el orden correcto
	 */
	public function _update_extrafields_positions()
	{

		return 1;
	}

	/**
	 * Registrar la plantilla PDF de Verifactu en la base de datos
	 */
	public function _register_pdf_template()
	{
		global $conf;

		// Registrar la plantilla de factura Verifactu
		$sql = "DELETE FROM " . $this->db->prefix() . "document_model WHERE nom = 'verifactu' AND type = 'facture' AND entity = " . ((int) $conf->entity);
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_print_error($this->db);
			return -1;
		}

		$sql = "INSERT INTO " . $this->db->prefix() . "document_model (nom, type, entity) VALUES('verifactu', 'facture', " . ((int) $conf->entity) . ")";
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_print_error($this->db);
			return -1;
		}

		return 1;
	}

	/**
	 * Eliminar la plantilla PDF de Verifactu de la base de datos
	 */
	public function _remove_pdf_template()
	{
		global $conf;

		// Eliminar la plantilla de factura Verifactu
		$sql = "DELETE FROM " . $this->db->prefix() . "document_model WHERE nom = 'verifactu' AND type = 'facture' AND entity = " . ((int) $conf->entity);
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_print_error($this->db);
			return -1;
		}

		return 1;
	}

	public function _remove_extra_fields()
	{
		include_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
		// $extrafields = new ExtraFields($this->db);

		// // Eliminar campo fk_facture_type
		// $result1 = $extrafields->delete('fk_facture_type', 'facture');
		// if ($result1 < 0) {
		// 	return -1;
		// }


		// // Eliminar campos de líneas de facturas (facturedet)
		// $result6 = $extrafields->delete('fk_clave_regimen', 'facturedet');
		// if ($result6 < 0) {
		// 	return -1;
		// }

		// $result7 = $extrafields->delete('fk_clave_operacion', 'facturedet');
		// if ($result7 < 0) {
		// 	return -1;
		// }

		// $result8 = $extrafields->delete('fk_clave_exencion', 'facturedet');
		// if ($result8 < 0) {
		// 	return -1;
		// }

		// $result9 = $extrafields->delete('fk_verifactu_registro_estado', 'facture');
		// if ($result9 < 0) {
		// 	return -1;
		// }

		// $customerSnapshotFields = array(
		// 	'verifactu_client_name',
		// 	'verifactu_client_vat',
		// 	'verifactu_client_address',
		// 	'verifactu_client_zip',
		// 	'verifactu_client_town',
		// 	'verifactu_client_state',
		// 	'verifactu_client_country',
		// 	'verifactu_client_country_code',
		// );

		// foreach ($customerSnapshotFields as $fieldName) {
		// 	$resultDelete = $extrafields->delete($fieldName, 'facture');
		// 	if ($resultDelete < 0 && $extrafields->error != 'ErrorFieldNotFound') {
		// 		return -1;
		// 	}
		// }


		return 1;
	}
}
