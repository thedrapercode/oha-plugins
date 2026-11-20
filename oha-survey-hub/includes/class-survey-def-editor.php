<?php
if (!defined('ABSPATH')) exit;

/**
 * Admin editor for hardcoded definition surveys.
 * Saves overrides to WP options; get_survey() checks these before returning PHP defaults.
 *
 * Option format:  oha_survey_def_override_{type}  =>  JSON string of pages array
 * Reset option:   delete the option to restore PHP defaults
 */
class OHA_Survey_Def_Editor {

    private static $survey_labels = [
        'member'             => 'OHA Member',
        'haunt_owner'        => 'Haunt Owner',
        'scare_actor'        => 'Scare Actor',
        'behind_scenes'      => 'Behind the Scenes',
        'home_haunt'         => 'Home Haunt',
        'industry_deep_dive' => 'Industry Deep Dive',
        'convention_planning' => 'Convention Planning',
    ];

    // ------------------------------------------------------------------
    // Admin page render
    // ------------------------------------------------------------------

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions.');
        }

        // Handle save / reset actions
        if (isset($_POST['oha_def_action']) && check_admin_referer('oha_def_editor_nonce', 'oha_def_nonce')) {
            $type = sanitize_key($_POST['survey_type'] ?? '');
            if (isset(self::$survey_labels[$type])) {
                if ($_POST['oha_def_action'] === 'save') {
                    $raw = stripslashes($_POST['pages_json'] ?? '');
                    $decoded = json_decode($raw, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        update_option('oha_survey_def_override_' . $type, wp_json_encode($decoded));
                        $notice = ['type' => 'success', 'msg' => 'Questions saved for ' . self::$survey_labels[$type] . '.'];
                    } else {
                        $notice = ['type' => 'error', 'msg' => 'Invalid JSON. Changes not saved. Error: ' . json_last_error_msg()];
                    }
                } elseif ($_POST['oha_def_action'] === 'reset') {
                    delete_option('oha_survey_def_override_' . $type);
                    $notice = ['type' => 'success', 'msg' => self::$survey_labels[$type] . ' reset to built-in defaults.'];
                }
            }
        }

        $active_type = sanitize_key($_GET['def_type'] ?? 'member');
        if (!isset(self::$survey_labels[$active_type])) {
            $active_type = 'member';
        }

        // Load current definition (may already include override via get_survey())
        $survey = OHA_Survey_Definitions::get_survey($active_type);
        $is_overridden = !empty($survey['overridden']);

        // Build the pages JSON for the editor -- use the override raw value if set, else serialize defaults
        $override_raw = get_option('oha_survey_def_override_' . $active_type, '');
        if ($override_raw) {
            $editor_json = json_encode(json_decode($override_raw, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            $editor_json = json_encode($survey['pages'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        $base_url = admin_url('admin.php?page=oha-survey-def-editor');
        ?>
        <div class="wrap">
            <h1>Edit Survey Questions</h1>
            <p style="color:#666;max-width:700px;">
                Edit the questions for each survey type below. Changes are saved to the database as overrides --
                the built-in PHP defaults are never modified. Use <strong>Reset to Defaults</strong> to undo all changes for a survey.
            </p>

            <?php if (!empty($notice)): ?>
                <div class="notice notice-<?php echo $notice['type'] === 'success' ? 'success' : 'error'; ?> is-dismissible">
                    <p><?php echo esc_html($notice['msg']); ?></p>
                </div>
            <?php endif; ?>

            <nav class="nav-tab-wrapper" style="margin-bottom:20px;">
                <?php foreach (self::$survey_labels as $slug => $label):
                    $has_override = (bool) get_option('oha_survey_def_override_' . $slug, '');
                ?>
                    <a href="<?php echo esc_url($base_url . '&def_type=' . $slug); ?>"
                       class="nav-tab<?php echo $active_type === $slug ? ' nav-tab-active' : ''; ?>">
                        <?php echo esc_html($label); ?>
                        <?php if ($has_override): ?>
                            <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#1a8f1a;margin-left:4px;vertical-align:middle;" title="Override active"></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div style="max-width:900px;">

                <div style="background:#f9f9f9;border:1px solid #ccc;border-radius:4px;padding:14px 18px;margin-bottom:16px;">
                    <strong style="font-size:15px;"><?php echo esc_html(self::$survey_labels[$active_type]); ?></strong>
                    <?php if ($is_overridden): ?>
                        <span style="margin-left:10px;background:#1a8f1a;color:#fff;padding:2px 8px;border-radius:3px;font-size:12px;">Override active</span>
                    <?php else: ?>
                        <span style="margin-left:10px;background:#888;color:#fff;padding:2px 8px;border-radius:3px;font-size:12px;">Using built-in defaults</span>
                    <?php endif; ?>
                    <p style="margin:8px 0 0;color:#555;font-size:13px;">
                        Editing the <code>pages</code> array. Each page has a <code>title</code> and a <code>questions</code> array.
                        Each question needs: <code>id</code>, <code>question</code>, <code>type</code> (radio/checkbox/scale/textarea/text), <code>required</code> (true/false).
                        Radio and checkbox questions also need an <code>options</code> object (<code>{"key":"Label"}</code>).
                    </p>
                </div>

                <form method="post" id="oha-def-editor-form">
                    <?php wp_nonce_field('oha_def_editor_nonce', 'oha_def_nonce'); ?>
                    <input type="hidden" name="survey_type" value="<?php echo esc_attr($active_type); ?>">

                    <textarea name="pages_json" id="oha-def-pages-json"
                        style="width:100%;height:520px;font-family:monospace;font-size:13px;border:1px solid #ccc;border-radius:3px;padding:10px;box-sizing:border-box;line-height:1.5;"
                        spellcheck="false"><?php echo esc_textarea($editor_json); ?></textarea>

                    <div id="oha-def-json-error" style="display:none;background:#fce8e8;border:1px solid #d9534f;border-radius:3px;padding:8px 12px;margin-top:8px;color:#a00;"></div>

                    <div style="display:flex;gap:12px;margin-top:12px;align-items:center;">
                        <button type="submit" name="oha_def_action" value="save" class="button button-primary" id="oha-def-save-btn">
                            Save Questions
                        </button>
                        <?php if ($is_overridden): ?>
                            <button type="submit" name="oha_def_action" value="reset" class="button button-secondary"
                                onclick="return confirm('Reset to built-in defaults and discard all custom changes for this survey?');">
                                Reset to Defaults
                            </button>
                        <?php endif; ?>
                        <span style="color:#888;font-size:13px;" id="oha-def-status"></span>
                    </div>
                </form>

            </div>
        </div>

        <script>
        (function() {
            var textarea = document.getElementById('oha-def-pages-json');
            var errBox   = document.getElementById('oha-def-json-error');
            var saveBtn  = document.getElementById('oha-def-save-btn');
            var status   = document.getElementById('oha-def-status');

            function validateJson() {
                try {
                    var val = textarea.value.trim();
                    if (val) JSON.parse(val);
                    errBox.style.display = 'none';
                    saveBtn.disabled = false;
                    return true;
                } catch (e) {
                    errBox.textContent = 'JSON parse error: ' + e.message;
                    errBox.style.display = 'block';
                    saveBtn.disabled = true;
                    return false;
                }
            }

            // Format JSON on load
            try {
                var parsed = JSON.parse(textarea.value);
                textarea.value = JSON.stringify(parsed, null, 2);
            } catch(e) {}

            textarea.addEventListener('input', function() { validateJson(); });

            document.getElementById('oha-def-editor-form').addEventListener('submit', function(e) {
                if (!validateJson()) {
                    e.preventDefault();
                    return;
                }
                // Pretty-print before submit
                try {
                    textarea.value = JSON.stringify(JSON.parse(textarea.value), null, 2);
                } catch(e) {}
            });
        })();
        </script>
        <?php
    }
}
