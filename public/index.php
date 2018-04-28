<?php
//ini_set('display_errors', 1);

define('METHODS_DIR', realpath(__DIR__ . '/../methods/'));

$method = mb_strstr($_SERVER['REQUEST_URI'], '?', TRUE) ?: $_SERVER['REQUEST_URI'];

if (file_exists($methodFile = METHODS_DIR . $method . '.php')) {
	require_once $methodFile;
} else {
	header("HTTP/1.X 404 Not Found");
}
