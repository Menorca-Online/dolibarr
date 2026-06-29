<?php
/* Copyright (C) 2026 Menorca Online S.L
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    menorcaonsentry/admin/setup.php
 * \ingroup menorcaonsentry
 * \brief   MenorcaOnSentry setup page.
 */

// Load Dolibarr environment.
$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
	$res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/main.inc.php';
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)).'/main.inc.php')) {
	$res = @include substr($tmp, 0, ($i + 1)).'/main.inc.php';
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))).'/main.inc.php')) {
	$res = @include dirname(substr($tmp, 0, ($i + 1))).'/main.inc.php';
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

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/menorcaonsentry/lib/menorcaonsentry.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/menorcaonsentry/lib/sentry.lib.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array('admin', 'menorcaonsentry@menorcaonsentry'));
$hookmanager->initHooks(array('menorcaonsentrysetup', 'globalsetup'));

if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$error = 0;
$eventId = '';

/**
 * @param string $dsn DSN
 * @return string
 */
function menorcaonsentryMaskDsnForHtml($dsn)
{
	if ($dsn === '') {
		return '';
	}

	$parts = parse_url($dsn);
	if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
		return '********';
	}

	$user = isset($parts['user']) ? $parts['user'] : '';
	if ($user !== '') {
		$user = substr($user, 0, 6).'********';
	}

	$path = isset($parts['path']) ? $parts['path'] : '';
	return $parts['scheme'].'://'.$user.'@'.$parts['host'].$path;
}

/*
 * Actions
 */
if ($action === 'save') {
	$postedDsn = GETPOST('MENORCAON_SENTRY_DSN', 'restricthtml');
	$constants = array(
		'MENORCAON_SENTRY_DSN' => ($postedDsn === '' ? getDolGlobalString('MENORCAON_SENTRY_DSN') : $postedDsn),
		'MENORCAON_SENTRY_ENVIRONMENT' => GETPOST('MENORCAON_SENTRY_ENVIRONMENT', 'alphanohtml'),
		'MENORCAON_SENTRY_RELEASE' => GETPOST('MENORCAON_SENTRY_RELEASE', 'alphanohtml'),
		'MENORCAON_SENTRY_COMPANY' => GETPOST('MENORCAON_SENTRY_COMPANY', 'alphanohtml'),
		'MENORCAON_SENTRY_PROJECT' => GETPOST('MENORCAON_SENTRY_PROJECT', 'alphanohtml'),
		'MENORCAON_SENTRY_APPLICATION' => GETPOST('MENORCAON_SENTRY_APPLICATION', 'alphanohtml'),
	);

	if ($constants['MENORCAON_SENTRY_ENVIRONMENT'] === '') {
		$constants['MENORCAON_SENTRY_ENVIRONMENT'] = 'production';
	}
	if ($constants['MENORCAON_SENTRY_PROJECT'] === '') {
		$constants['MENORCAON_SENTRY_PROJECT'] = 'dolibarr';
	}
	if ($constants['MENORCAON_SENTRY_APPLICATION'] === '') {
		$constants['MENORCAON_SENTRY_APPLICATION'] = 'erp';
	}

	foreach ($constants as $name => $value) {
		$res = dolibarr_set_const($db, $name, $value, 'chaine', 0, '', $conf->entity);
		if (!($res > 0)) {
			$error++;
		} else {
			$conf->global->{$name} = $value;
		}
	}

	if (!$error) {
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	} else {
		setEventMessages($langs->trans('Error'), null, 'errors');
	}
}

if ($action === 'test') {
	try {
		$eventId = (string) menorcaonSentryCaptureException(new RuntimeException('MenorcaOnSentry test exception'), array(
			'module' => 'menorcaonsentry',
			'operation' => 'admin_test',
		));
		if ($eventId !== '') {
			setEventMessages($langs->trans('MenorcaOnSentryTestSent', $eventId), null, 'mesgs');
		} else {
			setEventMessages($langs->trans('MenorcaOnSentryTestNotSent'), null, 'warnings');
		}
	} catch (Throwable $e) {
		dol_syslog('MenorcaOnSentry: test capture failed: '.$e->getMessage(), LOG_ERR);
		setEventMessages($langs->trans('MenorcaOnSentryTestFailed'), null, 'errors');
	}
}

/*
 * View
 */
$form = new Form($db);
$title = 'MenorcaOnSentrySetup';

llxHeader('', $langs->trans($title), '', '', 0, 0, '', '', '', 'mod-menorcaonsentry page-admin');

$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

$head = menorcaonsentryAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans($title), -1, 'fa-bug');

print '<span class="opacitymedium">'.$langs->trans('MenorcaOnSentrySetupPage').'</span><br><br>';

$autoload = DOL_DOCUMENT_ROOT.'/custom/menorcaonsentry/vendor/autoload.php';
$vendorExists = file_exists($autoload);
$sdkAvailable = false;
if ($vendorExists) {
	try {
		require_once $autoload;
		$sdkAvailable = function_exists('\Sentry\init');
	} catch (Throwable $e) {
		dol_syslog('MenorcaOnSentry: setup SDK check failed: '.$e->getMessage(), LOG_WARNING);
	}
}

print load_fiche_titre($langs->trans('MenorcaOnSentryStatus'), '', '');
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Parameter').'</th><th>'.$langs->trans('Value').'</th></tr>';
print '<tr class="oddeven"><td>vendor/autoload.php</td><td>'.yn($vendorExists).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('MenorcaOnSentrySdkAvailable').'</td><td>'.yn($sdkAvailable).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('MenorcaOnSentryConfiguredDsn').'</td><td>'.dol_escape_htmltag(menorcaonsentryMaskDsnForHtml(getDolGlobalString('MENORCAON_SENTRY_DSN'))).'</td></tr>';
if ($eventId !== '') {
	print '<tr class="oddeven"><td>Event ID</td><td>'.dol_escape_htmltag($eventId).'</td></tr>';
}
print '</table>';
print '</div><br>';

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Parameter').'</th><th>'.$langs->trans('Value').'</th><th>'.$langs->trans('Description').'</th></tr>';

$fields = array(
	'MENORCAON_SENTRY_DSN' => array('type' => 'password', 'default' => '', 'help' => 'MenorcaOnSentryDsnHelp'),
	'MENORCAON_SENTRY_ENVIRONMENT' => array('type' => 'text', 'default' => 'production', 'help' => 'MenorcaOnSentryEnvironmentHelp'),
	'MENORCAON_SENTRY_RELEASE' => array('type' => 'text', 'default' => '', 'help' => 'MenorcaOnSentryReleaseHelp'),
	'MENORCAON_SENTRY_COMPANY' => array('type' => 'text', 'default' => '', 'help' => 'MenorcaOnSentryCompanyHelp'),
	'MENORCAON_SENTRY_PROJECT' => array('type' => 'text', 'default' => 'dolibarr', 'help' => 'MenorcaOnSentryProjectHelp'),
	'MENORCAON_SENTRY_APPLICATION' => array('type' => 'text', 'default' => 'erp', 'help' => 'MenorcaOnSentryApplicationHelp'),
);

foreach ($fields as $name => $field) {
	$value = getDolGlobalString($name, $field['default']);
	$inputValue = ($name === 'MENORCAON_SENTRY_DSN') ? '' : $value;
	$placeholder = ($name === 'MENORCAON_SENTRY_DSN') ? menorcaonsentryMaskDsnForHtml($value) : '';
	print '<tr class="oddeven">';
	print '<td><label for="'.$name.'">'.$langs->trans($name).'</label></td>';
	print '<td><input class="minwidth500" id="'.$name.'" name="'.$name.'" type="'.$field['type'].'" value="'.dol_escape_htmltag($inputValue).'" placeholder="'.dol_escape_htmltag($placeholder).'"></td>';
	print '<td class="opacitymedium">'.$langs->trans($field['help']).'</td>';
	print '</tr>';
}

print '</table>';
print '</div>';
print $form->buttonsSaveCancel('Save', '');
print '</form>';

print '<div class="tabsAction">';
if ($vendorExists && $sdkAvailable && getDolGlobalString('MENORCAON_SENTRY_DSN')) {
	print '<a class="butAction" href="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?action=test&token='.newToken().'">'.$langs->trans('MenorcaOnSentrySendTest').'</a>';
} else {
	print '<span class="butActionRefused classfortooltip" title="'.dol_escape_htmltag($langs->trans('MenorcaOnSentryTestDisabledHelp')).'">'.$langs->trans('MenorcaOnSentrySendTest').'</span>';
}
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
