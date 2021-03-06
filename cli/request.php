<?php
/**
 * handles the shutdown:
 * show error-message, if a fatal error occurs
 */
function tx_directrequest_handleShutdown() {
	$errorCodes = array(E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR,E_RECOVERABLE_ERROR);
	$errorInfo = error_get_last();
	if(is_array($errorInfo) && in_array($errorInfo['type'],$errorCodes)) {
		echo printf('<error>%d: %s in %s on line %d</error>', $errorInfo['type'], $errorInfo['message'], $errorInfo['file'], $errorInfo['line']);
	}
}

register_shutdown_function('tx_directrequest_handleShutdown');


/**
 * Retrieve path (taken from cli_dispatch.phpsh)
 */
// echo realpath(dirname(__FILE__).'/../../../..'), "\n";


	// Get path to this script
$temp_PATH_thisScript = isset($_SERVER['argv'][0]) ? $_SERVER['argv'][0] : (isset($_ENV['_']) ? $_ENV['_'] : $_SERVER['_']);

	// Figure out if the path is relative
$relativePath = FALSE;
if (stristr(PHP_OS,'win') && !stristr(PHP_OS,'darwin')) {
		// Windows
	if (!preg_match('/^([A-Z]:)?\\\/', $temp_PATH_thisScript)) {
		$relativePath = TRUE;
	}
} else {
		// *nix, et al
	if ($temp_PATH_thisScript{0} != '/') {
		$relativePath = TRUE;
	}
}

	// Resolve path
if ($relativePath) {
	$workingDirectory = $_SERVER['PWD'] ? $_SERVER['PWD'] : getcwd();
	if ($workingDirectory) {
		$temp_PATH_thisScript =
			$workingDirectory.'/'.preg_replace('/\.\//','',$temp_PATH_thisScript);
		if (!@is_file($temp_PATH_thisScript)) {
			die ('Relative path found, but an error occured during resolving of the absolute path: '.$temp_PATH_thisScript.chr(10));
		}
	} else {
		die ('Relative path found, but resolving absolute path is not supported on this platform.'.chr(10));
	}
}

$typo3Root = preg_replace('#typo3conf/ext/directrequest/cli/request.php$#', '', $temp_PATH_thisScript);



/**
 * Second paramater is a base64 encoded serialzed array of header data
 */
$headerData = array();
if (isset($_SERVER['argv'][3])) {
	$additionalHeaders = unserialize(base64_decode($_SERVER['argv'][3]));
	if (is_array($additionalHeaders)) {
		foreach ($additionalHeaders as $additionalHeader) {
			if (strpos($additionalHeader, ':') !== false) {
				list($key, $value) = explode(':', $additionalHeader, 2);
				$key = str_replace('-', '_', strtoupper(trim($key)));
				if ($key != 'HOST') {
					$headerData['HTTP_' . $key] = trim($value);
				}
			}
		}
	}
}

// put parsed query parts into $_GET array
$urlParts = parse_url($_SERVER['argv'][2]);
if(FALSE === $urlParts){
	exit('could not parse url: '.$_SERVER['argv'][2]);
}

// Populating $_GET and $_REQUEST is query part is set:
if (isset($urlParts['query'])) {
	parse_str($urlParts['query'], $_GET);
	parse_str($urlParts['query'], $_REQUEST);
}

// Populating $_POST
$_POST = array();
// Populating $_COOKIE
$_COOKIE = array();

// Get the TYPO3_SITE_PATH of the website frontend:
$typo3SitePath = $_SERVER['argv'][1];

// faking the environment
$_SERVER = array();
$_SERVER['DOCUMENT_ROOT'] = preg_replace('#' . preg_quote($typo3SitePath, '#') . '$#', '', $typo3Root);
$_SERVER['HTTP_USER_AGENT'] = 'CLI Mode';
$_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = $urlParts['host'];
$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = $typo3SitePath . 'index.php';
$_SERVER['SCRIPT_FILENAME'] = $_SERVER['PATH_TRANSLATED'] = $typo3Root . 'index.php';
$_SERVER['QUERY_STRING'] = (isset($urlParts['query']) ? $urlParts['query'] : '');
$_SERVER['REQUEST_URI'] = $urlParts['path'] . (isset($urlParts['query']) ? '?' . $urlParts['query'] : '');
$_SERVER['REQUEST_METHOD'] = 'GET'; // set request-method, otherwise extbase will throw an exception
$_SERVER += $headerData;

// Define a port if used in the URL: 
if (isset($urlParts['port'])) {
	$_SERVER['HTTP_HOST'] .= ':' . $urlParts['port'];
	$_SERVER['SERVER_PORT'] = $urlParts['port'];
}
// Define HTTPS disposal:
if ($urlParts['scheme'] === 'https') {
	$_SERVER['HTTPS'] = 'on';
}
chdir($typo3Root);
include($typo3Root . '/index.php');

?>