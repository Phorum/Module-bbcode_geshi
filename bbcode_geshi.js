var editor_tools_code_picker_obj = null;
var editor_tools_geshi_languages = null;

// Override the default [code] Editor Tools handler.
function editor_tools_handle_code()
{
    editor_tools_store_range();

    // Create the code picker on first access.
    if (!editor_tools_code_picker_obj)
    {
        // Create a new popup.
        var popup = editor_tools_construct_popup('editor-tools-code-picker','l');
        editor_tools_code_picker_obj = popup[0];
        var content_obj = popup[1];

        content_obj.style.fontSize = '80%';

        // Populate the new popup with selected languages.
        for (var code_id in editor_tools_geshi_languages)
        {
            var code = editor_tools_geshi_languages[code_id];
            var a_obj = document.createElement('a');
            a_obj.href = 'javascript:editor_tools_handle_code_select("' + code_id + '")';
            a_obj.innerHTML = code;
            content_obj.appendChild(a_obj);

            var br_obj = document.createElement('br');
            content_obj.appendChild(br_obj);
        }

        // Add an option for line numbering if that is enabled.
        if (editor_tools_geshi_enable_line_numbers)
        {
            // Add a separator in the menu.
            content_obj.appendChild(document.createElement('hr'));

            // A div for the first line: checkbox + label (enable numbering).
            var div_obj = document.createElement('div');
            content_obj.appendChild(div_obj);

            // Add the checkbox to the div.
            var cb_obj = document.createElement('input');
            cb_obj.type = 'checkbox';
            cb_obj.id = 'editor_tools_geshi_linenumbers';
            div_obj.appendChild(cb_obj);

            // Add the label for the checkbox.
            var label_obj = document.createElement('label');
            label_obj.htmlFor = 'editor_tools_geshi_linenumbers';
            label_obj.innerHTML = editor_tools_translate('ShowLineNumbers');
            label_obj.style.paddingLeft = '0.5em';
            div_obj.appendChild(label_obj);

            // A div for the second line: label + entry (start number).
            div_obj = document.createElement('div');
            content_obj.appendChild(div_obj);

            // Add the label for the entry.
            label_obj = document.createElement('label');
            label_obj.htmlFor = 'editor_tools_geshi_startnumber';
            label_obj.innerHTML = editor_tools_translate('StartAtNumber');
            label_obj.style.paddingRight = '0.5em';
            div_obj.appendChild(label_obj);

            // Add the entry.
            var input_obj = document.createElement('input');
            input_obj.type = 'text';
            input_obj.id = 'editor_tools_geshi_startnumber';
            input_obj.size = 4;
            div_obj.appendChild(input_obj);
        }


        // Register the popup with the editor tools.
        editor_tools_register_popup_object(editor_tools_code_picker_obj);
    }

    // Display the popup.
    var button_obj = document.getElementById('editor-tools-img-code');
    editor_tools_toggle_popup(editor_tools_code_picker_obj, button_obj);
}

function editor_tools_handle_code_select(code_id)
{
    editor_tools_hide_all_popups();
    editor_tools_restore_range();

    // Handle line numbering options if linenumbers are enabled in the admin.
    var options = '';
    if (editor_tools_geshi_enable_line_numbers)
    {
        var ln = document.getElementById('editor_tools_geshi_linenumbers');
        var sn = document.getElementById('editor_tools_geshi_startnumber');

        if (ln && ln.checked) {
            options += ' number';
            if (sn) {
                var startnumber = parseInt(sn.value);
                if (startnumber > 0) {
                    options += '="' + startnumber + '"';
                }
            }
        }
    }

    code_id = editor_tools_strip_whitespace(code_id);
    if (code_id && code_id != 'NULL') {
        editor_tools_add_tags(
            '[code="' + code_id + '"' + options + ']\n',
            '\n[/code]\n'
        );
    } else {
        editor_tools_add_tags(
            '[code]\n',
            '\n[/code]\n'
        );
    }

    editor_tools_focus_textarea();
}
