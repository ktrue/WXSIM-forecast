<?php
// plaintext-rss.php script by Ken True - webmaster@saratoga-weather.org
//
// Version 1.00 - 16-May-2007 - Initial release
// Version 1.01 - 17-May-2007 - changed method to get hostname to $_SERVER["SERVER_NAME"]
// Versoon 1.02 - 18-May-2007 - added ISO-like date string to <link> URL (as #...)
// Version 1.03 - 03-Jun-2007 - added Wind dir/speed/units to icon image set
// Version 1.04 - 27-Jul-2007 - added Beaufort wind scale output
// Version 1.05 - 19-Aug-2009 - updated for PHP5
// Version 1.06 - 09-Jul-2010 - added humidex and frost displays
// Version 1.07 - 07-Jan-2011 - corrected feed validation issues with the Cold! and Hot! displays in the title
// Version 1.08 - 27-Dec-2011 - changed get_lang() calls to PPget_lang() calls
// Version 1.09 - 24-Aug-2016 - added code to correct htmlspecialchars() call for some languages
// Version 1.10 - 29-Jan-2019 - fixed HTTPS icon usage
//
$VersionRSS = "plaintext-rss.php Version 1.10 - 29-Jan-2019";
//
// error_reporting(E_ALL);  // uncomment to turn on full error reporting
//
// script available at http://saratoga-weather.org/scripts.php
//  
// you may copy/modify/use this script as you see fit,
// no warranty is expressed or implied.
//
// This script uses plaintext-parser.php to parse the plaintext.txt forecast output from WXSIM 
//  (https://www.wxsim.com/) to create a RSS feed of the forecast from WXSIM.
//
// output: creates RSS 2.0 type feed
// more info on RSS 2.0 available at http://cyber.law.harvard.edu/rss/rss.html
//
// NOTE: This script requires a working copy of plaintext-parser.php to be in the SAME directory
// as this script.
//
//
// Options on URL:
//   lang=en          (default) - use English language
//   lang=ZZ          - use 'ZZ' language translation file for conversion from English
//                    note: there must be a plaintext-parser-lang-LL.txt file with the
//                    conversion rules.   A sample Dutch file is included (lang=nl).
//
// Settings ---------------------------------------------------------------
//
  $ourTZ = "PST8PDT";  //NOTE: this *MUST* be set correctly to
// translate UTC times to your LOCAL time for the displays.
//  https://saratoga-weather.org/timezone.txt  has the list of timezone names
//  pick the one that is closest to your location and put in $ourTZ
// also available is the list of country codes (helpful to pick your zone
//  from the timezone.txt table
//  https://saratoga-weather.org/country-codes.txt : list of country codes
  $ourLinkPage = '/WXSIM-forecast.php'; // set to your page name to display
//				// the full forecast using your template
  $ourEmail = 'webmaster@saratoga-weather.org (Ken True)';  // set to your email address
//
  $absIconDir = '/forecast/images/'; // set to absolute web address of images
//                                       without './' or '../' and include
//                                       trailing '/'
// ---- end of settings ---------------------------------------------------
//
// -------------------begin code ------------------------------------------
if (isset($_REQUEST['sce']) && strtolower($_REQUEST['sce']) == 'view' ) {
   //--self downloader --
   $filenameReal = __FILE__;
   $download_size = filesize($filenameReal);
   header('Pragma: public');
   header('Cache-Control: private');
   header('Cache-Control: no-cache, must-revalidate');
   header("Content-type: text/plain");
   header("Accept-Ranges: bytes");
   header("Content-Length: $download_size");
   header('Connection: close');
   
   readfile($filenameReal);
   exit;
}

# Set timezone in PHP5/PHP4 manner
  if (!function_exists('date_default_timezone_set')) {
	  if (! ini_get('safe_mode') ) {
		 putenv("TZ=$ourTZ");  // set our timezone for 'as of' date on file
	  }  
#	$Status .= "<!-- using putenv(\"TZ=$ourTZ\") -->\n";
    } else {
	date_default_timezone_set("$ourTZ");
#	$Status .= "<!-- using date_default_timezone_set(\"$ourTZ\") -->\n";
   }
$GMTtimeFormat = 'D, d M Y H:i:s';  // Fri, 31 Mar 2006 14:03:22 for RSS Feed date/time

//setup translation table for our $lang to the official XML languages for the XML
//see http://xml.coverpages.org/iso639a.html and http://cyber.law.harvard.edu/rss/languages.html
// for a list,  If not included in the list, then the lang= ($lang) is used;
//
$XMLLanguages = array( //see http://xml.coverpages.org/iso639a.html and
  'en' => 'en-us',
  'dk' => 'da',
  'gr' => 'el',
  'se' => 'sv');
  
header("Content-Type: application/rss+xml");
$doPrint = false;
require_once("plaintext-parser.php"); // don't change this.. must be in same directory
// -----------------------------------------------------------------------------------
// Note: the following code uses variables defined and loaded by plaintext-parser.php
//
//
$XMLlang = $lang; // $lang is from plaintext-parser.php after processing
if (isset($XMLLanguages[$lang])) {
  $XMLlang = $XMLLanguages[$lang]; // for the <language></language>
}
 
// Begin XML generation for RSS feed 
// note: $useCharSet is from plaintext-parser.php
print '<?xml version="1.0" encoding="' . $useCharSet .'"?>
<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:atom="http://www.w3.org/2005/Atom">
';
print "<!-- $VersionRSS -->\n";
print $Status; // print status from plaintext-parser.php comments
print "<!-- using lang='$lang' -->\n";

$urlParts = parse_url($_SERVER['SCRIPT_URI']);
$ourBase = $urlParts['scheme'] . '://' . $urlParts['host'];
if (($urlParts['scheme'] == 'http' and $_SERVER['SERVER_PORT'] <> '80') or 
    ($urlParts['scheme'] == 'https' and $_SERVER['SERVER_PORT'] <> '443')) {
  $ourBase .= ':' . $_SERVER['SERVER_PORT'];
}
if (!isset($PHP_SELF)) {$PHP_SELF = $_SERVER['SCRIPT_NAME']; }
// now $ourBase has http://our.server.com

// Use forecast date from WXSIM output (parsed by plaintext-parser.php)
// as the timestamp for pubDate, lastBuildDate XML items
//  (note the $wDay, $wMon, $wYear, $wTime vars are set by plaintext-parser.php)
  $wdate = "$wDay-$wMon-$wYear $wTime:00 " . date('O',time());
  $d = strtotime($wdate);
  $TStamp = gmdate($GMTtimeFormat,$d) . ' GMT';
  $YEAR = date('Y',$d);
  $ISOdate = gmdate('Ymd',$d) . 'Z' . gmdate('Hi',$d);
  $GUID = 'fcst/' . gmdate('Ymd/Hi',$d) . '/N';

// Print the Channel parts of the RSS feed
//
print '<channel>
  <atom:link href="' . $_SERVER['SCRIPT_URI'] . '" rel="self" type="application/rss+xml" />
  <title>' . PPget_lang("WXSIM Forecast for:") . " $WXSIMcity" . '</title>
  <link>http://' . $_SERVER['SERVER_NAME'] . $ourLinkPage . '</link>
  <description>' . PPget_lang("Issued by:") . " $WXSIMstation.   " . 
     PPget_lang("Updated:") . " $WXSIMupdated" . '</description>
  <language>'. $XMLlang .'</language>
  <webMaster>' . $ourEmail . '</webMaster>
  <generator>' . $VersionRSS . '</generator>
  <copyright>Copyright' . " $YEAR $WXSIMstation" . '</copyright>
  <pubDate>' . $TStamp . '</pubDate>
  <ttl>15</ttl>
  <lastBuildDate>' . $TStamp . '</lastBuildDate>';

//generate the details for each forecast period as an <item></item>

for ($i=0;$i<count($WXSIMday);$i++) {

  $temperature = preg_replace('|<[^>]+>|Uis','',$WXSIMtemp[$i]); // remove html from Temp.
  $temperature = preg_replace('|&deg;|','',$temperature); // Feeds can't have html in title
  $day = preg_replace('|&nbsp;|Uis',' ',$WXSIMday[$i]);  // strip out non valid stuff
  if ($WXSIMhumidex[$i] <> '') {
	$temperature .= ', '.PPget_lang('Humidex').': '.$WXSIMhumidex[$i];
  }
  if ($WXSIMfrost[$i] <> '') {
	$temperature .= ', '.$WXSIMfrost[$i];
  }

  if ($WXSIMuv[$i] <> '') {
    $temperature .= ', UV: ' . $WXSIMuv[$i];
  }
  $detail = '<table width="100%"><tr valign="top"><td align="center" width="120">' .
     str_replace($iconDir,$ourBase . $absIconDir,$WXSIMicons[$i]) . '<br/>' . 
	 str_replace($iconDir,$ourBase . $absIconDir,$WXSIMtemp[$i]) ;
	  if ($WXSIMhumidex[$i] <> '') {
		$detail .= '<br/>'.PPget_lang('Humidex').': '.$WXSIMhumidex[$i];
	  }
	  if ($WXSIMfrost[$i] <> '') {
		$detail .= '<br/>'.$WXSIMfrost[$i];
	  }

	 if ($WXSIMprecip[$i] <> '') {
	   $detail .= '<br/>' . "<span style=\"color: green;\">$WXSIMprecip[$i]</span>" ;
	 }
	 $detail .= '<br/>' . $WXSIMwinddir[$i] . " " .
		$WXSIMwind[$i] . " " . $WXSIMwindunits[$i] ;
	 if ($showBeaufort) {
	    $detail .= '<br/><i>' . $WXSIMBeaufort[$i] . '</i>' ;
	 }
	 $detail .= '<br/>' . PPset_UV_string($WXSIMuv[$i]) .
	 '</td><td align="left">' . $WXSIMtext[$i] .
	 '</td></tr></table>';
	 $tcond = strip_tags(preg_replace('|<br/>|i',', ',$WXSIMcond[$i]));
	 
  print '
  <item>
    <title>'. strip_tags($day . ', ' . $tcond . ', ' . $temperature) .'</title>
    <link>http://' . $_SERVER['SERVER_NAME'] . $ourLinkPage . '#D' . $ISOdate . 'N' . $i . '</link>
    <pubDate>' . $TStamp . '</pubDate>
    <guid isPermaLink="false">' . $GUID . $i . '</guid>
    <description>' . @htmlspecialchars($detail, ENT_IGNORE ,strtoupper($useCharSet)) .'</description>
  </item>';
} // end of <item></item> generation

// finish the feed.. close the XML.
print '
  </channel>
</rss>';
?>