<?php
// A simple helper script that will setup initial module
// settings in case one of these settings is missing.

if(!defined("PHORUM") && !defined("PHORUM_ADMIN")) return;

if (! isset($GLOBALS["PHORUM"]["mod_bbcode_geshi"])) {
    $GLOBALS["PHORUM"]["mod_editor_tools"] = array();
}

if (! isset($GLOBALS["PHORUM"]["mod_bbcode_geshi"]["enable_line_numbers"])) {
    $GLOBALS["PHORUM"]["mod_bbcode_geshi"]["enable_line_numbers"] = 1;
}

if (! isset($GLOBALS["PHORUM"]["mod_bbcode_geshi"]["enable_editor_tool"])) {
    $GLOBALS["PHORUM"]["mod_bbcode_geshi"]["enable_editor_tool"] = 1;
}

if (! isset($GLOBALS["PHORUM"]["mod_bbcode_geshi"]["enable_strict_mode"])) {
    $GLOBALS["PHORUM"]["mod_bbcode_geshi"]["enable_strict_mode"] = 0;
}

if (! isset($GLOBALS["PHORUM"]["mod_bbcode_geshi"]["languages"])) {
    $GLOBALS["PHORUM"]["mod_bbcode_geshi"]["languages"] = array(
        "php"         => "PHP",
        "sql"         => "SQL",
        "html4strict" => "HTML",
        "javascript"  => "Javascript",
        "css"         => "CSS"
    );
}

?>
