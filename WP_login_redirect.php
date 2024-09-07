<?php
/*
Plugin Name: Universal Role Redirect
Description: Redirects all users to a custom page after login.
Version: 1.1
Author: Gustavo Iosimura
Author URI: https://github.com/gustavoiosimrua
*/
 
  
 
add_action('wp_login', 'redirect_after_login', 10, 2);

function redirect_after_login($user_login, $user) {
    // Get the user's username
    $username = $user->user_login;

    // Build the redirect URL based on the username
    $redirect_url = home_url('/' . $username);
    
    // Check if the user has the 'subscriber' role and is not already on the target page
    if (in_array('subscriber', $user->roles) && $_SERVER['REQUEST_URI'] !== '/' . $username) {
        // Redirect subscribers to their specific page
        wp_safe_redirect($redirect_url);
        exit; // Always call exit after wp_safe_redirect
    }
}
 