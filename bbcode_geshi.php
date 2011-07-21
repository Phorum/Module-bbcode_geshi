<?php

if (!defined('PHORUM')) return;

define('GESHI_PATH', './mods/bbcode_geshi/geshi');
define('GESHI_LANG_PATH', GESHI_PATH.'/geshi');

include_once('./mods/bbcode_geshi/defaults.php');

// Register the CSS code that is provided by GeSHi and the additional CSS
// code for adding / overriding some definitions from our module templates.
function phorum_mod_bbcode_geshi_css_register($data)
{
    $data['register'][] = array(
        "module"    => "bbcode_geshi",
        "where"     => "after",
        "source"    => "function(mod_bbcode_geshi_css)",
        "cache_key" => filemtime(GESHI_PATH."/geshi.php")
    );

    $data['register'][] = array(
        "module"    => "bbcode_geshi",
        "where"     => "after",
        "source"    => "template(bbcode_geshi::css)"
    );

    return $data;
}

// A function that is used as the GeSHi CSS source (as registered in
// the above hook function).
function mod_bbcode_geshi_css()
{
    // Let GeSHi generate a stylesheet.
    include_once(GESHI_PATH . '/geshi.php');

    $geshi = new GeSHi();
    $geshi->set_overall_class('bbcode_geshi');

    $languages = array();
    if ($handle = opendir($geshi->language_path)) {
        while (($file = readdir($handle)) !== false) {
            $pos = strpos($file, '.');
            if ($pos > 0 && substr($file, $pos) == '.php') {
                $languages[] = substr($file, 0, $pos);
            }
        }
        closedir($handle);
    }
    sort($languages);

    $css = '';
    foreach ($languages as $language)
    {
        $geshi->set_language($language);
        $add_css = $geshi->get_stylesheet(false);
        $add_css = preg_replace('/^\/\*\*.*?\*\//s', '', $add_css);
        $css .= $add_css;
    }

    // For more specific matching, we add #phorum.
    $css = preg_replace('/^\./m', '#phorum .', $css);

    return $css;
}


// Register the JavaScript for this module, which implements the
// overrid behavior for the [code] editor tool button.
function phorum_mod_bbcode_geshi_javascript_register($data)
{
    // Only load the javascript code if the editor tool support is enabled.
    if (!empty($GLOBALS['PHORUM']['mod_bbcode_geshi']['enable_editor_tool']))
    {
        $data[] = array(
            'module' => 'bbcode_geshi',
            'source' => 'file(mods/bbcode_geshi/bbcode_geshi.js)'
        );
    }

    return $data;
}

// In the before_editor hook, we can be sure that we are displaying
// an editor on the screen. Here we add a list of languages to the
// page header (in a JavaScript object), which should be shown in
// the editor tool [code] dropdown.
function phorum_mod_bbcode_geshi_before_editor($message)
{
    global $PHORUM;
    $lang = $PHORUM['DATA']['LANG']['mod_bbcode_geshi'];

    // No work for us here, unless the editor tool option is enabled.
    if (empty($PHORUM['mod_bbcode_geshi']['enable_editor_tool'])) {
        return $message;
    }

    // No languages available? Should not happen, but let's be prepared.
    if (empty($PHORUM['mod_bbcode_geshi']['languages'])) {
        return $message;
    }

    // Add available languages, translation strings and options for javascript.
    $js = "<script type=\"text/javascript\">\n" .
          "//<![CDATA[\n" .
          "editor_tools_lang['ShowLineNumbers']='$lang[ShowLineNumbers]';\n" .
          "editor_tools_lang['StartAtNumber']='$lang[StartAtNumber]';\n" .
          "var editor_tools_geshi_enable_line_numbers = " .
          (empty($PHORUM['mod_bbcode_geshi']['enable_line_numbers']) ? 0 : 1) .
          ";\n" .
          "var editor_tools_geshi_languages = {\n";
    $js .= "'NULL':'".addslashes($lang['PlainText'])."'";
    foreach ($PHORUM['mod_bbcode_geshi']['languages'] as $id => $lang) {
        $id = addslashes($id);
        $lang = addslashes($lang);
        $js .= ",\n'$id':'$lang'";
    }
    $js .="\n};\n" .
          "//]]>\n" .
          "</script>\n";

    $PHORUM['DATA']['HEAD_TAGS'] .= $js;

    return $message;
}

// First stage formatting: strip out all the valid code blocks and replace
// them with temporary placeholders.
function phorum_mod_bbcode_geshi_format($messages)
{
    $PHORUM = $GLOBALS['PHORUM'];

    // Regexps for matching code block start and end tags. The start tag is
    // not fully qualified here. This is only used for a first tokenizing
    // pass over the message body.
    static $start  = '\[(?:code|syntax)[\s=][^\]]+\]';
    static $end    = '\[\/(?:code|syntax)\]';

    foreach ($messages as $id => $message)
    {
        // Skip formatting if bbcode formatting was disabled for the post
        // (this is a feature of the BBcode module that we should honor).
        if (!empty($PHORUM["mod_bbcode"]["allow_disable_per_post"]) &&
            !empty($message['meta']['disable_bbcode'])) {
           continue;
        }

        // Skipping this message if there is no body or a body
        // without a syntax tag in it.
        if (!isset($message['body']) ||
            !preg_match('!\[(?:syntax|code)!', $message['body'])) continue;

        // Split into tokens.
        $tokens = preg_split(
            "/($start|$end)/", $message['body'], -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        // Process the tokens to find the syntax blocks.
        $newbody = '';
        $blocks = array();
        $block_idx = 0;
        $block_id = NULL;
        $block_tag = NULL;
        foreach ($tokens as $token)
        {
            // Not in a syntax block.
            if ($block_id === NULL)
            {
                // See if we have a valid syntax starting block.
                if (preg_match('/\[(code|syntax)[\s=]([^\]]+)\]/', $token, $t))
                {
                    // Clean up quoting and spacing.
                    $options = str_replace(
                        array('&quot;', '"', "'"),
                        '', trim($t[2])
                    );

                    // We can have multiple options.
                    $options = preg_split('/\s+/', $options);

                    // The first option always represents the language.
                    $lang = strtolower(array_shift($options));

                    // Check if the language name is valid.
                    if (preg_match('/^[\w_-]+$/', $lang))
                    {
                        // Check if the language exists.
                        $lang_file = GESHI_LANG_PATH.'/'.$lang.'.php';
                        if (file_exists($lang_file))
                        {
                            // Valid block start found.
                            // Initialize a new syntax block.
                            $block_idx ++;
                            $block_id = md5(microtime() .':'. $block_idx);
                            $temp_tag = "[bbcode_syntax $block_id]";
                            $block_tag = $t[1];
                            $newbody .= $temp_tag;
                            $blocks[$block_id] = array(
                                '',        // block code storage
                                $lang,     // the requested language
                                $temp_tag, // the temporary replacement tag
                                $options   // additional options in the bbcode
                            );
                            continue;
                        }
                    }
                }

                // No start of block found. Add the token to the new body.
                $newbody .= $token;
            }
            // In a syntax block.
            else
            {
                // End of block found.
                if ($token == '[/'.$block_tag.']')
                {
                    $block_id = NULL;
                    continue;
                }

                // No end of block found. Add the token to the syntax block
                $blocks[$block_id][0] .= $token;
            }
        }

        $messages[$id]['body'] = $newbody;
        $messages[$id]['bbcode_geshi'] = $blocks;
    }

    return $messages;
}

// Second stage formatting: replace all temporary placeholders with
// syntax highlighted code.
function phorum_mod_bbcode_geshi_format_fixup($messages)
{
    $PHORUM = $GLOBALS['PHORUM'];
    static $geshi = NULL;

    foreach ($messages as $id => $message)
    {
        if (!empty($message['bbcode_geshi']))
        {
            // Initialize the GeSHi parser.
            if ($geshi === NULL)
            {
                // Create the GeSHi object.
                include_once(GESHI_PATH . '/geshi.php');
                $geshi = new GeSHi('', 'php');

                // We use the classes based output, for making the code
                // less bloated.
                $geshi->enable_classes();
            }

            foreach ($message['bbcode_geshi'] as $block)
            {
                list ($code, $lang, $temp_tag, $options) = $block;

                // Remove Phorum's break tags.
                $code = str_replace('<phorum break>', '', $code);

                // Remove surrounding white space.
                $code = preg_replace('/^\s+|\s+$/', '', $code);

                // Unescape special HTML characters. GeSHi expects
                // them to be not HTML escaped.
                $code = str_replace(
                    array('&lt;', '&gt;', '&quot;', '&#039', '&amp;'),
                    array('<',    '>',    '"',      "'",     '&'),
                    $code
                );

                // Initialize the code and language for highlighting.
                $geshi->set_source($code);
                $geshi->set_language($lang);
                $geshi->enable_classes(TRUE);
                $geshi->set_overall_class('bbcode_geshi');
                $geshi->set_header_content(
                    "Language: ".htmlspecialchars($geshi->get_language_name())
                );

                // Parse extra bbcode options.
                $parsed_options = array(
                    'number'   => 0,
                    'strict'   => 0,
                    'nostrict' => 0
                );
                if (!empty($options))
                {
                    foreach ($options as $option)
                    {
                        if (strstr($option, '=')) {
                            list ($key, $value) = split('=', $option, 2);
                        } else {
                            $value = 1;
                            $key   = $option;
                        }

                        $parsed_options[$key] = (int) $value;
                    }
                }

                // Enable line numbers if enabled in the settings and
                // requested from the tag options.
                if (!empty($PHORUM['mod_bbcode_geshi']['enable_line_numbers'])){
                  if (!empty($parsed_options['number'])) {
                    $geshi->enable_line_numbers(GESHI_NORMAL_LINE_NUMBERS);
                    $geshi->start_line_numbers_at($parsed_options['number']);
                  } else {
                    $geshi->enable_line_numbers(GESHI_NO_LINE_NUMBERS);
                  }
                }

                // Enable strict parsing mode if requested from the settings
                // and not disabled by the tag options. The tag options are
                // not on public display currently, because I think these
                // would be too difficult to understand for the regular
                // forum user.
                if ((!empty($PHORUM['mod_bbcode_geshi']['enable_strict_mode'])||
                     !empty($parsed_options['strict'])) &&
                    empty($parsed_options['nostrict'])) {
                    $geshi->enable_strict_mode(TRUE);
                } else {
                    $geshi->enable_strict_mode(FALSE);
                }

                // Let GeSHi highlight the code block.
                $highlighted_code = $geshi->parse_code();

                // Replace the temporary tag with the highlighted code block.
                $messages[$id]['body'] = str_replace(
                    $temp_tag,
                    $highlighted_code,
                    $messages[$id]['body']
                );
            }
        }
    }

    return $messages;
}


?>
