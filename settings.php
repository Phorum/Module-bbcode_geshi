<?php

if (!defined("PHORUM_ADMIN")) return;

define('GESHI_LANGUAGES_PATH', './mods/bbcode_geshi/geshi/geshi');

include_once('./mods/bbcode_geshi/defaults.php');

// -------------------------------------------------------------------
// Print the GeSHi logo at the top of the page.
// -------------------------------------------------------------------
?>
<div style="text-align:right">
  <a href="http://qbnz.com/highlighter/"><img style="border:none" border="0"
    src="<?php print $PHORUM[http_path] ?>/mods/bbcode_geshi/geshi.png"/></a>
</div>
<?php

// -------------------------------------------------------------------
// Build a list of all available GeSHIi languages.
// -------------------------------------------------------------------

$dh = opendir(GESHI_LANGUAGES_PATH);
if (!$dh) {
    phorum_admin_error("Unable to read dir \"".GESHI_LANGUAGES_PATH."\"!");
    return;
}
$languages = array();
while ($entry = readdir($dh)) {
    if (preg_match('/^([\w_-]+)\.php$/', $entry, $m)) {
        include(GESHI_LANGUAGES_PATH."/$entry");
        if (isset($language_data["LANG_NAME"])) {
            $languages[$m[1]] = $language_data["LANG_NAME"];
        }
    }
}
closedir($dh);

// -------------------------------------------------------------------
// Handle posted form data
// -------------------------------------------------------------------

if (count($_POST))
{
    $languages_enabled = array();
    foreach ($_POST['languages'] as $id => $enabled) {
        if (isset($languages[$id])) {
            $languages_enabled[$id] = $languages[$id];
        }
    }

    $tool = !empty($_POST["enable_editor_tool"]) && !empty($languages_enabled);

    $PHORUM["mod_bbcode_geshi"] = array(
        "enable_editor_tool"  => $tool ? 1 : 0,
        "enable_strict_mode"  => empty($_POST['enable_strict_mode']) ? 0 : 1,
        "enable_line_numbers" => empty($_POST['enable_line_numbers']) ? 0 : 1,
        "languages"           => $languages_enabled
    );

    phorum_db_update_settings(array(
        "mod_bbcode_geshi" => $PHORUM["mod_bbcode_geshi"],
    ));

    phorum_admin_okmsg("The settings were successfully stored.");
}

// -------------------------------------------------------------------
// Build and show the settings form
// -------------------------------------------------------------------

require_once('./include/admin/PhorumInputForm.php');
$frm = new PhorumInputForm ("", "post", "Save");
$frm->hidden("module", "modsettings");
$frm->hidden("mod", "bbcode_geshi");

$frm->addbreak("Edit settings for the BBcode GeSHi module");

$row = $frm->addrow(
    "Enable support for showing line numbers in the highlighted code blocks?",
    $frm->select_tag(
        "enable_line_numbers", array(0 => "No", 1 => "Yes"),
        empty($PHORUM["mod_bbcode_geshi"]["enable_line_numbers"]) ? 0 : 1
    )
);
$frm->addhelp($row, "Enable support for showing line numbers?",
    "If you enable this option, then the users will be able to add
     line numbering to the code blocks, by using the syntax:<br/>
     <br/>
     [code=\"language\" number]<br/>
     <br/>
     or<br/>
     <br/>
     [code=\"language\" number=\"1234\"]<br/>
     <br/>
     In the Editor Tool code dropdown, this will also add some features
     for setting up line numbers in an easy way."
);

$row = $frm->addrow(
    "Enable GeSHi's strict parsing mode?",
    $frm->select_tag(
        "enable_strict_mode", array(0 => "No", 1 => "Yes"),
        empty($PHORUM["mod_bbcode_geshi"]["enable_strict_mode"]) ? 0 : 1
    )
);
$frm->addhelp($row, "Enable GeSHi's strict parsing mode?",
    "Some languages like to get tricky, and jump in and out of the file that
     they are in. For example, the vast majority of you reading this will have
     used a PHP file before. And you know that PHP code is only executed if
     it is within delimiters like &lt;?php and ?&gt; (and there are other
     languages that do the same kind of thing).<br/>
     <br/>
     Enabling strict mode will tell the parser to be strict about only
     highlight code that is within these delimiters. For more information,
     please refer to the GeSHi documentation."
);

$row = $frm->addrow(
    "Extend the code button for the Editor Tools?" .
    (empty($PHORUM['mods']['editor_tools'])
     ? "<br/>The Editor Tools module must be enabled for this feature!" : ""),
    $frm->select_tag(
        "enable_editor_tool", array(0 => "No", 1 => "Yes"),
        empty($PHORUM["mod_bbcode_geshi"]["enable_editor_tool"]) ? 0 : 1,
        "id=\"editor_tool_option\" onchange=\"toggleLanguagePane()\""
    )
);

$frm->addhelp($row, "Extend the code button for the Editor Tools?",
    "If this option is enabled, then the code button will show a drop
     down menu instead of directly adding [code] to the message when clicked.
     From the drop down menu, the user can select \"plain text\"
     or any of the languages that are enabled for this menu.");

// Build the checkboxes for the languages.
$language_options = '';
natcasesort($languages);
foreach ($languages as $id => $lang)
{
    $language_options .=
        "<div style=\"float:left; width:50%\">" .
        $frm->checkbox(
            'languages['.$id.']', 1, htmlspecialchars($lang),
            empty($PHORUM['mod_bbcode_geshi']['languages'][$id]) ? 0 : 1
        ) .
        " ($id)</div>";
}

$frm->addmessage(
    "<div id=\"language_pane\" " .
    (empty($PHORUM["mod_bbcode_geshi"]["enable_editor_tool"])
     ? 'style="display:none"': '') . ">" .
    "Select the languages that you want to show up in the Editor Tools drop
     down menu. This only affects the drop down menu. Users can still make use
     of all available GeSHi languages by manually entering the language
     in the [syntax=\"language\"]...[/syntax] block.<br/><br/>" .
    $language_options .
    "</div>"
);

$frm->show();
?>

<script type="text/javascript">
//<![CDATA[
function toggleLanguagePane()
{
    var to = document.getElementById('editor_tool_option');
    var lp = document.getElementById('language_pane');
    var show_it = to.options[to.selectedIndex].value;
    lp.style.display = (show_it == 1 ? 'block' : 'none');
}

toggleLanguagePane();
//]]>
</script>
