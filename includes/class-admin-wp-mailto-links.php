<?php
if (!class_exists('Admin_WP_Mailto_Links')):

/**
 * Class Admin_WP_Mailto_Links
 * @package WP_Mailto_Links
 * @category WordPress Plugins
 */
class Admin_WP_Mailto_Links {

	/**
	 * Current version
	 * @var string
	 */
	protected $version = '1.2.0';

	/**
	 * Used as prefix for options entry and could be used as text domain (for translations)
	 * @var string
	 */
	protected $domain = 'WP_Mailto_Links';

	/**
	 * Name of the options
	 * @var string
	 */
	protected $options_name = 'WP_Mailto_Links_options';

	/**
	 * Options to be saved
	 * @var array
	 */
	protected $options = array(
		'convert_emails' => 1,
		'protect' => 1,
		'filter_body' => 1,
		'filter_posts' => 1,
		'filter_comments' => 1,
		'filter_widgets' => 1,
		'filter_rss' => 1,
		'filter_head' => 1,
		'protection_text' => '*protected email*',
		'icon' => 0,
		'image_no_icon' => 0,
		'no_icon_class' => 'no-mail-icon',
		'class_name' => 'mail-link',
		'widget_logic_filter' => 0,
		'own_admin_menu' => 0,
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		// set option values
		$this->set_options();

		// set uninstall hook
		if (function_exists('register_deactivation_hook')) {
			register_deactivation_hook(WP_MAILTO_LINKS_FILE, array($this, 'deactivation'));
		}

		// add actions
		add_action('admin_init', array($this, 'admin_init'));
		add_action('admin_menu', array($this, 'admin_menu'));
	}

	/**
	 * Set options from save values or defaults
	 */
	private function set_options($saved_options = null) {
		if ($saved_options === null) {
			// set options
			$saved_options = get_option($this->options_name);

			// backwards compatible (old values)
			if (empty($saved_options)) {
				$saved_options = get_option($this->domain . 'options');
			}
		}

		// set all options
		if (!empty($saved_options)) {
			foreach ($saved_options AS $key => $value) {
				$this->options[$key] = $value;
			}
		}

		// set widget_content filter of Widget Logic plugin
		$widget_logic_opts = get_option('widget_logic');
		if (is_array($widget_logic_opts) && key_exists('widget_logic-options-filter', $widget_logic_opts)) {
			$this->options['widget_logic_filter'] = ($widget_logic_opts['widget_logic-options-filter'] == 'checked') ? 1 : 0;
		}
	}

	/**
	 * Method for test purpuses
	 */
	public function __options($values = null) {
		if (class_exists('Test_WP_Mailto_Links')) {
			if ($values !== null) {
				$this->set_options($values);
			}

			return $this->options;
		}
	}

	/**
	 * Deactivation plugin
	 */
	public function deactivation() {
		delete_option($this->options_name);
		unregister_setting($this->domain, $this->options_name);
	}

	/**
	 * admin_init action
	 */
	public function admin_init() {
		// register settings
		register_setting($this->domain, $this->options_name);

		// notice
		add_action('admin_notices', array($this, 'show_notices'));
	}

	/**
	 * admin_menu action
	 */
	public function admin_menu() {
		$page = sanitize_key($this->domain);

		// add page and menu item
		if ($this->options['own_admin_menu']) {
		// create main menu item
			$page_hook = add_menu_page(__('WP Mailto Links', $this->domain), __('WP Mailto Links', $this->domain),
								'manage_options', $page, array($this, 'show_options_page'),
								plugins_url('images/icon-wp-mailto-links-16.png', WP_MAILTO_LINKS_FILE));
		} else {
		// create submenu item under "Settings"
			$page_hook = add_options_page(__('WP Mailto Links', $this->domain), __('WP Mailto Links', $this->domain),
								'manage_options', $page, array($this, 'show_options_page'));
		}

		// load plugin page
		add_action('load-' . $page_hook, array($this, 'load_options_page'));
	}

	/* -------------------------------------------------------------------------
	 *  Admin Options Page
	 * ------------------------------------------------------------------------*/

	/**
	 * show notices
	 */
	public function show_notices() {
		if (isset($_GET['page']) && $_GET['page'] == sanitize_key($this->domain) && is_plugin_active('email-encoder-bundle/email-encoder-bundle.php')) {
			echo '<div class="error fade"><p>';
			_e('<strong>Warning:</strong> "Email Encoder Bundle"-plugin is also activated, which could cause conflicts on encoding email addresses and mailto links.', $this->domain);
			echo '</p></div>';
		}
	}

	/**
	 * Load admin options page
	 */
	public function load_options_page() {
		// add script
		wp_enqueue_script('mailto_links_admin', plugins_url('js/admin-wp-mailto-links.js', WP_MAILTO_LINKS_FILE), array('jquery'), $this->version);
		// set dashboard postbox
		wp_enqueue_script('dashboard');

		// add help tabs
		$this->add_help_tabs();

		// screen settings
		if (function_exists('add_screen_option')) {
			add_screen_option('layout_columns', array(
				'max' => 2,
				'default' => 2
			));
		}

		// add meta boxes
		add_meta_box('general_settings', __('General Settings'), array($this, 'show_meta_box_content'), null, 'normal', 'core', array('general_settings'));
		add_meta_box('style_settings', __('Style Settings'), array($this, 'show_meta_box_content'), null, 'normal', 'core', array('style_settings'));
		add_meta_box('admin_settings', __('Admin Settings'), array($this, 'show_meta_box_content'), null, 'normal', 'core', array('admin_settings'));
		add_meta_box('other_plugins', __('Other Plugins'), array($this, 'show_meta_box_content'), null, 'side', 'core', array('other_plugins'));
	}

	/**
	 * Show admin options page
	 */
	public function show_options_page() {
		global $wp_version;
		global $screen_layout_columns;

		$screen = get_current_screen();

		if (is_callable(array($screen, 'get_columns'))) {
			$columns = $screen->get_columns();
		} else {
			$columns = $screen_layout_columns;
		}
		// set dashboard style for wp < 3.4.0
		if (version_compare(preg_replace('/-.*$/', '', $wp_version), '3.4.0', '<')) {
?>
		<style type="text/css" data-js="screen-columns">
			#poststuff #post-body.columns-2 { margin-right:300px; }
			#post-body.columns-2 #postbox-container-1 { float:right; margin-right:-300px; width:280px; }
			#post-body.columns-1 #postbox-container-1 { display:none; }
			#poststuff .postbox-container { width:100%; }
		</style>
<?php
		}

		$this->set_options();
?>
		<div class="wrap">
			<div class="icon32" id="icon-options-custom" style="background:url(<?php echo plugins_url('images/icon-wp-mailto-links.png', WP_MAILTO_LINKS_FILE) ?>) no-repeat 50% 50%"><br></div>
			<h2><?php echo get_admin_page_title() ?></h2>

			<?php if ($this->options['own_admin_menu'] && isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true'): ?>
			<div class="updated settings-error" id="setting-error-settings_updated">
				<p><strong><?php _e('Settings saved.' ) ?></strong></p>
			</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields($this->domain); ?>

				<input type="hidden" name="<?php echo $this->domain ?>_nonce" value="<?php echo wp_create_nonce($this->domain) ?>" />
				<?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false); ?>
				<?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false); ?>

				<div id="poststuff">
					<div id="post-body" class="metabox-holder columns-<?php echo 1 == $columns ? '1' : '2'; ?>">
						<!--<div id="post-body-content"></div>-->

						<div id="postbox-container-1" class="postbox-container">
							<?php do_meta_boxes('', 'side', ''); ?>
						</div>

						<div id="postbox-container-2" class="postbox-container">
							<?php do_meta_boxes('', 'normal', ''); ?>
							<?php do_meta_boxes('', 'advanced', ''); ?>
						</div>
					</div> <!-- #post-body -->
				</div> <!-- #poststuff -->
			</form>
		</div>
<?php
	}

	/**
	 * Show content of metabox (callback)
	 * @param array $post
	 * @param array $meta_box
	 */
	public function show_meta_box_content($post, $meta_box) {
		$key = $meta_box['args'][0];
		$options = $this->options;

		if ($key === 'general_settings') {
?>
			<fieldset class="options">
				<table class="form-table">
				<tr>
					<th><?php _e('Protect mailto links', $this->domain) ?></th>
					<td><label><input type="checkbox" id="<?php echo $this->options_name ?>[protect]" name="<?php echo $this->options_name ?>[protect]" value="1" <?php checked('1', (int) $options['protect']); ?> />
						<span><?php _e('Protect mailto links against spambots', $this->domain) ?></span></label>
					</td>
				</tr>
				<tr>
					<th><?php _e('Protect plain emails', $this->domain) ?></th>
					<td><label><input type="radio" id="<?php echo $this->options_name ?>[convert_emails]" name="<?php echo $this->options_name ?>[convert_emails]" value="0" <?php checked('0', (int) $options['convert_emails']); ?> />
						<span><?php _e('No, keep plain emails as they are', $this->domain) ?></span></label>
						<br/><label><input type="radio" id="<?php echo $this->options_name ?>[convert_emails]" name="<?php echo $this->options_name ?>[convert_emails]" value="1" <?php checked('1', (int) $options['convert_emails']); ?> />
						<span><?php _e('Yes, protect plain emails with protection text *', $this->domain) ?></span> <span class="description"><?php _e('(recommended)', $this->domain) ?></span></label>
						<br/><label><input type="radio" id="<?php echo $this->options_name ?>[convert_emails]" name="<?php echo $this->options_name ?>[convert_emails]" value="2" <?php checked('2', (int) $options['convert_emails']); ?> />
						<span><?php _e('Yes, convert plain emails to mailto links', $this->domain) ?></span></label>
					</td>
				</tr>
				<tr>
					<th><?php _e('Options have effect on', $this->domain) ?></th>
					<td>
						<label><input type="checkbox" name="<?php echo $this->options_name ?>[filter_body]" id="filter_body" value="1" <?php checked('1', (int) $options['filter_body']); ?> />
						<span><?php _e('All contents', $this->domain) ?></span> <span class="description"><?php _e('(the whole <code>&lt;body&gt;</code>)', $this->domain) ?></span></label>
						<br/>&nbsp;&nbsp;<label><input type="checkbox" name="<?php echo $this->options_name ?>[filter_posts]" id="filter_posts" value="1" <?php checked('1', (int) $options['filter_posts']); ?> />
								<span><?php _e('Post contents', $this->domain) ?></span></label>
						<br/>&nbsp;&nbsp;<label><input type="checkbox" name="<?php echo $this->options_name ?>[filter_comments]" id="filter_comments" value="1" <?php checked('1', (int) $options['filter_comments']); ?> />
								<span><?php _e('Comments', $this->domain) ?></span></label>
						<br/>&nbsp;&nbsp;<label><input type="checkbox" name="<?php echo $this->options_name ?>[filter_widgets]" id="filter_widgets" value="1" <?php checked('1', (int) $options['filter_widgets']); ?> />
								<span><?php if ($this->options['widget_logic_filter']) { _e('All widgets (uses the <code>widget_content</code> filter of the Widget Logic plugin)', $this->domain); } else { _e('All text widgets', $this->domain); } ?></span></label>
					</td>
				</tr>
				<tr>
					<th><?php _e('Also protect...', $this->domain) ?></th>
					<td><label><input type="checkbox" name="<?php echo $this->options_name ?>[filter_head]" value="1" <?php checked('1', (int) $options['filter_head']); ?> />
							<span><?php _e('<code>&lt;head&gt;</code>-section by replacing emails with protection text *', $this->domain) ?></span></label>
						<br/><label><input type="checkbox" name="<?php echo $this->options_name ?>[filter_rss]" value="1" <?php checked('1', (int) $options['filter_rss']); ?> />
							<span><?php _e('RSS feed by replacing emails with protection text *', $this->domain) ?></span></label>
					</td>
				</tr>
				<tr>
					<th><?php _e('Set protection text *', $this->domain) ?></th>
					<td><label><input type="text" id="protection_text" name="<?php echo $this->options_name ?>[protection_text]" value="<?php echo $options['protection_text']; ?>" />
						<span class="description"><?php _e('This text will be shown for protected emails', $this->domain) ?></span></label>
					</td>
				</tr>
				</table>
			</fieldset>
			<p class="submit">
				<input class="button-primary" type="submit" disabled="disabled" value="<?php _e('Save Changes' ) ?>" />
			</p>
			<br class="clear" />
<?php
		} elseif ($key === 'style_settings') {
?>
			<fieldset class="options">
				<table class="form-table">
				<tr>
					<th><?php _e('Show icon', $this->domain) ?>
					</th>
					<td>
						<div>
							<div style="width:15%;float:left">
								<label><input type="radio" name="<?php echo $this->options_name ?>[icon]" value="0" <?php checked('0', (int) $options['icon']); ?> />
								<span><?php _e('No icon', $this->domain) ?></span></label>
							<?php for ($x = 1; $x <= 25; $x++): ?>
								<br/>
								<label title="<?php echo sprintf(__( 'Icon %1$s: choose this icon to show for all mailto links or add the class \'mail-icon-%1$s\' to a specific link.' ), $x ) ?>"><input type="radio" name="<?php echo $this->options_name ?>[icon]" value="<?php echo $x ?>" <?php checked($x, (int) $options['icon']); ?> />
								<img src="<?php echo plugins_url('images/mail-icon-'. $x .'.png', WP_MAILTO_LINKS_FILE)  ?>" /></label>
								<?php if ($x % 5 == 0): ?>
							</div>
							<div style="width:12%;float:left">
								<?php endif; ?>
							<?php endfor; ?>
							</div>
							<div style="width:29%;float:left;"><span class="description"><?php _e('Example:', $this->domain) ?></span>
								<br/><img src="<?php echo plugins_url('images/link-icon-example.png', WP_MAILTO_LINKS_FILE) ?>"	/>
							</div>
							<br style="clear:both" />
						</div>
					</td>
				</tr>
				<tr>
					<th><?php _e('Skip images', $this->domain) ?></th>
					<td><label><input type="checkbox" name="<?php echo $this->options_name ?>[image_no_icon]" value="1" <?php checked('1', (int) $options['image_no_icon']); ?> />
						<span><?php _e('Do not show icon for mailto links containing an image', $this->domain) ?></span></label>
					</td>
				</tr>
				<tr>
					<th><?php _e('No-icon Class', $this->domain) ?></th>
					<td><label><input type="text" id="<?php echo $this->options_name ?>[no_icon_class]" name="<?php echo $this->options_name ?>[no_icon_class]" value="<?php echo $options['no_icon_class']; ?>" />
						<span class="description"><?php _e('Use this class when a mailto link should not show an icon', $this->domain) ?></span></label>
					</td>
				</tr>
				<tr>
					<th><?php _e('Additional Classes (optional)', $this->domain) ?></th>
					<td><label><input type="text" id="<?php echo $this->options_name ?>[class_name]" name="<?php echo $this->options_name ?>[class_name]" value="<?php echo $options['class_name']; ?>" />
						<span class="description"><?php _e('Add extra classes to mailto links (or leave blank)', $this->domain) ?></span></label></td>
				</tr>
				</table>
			</fieldset>
			<p class="submit">
				<input class="button-primary" type="submit" disabled="disabled" value="<?php _e('Save Changes' ) ?>" />
			</p>
			<br class="clear" />
<?php
		} elseif ($key === 'admin_settings') {
?>
			<fieldset class="options">
				<table class="form-table">
				<tr>
					<th><?php _e('Admin menu position', $this->domain) ?></th>
					<td><label><input type="checkbox" id="<?php echo $this->options_name ?>[own_admin_menu]" name="<?php echo $this->options_name ?>[own_admin_menu]" value="1" <?php checked('1', (int) $options['own_admin_menu']); ?> /> <span><?php _e('Show as main menu item', $this->domain) ?></span> <span class="description">(when disabled item will be shown under "General settings")</span></label></td>
				</tr>
				</table>
			</fieldset>
			<p class="submit">
				<input class="button-primary" type="submit" disabled="disabled" value="<?php _e('Save Changes') ?>" />
			</p>
			<br class="clear" />
<?php
		} elseif ($key === 'other_plugins') {
?>
			<h4><img src="<?php echo plugins_url('images/icon-wp-external-links.png', WP_MAILTO_LINKS_FILE) ?>" width="16" height="16" /> <?php _e('WP External Links', $this->domain) ?> -
				<?php if (is_plugin_active('wp-external-links/wp-external-links.php')): ?>
					<a href="<?php echo get_bloginfo('url') ?>/wp-admin/admin.php?page=wp_external_links"><?php _e('Settings') ?></a>
				<?php elseif( file_exists( WP_PLUGIN_DIR . '/wp-external-links/wp-external-links.php')): ?>
					<a href="<?php echo get_bloginfo('url') ?>/wp-admin/plugins.php?plugin_status=inactive"><?php _e('Activate', $this->domain) ?></a>
				<?php else: ?>
					<a href="<?php echo get_bloginfo('url') ?>/wp-admin/plugin-install.php?tab=search&type=term&s=WP+External+Links+freelancephp&plugin-search-input=Search+Plugins"><?php _e('Get this plugin', $this->domain) ?></a>
				<?php endif; ?>
			</h4>
			<p><?php _e('Manage external links on your site: open in new window/tab, set icon, add "external", add "nofollow" and more.', $this->domain) ?>
				<br /><a href="http://wordpress.org/extend/plugins/wp-external-links/" target="_blank">WordPress.org</a> | <a href="http://www.freelancephp.net/wp-external-links-plugin/" target="_blank">FreelancePHP.net</a>
			</p>

			<h4><img src="<?php echo plugins_url('images/icon-email-encoder-bundle.png', WP_MAILTO_LINKS_FILE) ?>" width="16" height="16" /> <?php _e('Email Encoder Bundle', $this->domain) ?> -
				<?php if (is_plugin_active('email-encoder-bundle/email-encoder-bundle.php')): ?>
					<a href="<?php echo get_bloginfo('url') ?>/wp-admin/admin.php?page=email-encoder-bundle/email-encoder-bundle.php"><?php _e('Settings') ?></a>
				<?php elseif( file_exists( WP_PLUGIN_DIR . '/email-encoder-bundle/email-encoder-bundle.php')): ?>
					<a href="<?php echo get_bloginfo('url') ?>/wp-admin/plugins.php?plugin_status=inactive"><?php _e('Activate', $this->domain) ?></a>
				<?php else: ?>
					<a href="<?php echo get_bloginfo('url') ?>/wp-admin/plugin-install.php?tab=search&type=term&s=WP+Mailto+Links+freelancephp&plugin-search-input=Search+Plugins"><?php _e('Get this plugin', $this->domain) ?></a>
				<?php endif; ?>
			</h4>
			<p><?php _e('Encode mailto links and (plain) email addresses and hide them from spambots. Easy to use, plugin works directly when activated. Save way to protect email addresses on your site.', $this->domain) ?>
				<br /><a href="http://wordpress.org/extend/plugins/email-encoder-bundle/" target="_blank">WordPress.org</a> | <a href="http://www.freelancephp.net/wp-email-encoder-bundle-plugin-3/" target="_blank">FreelancePHP.net</a>
			</p>
<?php
		}
	}

	/* -------------------------------------------------------------------------
	 *  Help Tabs
	 * ------------------------------------------------------------------------*/

	/**
	 * Add help tabs
	 */
	public function add_help_tabs() {
		if (!function_exists('get_current_screen')) {
			return;
		}

		$screen = get_current_screen();

		$screen->set_help_sidebar($this->get_help_text('sidebar'));

		$screen->add_help_tab(array(
			'id' => 'general',
			'title'	=> __('General'),
			'content' => $this->get_help_text('general'),
		));
		$screen->add_help_tab(array(
			'id' => 'shortcodes',
			'title'	=> __('Shortcodes'),
			'content' => $this->get_help_text('shortcodes'),
		));
		$screen->add_help_tab(array(
			'id' => 'templatefunctions',
			'title'	=> __('Template functions'),
			'content' => $this->get_help_text('templatefunctions'),
		));
		$screen->add_help_tab(array(
			'id' => 'actionhooks',
			'title'	=> __('Action hook'),
			'content' => $this->get_help_text('actionhooks'),
		));
		$screen->add_help_tab(array(
			'id' => 'filterhooks',
			'title'	=> __('Filter hook'),
			'content' => $this->get_help_text('filterhooks'),
		));
	}

	/**
	 * Get text for given help tab
	 * @param string $key
	 * @return string
	 */
	private function get_help_text($key) {
		if ($key === 'general') {
			$plugin_title = get_admin_page_title();
			$icon_url = plugins_url('images/icon-wp-mailto-links.png', WP_MAILTO_LINKS_FILE);
			$content = <<<GENERAL
<p><strong><img src="{$icon_url}" width="16" height="16" /> {$plugin_title} - version {$this->version}</strong></p>
<p>Protect your email addresses (automatically) and manage mailto links on your site, set mail icon, styling and more.</p>
<p><strong>Please <a href="http://wordpress.org/extend/plugins/wp-mailto-links/" target="_blank">rate this plugin</a>.</strong></p>
GENERAL;
		} elseif ($key === 'shortcodes') {
			$content = <<<SHORTCODES
<p>Create a protected mailto link in your posts:
<br/><code>[wpml_mailto email="info@myemail.com"]My Email[/wpml_mailto]</code>
</p>
<p>It's also possible to add attributes to the mailto link, like a target:
<br/><code>[wpml_mailto email="info@myemail.com" target="_blank"]My Email[/wpml_mailto]</code>
</p>
SHORTCODES;
		} elseif ($key === 'templatefunctions') {
			$content = <<<TEMPLATEFUNCTIONS
<p>Create a protected mailto link:
<br/><code><&#63;php if (function_exists('wpml_mailto')) { echo wpml_mailto(\$display, \$attrs); } &#63;></code>
</p>
<p>Filter given content to protect mailto links, shortcodes and plain emails (according to the settings in admin):
<br/><code><&#63;php if (function_exists('wpml_filter')) { echo wpml_filter(\$content); } &#63;></code>
</p>
TEMPLATEFUNCTIONS;
		} elseif ($key === 'actionhooks') {
			$content = <<<ACTIONHOOKS
<p>Add extra code after plugin is ready on the site, f.e. to add extra filters:</p>
<pre>
function extra_filters(\$filter_callback, \$object) {
	add_filter('some_filter', \$filter_callback);
}
add_action('wpml_ready', 'extra_filters');
</pre>
ACTIONHOOKS;
		} elseif ($key === 'filterhooks') {
			$content = <<<FILTERHOOKS
<p>The wpml_mailto filter gives you the possibility to manipulate output of the mailto created by the plugin. F.e. make all mailto links bold:</p>
<pre>
public function special_mailto(\$link, \$display, \$email, \$attrs) {
	return '&lt;b&gt;'. \$link .'&lt;/b&gt;';
}
add_filter('wpml_mailto', 'special_mailto', 10, 4);
</pre>
<p>Now all mailto links will be wrapped around a &lt;b&gt;-tag.</p>
FILTERHOOKS;
		} elseif ($key === 'sidebar') {
			$content = <<<SIDEBAR
<h4>Support</h4>
<ul>
	<li><a href="http://wordpress.org/extend/plugins/wp-mailto-links/faq/" target="_blank">FAQ</a></li>
	<li><a href="http://wordpress.org/support/plugin/wp-mailto-links" target="_blank">Report a problem</a></li>
	<li><a href="http://www.freelancephp.net/contact/" target="_blank">Send a question</a></li>
</ul>
SIDEBAR;
		}

		return ((empty($content)) ? '' : __($content, $this->domain));
	}


} // end class Admin_WP_Mailto_Links

endif;
/*?> // ommit closing tag, to prevent unwanted whitespace at the end of the parts generated by the included files */