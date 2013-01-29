<?php

// This class simply has constants of the keys you need to use various APIs, I put them all in one place then I load them in 
// class constructors to private variables in classes
class myInfo
{
	const MY_AMAZON_PUBLIC_KEY ="";
	const MY_AMAZON_PRIVATE_KEY ="";
	const MY_LAST_FM_PUBLIC_KEY ="";
	const MY_LAST_FM_PRIVATE_KEY ="";
	const MY_LAST_FM_USER_NAME = "";  // Useful for music mashups if you plan to make any
	const MISSING_COVER_URL = "http://cdn.last.fm/depth/catalogue/noimage/cover_85px.gif";
	const MY_FACEBOOK_PUBLIC_KEY ="";
	const MY_FACEBOOK_SECRET_KEY ="";
	const CACHE_RENEW_TIME ="1814400";  // This is currently three weeks in seconds
	const FACEBOOK_ICON_URL = "http://www.muschamp.ca/CommonImages/SocialMediaIcons/facebook_32.png";
	const LAST_FM_ICON_URL = "http://www.muschamp.ca/CommonImages/SocialMediaIcons/lastfm_32.png";
	const AMAZON_ICON_URL = "http://www.muschamp.ca/CommonImages/SocialMediaIcons/amazon_32.png";
	const APPLE_ICON_URL = "http://www.muschamp.ca/CommonImages/SocialMediaIcons/apple_32.png";
	const MY_AMAZON_ASSOCIATE_ID = "";
	const MY_TWITTER_ACCOUNT = "";
	const DEFAULT_TWEET = " ";  // You have way less than 140 characters
	const MY_HOME_PAGE = "";
	const MY_FLICKR_PUBLIC_KEY = "";
	const MY_FLICKR_PRIVATE_KEY = "";
	const MY_ROTTEN_TOMATOES_KEY = "";
	const MY_KLOUT_KEY = '';
	const CACHING_DIRECTORY = '';
	const CACHE_FILE_EXTENSION = 'cache';  // No period!
	const MY_BESTBUY_PUBLIC_KEY = '';
	const MY_TOPSY_KEY = '';
}
?>