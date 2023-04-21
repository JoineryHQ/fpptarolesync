<?php

/**
 * The core plugin class.
 */
class FpptarolesyncSettings {

  public static function admin_init() {

    // Register a new setting for "fpptarolesync_options".
    register_setting('fpptarolesync', 'fpptarolesync_options');

    // Register a new section in our "settings" page.
    add_settings_section(
      'section_default',
      '',
      ['FpptarolesyncSettings', 'section_default_callback'],
      'fpptarolesync'
    );

    // Register our settings fields in the "section_default" section, inside our "settings" page.
    add_settings_field(
      'fpptarolesync_field_role', // As of WP 4.6 this value is used only internally.
      // Use $args' label_for to populate the id inside the callback.
      'Managed Role *',
      ['FpptarolesyncSettings', 'fpptarolesync_field_role_callback'],
      'fpptarolesync',
      'section_default',
      array(
        'label_for' => 'fpptarolesync_field_role',
        'name' => 'role',
        'class' => '',
      )
    );
    add_settings_field(
      'fpptarolesync_field_membership_type_ids', // As of WP 4.6 this value is used only internally.
      // Use $args' label_for to populate the id inside the callback.
      'Relevant Membership Types *',
      ['FpptarolesyncSettings', 'fpptarolesync_field_membership_type_ids_callback'],
      'fpptarolesync',
      'section_default',
      array(
        'label_for' => 'fpptarolesync_field_membership_type_ids',
        'name' => 'membership_type_ids',
        'class' => '',
      )
    );
    add_settings_field(
      'fpptarolesync_field_logging', // As of WP 4.6 this value is used only internally.
      // Use $args' label_for to populate the id inside the callback.
      'Log to File',
      ['FpptarolesyncSettings', 'fpptarolesync_field_logging_callback'],
      'fpptarolesync',
      'section_default',
      array(
        'label_for' => '',
        'name' => 'logging',
        'class' => '',
      )
    );
  }

  /**
   * Membership Types field callback function.
   *
   * WordPress has magic interaction with the following keys: label_for, class.
   * - the "label_for" key value is used for the "for" attribute of the <label>.
   * - the "class" key value is used for the "class" attribute of the <tr> containing the field.
   * Note: you can add custom key value pairs to be used inside your callbacks.
   *
   * @param array $args
   */
  public static function fpptarolesync_field_membership_type_ids_callback($args) {
    // Get the our registered options/settings
    $options = get_option('fpptarolesync_options');
    // Get list of civicrm active membership types.
    civicrm_initialize();
    $membershipTypes = \Civi\Api4\MembershipType::get()
      ->setCheckPermissions(FALSE)
      ->addWhere('is_active', '=', TRUE)
      ->setLimit(0)
      ->execute();
    foreach ($membershipTypes as $membershipType) {
      $membershipTypeId = $membershipType['id'];
      $checked = in_array($membershipTypeId, ($options['membership_type_ids'] ?? [])) ? 'checked' : '';
      ?>
      <input
        type="checkbox"
        name="fpptarolesync_options[<?= esc_attr($args['name']); ?>][]"
        <?= $checked; ?> value="<?= $membershipTypeId; ?>"
        > <?= $membershipType['name']; ?><br/>
        <?php
      }
      ?>
    <p class="description">
      The CiviCRM membership types which will be monitored in the adding or removing of the Managed Role.
    </p>
    <?php
  }

  public static function fpptarolesync_field_role_callback($args) {
    $options = get_option('fpptarolesync_options');
    $selectedRole = $options['role'] ?? NULL;
    ?>
    <select
      id="<?= esc_attr($args['label_for']); ?>"
      name="fpptarolesync_options[<?= esc_attr($args['name']); ?>]"
      >
      <option value="">- [NONE] -</option>
      <?php
      echo wp_dropdown_roles($selectedRole);
      ?>
    </select>
    <p class="description">
      The WordPress user role to be managed (automatically added to or removed from all WordPress users) by this plugin.
    </p>
    <?php
  }

  public static function fpptarolesync_field_logging_callback($args) {
    $options = get_option('fpptarolesync_options');
    $isLogging = $options['logging'] ?? 0;

    $radios = [
      1 => 'Yes',
      0 => 'No',
    ];
    foreach ($radios as $value => $label) {
      $checked = ($isLogging == $value ? 'checked' : '');
      ?>
      <input
        type="radio"
        name="fpptarolesync_options[<?= esc_attr($args['name']); ?>]"
        id="<?= esc_attr($args['name']); ?>-<?= $value; ?>"
      <?= $checked; ?> value="<?= $value; ?>"
        >
      <label for="logging-<?= $value; ?>"><?= $label; ?></label> <br/>
      <?php
    }
    ?>
    <p class="description">
      If "yes", this plugin will log its actions in a custom log file under CiviCRM's ConfigAndLog/ directory. The log filename will contain the string 'fpptarolesync'.
    </p>
    <?php
  }

  public static function addPluginAdminMenu() {
    add_submenu_page(
      'users.php',
      'CiviCRM Membership Role Sync for FPPTA: Settings',
      'CiviCRM Membership Role Sync for FPPTA',
      'edit_users',
      'fpptarolesync_settings',
      ['FpptarolesyncSettings', 'settings_html']
    );
  }

  public static function settings_html() {
    // check user capabilities
    if (!current_user_can('edit_users')) {
      return;
    }

    // add error/update messages
    // check if the user have submitted the settings
    // WordPress will add the "settings-updated" $_GET parameter to the url
    if (isset($_GET['settings-updated'])) {
      // add settings saved message with the class of "updated"
      add_settings_error('fpptarolesync_messages', 'fpptarolesync_message', __('Settings saved.', 'fpptarolesync'), 'updated');
    }

    // show error/update messages
    settings_errors('fpptarolesync_messages');
    ?>
    <div class="wrap">
      <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
      <form action="options.php" method="post">
        <?php
        // output our settings fields
        settings_fields('fpptarolesync');
        // output setting sections and their fields
        do_settings_sections('fpptarolesync');
        // output save settings button
        submit_button('Save Settings');
        ?>
      </form>
    </div>
    <?php
  }

  static function section_default_callback($args) {
    $pluginData = FpptarolesyncUtil::getPluginData();
    ?>
    <div id="<?php echo esc_attr($args['id']); ?>">
      <p>
        See also: <a href="<?= $pluginData['PluginURI']; ?>" target="_blank">Documentation: <?= $pluginData['Name']; ?></a>.
      </p>
      <p>
        * Settings marked with an asterisk are required. This plugin will take no action if any of these required settings are not set.
      </p>
    </p>
    <?php
  }

}
