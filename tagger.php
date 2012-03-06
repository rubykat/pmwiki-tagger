<?php if (!defined('PmWiki')) exit();
/*
 * Copyright 2008 Kathryn Andersen
 * 
 * This program is free software; you can redistribute it and/or modify it
 * under the Gnu Public Licence or the Artistic Licence.
 */ 

/** \file tagger.php
 * \brief multiple category groups (tags)
 *
 * See Also: http://www.pmwiki.org/wiki/Cookbook/Tagger
 *
 * This script enables one to auto-link with (a subset of) page-text-variable
 * markup, to different "category" groups.
 * The markup is
 *     name:value
 *
 * This also makes corresponding Page Variables <name>Linked, which
 * contains the "linked" value.
 * 
 * This also allows multiple values, separated by commas.
 *
 * This requires 2.2-beta64 or later.
 *
 * To activate this script, copy it into the cookbook/ directory, then add
 * the following line to your local/config.php:
 *
 *      include_once("$FarmD/cookbook/multicat.php");
 * 
*/

$RecipeInfo['Tagger']['Version'] = '20080127';

SDVA($TaggerGroups, array());
SDV($TaggerTagSeparators, array(',','/','&'));

/*======================================================================
 * Tagger
 */
// set up multiple category groups
function TaggerSetup () {
    global $TaggerGroups;
    global $FmtPV;
    if ($TaggerGroups)
    {
	foreach ($TaggerGroups as $tagname => $catgroup)
	{
	    $FmtPV['$' . $tagname . 'Linked'] = "TaggerLinksVar(\$pn, '$tagname', '$catgroup', 'LinkedTitle')";
	    $FmtPV['$' . $tagname . 'LinkedName'] = "TaggerLinksVar(\$pn, '$tagname', '$catgroup', 'LinkedName')";
	    $FmtPV['$' . $tagname . 'Name'] = "TaggerLinksVar(\$pn, '$tagname', '$catgroup', 'Name')";
	}
	// markup to insert the links
	$tags = array_keys($TaggerGroups);
	$tagpat = implode('|', $tags);
	Markup("tagger",
	       '<directives','/^(\(:)?(' . $tagpat . '):(.*?)(:\))?$/e',
	       "TaggerLinks(\$pagename, '$1', '$2', '$3', '$4')");

    	// deal with the "hidden" PTVs
    	Markup('textvar:', '<split', '/\\(:(\\w[-\\w]*):((?!\\)).*?):\\)/se', "TaggerHiddenVars(\$pagename, '$1', '$2')");
    	// hidden Tagger PTVs get hidden after link-processing
    	Markup('textvar:ol', ">links", '/^\(:\w[-\w]*:.*?:\)$/', '');
    }
}
TaggerSetup();

// deal with hidden PTVs
function TaggerHiddenVars($pagename, $varname, $varval)
{
	global $TaggerGroups;
	if (strpos($varval, "\n") !== false)
	{
		// Multi-line PTVs shall be fully hidden
		return '';
	}
	else
	{
		// if this is a Tagger tag, keep it, otherwise hide it
		if ($TaggerGroups["$varname"])
		{
		    return "(:$varname:$varval:)";
		}
		else
		{
			return '';
		}
	}
}

// process link shortcut markup
function TaggerLinks($pagename, $prefix, $tagname, $inval, $postfix) {
	global $TaggerGroups;
	$catgroup = $TaggerGroups["$tagname"];
	//print "prefix=$prefix, tagname=$tagname, inval=$inval, postfix=$postfix\n";
	$out = "$prefix$tagname:";
	$out .= TaggerProcessTags($pagename, $catgroup, $inval);
	$out .= "$postfix\n";
	return $out;
}

// Page-Variable
function TaggerLinksVar($pagename, $tagname, $catgroup, $label) {
	$inval = PageTextVar($pagename, $tagname);
	$outval = TaggerProcessTags($pagename, $catgroup, $inval, $label);
	rtrim($outval);
	return $outval;
}


function TaggerProcessTags($pagename, $catgroup, $inval, $label='LinkedName') {
	global $TaggerTagSeparators;
	global $TaggerGroupSeparators;
	$out = '';
	// don't process if there are already links there
	if (strpos($inval, '[[') !== false)
	{
		$out = $inval;
	}
	else
	{
	    $array_sep = '';
	    if ($TaggerGroupSeparators[$catgroup])
	    {
		foreach ($TaggerGroupSeparators[$catgroup] as $tsep)
		{
		    if (strpos($inval, $tsep) !== false)
		    {
			$array_sep = $tsep;
			break;
		    }
		}
	    }
	    else
	    {
		foreach ($TaggerTagSeparators as $tsep)
		{
		    if (strpos($inval, $tsep) !== false)
		    {
			$array_sep = $tsep;
			break;
		    }
		}
	    }
	    $oo = array();
	    if ($label == 'Name') // one page name, not parts
	    {
	    	$pn = str_replace($array_sep, ' ', $inval);
		$cpage = MakePageName($pagename, "$catgroup.$pn");
		$out = PageVar($cpage, '$Name');
	    }
	    else
	    {
		$parts = ($array_sep
			  ? explode($array_sep, $inval)
			  : array($inval));
		foreach($parts as $part)
		{
		    $part = trim($part);
		    if ($part)
		    {
			$cpage = MakePageName($pagename, "$catgroup.$part");
			if ($label == 'LinkedTitle')
			{
			    $oo[] = "[[$cpage|+]]";
			}
			else
			{
			    $oo[] = "[[$cpage|$part]]";
			}
		    }
		}
	    }
	    if ($array_sep == ',')
	    {
		$out .= implode("$array_sep ", $oo);
	    }
	    else if ($array_sep == '/' or $array_sep == ' ')
	    {
		$out .= implode($array_sep, $oo);
	    }
	    else
	    {
		$out .= implode(" $array_sep ", $oo);
	    }
	}
	return $out;
}

