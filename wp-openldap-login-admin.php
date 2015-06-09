<?php
global $OpenLDAPLogin;

if (isset($_GET['tab'])) {
  $active_tab = $_GET[ 'tab' ];
} else {
  $active_tab = 'simple';
}
?>

<div class="wrap">
  <div id="icon-themes" class="icon32"></div>
  <h2>OpenLDAP Login Settings</h2>

  <h2 class="nav-tab-wrapper">
    <a href="<?php echo add_query_arg( array('tab' => 'simple'), $_SERVER['REQUEST_URI'] ); ?>" class="nav-tab <?php echo $active_tab == 'simple' ? 'nav-tab-active' : ''; ?>">Simple</a>
    <a href="<?php echo add_query_arg( array('tab' => 'advanced'), $_SERVER['REQUEST_URI'] ); ?>" class="nav-tab <?php echo $active_tab == 'advanced' ? 'nav-tab-active' : ''; ?>">Advanced</a>
  </h2>

  <form method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
<?php wp_nonce_field( 'save_oll_settings','save_the_oll' ); ?>
<?php if( $active_tab == "simple" ): ?>
    <h3>Required</h3>
    <p>These are the most basic settings you must configure. Without these, you won't be able to use OpenLDAP Login.</p>
    <table class="form-table">
      <tbody>
        <tr>
          <th scope="row" valign="top">Enable LDAP Authentication</th>
          <td>
            <input type="hidden" name="<?php echo $this->get_field_name('enabled'); ?>" value="false" />
            <label><input type="checkbox" name="<?php echo $this->get_field_name('enabled'); ?>" value="true" <?php if( str_true($this->get_setting('enabled')) ) echo "checked"; ?> /> Enable LDAP login authentication for WordPress. (this one is kind of important)</label>
          </td>
        </tr>
        <tr>
          <th scope="row" valign="top">Account Preffix</th>
          <td>
            <input type="text" name="<?php echo $this->get_field_name('account_preffix'); ?>" value="<?php echo $OpenLDAPLogin->get_setting('account_preffix'); ?>" /><br/>
            For "<b>cn</b>=aidistan,ou=Users,dc=example,dc=com", fill "<b>cn</b>" here.
          </td>
        </tr>
        <tr>
          <th scope="row" valign="top">Account Suffix</th>
          <td>
            <input type="text" name="<?php echo $this->get_field_name('account_suffix'); ?>" value="<?php echo $OpenLDAPLogin->get_setting('account_suffix'); ?>" /><br/>
            For "cn=aidistan,<b>ou=Users,dc=example,dc=com</b>", fill "<b>ou=Users,dc=example,dc=com</b>" here.
          </td>
        </tr>
        <tr>
          <th scope="row" valign="top">Domain Controller(s)</th>
          <td>
            <input type="text" name="<?php echo $this->get_field_name('domain_controllers', 'array'); ?>" value="<?php echo join(';', (array)$OpenLDAPLogin->get_setting('domain_controllers')); ?>" /><br/>
            Separate with semi-colons.
          </td>
        </tr>
      </tbody>
    </table>
    <p>
      <input class="button-primary" type="submit" value="Save Settings" />
    </p>
<?php elseif ( $active_tab == "advanced" ): ?>
    <h3>Roles</h3>
    <p>These settings give you finer control over user roles.</p>
    <table class="form-table" style="margin-bottom: 20px;">
      <tbody>
        <tr>
          <th scope="row" valign="top">Required Groups</th>
          <td>
            <input type="text" name="<?php echo $this->get_field_name('required_groups', 'array'); ?>" value="<?php echo join(';', (array)$OpenLDAPLogin->get_setting('required_groups')); ?>" /><br/>
            The groups, if any, that authenticating LDAP users must belong to. <br/>
            Empty means no group required. Separate with semi-colons.
          </td>
        </tr>
        <tr>
          <th scope="row" valign="top">Administrator Group</th>
          <td>
            <input type="text" name="<?php echo $this->get_field_name('admin_group'); ?>" value="<?php echo $OpenLDAPLogin->get_setting('admin_group'); ?>" /><br/>
            The group, if any, that users belonging to will be administrators.
          </td>
        </tr>
        <tr>
          <th scope="row" valign="top">Editor Group</th>
          <td>
            <input type="text" name="<?php echo $this->get_field_name('editor_group'); ?>" value="<?php echo $OpenLDAPLogin->get_setting('editor_group'); ?>" /><br/>
            The group, if any, that users belonging to will be editors.
          </td>
        </tr>
        <tr>
          <th scope="row" valign="top">Author Group</th>
          <td>
            <input type="text" name="<?php echo $this->get_field_name('author_group'); ?>" value="<?php echo $OpenLDAPLogin->get_setting('author_group'); ?>" /><br/>
            The group, if any, that users belonging to will be authors.
          </td>
        </tr>
        <tr>
          <th scope="row" valign="top">Default User Role</th>
          <td>
            <select name="<?php echo $this->get_field_name('default_role'); ?>">
              <?php wp_dropdown_roles( strtolower($this->get_setting('default_role')) ); ?>
            </select>
          </td>
        </tr>
      </tbody>
    </table>
    <hr />
    <h3>Extraordinary</h3>
    <p>Most users should leave these alone.</p>
    <table class="form-table">
      <tbody>
        <tr>
          <th scope="row" valign="top">LDAP Port</th>
          <td>
            <input type="text" name="<?php echo $this->get_field_name('ldap_port'); ?>" value="<?php echo $OpenLDAPLogin->get_setting('ldap_port'); ?>" />
            Usually 389.
          </td>
        </tr>
        <tr>
          <th scope="row" valign="top">LDAP Version</th>
          <td>
            <input type="text" name="<?php echo $this->get_field_name('ldap_version'); ?>" value="<?php echo $OpenLDAPLogin->get_setting('ldap_version'); ?>" />
            Typically 3
          </td>
        </tr>
      </tbody>
    </table>
    <p><input class="button-primary" type="submit" value="Save Settings" /></p>
<?php endif; ?>
  </form>
</div>
