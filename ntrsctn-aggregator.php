<?php
/*
Plugin Name: NTRSCTN Content Aggregator
Plugin URI: http://wordpress.org/extend/plugins/ntrsctn-content-aggregator/
Description: Required plugin to participate in the NTRSCTN Mobile / Social platform, powered by Treemo Labs.
Version: 1.1
Author: Complex Media 
Originally By: Dan Phiffer

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


define(DEFAULT_AGGREGATOR_NOTIFICATION_API, '');
define(DEFAULT_AGGREGATOR_REMOTE_COMMENTS_ON, false);
define(DEFAULT_NTRSCTN_COMMENTS_URL, '');

$dir = ntrsctn_json_api_dir();
@include_once "$dir/singletons/api.php";
@include_once "$dir/singletons/query.php";
@include_once "$dir/singletons/introspector.php";
@include_once "$dir/singletons/response.php";
@include_once "$dir/models/post.php";
@include_once "$dir/models/comment.php";
@include_once "$dir/models/category.php";
@include_once "$dir/models/tag.php";
@include_once "$dir/models/author.php";
@include_once "$dir/models/attachment.php";

function ntrsctn_json_api_init() {
  global $ntrsctn_json_api;
  if (phpversion() < 5) {
    add_action('admin_notices', 'ntrsctn_json_api_php_version_warning');
    return;
  }
  if (!class_exists('NTRSCTN_JSON_API')) {
    add_action('admin_notices', 'ntrsctn_json_api_class_warning');
    return;
  }
  add_filter('rewrite_rules_array', 'ntrsctn_json_api_rewrites');
  $ntrsctn_json_api = new NTRSCTN_JSON_API();
  
}

function ntrsctn_json_api_php_version_warning() {
  echo "<div id=\"json-api-warning\" class=\"updated fade\"><p>Sorry, JSON API requires PHP version 5.0 or greater.</p></div>";
}

function ntrsctn_json_api_class_warning() {
  echo "<div id=\"json-api-warning\" class=\"updated fade\"><p>Oops, NTRSCTN_JSON_API class not found. If you've defined a NTRSCTN_JSON_API_DIR constant, double check that the path is correct.</p></div>";
}

function ntrsctn_api_notify_version() {
    $last_notified_version = get_option('ntrsctn_json_api_notification_api_version_notified');
	if ($last_notified_version != ntrsctn_api_get_version()) {
		ntrsctn_json_api_register_mothership();	
	}
}

function ntrsctn_api_get_version() {
	if (preg_match('/^\s*Version:\s*(.+)$/m', file_get_contents(__FILE__), $matches)) {
	  return $matches[1];
	} else {
	  return '(Unknown)';
	}
}

function ntrsctn_api_notify_mothership($action, $params = array()) {
  $mothership = trim(get_option('ntrsctn_json_api_notification_api', DEFAULT_AGGREGATOR_NOTIFICATION_API));
  if (empty($mothership))
    return false;
    
  $params['version'] = ntrsctn_api_get_version();
  $params['secret'] = get_option('ntrsctn_json_api_secret');
  
  if (empty($params['secret'])) {
  	// Create a secret for safe communiction between Aggregator and WP Instance
  	$secret = md5(microtime(true)+rand());
  	add_option('ntrsctn_json_api_secret', $secret);
  	$params['secret'] = $secret;
  }
  
  $params['api_url'] = get_bloginfo('url').'/'.get_option('ntrsctn_json_api_base', 'api');
  $params['action'] = $action;
	
  $ch  = curl_init($mothership);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
  $result = curl_exec($ch);
  curl_close($ch);
  $json = @json_decode($result);
  
  update_option('ntrsctn_json_api_notification_api_version_notified', $params['version']);

  if (!empty($json->shortname) && !empty($json->secret) && $json->secret == $params['secret']) {
	update_option('ntrsctn_json_api_shortname', $json->shortname);
  }

  if ($json && !empty($json->error)) {
    wp_die("<h1>NTRSCTN Content Aggregator Fatal Error</h1><p>{$json->error}</p>", "Error", array('back_link'=>true));
  }
}

// Notify Aggregator that this node is now online and you should start syncing
function ntrsctn_json_api_register_mothership() {
	$params = array(
		'public_url' => get_bloginfo('url'),
		'description' => get_bloginfo('description'),
		'name' => get_bloginfo('name'),
		'platform' => 'wordpress'
	);
	ntrsctn_api_notify_mothership('register', $params);
}

function ntrsctn_json_api_activation() {
  if (class_exists('JSON_API')) {
    wp_die("<h1>This plugin is not compatible with the JSON API plugin</h1><p> Please first deactivate the JSON API before activating this plugin.</p>", "Error activating plugin", array('back_link'=>true));
  }

  if (class_exists('TREEMO_JSON_API')) {
    wp_die("<h1>This plugin is not compatible with the Treemo Labs plugin</h1><p> Please first deactivate the Treemo Labs plugin before activating this plugin.</p>", "Error activating plugin", array('back_link'=>true));
  }

  ntrsctn_json_api_register_mothership();

  // Add the rewrite rule on activation
  global $wp_rewrite;
  add_filter('rewrite_rules_array', 'ntrsctn_json_api_rewrites');
  $wp_rewrite->flush_rules();
}

function ntrsctn_json_api_deactivation() {
  // Remove the rewrite rule on deactivation
  global $wp_rewrite;
  $wp_rewrite->flush_rules();
}

function ntrsctn_json_api_rewrites($wp_rules) {
  $base = get_option('ntrsctn_json_api_base', 'api');
  if (empty($base)) {
    return $wp_rules;
  }
  $ntrsctn_json_api_rules = array(
    "$base\$" => 'index.php?json=info',
    "$base/(.+)\$" => 'index.php?json=$matches[1]'
  );
  return array_merge($ntrsctn_json_api_rules, $wp_rules);
}

function ntrsctn_json_api_dir() {
  if (defined('NTRSCTN_JSON_API_DIR') && file_exists(NTRSCTN_JSON_API_DIR)) {
    return NTRSCTN_JSON_API_DIR;
  } else {
    return dirname(__FILE__);
  }
}

function ntrsctn_json_api_notify_post_status($new_status, $old_status, $post) {
  if ($new_status == 'publish' || $old_status == 'publish') {
    $params = array(
	  'new_status' => $new_status,
	  'old_status' => $old_status,
	  'post_id' => $post->ID
    );
    ntrsctn_api_notify_mothership('publish_change', $params);
  }
}

function ntrsctn_comments_enabled_after() {
	$date = strtotime(get_option('ntrsctn_comments_enable_after_date', ''));
	if(empty($date)) {
		return 0;
	}
	return $date;
}

function ntrsctn_comments_enabled($post=null) {
	$ntrsctn_remote_comments_on = get_option('ntrsctn_json_api_remote_comments_on', DEFAULT_AGGREGATOR_REMOTE_COMMENTS_ON);
	if (!$ntrsctn_remote_comments_on) {
		return false;
	}
		
	$comments_url = trim(get_option('ntrsctn_comments_url', DEFAULT_NTRSCTN_COMMENTS_URL));
	$shortname = get_option('ntrsctn_json_api_shortname', '');
	
	if (empty($comments_url) || empty($shortname)) {
		return false;
	}
	
	if (empty($post)) {
		return true;
	}
	
	// Check date based enabled
	$after_date = ntrsctn_comments_enabled_after();
	if (!empty($after_date)) {
		if (strtotime($post->post_date) < $after_date) {
			return false;
		}
	}
	
	return true;
}

function ntrsctn_comments($comments = '') {
	global $wp_query;
	$post_id = $wp_query->post->ID;
	$comments_url = trim(get_option('ntrsctn_comments_url', DEFAULT_NTRSCTN_COMMENTS_URL));
	$shortname = get_option('ntrsctn_json_api_shortname', '');
	
	if (!ntrsctn_comments_enabled($wp_query->post)) {
		return $comments;
	}

	print <<<EOS
		<div id="tComments">Loading Comments...</div>
		<script type="text/javascript">
			function reloadComments(){
				var origSrc = $('#tComments iframe:first').attr('src');
				$('#tComments iframe:first').attr('src', '').attr('src', origSrc);
			}
		</script>
		<script type="text/javascript" src="{$comments_url}/widget/tComments.js"></script>
    	<script type="text/javascript">
			tComments({
				id: 'tComments',
				shortname: '{$shortname}',
				domain: '{$comments_url}',
				post_id: '{$post_id}'
			});
		</script>
		<style type='text/css'>
			/*****************************/
			/* Hide the WordPress commenting form
			/*****************************/
			#respond, #commentform, #addcomment, .entry-comments, .nocomments {
  				display: none;
			}	
		</style>
EOS;

	// if don't use wordpress comments, return an empty array
	return array();
}

function ntrsctn_comments_number($count) {
	global $wp_query;
	if (!ntrsctn_comments_enabled($wp_query->post)) {
		return $count;
	}
	
	$post_id = $wp_query->post->ID;
	$transient = 'ntrsctn_c_'.$post_id;
	$result = get_transient($transient);
	if ($result !== false) {
		return $result;
	}
	
	$comments_url = trim(get_option('ntrsctn_comments_url', DEFAULT_NTRSCTN_COMMENTS_URL));
	$shortname = get_option('ntrsctn_json_api_shortname', '');
	$params = array(
		'shortname' => $shortname,
		'post_id' => $post_id
	);
	
	$ch  = curl_init($comments_url.'/comment/count');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
	$result = (int)curl_exec($ch);
	curl_close($ch);

	$result = $result > 0 ? $result : 0;
	// cache the number of comments on this article for 60 seconds
	set_transient($transient, $result, 60);
	return $result;
}

function ntrsctn_comments_text($comments_text) {
	return $comments_text;
}

// Use CSS to hide the recent comments sidebar widget that comes default with WordPress since it won't be right
function ntrsctn_hide_recent_comments_sidebar() {
	print "<style type='text/css'>.widget_recent_comments { display: none !important; }</style>";
}

// Add initialization and activation hooks
add_action('init', 'ntrsctn_json_api_init');
add_action('admin_init', 'ntrsctn_api_notify_version');
register_activation_hook("$dir/ntrsctn-aggregator.php", 'ntrsctn_json_api_activation');
register_deactivation_hook("$dir/ntrsctn-aggregator.php", 'ntrsctn_json_api_deactivation');

// Add hooks for when a post is published, modified or moved to the trash to notify the aggregator
add_action('transition_post_status', 'ntrsctn_json_api_notify_post_status', 10, 3);

// Add a filter to replace wordpress comments with ntrsctn comments
$ntrsctn_remote_comments_on = get_option('ntrsctn_json_api_remote_comments_on', DEFAULT_AGGREGATOR_REMOTE_COMMENTS_ON);
if ($ntrsctn_remote_comments_on) {
	add_filter('comments_array', 'ntrsctn_comments');
	add_filter('get_comments_number', 'ntrsctn_comments_number');
	add_filter('comments_number', 'ntrsctn_comments_text');
	
	add_action('wp_head', 'ntrsctn_hide_recent_comments_sidebar');
	
}
