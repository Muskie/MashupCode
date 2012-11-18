<?php

// Include the API
require '../../lastfmapi/lastfmapi.php';

// Get the session auth data
$file = fopen('../auth.txt', 'r');
// Put the auth data into an array
$authVars = array(
	'apiKey' => trim(fgets($file)),
	'secret' => trim(fgets($file)),
	'username' => trim(fgets($file)),
	'sessionKey' => trim(fgets($file)),
	'subscriber' => trim(fgets($file))
);
$config = array(
	'enabled' => true,
	'path' => '../../lastfmapi/',
	'cache_length' => 1800
);
// Pass the array to the auth class to eturn a valid auth
$auth = new lastfmApiAuth('setsession', $authVars);

// Call for the album package class with auth data
$apiClass = new lastfmApi();
$libraryClass = $apiClass->getPackage($auth, 'library', $config);

// Setup the variables
$methodVars = array(
	'artist' => 'Various Artists'
);

if ( $libraryClass->addArtist($methodVars) ) {
	echo '<b>Done!</b>';
}
else {
	die('<b>Error '.$libraryClass->error['code'].' - </b><i>'.$libraryClass->error['desc'].'</i>');
}

?>