<?php
/*
  Plugin Name: OpenLDAP Login
  Version: 0.1.0
  Description: 使用 OpenLDAP 认证 WordPress 用户
  Plugin URI: https://github.com/aidistan/wp-openldap-login
  Author: Aidi Stan
  Author URI: http://github.com/aidistan
*/

class OpenLDAPLogin {
	static $instance = false;
	const VERSION = '0.1.0';

	var $prefix = 'oll_';
	var $settings = array();
	var $ldap;
	var $network_version = null;

	public function __construct() {
		$this->settings = $this->get_settings_obj($this->prefix);

		add_action('admin_init', array($this, 'save_settings'));

		if ($this->is_network_version()) {
			add_action('network_admin_menu', array($this, 'menu'));
		}
		else {
			add_action('admin_menu', array($this, 'menu'));
		}

		if (str_true($this->get_setting('enabled'))) {
			// Prior to default authentication
			add_filter('authenticate', array($this, 'authenticate'), 10, 3);
		}

		register_activation_hook(__FILE__, array($this, 'activate'));
		if($this->get_setting('version') !== self::VERSION) {
			$this->activate();
		}
	}

	public static function getInstance() {
		if (!self::$instance) {
		  self::$instance = new self;
		}
		return self::$instance;
	}

	function activate() {
		$this->add_setting('version', self::VERSION);

		// Default settings
		$this->add_setting('enabled', 'false');
		$this->add_setting('account_preffix', 'cn');
		$this->add_setting('account_suffix', 'dc=example,dc=com');
		$this->add_setting('domain_controllers', array('ldap.example.com'));
		$this->add_setting('required_groups', array());
		$this->add_setting('admin_group', '');
		$this->add_setting('editor_group', '');
		$this->add_setting('author_group', '');
		$this->add_setting('default_role', 'subscriber');
		$this->add_setting('ldap_port', 389);
		$this->add_setting('ldap_version', 3);
	}

	function menu() {
		if ($this->is_network_version()) {
			add_submenu_page(
				"settings.php",
				"OpenLDAP Login",
				"OpenLDAP Login",
				'manage_network_plugins',
				"openldap-login",
				array($this, 'admin_page')
			);
		}
		else {
			add_options_page("OpenLDAP Login", "OpenLDAP Login", 'manage_options', "openldap-login", array($this, 'admin_page'));
		}
	}

	function admin_page() {
		include 'wp-openldap-login-admin.php';
	}

	function get_settings_obj() {
		if ($this->is_network_version()) {
			return get_site_option("{$this->prefix}settings", false);
		}
		else {
			return get_option("{$this->prefix}settings", false);
		}
	}

	function set_settings_obj($newobj) {
		if ($this->is_network_version()) {
			return update_site_option("{$this->prefix}settings", $newobj);
		}
		else {
			return update_option("{$this->prefix}settings", $newobj);
		}
	}

	function set_setting($option = false, $newvalue) {
		if ($option === false) return false;

		$this->settings = $this->get_settings_obj($this->prefix);
		$this->settings[$option] = $newvalue;
		return $this->set_settings_obj($this->settings);
	}

	function get_setting($option = false) {
		if ($option === false || !isset($this->settings[$option])) return false;

		return $this->settings[$option];
	}

	function add_setting($option = false, $newvalue) {
		if ($option === false) return false;

		if (isset($this->settings[$option])) {
			return false;
		} else {
			return $this->set_setting($option, $newvalue);
		}
	}

	function get_field_name($setting, $type = 'string') {
		return "{$this->prefix}setting[$setting][$type]";
	}

	function save_settings() {
		if (isset($_REQUEST["{$this->prefix}setting"]) && check_admin_referer('save_oll_settings','save_the_oll')) {
			$new_settings = $_REQUEST["{$this->prefix}setting"];

			foreach ($new_settings as $setting_name => $setting_value) {
				foreach ($setting_value as $type => $value) {
					if ($type == "array") {
						$this->set_setting($setting_name, explode(";", $value));
					} else {
						$this->set_setting($setting_name, $value);
					}
				}
			}

			add_action('admin_notices', array($this, 'saved_admin_notice'));
		}
	}

	function saved_admin_notice(){
    if(!str_true($this->get_setting('enabled'))) {
			echo '<div class="error"><p>OpenLDAP Login is disabled.</p></div>';
    } else {
			echo '<div class="updated"><p>OpenLDAP Login settings have been saved.</p></div>';
		}
	}

	function authenticate($user, $username, $password) {
		// Respect succeeded authentications with high priorities
		if (is_a($user, 'WP_User')) {
			return $user;
		}

		// Check username & password
		if (empty($username) || empty($password)) {
			$error = new WP_Error();

			if (empty($username)) {
				$error->add('empty_username', __('<strong>ERROR</strong>: The username field is empty.'));
			}

			if (empty($password)) {
				$error->add('empty_password', __('<strong>ERROR</strong>: The password field is empty.'));
			}

			return $error;
		}

		// Authenticate the username and the password against LDAP
		if ($this->ldap_auth($username, $password)) {

			// Does user have required groups?
			if ($this->user_has_required_groups($username)) {
				$user = get_user_by('login', $username);
				$user_data = $this->get_user_data($username);

				// Does the user exist already ?
				if (!$user || (strtolower($user->user_login) !== strtolower($username))) {
					// New user
					$user = wp_insert_user($user_data);

					if(is_wp_error($user)) {
						// Failed to create
						return $this->ldap_auth_error("{$this->prefix}login_error", __('<strong>OpenLDAP Login Error</strong>: LDAP credentials are correct but an error occurred when creating the user in WordPress. Actual error: '.$user->get_error_message()));
					}
				} else {
					// Existing user
					$user_data['ID'] = $user->ID;
					$user = wp_update_user($user_data);

					if(is_wp_error($user)) {
						// Failed to update
						return $this->ldap_auth_error("{$this->prefix}login_error", __('<strong>OpenLDAP Login Error</strong>: LDAP credentials are correct but an error occurred when updating the user in WordPress. Actual error: '.$user->get_error_message()));
					}
				}

				// Successful login
				return new WP_User($user);
			} else {
				// Failed to authenticate by required_groups
				return $this->ldap_auth_error("{$this->prefix}login_error", __('<strong>OpenLDAP Login Error</strong>: Your LDAP credentials are correct, but you are not in an authorized LDAP group.'));
			}
		} else {
			// Failed to authenticate
			return false;
		}
	}

	function ldap_auth($username, $password) {
		// Create the connection
		$this->ldap = ldap_connect(join(' ', (array)$this->get_setting('domain_controllers')), (int)$this->get_setting('ldap_port'));
		ldap_set_option($this->ldap, LDAP_OPT_PROTOCOL_VERSION, (int)$this->get_setting('ldap_version'));

		// Bind
		return $result = @ldap_bind(
			$this->ldap,
			$this->get_setting('account_preffix') .'=' . $username . ',' . $this->get_setting('account_suffix'),
			$password
		);
	}

	/**
	 * Prevent modification of the error message by other authenticate hooks
	 * before it is shown to the user
	 *
	 * @param string $code
	 * @param string $message
	 * @return WP_Error
	 */
	function ldap_auth_error($code, $message) {
		remove_all_filters('authenticate');
		return new WP_Error($code, $message);
	}

	function user_has_required_groups($username = false) {
		$required_groups = (array)$this->get_setting('required_groups');
		$required_groups = array_filter($required_groups);

		if (!$username) { return false; }
		if ($this->ldap === false) { return false; }
		if (count($required_groups) == 0) { return true; }

		for ($i = 0; $i < count($required_groups); $i++) {
			if ($this->user_has_group($username, $required_groups[$i])) {
				return true;
			}
		}

		return false;
	}

	function user_has_group($username = false, $group_dn = '') {
		if (!$username) { return false; }
		if ($this->ldap == null) { return false; }

		$result = ldap_search($this->ldap, $group_dn, '(member=' . $this->get_setting('account_preffix') . '=' . $username . ',' . $this->get_setting('account_suffix') . ')');
		$result = ldap_get_entries($this->ldap, $result);

		if ($result['count'] > 0) {
			return true;
		} else {
			return false;
		}
	}

	function get_user_data($username) {
		if ($this->ldap == null) { return false; }

		$user_data = array(
			'user_pass' => md5(microtime()),
			'user_login' => $username,
			'user_nicename' => '',
			'user_email' => '',
			'display_name' => '',
			'first_name' => '',
			'last_name' => ''
		);

		if ($this->user_has_group($username, $this->get_setting('admin_group'))) {
			$user_data['role'] = 'administrator';
		} elseif ($this->user_has_group($username, $this->get_setting('editor_group'))) {
			$user_data['role'] = 'editor';
		} elseif ($this->user_has_group($username, $this->get_setting('author_group'))) {
			$user_data['role'] = 'author';
		} else {
			$user_data['role'] = $this->get_setting('default_role');
		}

		$result = ldap_search($this->ldap, $this->get_setting('account_suffix'), '(' . $this->get_setting('account_preffix') . '=' . $username . ')', array($this->get_setting('account_preffix'), 'sn', 'givenname', 'mail'));
		$userinfo = ldap_get_entries($this->ldap, $result);
		$userinfo = $userinfo[0];

		if(is_array($userinfo)) {
			$user_data['user_nicename'] = strtolower($userinfo['givenname'][0]) . strtolower($userinfo['sn'][0]);
			$user_data['user_email'] 	= $userinfo['mail'][0];
			$user_data['display_name']	= $userinfo['givenname'][0] . ' ' . $userinfo['sn'][0];
			$user_data['first_name']	= $userinfo['givenname'][0];
			$user_data['last_name'] 	= $userinfo['sn'][0];
		}

		return $user_data;
	}

	/**
	 * Returns whether this plugin is currently network activated
	 */
	function is_network_version() {
		if ($this->network_version !== null) {
			return $this->network_version;
		}

		if (!function_exists( 'is_plugin_active_for_network' ) ) {
			require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
		}

		if (is_plugin_active_for_network(plugin_basename(__FILE__))) {
			$this->network_version = true;
		}
		else {
			$this->network_version = false;
		}
		return $this->network_version;
	}
}

if (!function_exists('str_true')) {
	/**
	 * Evaluates natural language strings to boolean equivalent
	 *
	 * Used primarily for handling boolean text provided in shopp() tag options.
	 * All values defined as true will return true, anything else is false.
	 *
	 * Boolean values will be passed through.
	 *
	 * @author Jonathan Davis
	 * @since 0.1.0
	 *
	 * @param string $string The natural language value
	 * @param array $istrue A list strings that are true
	 * @return boolean The boolean value of the provided text
	 **/
	function str_true ( $string, $istrue = array('yes', 'y', 'true','1','on','open') ) {
		if (is_array($string)) return false;
		if (is_bool($string)) return $string;
		return in_array(strtolower($string),$istrue);
	}
}

$OpenLDAPLogin = OpenLDAPLogin::getInstance();
