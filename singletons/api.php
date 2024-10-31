<?php

class NTRSCTN_JSON_API {
  
  function __construct() {
    $this->query = new NTRSCTN_JSON_API_Query();
    $this->introspector = new NTRSCTN_JSON_API_Introspector();
    $this->response = new NTRSCTN_JSON_API_Response();
    add_action('template_redirect', array(&$this, 'template_redirect'));
    add_action('admin_menu', array(&$this, 'admin_menu'));
    add_action('update_option_ntrsctn_json_api_base', array(&$this, 'flush_rewrite_rules'));
    add_action('pre_update_option_ntrsctn_json_api_controllers', array(&$this, 'update_controllers'));
  }
  
  function template_redirect() {
    // Check to see if there's an appropriate API controller + method    
    $controller = strtolower($this->query->get_controller());
    $available_controllers = $this->get_controllers();
    $enabled_controllers = explode(',', get_option('ntrsctn_json_api_controllers', 'core'));
    $active_controllers = array_intersect($available_controllers, $enabled_controllers);
    
    if ($controller) {
      
      if (!in_array($controller, $active_controllers)) {
        $this->error("Unknown controller '$controller'.");
      }
      
      $controller_path = $this->controller_path($controller);
      if (file_exists($controller_path)) {
        require_once $controller_path;
      }
      $controller_class = $this->controller_class($controller);
      
      if (!class_exists($controller_class)) {
        $this->error("Unknown controller '$controller_class'.");
      }
      
      $this->controller = new $controller_class();
      $method = $this->query->get_method($controller);
      
      if ($method) {
        
        $this->response->setup();
        
        // Run action hooks for method
        do_action("ntrsctn_json_api-{$controller}-$method");
        
        // Error out if nothing is found
        if ($method == '404') {
          $this->error('Not found');
        }
        
        // Run the method
        $result = $this->controller->$method();
        
        // Handle the result
        $this->response->respond($result);
        
        // Done!
        exit;
      }
    }
  }
  
  function admin_menu() {
    add_options_page('NTRSCTN Content Aggregator Settings', 'NTRSCTN Content Aggregator', 'manage_options', 'ntrsctn-aggregator', array(&$this, 'admin_options'));
  }
  
  function admin_options() {
    if (!current_user_can('manage_options'))  {
      wp_die( __('You do not have sufficient permissions to access this page.') );
    }
    
    $available_controllers = $this->get_controllers();
    $active_controllers = explode(',', get_option('ntrsctn_json_api_controllers', 'core'));
    
    if (count($active_controllers) == 1 && empty($active_controllers[0])) {
      $active_controllers = array();
    }
    
    if (!empty($_REQUEST['_wpnonce']) && wp_verify_nonce($_REQUEST['_wpnonce'], "update-options")) {
      if ((!empty($_REQUEST['action']) || !empty($_REQUEST['action2'])) &&
          (!empty($_REQUEST['controller']) || !empty($_REQUEST['controllers']))) {
        if (!empty($_REQUEST['action'])) {
          $action = $_REQUEST['action'];
        } else {
          $action = $_REQUEST['action2'];
        }
        
        if (!empty($_REQUEST['controllers'])) {
          $controllers = $_REQUEST['controllers'];
        } else {
          $controllers = array($_REQUEST['controller']);
        }
        
        foreach ($controllers as $controller) {
          if (in_array($controller, $available_controllers)) {
            if ($action == 'activate' && !in_array($controller, $active_controllers)) {
              $active_controllers[] = $controller;
            } else if ($action == 'deactivate') {
              $index = array_search($controller, $active_controllers);
              if ($index !== false) {
                unset($active_controllers[$index]);
              }
            }
          }
        }
        $this->save_option('ntrsctn_json_api_controllers', implode(',', $active_controllers));
      }

      $this->save_option('ntrsctn_json_api_remote_comments_on', !empty($_REQUEST['ntrsctn_json_api_remote_comments_on']));
      if (isset($_REQUEST['ntrsctn_json_api_base'])) {
        $this->save_option('ntrsctn_json_api_base', $_REQUEST['ntrsctn_json_api_base']);
      }
      if (isset($_REQUEST['ntrsctn_comments_url'])) {
        $this->save_option('ntrsctn_comments_url', $_REQUEST['ntrsctn_comments_url']);
      }
	  if (isset($_REQUEST['ntrsctn_comments_enable_after_date'])) {
        $this->save_option('ntrsctn_comments_enable_after_date', $_REQUEST['ntrsctn_comments_enable_after_date']);
      }
      if (isset($_REQUEST['ntrsctn_json_api_notification_api'])) {
        $this->save_option('ntrsctn_json_api_notification_api', $_REQUEST['ntrsctn_json_api_notification_api']);
        ntrsctn_json_api_register_mothership();
      }
    }

 	$ntrsctn_comments_enabled_after = ntrsctn_comments_enabled_after();
	$ntrsctn_comments_enabled_after = empty($ntrsctn_comments_enabled_after) ? '' : date('F j, Y, g:i a', $ntrsctn_comments_enabled_after);
    ?>
<div class="wrap">
  <div id="icon-options-general" class="icon32"><br /></div>
  <h2>NTRSCTN Content Aggregator Settings</h2>
  <form action="options-general.php?page=ntrsctn-aggregator" method="post">
    <?php wp_nonce_field('update-options'); ?>

	<h3>NTRSCTN COMMENTS</h3>
    <p>Replace the default wordpress commenting engine with the same comments that power ntrsctn.com</p>
    <table class="form-table">
      <tr valign="top">
        <th scope="row">Enable NTRSCTN Comments</th>
        <td><input type="checkbox" name="ntrsctn_json_api_remote_comments_on" <?php if (get_option('ntrsctn_json_api_remote_comments_on', DEFAULT_AGGREGATOR_REMOTE_COMMENTS_ON)) { print "checked";} ?> /></td>
      </tr>
      <tr valign="top">
        <th scope="row">NTRSCTN Comments URL</th>
        <td><input type="text" name="ntrsctn_comments_url" value="<?php echo get_option('ntrsctn_comments_url', DEFAULT_NTRSCTN_COMMENTS_URL); ?>" size="50" /></td>
      </tr>
      <tr valign="top">
        <th scope="row">Only use NTRSCTN Comments for posts after this date<br /><small>Leave blank for NTRSCTN Comments to be used for anything ever posted</small></th>
        <td><input type="text" name="ntrsctn_comments_enable_after_date" value="<?php echo $ntrsctn_comments_enabled_after; ?>" size="50" /></td>
      </tr>
    </table>

    <h3>API URL</h3>
    <p>Specify a base URL for JSON API. For example, using <code>api</code> as your API base URL would enable the following <code><?php bloginfo('url'); ?>/api/get_recent_posts/</code>. If you assign a blank value the API will only be available by setting a <code>json</code> query variable.</p>
    <table class="form-table">
      <tr valign="top">
        <th scope="row">API URL</th>
        <td><code><?php bloginfo('url'); ?>/</code><input type="text" name="ntrsctn_json_api_base" value="<?php echo get_option('ntrsctn_json_api_base', 'api'); ?>" size="15" /></td>
      </tr>
    </table>

    <h3>Notification API</h3>
    <p>By specifying a notification api url, the content aggregator will be informed of changes when they happen. This will make sure that all of your posts are included and updated in the aggregator as quickly as possible. If this is not set, there may be considerable lag between when you create, update or delete a post and when this change is reflected in the aggregator.</p>
    <table class="form-table">
      <tr valign="top">
        <th scope="row">Notification API</th>
        <td><input type="text" name="ntrsctn_json_api_notification_api" value="<?php echo get_option('ntrsctn_json_api_notification_api', DEFAULT_AGGREGATOR_NOTIFICATION_API); ?>" size="50" /></td>
      </tr>
    </table>

    <?php if (!get_option('permalink_structure', '')) { ?>
      <br />
      <p><strong>Note:</strong> User-friendly permalinks are not currently enabled. <a target="_blank" class="button" href="options-permalink.php">Change Permalinks</a>
    <?php } ?>
    <p class="submit">
      <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
    </p>
  </form>
</div>
<?php
  }
  
  function print_controller_actions($name = 'action') {
    ?>
    <div class="tablenav">
      <div class="alignleft actions">
        <select name="<?php echo $name; ?>">
          <option selected="selected" value="-1">Bulk Actions</option>
          <option value="activate">Activate</option>
          <option value="deactivate">Deactivate</option>
        </select>
        <input type="submit" class="button-secondary action" id="doaction" name="doaction" value="Apply">
      </div>
      <div class="clear"></div>
    </div>
    <div class="clear"></div>
    <?php
  }
  
  function get_method_url($controller, $method, $options = '') {
    $url = get_bloginfo('url');
    $base = get_option('ntrsctn_json_api_base', 'api');
    $permalink_structure = get_option('permalink_structure', '');
    if (!empty($options) && is_array($options)) {
      $args = array();
      foreach ($options as $key => $value) {
        $args[] = urlencode($key) . '=' . urlencode($value);
      }
      $args = implode('&', $args);
    } else {
      $args = $options;
    }
    if ($controller != 'core') {
      $method = "$controller/$method";
    }
    if (!empty($base) && !empty($permalink_structure)) {
      if (!empty($args)) {
        $args = "?$args";
      }
      return "$url/$base/$method/$args";
    } else {
      return "$url?json=$method&$args";
    }
  }
  
  function save_option($id, $value) {
    $option_exists = (get_option($id, null) !== null);
    if ($option_exists) {
      update_option($id, $value);
    } else {
      add_option($id, $value);
    }
  }
  
  function get_controllers() {
    $controllers = array();
    $dir = ntrsctn_json_api_dir();
    $dh = opendir("$dir/controllers");
    while ($file = readdir($dh)) {
      if (preg_match('/(.+)\.php$/', $file, $matches)) {
        $controllers[] = $matches[1];
      }
    }
    $controllers = apply_filters('ntrsctn_json_api_controllers', $controllers);
    return array_map('strtolower', $controllers);
  }
  
  function controller_is_active($controller) {
    if (defined('NTRSCTN_JSON_API_CONTROLLERS')) {
      $default = NTRSCTN_NTRSCTN_JSON_API_CONTROLLERS;
    } else {
      $default = 'core';
    }
    $active_controllers = explode(',', get_option('ntrsctn_json_api_controllers', $default));
    return (in_array($controller, $active_controllers));
  }
  
  function update_controllers($controllers) {
    if (is_array($controllers)) {
      return implode(',', $controllers);
    } else {
      return $controllers;
    }
  }
  
  function controller_info($controller) {
    $path = $this->controller_path($controller);
    $class = $this->controller_class($controller);
    $response = array(
      'name' => $controller,
      'description' => '(No description available)',
      'methods' => array()
    );
    if (file_exists($path)) {
      $source = file_get_contents($path);
      if (preg_match('/^\s*Controller name:(.+)$/im', $source, $matches)) {
        $response['name'] = trim($matches[1]);
      }
      if (preg_match('/^\s*Controller description:(.+)$/im', $source, $matches)) {
        $response['description'] = trim($matches[1]);
      }
      if (preg_match('/^\s*Controller URI:(.+)$/im', $source, $matches)) {
        $response['docs'] = trim($matches[1]);
      }
      if (!class_exists($class)) {
        require_once($path);
      }
      $response['methods'] = get_class_methods($class);
      return $response;
    } else if (is_admin()) {
      return "Cannot find controller class '$class' (filtered path: $path).";
    } else {
      $this->error("Unknown controller '$controller'.");
    }
    return $response;
  }
  
  function controller_class($controller) {
    return "ntrsctn_json_api_{$controller}_controller";
  }
  
  function controller_path($controller) {
    $dir = ntrsctn_json_api_dir();
    $controller_class = $this->controller_class($controller);
    return apply_filters("{$controller_class}_path", "$dir/controllers/$controller.php");
  }
  
  function get_nonce_id($controller, $method) {
    $controller = strtolower($controller);
    $method = strtolower($method);
    return "ntrsctn_json_api-$controller-$method";
  }
  
  function flush_rewrite_rules() {
    global $wp_rewrite;
    $wp_rewrite->flush_rules();
  }
  
  function error($message = 'Unknown error', $status = 'error') {
    $this->response->respond(array(
      'error' => $message
    ), $status);
  }
  
  function include_value($key) {
    return $this->response->is_value_included($key);
  }
  
}

?>
