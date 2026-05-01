<?php
/**
 * Core: Sarkari Engine Admin Control Center
 * Architecture: WordPress Settings API with Premium UI
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ==========================================
// 1. REGISTER ADMIN MENU
// ==========================================
add_action( 'admin_menu', 'cppm_register_admin_menu' );
function cppm_register_admin_menu() {
    add_menu_page(
        'Sarkari Engine',        // Page Title
        'Sarkari Engine',        // Menu Title
        'manage_options',        // Capability required
        'cppm_sarkari_engine',   // Menu Slug
        'cppm_render_admin_dashboard', // Callback Function
        'dashicons-superhero',   // Icon
        2                        // Position (right at the top)
    );
}

// ==========================================
// 2. REGISTER SETTINGS
// ==========================================
add_action( 'admin_init', 'cppm_register_settings' );
function cppm_register_settings() {
    register_setting( 'cppm_core_settings', 'cppm_login_logo' );
    register_setting( 'cppm_core_settings', 'cppm_ui_btn_color' );
    register_setting( 'cppm_core_settings', 'cppm_ui_active_bg' );
    register_setting( 'cppm_core_settings', 'cppm_enable_abcjs' );
    register_setting( 'cppm_core_settings', 'cppm_custom_css' );
}

// ==========================================
// 3. RENDER PREMIUM DASHBOARD UI
// ==========================================
function cppm_render_admin_dashboard() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    
    // Check if settings were updated
    if ( isset( $_GET['settings-updated'] ) ) {
        add_settings_error( 'cppm_messages', 'cppm_message', 'Sarkari Engine settings saved successfully.', 'updated' );
    }
    
    // Get existing options (or defaults)
    $logo_url   = get_option( 'cppm_login_logo', 'https://sarkarimusician.store/wp-content/uploads/2026/04/logo-1.png' );
    $btn_color  = get_option( 'cppm_ui_btn_color', '#2874f0' );
    $bg_color   = get_option( 'cppm_ui_active_bg', '#f0f7ff' );
    $abcjs      = get_option( 'cppm_enable_abcjs', 'yes' );
    $custom_css = get_option( 'cppm_custom_css', '' );
    ?>
    
    <div class="wrap">
        <style>
            .cppm-admin-header { background: #0f172a; color: #fff; padding: 30px; border-radius: 12px 12px 0 0; margin-top: 20px; display: flex; align-items: center; gap: 15px; }
            .cppm-admin-header h1 { color: #fff; margin: 0; font-size: 24px; font-weight: 800; }
            .cppm-admin-body { background: #fff; padding: 40px; border-radius: 0 0 12px 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; border-top: none; }
            .cppm-form-table { width: 100%; border-collapse: collapse; }
            .cppm-form-table th { text-align: left; padding: 25px 20px 25px 0; width: 250px; border-bottom: 1px solid #f1f5f9; vertical-align: top; font-weight: 600; color: #1e293b; font-size: 15px; }
            .cppm-form-table td { padding: 25px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
            .cppm-form-table tr:last-child th, .cppm-form-table tr:last-child td { border-bottom: none; }
            .cppm-input { width: 100%; max-width: 400px; padding: 10px 15px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; box-shadow: none; outline: none; transition: 0.2s; }
            .cppm-input:focus { border-color: #2874f0; box-shadow: 0 0 0 3px rgba(40,116,240,0.1); }
            .cppm-color-picker { width: 60px; height: 40px; padding: 2px; border-radius: 6px; border: 1px solid #cbd5e1; cursor: pointer; }
            .cppm-btn-save { background: #2874f0; color: #fff; border: none; padding: 12px 30px; font-size: 16px; font-weight: bold; border-radius: 8px; cursor: pointer; margin-top: 30px; transition: 0.2s; box-shadow: 0 4px 10px rgba(40,116,240,0.2); }
            .cppm-btn-save:hover { background: #1d4ed8; transform: translateY(-1px); box-shadow: 0 6px 15px rgba(40,116,240,0.3); }
            .cppm-description { font-size: 13px; color: #64748b; margin-top: 6px; display: block; }
        </style>

        <div class="cppm-admin-header">
            <span class="dashicons dashicons-superhero" style="font-size: 32px; width: 32px; height: 32px;"></span>
            <div>
                <h1>Sarkari Engine Control Center</h1>
                <span style="color: #94a3b8; font-size: 14px;">Manage global aesthetics, learning tools, and platform settings.</span>
            </div>
        </div>

        <div class="cppm-admin-body">
            <?php settings_errors( 'cppm_messages' ); ?>
            
            <form method="post" action="options.php">
                <?php settings_fields( 'cppm_core_settings' ); ?>
                
                <table class="cppm-form-table">
                    <tr>
                        <th>
                            Custom Login Logo URL
                            <span class="cppm-description">Displayed on the Student Login and Register screens.</span>
                        </th>
                        <td>
                            <input type="url" name="cppm_login_logo" class="cppm-input" value="<?php echo esc_url( $logo_url ); ?>" placeholder="https://yourdomain.com/logo.png" />
                        </td>
                    </tr>

                    <tr>
                        <th>
                            Global Brand Color
                            <span class="cppm-description">The primary color used for buttons, icons, and active states. (Default: Flipkart Blue #2874f0)</span>
                        </th>
                        <td>
                            <input type="color" name="cppm_ui_btn_color" class="cppm-color-picker" value="<?php echo esc_attr( $btn_color ); ?>" />
                        </td>
                    </tr>

                    <tr>
                        <th>
                            Active Background Color
                            <span class="cppm-description">The light background color used when a menu item is selected. (Default: Light Blue #f0f7ff)</span>
                        </th>
                        <td>
                            <input type="color" name="cppm_ui_active_bg" class="cppm-color-picker" value="<?php echo esc_attr( $bg_color ); ?>" />
                        </td>
                    </tr>

                    <tr>
                        <th>
                            Enable ABCjs Engine
                            <span class="cppm-description">Loads the Javascript engine required to render dynamic sheet music. Turn off if not teaching music.</span>
                        </th>
                        <td>
                            <label style="display: flex; align-items: center; gap: 10px; font-weight: bold; cursor: pointer;">
                                <input type="checkbox" name="cppm_enable_abcjs" value="yes" <?php checked( $abcjs, 'yes' ); ?> style="width: 20px; height: 20px;" />
                                Enable Live Sheet Music Rendering
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th>
                            Classroom Custom CSS
                            <span class="cppm-description">Inject custom styles directly into the Video Player and Classroom area without editing theme files.</span>
                        </th>
                        <td>
                            <textarea name="cppm_custom_css" class="cppm-input" style="height: 150px; font-family: monospace; max-width: 600px;" placeholder="/* Enter CSS here */"><?php echo esc_textarea( $custom_css ); ?></textarea>
                        </td>
                    </tr>
                </table>

                <?php submit_button( 'Save Engine Settings', 'cppm-btn-save', 'submit', false ); ?>
            </form>
        </div>
    </div>
    <?php
}