<?php

/**
 * json_split_objects - Return an array of many JSON objects
 *
 * In some applications (such as PHPUnit, or salt), JSON output is presented as multiple
 * objects, which you cannot simply pass in to json_decode(). This function will split
 * the JSON objects apart and return them as an array of strings, one object per indice.
 *
 * @param string $json  The JSON data to parse
 *
 * @return array
 */
function json_split_objects($json)
{
    $q = FALSE;
    $len = strlen($json);
    for($l=$c=$i=0;$i<$len;$i++)
    {   
        $json[$i] == '"' && ($i>0?$json[$i-1]:'') != '\\' && $q = !$q;
        if(!$q && in_array($json[$i], array(" ", "\r", "\n", "\t"))){continue;}
        in_array($json[$i], array('{', '[')) && !$q && $l++;
        in_array($json[$i], array('}', ']')) && !$q && $l--;
        (isset($objects[$c]) && $objects[$c] .= $json[$i]) || $objects[$c] = $json[$i];
        $c += ($l == 0);
    }   
    return $objects;
}

?>
