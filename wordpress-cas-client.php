<?php
/*
Plugin Name: WordPress CAS Client
Description: Integrates WordPress with existing <a href="http://en.wikipedia.org/wiki/Central_Authentication_Service">CAS</a> single sign-on architectures. Additionally this plugin can use a LDAP server (such as Active Directory) for populating user information after the user has successfully logged on to WordPress. This plugin is a fork of the <a href="http://wordpress.org/extend/plugins/wpcas-w-ldap">wpCAS-w-LDAP</a> plugin.
Version: 1.4
Author: Bellevue College
Author URI: http://www.bellevuecollege.edu
License: GNU General Public License v2 or later
Plugin URI: BellevueCollege/wordpress-cas-client
*/

/*
 * WordPress CAS Client plugin used to authenticate users against a CAS server
 *
 * Copyright (C) 2014 Bellevue College
 * Copyright (C) 2009 Ioannis C. Yessios
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * This plugin owes a huge debt to
 * Casey Bisson's wpCAS, copyright (C) 2008
 * and released under GPL. http://wordpress.org/extend/plugins/wpcasldap/
 *
 * Casey Bisson's plugin owes a huge debt to Stephen Schwink's CAS
 * Authentication plugin, copyright (C) 2008 and released under GPL.
 * http://wordpress.org/extend/plugins/cas-authentication/
 *
 * It also borrowed a few lines of code from Jeff Johnson's SoJ CAS/LDAP Login
 * plugin. http://wordpress.org/extend/plugins/soj-casldap/
 *
 * This plugin honors and extends Bisson's and Schwink's work, and is licensed
 * under the same terms.
 *
 * Bellevue College
 * Address: 3000 Landerholm Circle SE
 *          Room N215F
 *          Bellevue WA 98007-6484
 * Phone:   +1 425.564.4201
 */

define( 'CAPABILITY', 'edit_themes' );
define( 'CAS_CLIENT_ROOT', dirname( __FILE__ ) );

require_once constant( 'CAS_CLIENT_ROOT' ) . '/includes/admin-option-page-functions.php';
require_once constant( 'CAS_CLIENT_ROOT' ) . '/includes/class-wp-cas-ldap.php';
require_once constant( 'CAS_CLIENT_ROOT' ) . '/includes/update-network-settings.php';
require_once constant( 'CAS_CLIENT_ROOT' ) . '/config.php';

/*
 * Configure plugin WordPress Hooks
 */

/*
 * This global variable is set to either 'get_option' or 'get_site_option'
 * depending on multisite option value.
 */
global $get_options_func;
$get_options_func = 'get_option';

/*
 * This global variable is defaulted to 'options.php' , but for network
 * setting we want the form to submit to itself, so we will leave it empty.
 */
global $form_action;
$form_action = 'options.php';

if ( is_multisite( ) ) {
	update_network_settings( );
	add_action( 'network_admin_menu', 'cas_client_settings' );

	$get_options_func = 'get_site_option';
	$form_action = '';
} elseif ( is_admin( ) ) {
	add_action( 'admin_init', 'wp_cas_ldap_register_settings' );
	add_action( 'admin_menu', 'wp_cas_ldap_options_page_add' );
}

add_action( 'wp_authenticate', array( 'WP_CAS_LDAP', 'authenticate' ), 10, 2 );
add_action( 'wp_logout', array( 'WP_CAS_LDAP', 'logout' ) );
add_action( 'lost_password', array( 'WP_CAS_LDAP', 'disable_function' ) );
add_action( 'retrieve_password', array( 'WP_CAS_LDAP', 'disable_function' ) );
add_action( 'password_reset', array( 'WP_CAS_LDAP', 'disable_function' ) );
add_filter( 'show_password_fields', array( 'WP_CAS_LDAP', 'show_password_fields' ) );

/*
 * Prevent 'Password Changed' email from being sent
 *
 * Email was introduced in WordPress 4.3, and was sent on every login
 * due to password being programatically changed as needed.
 */
add_filter( 'send_password_change_email', '__return_false' );

global $wp_cas_ldap_options;
if ( $wp_cas_ldap_options ) {
	if ( ! is_array( $wp_cas_ldap_options ) ) {
		$wp_cas_ldap_options = array( );
	}
}

$wp_cas_ldap_use_options = wp_cas_ldap_get_options( );

global $cas_configured;
$cas_configured = false;

/*
 * Check to see if the phpCAS class exists in our environment. If it doesn't
 * then check to see if we have all the configuration variables we need to
 * configure phpCAS. If we do then import the phpCAS library and call the
 * phpCAS::client() method.
 *
 * NOTE: This assumes that if the phpCAS class does exist in the environment
 *       that the method phpCAS::client() has been already called by another
 *       piece of code elsewhere. If the client method has not been invoked but
 *       the phpCAS class has been imported into the environment anyway then
 *       this logic would cause the other phpCAS methods to fail when called
 *       later in this plugin.
 */
if ( ! class_exists( 'phpCAS' ) ) {
	if ( ! empty( $wp_cas_ldap_use_options['include_path'] ) &&
			file_exists( $wp_cas_ldap_use_options['include_path'] ) &&
			! empty( $wp_cas_ldap_use_options['server_hostname'] ) &&
			! empty( $wp_cas_ldap_use_options['server_path'] ) &&
			! empty( $wp_cas_ldap_use_options['server_port'] ) ) {
		require_once $wp_cas_ldap_use_options['include_path'];
		phpCAS::client($wp_cas_ldap_use_options['cas_version'],
			$wp_cas_ldap_use_options['server_hostname'],
			intval( $wp_cas_ldap_use_options['server_port'] ),
			$wp_cas_ldap_use_options['server_path']);
		phpCAS::setNoCasServerValidation( );
		$cas_configured = true;
	}
} else {
	$cas_configured = true;
}
