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

	// variable contains the output string 
	$resultingString = curl_exec($ch); 
	// close curl resource to free up system resources 
	curl_close($ch);  
	
	return $resultingString;
}

// Because the Twitter API version 1.1 is a lot stricter 
// http://oikos.org.uk/2013/02/tech-notes-displaying-twitter-statuses-using-api-v1-1-and-oath/
// This calculates a relative time, e.g. "1 minute ago"
function relativeTime($time)
{
    $second = 1;
    $minute = 60 * $second;
    $hour = 60 * $minute;
    $day = 24 * $hour;
    $month = 30 * $day;
 
    $delta = time() - $time;
 
    if ($delta < 1 * $minute)
    {
        return $delta == 1 ? "one second ago" : $delta . " seconds ago";
    }
    if ($delta < 2 * $minute)
    {
      return "a minute ago";
    }
    if ($delta < 45 * $minute)
    {
        return floor($delta / $minute) . " minutes ago";
    }
    if ($delta < 90 * $minute)
    {
      return "an hour ago";
    }
    if ($delta < 24 * $hour)
    {
      return floor($delta / $hour) . " hours ago";
    }
    if ($delta < 48 * $hour)
    {
      return "yesterday";
    }
    if ($delta < 30 * $day)
    {
        return floor($delta / $day) . " days ago";
    }
    if ($delta < 12 * $month)
    {
      $months = floor($delta / $day / 30);
      return $months <= 1 ? "one month ago" : $months . " months ago";
    }
    else
    {
        $years = floor($delta / $day / 365);
        return $years <= 1 ? "one year ago" : $years . " years ago";
    }
}

// Also necessary or at least helpful in meeting Twitter Display Requirements mandatory for API version 1.1
function linkify_tweet($raw_text, $tweet = NULL)
{
    // first set output to the value we received when calling this function
    $output = $raw_text;
 
    // create xhtml safe text (mostly to be safe of ampersands)
    $output = htmlentities(html_entity_decode($raw_text, ENT_NOQUOTES, 'UTF-8'), ENT_NOQUOTES, 'UTF-8');
 
    // parse urls
    if ($tweet == NULL)
    {
        // for regular strings, just create <a> tags for each url
        $pattern        = '/([A-Za-z]+:\/\/[A-Za-z0-9-_]+\.[A-Za-z0-9-_:%&\?\/.=]+)/i';
        $replacement    = '<a href="${1}" rel="external">${1}</a>';
        $output         = preg_replace($pattern, $replacement, $output);
    } else {
        // for tweets, let's extract the urls from the entities object
        foreach ($tweet->entities->urls as $url)
        {
            $old_url        = $url->url;
            $expanded_url   = (empty($url->expanded_url))   ? $url->url : $url->expanded_url;
            $display_url    = (empty($url->display_url))    ? $url->url : $url->display_url;
            $replacement    = '<a href="'.$expanded_url.'" rel="external">'.$old_url.'</a>';
            $output         = str_replace($old_url, $replacement, $output);
        }
 
        // let's extract the hashtags from the entities object
        foreach ($tweet->entities->hashtags as $hashtags)
        {
            $hashtag        = '#'.$hashtags->text;
            $replacement    = '<a href="http://twitter.com/search?q=%23'.$hashtags->text.'" rel="external">'.$hashtag.'</a>';
            $output         = str_ireplace($hashtag, $replacement, $output);
        }
 
        // let's extract the usernames from the entities object
        foreach ($tweet->entities->user_mentions as $user_mentions)
        {
            $username       = '@'.$user_mentions->screen_name;
            $replacement    = '<a href="http://twitter.com/'.$user_mentions->screen_name.'" rel="external" title="'.$user_mentions->name.' on Twitter">'.$username.'</a>';
            $output         = str_ireplace($username, $replacement, $output);
        }
 
        // if we have media attached, let's extract those from the entities as well
        if (isset($tweet->entities->media))
        {
            foreach ($tweet->entities->media as $media)
            {
                $old_url        = $media->url;
                $replacement    = '<a href="'.$media->expanded_url.'" rel="external" class="twitter-media" data-media="'.$media->media_url.'">'.$media->display_url.'</a>';
                $output         = str_replace($old_url, $replacement, $output);
            }
        }
    }
 
    return $output;
}

?>