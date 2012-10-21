<?php

// Since I often had to do this, now I can do it in a single line of code
function createArrayFromCSVFile($fileName)
{
	$myArray = array();
	$file = fopen($fileName, 'r');
	
	while (($result = fgetcsv($file)) !== false)
	{
		$myArray[] = $result;
	}
	
	fclose($file);
	
	return $myArray;
}


// Because you need to CURL a lot to use APIs at least I do...
function fetchThisURL($url)
{
	// create curl resource 
	$ch = curl_init(); 

	// set url 
	curl_setopt($ch, CURLOPT_URL, $url); 

	//return the transfer as a string 
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	
	//lets add in a user agent
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 6.1; he; rv:1.9.2.8) Gecko/20100722 Firefox/3.6.8');

	// $output contains the output string 
	$resultingString = curl_exec($ch); 
	// close curl resource to free up system resources 
	curl_close($ch);  
	
	return $resultingString;
}

?>