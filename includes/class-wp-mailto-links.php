<?php
if (!class_exists('WP_Mailto_Links')):

// load dependency
require_once dirname(WP_MAILTO_LINKS_FILE) . '/includes/class-admin-wp-mailto-links.php';

/**
 * Class WP_Mailto_Links
 * @package WP_Mailto_Links
 * @category WordPress Plugins
 */
class WP_Mailto_Links extends Admin_WP_Mailto_Links {

	/**
	 * Regular expressions
	 * @var array
	 */
	public $regexps = array(
		// @link http://www.mkyong.com/regular-expressions/how-to-validate-email-address-with-regular-expression/
		'email_plain' => '/([^mailto\:|mailto\:"|mailto\:\'|A-Z0-9])([_A-Za-z0-9-]+(\\.[_A-Za-z0-9-]+)*@[A-Za-z0-9]+(\\.[A-Za-z0-9]+)*(\\.[A-Za-z]{2,}))/i',
		'email_mailto' => '/mailto\:[\s+]*([_A-Za-z0-9-]+(\\.[_A-Za-z0-9-]+)*@[A-Za-z0-9]+(\\.[A-Za-z0-9]+)*(\\.[A-Za-z]{2,}))/i',
		'<a>' => '/<a[^A-Za-z](.*?)>(.*?)<\/a[\s+]*>/is',
		'<head>' => '/<head(([^>]*)>)(.*?)<\/head[\s+]*>/is',
		'<body>' => '/<body(([^>]*)>)(.*?)<\/body[\s+]*>/is',
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		// add actions
		add_action('wp', array($this, 'wp'));
	}

	/**
	 * WP action
	 */
	public function wp() {
		if (is_feed()) {
		// rss feed
			if ($this->options['filter_rss']) {
				$rss_filters = array('the_title', 'the_content', 'the_excerpt', 'the_title_rss', 'the_content_rss', 'the_excerpt_rss',
									'comment_text_rss', 'comment_author_rss', 'the_category_rss', 'the_content_feed', 'author_feed_link', 'feed_link');

				foreach($rss_filters as $filter) {
					add_filter($filter, array($this, 'callback_filter_rss'), 100);
				}
			}
		} else {
		// site
			// add stylesheet
			wp_enqueue_style('wp-mailto-links', plugins_url('css/wp-mailto-links.css', WP_MAILTO_LINKS_FILE), FALSE, $this->version);

			// set js file
			if ($this->options['protect']) {
				wp_enqueue_script('wp-mailto-links', plugins_url('js/wp-mailto-links.js', WP_MAILTO_LINKS_FILE), array('jquery'), $this->version);
			}

			if ($this->options['filter_body'] || $this->options['filter_head']) {
				ob_start(array($this, 'callback_filter_page'));
			}

			if (!$this->options['filter_body']) {
				$filters = array();

				// post content
				if ($this->options['filter_posts']) {
					array_push($filters, 'the_title', 'the_content', 'the_excerpt', 'get_the_excerpt');
				}

				// comments
				if ($this->options['filter_comments']) {
					array_push($filters, 'comment_text', 'comment_excerpt', 'comment_url', 'get_comment_author_url', 'get_comment_author_link', 'get_comment_author_url_link');
				}

				// widgets ( only text widgets )
				if ($this->options['filter_widgets']) {
					array_push($filters, 'widget_title', 'widget_text', 'widget_content'); // widget_content id filter of Widget Logic plugin
				}

				foreach($filters as $filter) {
					add_filter($filter, array($this, 'callback_filter_content'), 100);
				}
			}
		}

		// shortcodes
		add_shortcode('wpml_mailto', array($this, 'shortcode_protected_mailto'));

		// hook
		do_action('wpml_ready', array($this, 'callback_filter_content'), $this);
	}

	/* -------------------------------------------------------------------------
	 *  Filter Callbacks
	 * -------------------------------------------------------------------------/

	/**
	 * Filter complete <html>
	 * @param string $content
	 * @return string
	 */
	public function callback_filter_page($content) {
		try {
			// protect emails in <head> section
			if ($this->options['filter_head']) {
				$content = preg_replace_callback($this->regexps['<head>'], array($this, 'callback_filter_head'), $content);
			}

			// only replace links in <body> part
			if ($this->options['filter_body']) {
				$content = preg_replace_callback($this->regexps['<body>'], array($this, 'callback_filter_body'), $content);
			}
		} catch(Exception $e) {
		}

		return $content;
	}

	/**
	 * Filter <head>
	 * @param array $match
	 * @return string
	 */
	public function callback_filter_head($match) {
		if (count($match) == 0) {
			return '';
		}

		return $this->replace_plain_emails($match[0]);
	}

	/**
	 * Filter <body>
	 * @param array $match
	 * @return string
	 */
	public function callback_filter_body($match) {
		if (count($match) == 0) {
			return '';
		}

		return $this->callback_filter_content($match[0]);
	}

	/**
	 * Filter content
	 * @param string $content
	 * @return string
	 */
	public function callback_filter_content($content) {
		// get <a> elements
		$content = preg_replace_callback($this->regexps['<a>'], array($this, 'parse_link'), $content);

		// convert plain emails
		if ($this->options['convert_emails'] == 1) {
			// protect plain emails
			$content = $this->replace_plain_emails($content);

		} elseif ($this->options['convert_emails'] == 2) {
			// make mailto links from plain emails
			// set plain emails to tags
			$content = preg_replace($this->regexps['email_plain'], '${1}[wpml_mailto href="mailto:${2}"]${2}[/wpml_mailto]', $content);

			do_shortcode($content);
		}

		return $content;
	}

	/**
	 * Emails will be replaced by '*protected email*'
	 * @param string $content
	 * @return string
	 */
	public function callback_filter_rss($content) {
		$content = $this->replace_plain_emails($content);
		$content = preg_replace($this->regexps['email_mailto'], 'mailto:' . __($this->options['protection_text'], $this->domain), $content);
		return $content;
	}

	/**
	 * Emails will be replaced by '*protected email*'
	 * @param string $content
	 * @return string
	 */
	public function replace_plain_emails($content) {
		return preg_replace($this->regexps['email_plain'], '${1}' . __($this->options['protection_text'], $this->domain), $content);
	}

	/* -------------------------------------------------------------------------
	 *  Shortcode Functions
	 * -------------------------------------------------------------------------/

	/**
	 * Shortcode protected mailto link
	 * @param array $attrs
	 * @param string $content Optional
	 * @return string
	 */
	public function shortcode_protected_mailto($attrs, $content = '') {
		if ($this->options['protect'] && preg_match($this->regexps['email_plain'], $content) > 0) {
			$content = $this->get_protected_display($content);
		}

		return $this->protected_mailto($content, $attrs);
	}

	/* -------------------------------------------------------------------------
	 *  Link Functions
	 * -------------------------------------------------------------------------/

	/**
	 * Make a clean <a> code
	 * @param array $match Result of a preg call in callback_filter_content()
	 * @return string Clean <a> code
	 */
	public function parse_link($match) {
		$attrs = shortcode_parse_atts($match[1]);

		$href_tolower = (isset($attrs['href'])) ? strtolower($attrs['href']) : '';

		// check url
		if (substr($href_tolower, 0, 7) === 'mailto:') {
			$link = $this->protected_mailto($match[2], $attrs);
		} else {
			$link = $match[0];
		}

		return $link;
	}

	/**
	 * Create a protected mailto link
	 * @param string $display
	 * @param array $attrs Optional
	 * @return string
	 */
	public function protected_mailto($display, $attrs = array()) {
		$class_ori = (empty($attrs['class']))
						? ''
						: $attrs['class'];

		// set icon class, unless no-icon class isset or another icon class ('mail-icon-...') is found
		if ($this->options['icon'] > 0 && (empty($this->options['no_icon_class']) || strpos($class_ori, $this->options['no_icon_class']) === FALSE) && strpos($class_ori, 'mail-icon-') === FALSE) {
			$icon_class = 'mail-icon-'. $this->options['icon'];

			$attrs['class'] = (empty($attrs['class']))
								? $icon_class
								: $attrs['class'] .' '. $icon_class;
		}

		// set user-defined class
		if (!empty($this->options['class_name']) && strpos($class_ori, $this->options['class_name']) === FALSE) {
			$attrs['class'] = (empty($attrs['class']))
								? $this->options['class_name']
								: $attrs['class'] .' '. $this->options['class_name'];
		}

		// create element code
		$link = '<a ';

		foreach ($attrs AS $key => $value) {
			if (strtolower($key) == 'href' && $this->options['protect']) {
				// get email from href
				$email = substr($value, 7);
				// decode entities
				$email = html_entity_decode($email);
				// rot13 encoding
				$email = str_rot13($email);

				// set attrs
				$link .= 'href="javascript:;" ';
				$link .= 'data-enc-email="'. $email .'" ';
			} else {
				$link .= $key .'="'. $value .'" ';
			}
		}

		// remove last space
		$link = substr($link, 0, -1);

		$link .= '>';
		$link .= ($this->options['protect'] && preg_match($this->regexps['email_plain'], $display) > 0)
				? $this->get_protected_display($display)
				: $display;
		$link .= '</a>';

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
	public function get_protected_display($display) {
		// get display outof array (result of preg callback)
		if (is_array($display)) {
			$display = $display[0];
		}

		// first strip html tags
		$stripped_display = strip_tags($display);
		// decode entities
		$stripped_display = html_entity_decode($stripped_display);

		$length = strlen($stripped_display);
		$interval = ceil(min(5, $length / 2));
		$offset = 0;
		$dummy_content = time();

		// reverse string ( will be corrected with CSS )
		$rev = strrev($stripped_display);

		while ($offset < $length) {
			// set html entities
			$protected .= antispambot(substr($rev, $offset, $interval));

			// set some dummy value, will be hidden with CSS
			$protected .= '<span class="nodis">'. $dummy_content .'</span>';
			$offset += $interval;
		}

		$protected = '<span class="rtl">'. $protected .'</span>';

		return $protected;
	}

} // end class WP_Mailto_Links

endif;
/*?> // ommit closing tag, to prevent unwanted whitespace at the end of the parts generated by the included files */