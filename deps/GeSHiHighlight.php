<?php
# GeSHiHighlight.php
# 
# By: E. Rogan Creswick (aka: Largos)
# creswick@gmail.com
# wiki.ciscavate.org
#
# License: GeSHi Highlight is released under the Gnu Public License (GPL), and comes with no warranties.
# The text of the GPL can be found here: http://www.gnu.org/licenses/gpl.html
# Loosely based on SyntaxHighlight.php by Coffman, (www.wickle.com)

# you want to change the below two lines

require_once("/usr/share/php-geshi/geshi.php"); // i asume geshi.php is in the same directory as GeSHiHighlight.php (that is 'extensions' dir)
	 
define("GESHI_PATH","/usr/share/php-geshi/geshi");// definition where are stored geshi language parsing files
	  
	   
# ok, end of editing :)

	    
    class SyntaxSettings {};
	     
$wgSyntaxSettings = new SyntaxSettings; 
$wgExtensionFunctions[] = "wfSyntaxExtension"; 
	      
function wfSyntaxExtension() { 
    global $wgParser;
    $langArray = geshi_list_languages(GESHI_PATH);
# $langArray = array("actionscript","ada","apache","asm","asp","bash",
# "caddcl","cadlisp","c","cpp","css","delphi",
# "html4strict","java","javascript","lisp", "lua",
# "nsis","oobas","pascal","perl","php-brief","php",
# "python","qbasic","sql","vb","visualfoxpro","xml");
    foreach ( $langArray as $lang ){
	if ($lang == "" || $lang == "div") continue; 
	$wgParser->setHook( $lang, 
	create_function( '$text', '$geshi = new GeSHi(trim($text,"\n\r"), "' ."$lang". '", GESHI_PATH); return $geshi->parse_code();')); 
    } 
}

/**
 * function: geshi_list_languages
 * -------------------------
 * List supported languages by reading the files in the geshi/geshi subdirectory
 * (added by JeffK -- Jeff, any more contact info?) -- I haven't tested the code is is, will do that shortly. -Rogan
 *
 */
function geshi_list_languages ( $path = 'geshi/' )
{
    $lang_list = array();
    if ($handle = opendir($path)) {
	while (false !== ($file = readdir($handle))) {	// Loop over the directory. 
	    if(is_dir($file)) continue;		   	     // Drop anything that is a directory, cause we want files only
	    if( ".php" == substr($file, strrpos($file, "."),4)) // process only .php files
		{
		    $lang_list[]= substr($file, 0, strrpos($file, "."));
		}
	}
	closedir($handle);
    }
    sort($lang_list); //sort the output, i like ordered lists in Wiki Version page :)
    return $lang_list;
}
