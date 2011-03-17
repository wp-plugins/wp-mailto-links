<?php
/*
Plugin Name: WP Mailto Links
Plugin URI: http://www.freelancephp.net/wp-mailto-links-plugin
Description: Manage mailto links on your site and protect emails from spambots, set mail icon and more.
Author: Victor Villaverde Laan
Version: 0.10
Author URI: http://www.freelancephp.net
License: Dual licensed under the MIT and GPL licenses
*/

/**
 * Class WP_Mailto_Links
 * @category WordPress Plugins
 */
class WP_Mailto_Links {

	/**
	 * Current version
	 * @var string
	 */
	var $version = '0.10';

	/**
	 * Used as prefix for options entry and could be used as text domain (for translations)
	 * @var string
	 */
	var $domain = 'WP_Mailto_Links';

	/**
	 * Name of the options
	 * @var string
	 */
	var $options_name = 'WP_Mailto_Links_options';

	/**
	 * Options to be saved
	 * @var array
	 */
	var $options = array(
			'convert_emails' => 1,
			'protect' => 1,
			'filter_whole_page' => 1,
			'filter_posts' => 1,
			'filter_comments' => 1,
			'filter_widgets' => 1,
			'filter_rss' => 1,
			'icon' => 0,
			'no_icon_class' => 'no-mail-icon',
			'class_name' => 'mail-link',
		);

	/**
	 * PHP4 constructor
	 */
	function WP_Mailto_Links() {
		$this->__construct();
	}

	/**
	 * PHP5 constructor
	 */
	function __construct() {
		// set option values
		$this->_set_options();

		// load text domain for translations
		load_plugin_textdomain( $this->domain, dirname( __FILE__ ) . '/lang/', basename( dirname(__FILE__) ) . '/lang/' );

		// add actions
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		// add filters
		if ( $this->options[ 'filter_whole_page' ] ) {
			add_action( 'wp', array( $this, 'wp' ), 1 );
		} else {
			// set filter priority
			$priority = 1000000000;

			// content
			if ( $this->options[ 'filter_posts' ] ) {
				add_filter( 'the_title', array( $this, 'filter_content' ), $priority );
				add_filter( 'the_content', array( $this, 'filter_content' ), $priority );
				add_filter( 'the_excerpt', array( $this, 'filter_content' ), $priority );
				add_filter( 'get_the_excerpt', array( $this, 'filter_content' ), $priority );
			}

			// comments
			if ( $this->options[ 'filter_comments' ] ) {
				add_filter( 'comment_text', array( $this, 'filter_content' ), $priority );
				add_filter( 'comment_excerpt', array( $this, 'filter_content' ), $priority );
				add_filter( 'comment_url', array( $this, 'filter_content' ), $priority );
				add_filter( 'get_comment_author_url', array( $this, 'filter_content' ), $priority );
				add_filter( 'get_comment_author_link', array( $this, 'filter_content' ), $priority );
				add_filter( 'get_comment_author_url_link', array( $this, 'filter_content' ), $priority );
			}

			// widgets ( only text widgets )
			if ( $this->options[ 'filter_widgets' ] ) {
				add_filter( 'widget_title', array( $this, 'filter_content' ), $priority );
				add_filter( 'widget_text', array( $this, 'filter_content' ), $priority );

				// Only if Widget Logic plugin is installed
				// @todo Doesn't work and cannot find another way to filter all widget contents
				//add_filter( 'widget_content', array( $this, 'filter_content' ), $priority );
			}
		}

		// rss feed
		if ( $this->options[ 'filter_rss' ] ) {
			add_filter( 'the_content_rss', array( $this, 'filter_content' ), $priority );
			add_filter( 'the_content_feed', array( $this, 'filter_content' ), $priority );
			add_filter( 'the_excerpt_rss', array( $this, 'filter_content' ), $priority );
			add_filter( 'comment_text_rss', array( $this, 'filter_content' ), $priority );
		}

		// set uninstall hook
		if ( function_exists( 'register_deactivation_hook' ) )
			register_deactivation_hook( __FILE__, array( $this, 'deactivation' ));
	}

	/**
	 * Callback wp
	 */
	function wp() {
		if ( ! is_admin() && ! is_feed() ) {
			ob_start( array( $this, 'filter_content' ) );
		}
	}

	/**
	 * Callback admin_menu
	 */
	function admin_menu() {
		if ( function_exists( 'add_options_page' ) AND current_user_can( 'manage_options' ) ) {
			// add options page
			$page = add_options_page( __( 'Mailto Links', $this->domain ), __( 'Mailto Links', $this->domain ),
								'manage_options', __FILE__, array( $this, 'options_page' ) );
		}
	}

	/**
	 * Callback init
	 */
	function init() {
		if ( ! is_admin() ) {
			// add stylesheet
			wp_enqueue_style( 'wp-mailto-links', plugins_url( 'css/mailto-links.css', __FILE__ ), FALSE, $this->version );

			// set js file
			if ( $this->options[ 'protect' ] ) {
				wp_enqueue_script( 'wp-mailto-links', plugins_url( 'js/mailto-links.js', __FILE__ ), array( 'jquery' ), $this->version );
			}
		}
	}

	/**
	 * Filter content
	 * @param string $content
	 * @return string
	 */
	function filter_content( $content ) {
		$email_pattern = '/([A-Z0-9._-]+@[A-Z0-9][A-Z0-9.-]{0,61}[A-Z0-9]\.[A-Z.]{2,6})/i';
		$a_pattern = '/<[aA](.*?)>(.*?)<\/[aA][\s+]*>/i';
		$tag_pattern = '/\[a(.*?)\](.*?)\[\/a\]/i';

		// get <a> elements
		$content = preg_replace_callback( $a_pattern, array( $this, 'parse_link' ), $content );

		// convert plain emails
		if ( $this->options[ 'convert_emails' ] == 1 ) {
			// protect plain emails
			$content = preg_replace_callback( $email_pattern, array( $this, 'get_protected_display' ), $content );

		} elseif ( $this->options[ 'convert_emails' ] == 2 ) {
			// make mailto links from plain emails
			// set plain emails to tags
			$content = preg_replace( $email_pattern, '[a href="mailto:${0}"]${0}[/a]', $content );

			// make mailto links from tags
			$content = preg_replace_callback( $tag_pattern, array( $this, 'parse_link' ), $content );
		}

		// remove style when no-icon classes are found
		if ( strpos( $content, 'mail-icon-' ) === FALSE AND empty( $this->options[ 'protect' ] ) ) {
			// remove style with id wp-mailto-links-css
			$content = preg_replace( '/<link(.*?)wp-mailto-links-css(.*?)\/>[\s+]*/i', '', $content );
		}

		return $content;
	}

	/**
	 * Make a clean <a> code
	 * @param array $match Result of a preg call in filter_content()
	 * @return string Clean <a> code
	 */
	function parse_link( $match ) {
		$attrs = shortcode_parse_atts( $match[ 1 ] );

		$href_tolower = ( isset( $attrs[ 'href' ] ) ) ? strtolower( $attrs[ 'href' ] ) : '';

		// check url
		if ( substr( $href_tolower, 0, 7 ) == 'mailto:' ){
			// set icon class, unless no-icon class isset or another icon class ('mail-icon-...') is found
			if ( $this->options[ 'icon' ] > 0 AND ( empty( $this->options[ 'no_icon_class' ] ) OR strpos( $attrs[ 'class' ], $this->options[ 'no_icon_class' ] ) === FALSE ) AND strpos( $attrs[ 'class' ], 'mail-icon-' ) === FALSE  ){
				$icon_class = 'mail-icon-'. $this->options[ 'icon' ];

				$attrs[ 'class' ] = ( empty( $attrs[ 'class' ] ) )
									? $icon_class
									: $attrs[ 'class' ] .' '. $icon_class;
			}

			// set user-defined class
			if ( ! empty( $this->options[ 'class_name' ] ) AND ( empty( $attrs[ 'class' ] ) OR strpos( $attrs[ 'class' ], $this->options[ 'class_name' ] ) === FALSE ) ){
				$attrs[ 'class' ] = ( empty( $attrs[ 'class' ] ) )
									? $this->options[ 'class_name' ]
									: $attrs[ 'class' ] .' '. $this->options[ 'class_name' ];
			}

			// create element code
			$link = '<a ';

			foreach ( $attrs AS $key => $value ) {
				if ( $key == 'href' AND $this->options[ 'protect' ] ) {
					$email = str_replace( 'mailto:', '', $href_tolower );
					$value = 'javascript:wpml(\''. str_rot13( strrev( $email ) ) .'\')';
				}

				$link .= $key .'="'. $value .'" ';
			}

			// remove last space
			$link = substr( $link, 0, -1 );

			$link .= '>';
			$link .= ( $this->options[ 'protect' ] )
					? $this->get_protected_display( $match[ 2 ] )
					: $match[ 2 ];
			$link .= '</a>';
		} else {
			$link = $match[ 0 ];
		}

		return $link;
	}

	/**
	 * Create protected display combining these 3 methods:
	 * - reversing string
	 * - adding no-display spans with dummy values
	 * - using the wp antispambot function
	 *
	 * Source:
	 * - http://perishablepress.com/press/2010/08/01/best-method-for-email-obfuscation/
	 * - http://techblog.tilllate.com/2008/07/20/ten-methods-to-obfuscate-e-mail-addresses-compared/
	 *
	 * @param string|array $display
	 * @return string Protected display
	 */
	function get_protected_display( $display ) {
		// get display outof array (result of preg callback)
		if ( is_array( $display ) )
			$display = $display[ 0 ];

		// first strip html tags
		$stripped = strip_tags( $display );

		$length = strlen( $stripped );
		$interval = min( 5, $length / 2 );
		$offset = 0;
		$dummy_content = time();

		// reverse string ( will be corrected with CSS )
		$rev = strrev( $stripped );

		while ( $offset < $length ) {
			// set html entities
			$protected .=  antispambot( substr( $rev, $offset, $interval ) );
			// set some dummy value, will be hidden with CSS
			$protected .= '<span class="nodis">'. $dummy_content .'</span>';
			$offset += $interval;
		}

		$protected = '<span class="rtl">'. $protected .'</span>';

		return $protected;
	}

	/**
	 * Callback admin_init
	 */
	function admin_init() {
		// register settings
		register_setting( $this->domain, $this->options_name );

		// set dashboard postbox
		wp_admin_css( 'dashboard' );
		wp_enqueue_script( 'dashboard' );
	}

	/**
	 * Admin options page
	 */
	function options_page() {
?>
<script language="javascript">
jQuery(function( $ ){
	// remove message
	$( '.settings-error' )
		.hide()
		.slideDown( 600 )
		.delay( 3000 )
		.slideUp( 600 );

	// option filter whole page
	$( 'input#filter_whole_page' )
		.change(function(){
			var $i = $( 'input#filter_posts, input#filter_comments, input#filter_widgets' );

			if ( $( this ).attr( 'checked' ) ) {
				$i.attr( 'disabled', true )
					.attr( 'checked', true );
			} else {
				$i.attr( 'disabled', false )
			}
		})
		.change();
})
</script>
	<div class="wrap">
		<div class="icon32" id="icon-options-custom" style="background:url( <?php echo plugins_url( 'images/icon-wp-mailto-links.png', __FILE__ ) ?> ) no-repeat 50% 50%"><br></div>
		<h2><?php _e( 'Mailto Links Settings' ) ?></h2>

		<form method="post" action="options.php">
			<?php
				settings_fields( $this->domain );
				$this->_set_options();
				$options = $this->options;
			?>

		<div class="postbox-container metabox-holder meta-box-sortables" style="width:69%;">
		<div style="margin:0 5px;">
			<div class="postbox">
				<div class="handlediv" title="<?php _e( 'Click to toggle' ) ?>"><br/></div>
				<h3 class="hndle"><?php _e( 'General Settings', $this->domain ) ?></h3>
				<div class="inside">
					<fieldset class="options">
						<table class="form-table">
						<tr>
							<th><?php _e( 'Convert plain emails', $this->domain ) ?></th>
							<td><label><input type="radio" id="<?php echo $this->options_name ?>[convert_emails]" name="<?php echo $this->options_name ?>[convert_emails]" value="0" <?php checked( '0', (int) $options['convert_emails'] ); ?> />
								<span><?php _e( 'No, do nothing with plain emails', $this->domain ) ?></span></label>
								<br/><label><input type="radio" id="<?php echo $this->options_name ?>[convert_emails]" name="<?php echo $this->options_name ?>[convert_emails]" value="1" <?php checked( '1', (int) $options['convert_emails'] ); ?> />
								<span><?php _e( 'Yes, protect plain emails in the (selected) content', $this->domain ) ?></span></label>
								<br/><label><input type="radio" id="<?php echo $this->options_name ?>[convert_emails]" name="<?php echo $this->options_name ?>[convert_emails]" value="2" <?php checked( '2', (int) $options['convert_emails'] ); ?> />
								<span><?php _e( 'Yes, convert plain emails to mailto links', $this->domain ) ?></span></label>
							</td>
						</tr>
						<tr>
							<th><?php _e( 'Protect mailto links', $this->domain ) ?></th>
							<td><label><input type="checkbox" id="<?php echo $this->options_name ?>[protect]" name="<?php echo $this->options_name ?>[protect]" value="1" <?php checked( '1', (int) $options['protect'] ); ?> />
								<span><?php _e( 'Protect mailto links against spambots', $this->domain ) ?></span></label>
							</td>
						</tr>
						<tr>
							<th><?php _e( 'Options have effect on', $this->domain ) ?></th>
							<td>
								<label><input type="checkbox" name="<?php echo $this->options_name ?>[filter_whole_page]" id="filter_whole_page" value="1" <?php checked( '1', (int) $options['filter_whole_page'] ); ?> />
								<span><?php _e( 'All contents (the whole <code>&lt;body&gt;</code>)', $this->domain ) ?></span></label>
								<br/>&nbsp;&nbsp;<label><input type="checkbox" name="<?php echo $this->options_name ?>[filter_posts]" id="filter_posts" value="1" <?php checked( '1', (int) $options['filter_posts'] ); ?> />
										<span><?php _e( 'Post contents', $this->domain ) ?></span></label>
								<br/>&nbsp;&nbsp;<label><input type="checkbox" name="<?php echo $this->options_name ?>[filter_comments]" id="filter_comments" value="1" <?php checked( '1', (int) $options['filter_comments'] ); ?> />
										<span><?php _e( 'Comments', $this->domain ) ?></span></label>
								<br/>&nbsp;&nbsp;<label><input type="checkbox" name="<?php echo $this->options_name ?>[filter_widgets]" id="filter_widgets" value="1" <?php checked( '1', (int) $options['filter_widgets'] ); ?> />
										<span><?php _e( 'Text widgets', $this->domain ) ?></span></label>
								<br/><label><input type="checkbox" name="<?php echo $this->options_name ?>[filter_rss]" id="filter_rss" value="1" <?php checked( '1', (int) $options['filter_rss'] ); ?> />
										<span><?php _e( 'RSS feed', $this->domain ) ?></span></label>
							</td>
						</tr>
						</table>
					</fieldset>
					<p class="submit">
						<input class="button-primary" type="submit" value="<?php _e( 'Save Changes' ) ?>" />
					</p>
				</div>
			</div>

			<div class="postbox">
				<div class="handlediv" title="<?php _e( 'Click to toggle' ) ?>"><br/></div>
				<h3 class="hndle"><?php _e( 'Style Settings', $this->domain ) ?></h3>
				<div class="inside">
					<fieldset class="options">
						<table class="form-table">
						<tr>
							<th><?php _e( 'Show icon', $this->domain ) ?>
	 						</th>
							<td>
								<div>
									<div style="width:15%;float:left">
										<label><input type="radio" name="<?php echo $this->options_name ?>[icon]" value="0" <?php checked( '0', (int) $options['icon'] ); ?> />
										<span><?php _e( 'No icon', $this->domain ) ?></span></label>
									<?php for ( $x = 1; $x <= 25; $x++ ): ?>
										<br/>
										<label title="<?php echo sprintf( __( 'Icon %1$s: choose this icon to show for all mailto links or add the class \'mail-icon-%1$s\' to a specific link.' ), $x ) ?>"><input type="radio" name="<?php echo $this->options_name ?>[icon]" value="<?php echo $x ?>" <?php checked( $x, (int) $options['icon'] ); ?> />
										<img src="<?php echo plugins_url( 'images/mail-icon-'. $x .'.png', __FILE__ ) ?>" /></label>
										<?php if ( $x % 5 == 0 ): ?>
									</div>
									<div style="width:12%;float:left">
										<?php endif; ?>
									<?php endfor; ?>
									</div>
									<div style="width:29%;float:left;"><span class="description"><?php _e( 'Example:', $this->domain ) ?></span>
										<br/><img src="<?php echo plugins_url( 'images/link-icon-example.png', __FILE__ ) ?>"	/>
									</div>
									<br style="clear:both" />
								</div>
							</td>
						</tr>
						<tr>
							<th><?php _e( 'No-icon Class', $this->domain ) ?></th>
							<td><label><input type="text" id="<?php echo $this->options_name ?>[no_icon_class]" name="<?php echo $this->options_name ?>[no_icon_class]" value="<?php echo $options['no_icon_class']; ?>" />
								<span><?php _e( 'Use this class when a mailto link should not show an icon', $this->domain ) ?></span></label>
							</td>
						</tr>
						<tr>
							<th><?php _e( 'Additional Classes (optional)', $this->domain ) ?></th>
							<td><label><input type="text" id="<?php echo $this->options_name ?>[class_name]" name="<?php echo $this->options_name ?>[class_name]" value="<?php echo $options['class_name']; ?>" />
								<span><?php _e( 'Add extra classes to mailto links (or leave blank)', $this->domain ) ?></span></label></td>
						</tr>
						</table>
					</fieldset>
					<p class="submit">
						<input class="button-primary" type="submit" value="<?php _e( 'Save Changes' ) ?>" />
					</p>
				</div>
			</div>
		</div>
		</div>

		<div class="postbox-container metabox-holder meta-box-sortables" style="width:29%;">
		<div style="margin:0 5px;">
			<div class="postbox">
				<div class="handlediv" title="<?php _e( 'Click to toggle' ) ?>"><br/></div>
				<h3 class="hndle"><?php _e( 'About' ) ?>...</h3>
				<div class="inside">
					<h4><img src="<?php echo plugins_url( 'images/icon-wp-mailto-links.png', __FILE__ ) ?>" width="16" height="16" /> WP Mailto Links (v<?php echo $this->version ?>)</h4>
					<p><?php _e( 'Manage mailto links on your site and protect emails from spambots, set mail icon and more.', $this->domain ) ?></p>
					<ul>
						<li><a href="http://www.freelancephp.net/contact/" target="_blank"><?php _e( 'Questions or suggestions?', $this->domain ) ?></a></li>
						<li><?php _e( 'If you like this plugin please send your rating at WordPress.org.' ) ?></li>
						<li><a href="http://wordpress.org/extend/plugins/wp-mailto-links/" target="_blank">WordPress.org</a> | <a href="http://www.freelancephp.net/wp-mailto-links-plugin/" target="_blank">FreelancePHP.net</a></li>
					</ul>
				</div>
			</div>

			<div class="postbox">
				<div class="handlediv" title="<?php _e( 'Click to toggle' ) ?>"><br/></div>
				<h3 class="hndle"><?php _e( 'Other Plugins', $this->domain ) ?></h3>
				<div class="inside">
					<h4><img src="<?php echo plugins_url( 'images/icon-wp-external-links.png', __FILE__ ) ?>" width="16" height="16" /> WP External Links</h4>
					<p><?php _e( 'Manage external links on your site: open in new window/tab, set icon, add "external", add "nofollow" and more.', $this->domain ) ?></p>
					<ul>
						<li><a href="<?php echo get_bloginfo( 'url' ) ?>/wp-admin/plugin-install.php?tab=search&type=term&s=WP+External+Links+freelancephp&plugin-search-input=Search+Plugins" target="_blank"><?php _e( 'Get this plugin now' ) ?></a></li>
						<li><a href="http://wordpress.org/extend/plugins/wp-external-links/" target="_blank">WordPress.org</a> | <a href="http://www.freelancephp.net/wp-external-links-plugin/" target="_blank">FreelancePHP.net</a></li>
					</ul>
				</div>
			</div>
		</div>
		</div>
		</form>
		<div class="clear"></div>
	</div>
<?php
	}

	/**
	 * Deactivation plugin method
	 */
	function deactivation() {
		delete_option( $this->options_name );
		unregister_setting( $this->domain, $this->options_name );
	}

	/**
	 * Set options from save values or defaults
	 */
	function _set_options() {
		// set options
		$saved_options = get_option( $this->options_name );

		// set all options
		if ( ! empty( $saved_options ) ) {
			foreach ( $this->options AS $key => $option ) {
				$this->options[ $key ] = $saved_options[ $key ];
			}
		}
	}

} // end class WP_Mailto_Links


/**
 * Create WP_Mailto_Links instance
 */
$WP_Mailto_Links = new WP_Mailto_Links;

?>