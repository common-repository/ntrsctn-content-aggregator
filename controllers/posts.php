<?php
/*
Controller name: Posts
Controller description: Data manipulation methods for posts
*/

class NTRSCTN_JSON_API_Posts_Controller {

  public function create_post() {
    global $ntrsctn_json_api;
    if (!current_user_can('edit_posts')) {
      $ntrsctn_json_api->error("You need to login with a user capable of creating posts.");
    }
    if (!$ntrsctn_json_api->query->nonce) {
      $ntrsctn_json_api->error("You must include a 'nonce' value to create posts. Use the `get_nonce` Core API method.");
    }
    $nonce_id = $ntrsctn_json_api->get_nonce_id('posts', 'create_post');
    if (!wp_verify_nonce($ntrsctn_json_api->query->nonce, $nonce_id)) {
      $ntrsctn_json_api->error("Your 'nonce' value was incorrect. Use the 'get_nonce' API method.");
    }
    nocache_headers();
    $post = new NTRSCTN_JSON_API_Post();
    $id = $post->create($_REQUEST);
    if (empty($id)) {
      $ntrsctn_json_api->error("Could not create post.");
    }
    return array(
      'post' => $post
    );
  }
  
}

?>
