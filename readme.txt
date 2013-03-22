=== WP Mailto Links ===
Contributors: freelancephp
Tags: hide, email, emailaddress, mailto, link, antispam, protect, spambot, encode, encrypt, obfuscate, email icon, javascript
Requires at least: 3.2.0
Tested up to: 3.5.1
Stable tag: 1.0.0

Protect emailaddresses and manage mailto links on your site, set mail icon and styling.

== Description ==

Protect emailaddresses and manage mailto links on your site.

= Features =
* Protect mailto links
* Protect plain emailaddresses or convert them to mailto links
* Set link icon
* Set no-icon class
* Set additional classes (for your own styling)

The plugin combines the best email protection methods explained in [this article](http://perishablepress.com/press/2010/08/01/best-method-for-email-obfuscation/) by Jeff Starr.

= Documentation =
See help tab on the plugin page in the WP Admin Panel.

= Requirements =
This plugin has the same [requirements](http://wordpress.org/about/requirements/) as WordPress.

= Contact =
[Send your comment](http://www.freelancephp.net/email-encoder-php-class-wp-plugin/)[ or question](http://www.freelancephp.net/contact/)

== Installation ==

1. Go to `Plugins` in the Admin menu
1. Click on the button `Add new`
1. Search for `WP Mailto Links` and click 'Install Now' or click on the `upload` link to upload `wp-mailto-links.zip`
1. Click on `Activate plugin`

== Frequently Asked Questions ==

= Shortcode does not work in widget =
By default shortcodes are not applied to (text) widgets. To support that you can add it to the text widget filter ([for more](http://www.wprecipes.com/how-to-add-shortcodes-in-sidebar-widgets)).
If you are not a coder, then just activate [this plugin](http://wordpress.org/extend/plugins/shortcodes-in-sidebar-widgets/), which does the same thing.

[Do you have a question? Please ask me](http://www.freelancephp.net/contact/)

== Screenshots ==

1. Link Icon on the Site
1. Admin Settings Page

= Credits =
* Title icon on Admin Options Page was made by [Aha-Soft Team](http://www.aha-soft.com/) taken form [iconfinder](http://findicons.com/icon/219920/e_mail)

== Changelog ==

= 1.0.1 =
* Fixed bug in regexp plain email
* Fixed bug shortcode not working
* Fixed bug - sign in email addresses
* Fixed defined var $protected
* Fixed icon in admin menu
* Fixed update message in admin

= 1.0.0 =
* Added shortcode [wpml_mailto]
* Added template functions wpml_mailto() and wpml_filter()
* Added action hook wpml_ready
* Added registered metaboxes with screen settings
* Refactored code and reorganized files
* Changed to semantic versioning from 1.0.0

= 0.30 =
* Improved regular expressions
* Fixed keep lettercase in mailto params
* Fixed convert plain emails without dot on the end
* Replaced code from pre_get_posts to wp action

= 0.24 =
* Fixed IE layout problem (WP 3.3+)

= 0.23 =
* Fixed blank page bug (WP 3.2+)
* Fixed setting default option values

= 0.22 =
* Added support for widget_content filter of the Logic Widget plugin
* Changed script attribute `language` to `type`
* Displayed name will only be encrypted when containing emailaddress(es)

= 0.21 =
* Fixed problem of not showing the first letter
* Fixed rtl css problem
* Fixed PHP / WP notices

= 0.20 =
* Fixed bug of changing `<abbr>` tag
* Added protection text for replacing emails in head-section and RSS feed
* Better RSS protection
* Improved JS method
* Improved regular expressions
* Solved bug using "&" for extra params (subject, cc, bcc, body) on mailto links
* Small cosmetical adjustments

= 0.10 =
* First release, features: protect mailto links and plain emails , set link icon, set no-icon class and additional classes
