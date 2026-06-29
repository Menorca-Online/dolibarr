<?php
/* Copyright (C) 2026 Menorca Online S.L
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    menorcaonsentry/lib/sentry.lib.php
 * \ingroup menorcaonsentry
 * \brief   Defensive Sentry bootstrap and capture helpers for Dolibarr.
 */

if (!defined('MENORCAON_SENTRY_MODULE_PATH')) {
	define('MENORCAON_SENTRY_MODULE_PATH', dirname(__DIR__));
}

/**
 * Return true when Sentry is configured and its SDK can be loaded.
 *
 * @return bool
 */
function menorcaonSentryIsEnabled()
{
	return (bool) getDolGlobalString('MENORCAON_SENTRY_DSN') && file_exists(MENORCAON_SENTRY_MODULE_PATH.'/vendor/autoload.php');
}

/**
 * Initialize Sentry once for the current PHP request/process.
 *
 * @return bool
 */
function menorcaonSentryInit()
{
	static $initialized = false;
	static $initializing = false;
	static $failed = false;

	if ($initialized) {
		return true;
	}
	if ($initializing || $failed) {
		return false;
	}

	$dsn = getDolGlobalString('MENORCAON_SENTRY_DSN');
	if (empty($dsn)) {
		return false;
	}

	$autoload = MENORCAON_SENTRY_MODULE_PATH.'/vendor/autoload.php';
	if (!file_exists($autoload)) {
		dol_syslog('MenorcaOnSentry: vendor/autoload.php not found. Run composer install inside htdocs/custom/menorcaonsentry.', LOG_WARNING);
		$failed = true;
		return false;
	}

	try {
		$initializing = true;
		require_once $autoload;

		if (!function_exists('\Sentry\init')) {
			dol_syslog('MenorcaOnSentry: Sentry SDK is not available after loading vendor/autoload.php.', LOG_WARNING);
			$failed = true;
			return false;
		}

		\Sentry\init(array(
			'dsn' => $dsn,
			'environment' => getDolGlobalString('MENORCAON_SENTRY_ENVIRONMENT', 'production'),
			'release' => getDolGlobalString('MENORCAON_SENTRY_RELEASE'),
			'send_default_pii' => false,
			'traces_sample_rate' => 0.0,
			'before_send' => 'menorcaonSentryBeforeSend',
		));

		menorcaonSentryConfigureGlobalScope();
		menorcaonSentryRegisterHandlers();

		$initialized = true;
		return true;
	} catch (Throwable $e) {
		dol_syslog('MenorcaOnSentry: initialization failed: '.$e->getMessage(), LOG_ERR);
		$failed = true;
		return false;
	} finally {
		$initializing = false;
	}
}

/**
 * Capture an exception manually without leaking operation-specific context globally.
 *
 * @param Throwable    $exception Exception to capture
 * @param array<string,mixed> $context   Extra context for this capture only
 * @return mixed Event id or null
 */
function menorcaonSentryCaptureException(Throwable $exception, array $context = array())
{
	if (!menorcaonSentryInit()) {
		return null;
	}

	$fingerprint = menorcaonSentryFingerprintThrowable($exception);
	if (menorcaonSentryWasCaptured($fingerprint)) {
		return null;
	}
	menorcaonSentryMarkCaptured($fingerprint);

	try {
		return \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($exception, $context) {
			menorcaonSentryApplyContextToScope($scope, $context);
			return \Sentry\captureException($exception);
		});
	} catch (Throwable $e) {
		dol_syslog('MenorcaOnSentry: captureException failed: '.$e->getMessage(), LOG_ERR);
		return null;
	}
}

/**
 * Capture a message manually. dol_syslog is intentionally not wired here.
 *
 * @param string       $message Message to capture
 * @param array<string,mixed> $context Extra context for this capture only
 * @return mixed Event id or null
 */
function menorcaonSentryCaptureMessage(string $message, array $context = array())
{
	if (!menorcaonSentryInit()) {
		return null;
	}

	try {
		return \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($message, $context) {
			menorcaonSentryApplyContextToScope($scope, $context);
			return \Sentry\captureMessage($message);
		});
	} catch (Throwable $e) {
		dol_syslog('MenorcaOnSentry: captureMessage failed: '.$e->getMessage(), LOG_ERR);
		return null;
	}
}

/**
 * Configure tags/user/request context that is safe for all captures in this request.
 *
 * @return void
 */
function menorcaonSentryConfigureGlobalScope()
{
	try {
		if (!function_exists('\Sentry\configureScope')) {
			return;
		}

		\Sentry\configureScope(function (\Sentry\State\Scope $scope) {
			global $conf, $user;

			$scope->setTag('menorcaon.company', getDolGlobalString('MENORCAON_SENTRY_COMPANY'));
			$scope->setTag('menorcaon.project', getDolGlobalString('MENORCAON_SENTRY_PROJECT', 'dolibarr'));
			$scope->setTag('menorcaon.application', getDolGlobalString('MENORCAON_SENTRY_APPLICATION', 'erp'));

			if (isset($conf->entity)) {
				$scope->setTag('dolibarr.entity', (string) $conf->entity);
			}
			if (!empty($_SERVER['HTTP_HOST'])) {
				$scope->setTag('http.host', menorcaonSentrySanitizeHost($_SERVER['HTTP_HOST']));
			}

			if (is_object($user) && !empty($user->id)) {
				$scope->setUser(array(
					'id' => (string) $user->id,
					'username' => (string) $user->login,
				));
			}

			$scope->setContext('dolibarr', array_filter(array(
				'entity' => isset($conf->entity) ? (string) $conf->entity : null,
				'user_id' => (is_object($user) && !empty($user->id)) ? (string) $user->id : null,
				'version' => defined('DOL_VERSION') ? DOL_VERSION : null,
			), 'menorcaonSentryKeepContextValue'));

			$scope->setContext('request', array_filter(array(
				'host' => !empty($_SERVER['HTTP_HOST']) ? menorcaonSentrySanitizeHost($_SERVER['HTTP_HOST']) : null,
				'method' => !empty($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : null,
				'uri' => menorcaonSentrySanitizeUri(!empty($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''),
				'user_id' => (is_object($user) && !empty($user->id)) ? (string) $user->id : null,
			), 'menorcaonSentryKeepContextValue'));
		});
	} catch (Throwable $e) {
		dol_syslog('MenorcaOnSentry: scope configuration failed: '.$e->getMessage(), LOG_ERR);
	}
}

/**
 * Register exception and shutdown handlers once.
 *
 * @return void
 */
function menorcaonSentryRegisterHandlers()
{
	static $registered = false;

	if ($registered) {
		return;
	}
	$registered = true;

	$previousExceptionHandler = set_exception_handler(function (Throwable $exception) use (&$previousExceptionHandler) {
		menorcaonSentryCaptureException($exception, array('handler' => 'global_exception'));

		if (is_callable($previousExceptionHandler)) {
			try {
				call_user_func($previousExceptionHandler, $exception);
			} catch (Throwable $e) {
				dol_syslog('MenorcaOnSentry: previous exception handler failed: '.$e->getMessage(), LOG_ERR);
			}
		}
	});

	register_shutdown_function(function () {
		$error = error_get_last();
		if (!is_array($error) || !in_array((int) $error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR), true)) {
			return;
		}

		$exception = new ErrorException(
			(string) $error['message'],
			0,
			(int) $error['type'],
			(string) $error['file'],
			(int) $error['line']
		);

		menorcaonSentryCaptureException($exception, array('handler' => 'shutdown'));

		try {
			if (function_exists('\Sentry\flush')) {
				\Sentry\flush(2);
			}
		} catch (Throwable $e) {
			dol_syslog('MenorcaOnSentry: flush after fatal error failed: '.$e->getMessage(), LOG_ERR);
		}
	});
}

/**
 * before_send callback. Best-effort scrubbing, never throws.
 *
 * @param mixed $event Event object
 * @param mixed $hint  Event hint
 * @return mixed
 */
function menorcaonSentryBeforeSend($event, $hint = null)
{
	try {
		if (is_object($event)) {
			menorcaonSentryScrubEventObject($event);
		}
	} catch (Throwable $e) {
		dol_syslog('MenorcaOnSentry: before_send scrub failed: '.$e->getMessage(), LOG_ERR);
	}

	return $event;
}

/**
 * Apply operation context to a temporary scope.
 *
 * @param \Sentry\State\Scope $scope   Scope
 * @param array<string,mixed> $context Context
 * @return void
 */
function menorcaonSentryApplyContextToScope(\Sentry\State\Scope $scope, array $context)
{
	$safeContext = menorcaonSentryScrubArray($context);

	foreach (array('module', 'operation') as $tag) {
		if (!empty($safeContext[$tag]) && is_scalar($safeContext[$tag])) {
			$scope->setTag('menorcaon.'.$tag, (string) $safeContext[$tag]);
		}
	}

	if (!empty($safeContext)) {
		$scope->setContext('operation', $safeContext);
	}
}

/**
 * Scrub common mutable parts from Sentry event/request objects when the SDK exposes them.
 *
 * @param object $event Event
 * @return void
 */
function menorcaonSentryScrubEventObject($event)
{
	if (method_exists($event, 'getExtra') && method_exists($event, 'setExtra')) {
		$event->setExtra(menorcaonSentryScrubArray((array) $event->getExtra()));
	}

	if (method_exists($event, 'getContexts') && method_exists($event, 'setContext')) {
		foreach ((array) $event->getContexts() as $name => $context) {
			$event->setContext((string) $name, menorcaonSentryScrubArray((array) $context));
		}
	}

	if (method_exists($event, 'getRequest') && method_exists($event, 'setRequest')) {
		$request = menorcaonSentryScrubArray((array) $event->getRequest());
		foreach (array('cookies', 'data', 'env', 'server') as $key) {
			if (array_key_exists($key, $request)) {
				$request[$key] = array();
			}
		}
		if (!empty($request['headers']) && is_array($request['headers'])) {
			$request['headers'] = menorcaonSentryScrubArray($request['headers']);
		}
		if (!empty($request['url']) && is_string($request['url'])) {
			$request['url'] = menorcaonSentrySanitizeUri($request['url']);
		}
		if (!empty($request['query_string'])) {
			$request['query_string'] = '';
		}
		$event->setRequest($request);
	}
}

/**
 * Recursively scrub sensitive data.
 *
 * @param mixed $value Value
 * @return mixed
 */
function menorcaonSentryScrubArray($value)
{
	if (!is_array($value)) {
		return menorcaonSentryScrubScalar($value);
	}

	$clean = array();
	foreach ($value as $key => $item) {
		$keyAsString = is_int($key) ? (string) $key : (string) $key;
		if (menorcaonSentryIsSensitiveKey($keyAsString)) {
			$clean[$key] = '[Filtered]';
			continue;
		}
		$clean[$key] = menorcaonSentryScrubArray($item);
	}

	return $clean;
}

/**
 * @param mixed $value Value
 * @return mixed
 */
function menorcaonSentryScrubScalar($value)
{
	if (is_string($value) && preg_match('/https?:\/\/[^:@\/]+:[^@\/]+@/', $value)) {
		return '[Filtered]';
	}

	return $value;
}

/**
 * @param string $key Key
 * @return bool
 */
function menorcaonSentryIsSensitiveKey($key)
{
	return (bool) preg_match('/(password|passwd|pwd|secret|token|csrf|cookie|authorization|auth|dsn|api[_-]?key|private[_-]?key|session|env|_POST|_COOKIE|_ENV)/i', $key);
}

/**
 * @param string $uri Request URI
 * @return string
 */
function menorcaonSentrySanitizeUri($uri)
{
	if ($uri === '') {
		return '';
	}

	$parts = parse_url($uri);
	if (!is_array($parts)) {
		return '';
	}

	$path = isset($parts['path']) ? $parts['path'] : '';
	if (empty($parts['query'])) {
		return $path;
	}

	parse_str($parts['query'], $query);
	$query = menorcaonSentryScrubArray($query);
	foreach ($query as $key => $value) {
		if ($value === '[Filtered]') {
			unset($query[$key]);
		}
	}

	return $path.(empty($query) ? '' : '?'.http_build_query($query));
}

/**
 * @param string $host HTTP host
 * @return string
 */
function menorcaonSentrySanitizeHost($host)
{
	return preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', $host);
}

/**
 * @param mixed $value Value
 * @return bool
 */
function menorcaonSentryKeepContextValue($value)
{
	return $value !== null && $value !== '';
}

/**
 * @param Throwable $exception Exception
 * @return string
 */
function menorcaonSentryFingerprintThrowable(Throwable $exception)
{
	return get_class($exception).'|'.$exception->getFile().'|'.$exception->getLine().'|'.$exception->getMessage();
}

/**
 * @param string $fingerprint Fingerprint
 * @return bool
 */
function menorcaonSentryWasCaptured($fingerprint)
{
	if (!isset($GLOBALS['MENORCAON_SENTRY_CAPTURED'])) {
		$GLOBALS['MENORCAON_SENTRY_CAPTURED'] = array();
	}

	return !empty($GLOBALS['MENORCAON_SENTRY_CAPTURED'][$fingerprint]);
}

/**
 * @param string $fingerprint Fingerprint
 * @return void
 */
function menorcaonSentryMarkCaptured($fingerprint)
{
	if (!isset($GLOBALS['MENORCAON_SENTRY_CAPTURED'])) {
		$GLOBALS['MENORCAON_SENTRY_CAPTURED'] = array();
	}

	$GLOBALS['MENORCAON_SENTRY_CAPTURED'][$fingerprint] = true;
}
