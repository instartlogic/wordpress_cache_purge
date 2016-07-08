<?php

/**
 * Add the settings page menu item to the Wordpress Settings menu.
 */
function instart_logic_add_admin_menu() {
  add_options_page( 'Instart Logic', 'Instart Logic', 'manage_options', 'instart_logic', 'instart_logic_render_options_page' );

}

function instart_logic_settings_init() {

  register_setting( 'instart_api_page', 'instart_logic_user_settings', 'instart_logic_validate_api');
  register_setting( 'instart_domain_page', 'instart_logic_domain_settings', 'instart_logic_validate_domain');
  register_setting( 'instart_purge_urls_page', 'instart_logic_purge_urls_settings', 'instart_logic_validate_purge_urls');
  register_setting( 'instart_purge_site_page', 'instart_logic_purge_site_settings', 'instart_logic_validate_purge_site');

  add_settings_section(
    'instart_logic_api_section',
    __( 'API ACCESS', 'default' ),
    'instart_logic_settings_section_callback',
    'instart_api_page'
  );

  add_settings_field(
    'instart_logic_username',
    __( 'Instart Logic Username (*)', 'default' ),
    'instart_logic_username_render',
    'instart_api_page',
    'instart_logic_api_section'
  );

  add_settings_field(
    'instart_logic_password',
    __( 'Instart Logic Password (*)', 'default' ),
    'instart_logic_text_password_render',
    'instart_api_page',
    'instart_logic_api_section'
  );

  add_settings_field(
    'instart_logic_customername',
    __( 'Instart Logic Customer Name (*)', 'default' ),
    'instart_logic_customername_render',
    'instart_api_page',
    'instart_logic_api_section'
  );

  add_settings_section(
    'instart_logic_domain_section',
    __( 'EXTERNAL DOMAIN', 'default' ),
    'instart_logic_domain_settings_section_callback',
    'instart_domain_page'
  );

  add_settings_field(
    'instart_logic_external_domain',
    __( 'External Domain Name', 'default' ),
    'instart_logic_domain_render',
    'instart_domain_page',
    'instart_logic_domain_section'
  );

  add_settings_field(
    'instart_logic_external_base_http',
    __( 'Site uses http', 'default' ),
    'instart_logic_http_render',
    'instart_domain_page',
    'instart_logic_domain_section'
  );

  add_settings_field(
    'instart_logic_external_base_https',
    __( 'Site uses https', 'default' ),
    'instart_logic_https_render',
    'instart_domain_page',
    'instart_logic_domain_section'
  );

  add_settings_section(
    'instart_logic_purge_urls_section',
    __( 'PURGE URLs', 'default' ),
    'instart_logic_purge_urls_section_callback',
    'instart_purge_urls_page'
  );

  add_settings_field(
    'instart_logic_urls',
    __( 'URLs to Purge', 'default' ),
    'instart_logic_purge_url_render',
    'instart_purge_urls_page',
    'instart_logic_purge_urls_section'
  );

  add_settings_field(
    'instart_logic_purge_site',
    __( '', 'default' ),
    'instart_logic_purge_site_render',
    'instart_purge_site_page'
  );
}

/**
 * Validate the settings on the API tab (username, password, customername).
 *
 * @param $input
 * @return mixed
 */
function instart_logic_validate_api($input) {

  // Create our array for storing the validated options
  $message = null;

  // Loop through each of the incoming options
  foreach( $input as $key => $value ) {

    // Check to see if the current option has a value.
    if(empty($input[$key])) {
      $message = __($key . ' cannot be empty');
      instart_logic_show_settings_message('error',$message);
    }

  }

  return $input;

}

/**
 * Validate the external domain name.
 *
 * @param $input
 * @return array
 */
function instart_logic_validate_domain($input) {
  $output = array();
  if (empty($input['instart_logic_external_domain'])) {
    $message = 'Please input a value for the external domain.';
    instart_logic_show_settings_message('error',$message);
    return $output;
  }
  return $input;
}

/**
 * Purge an array of urls.
 *
 * @param $input
 * @return array
 */
function instart_logic_validate_purge_urls($input) {
  $output = array();
  $urls = $input['instart_logic_urls'];
  $valid_urls = array();
  if (empty($urls)) {
    $message = 'Please input the URLs to purge.';
    instart_logic_show_settings_message('error',$message);
    return $output;
  }
  $errors = FALSE;
  foreach (explode("\n", $urls) as $line) {
    $url = trim($line);
    $url_type = _instart_logic_get_url_type($url);
    if ($url_type == 'PREFIX_LITERAL') {
      $url = rtrim($url, '*');
    }
    if (!instart_logic_valid_url($url)) {
      $errors = TRUE;
      instart_logic_show_settings_message('error',"Error: {$url} is an invalid URL.");
    } else {
      $valid_urls[$url] = $url_type;
    }

  }

  if (!$errors) {
    $result = instart_logic_purge_urls($valid_urls,true);
    if ($result['status'] == 'success') {
      instart_logic_show_settings_message('success');
    } else {
      instart_logic_show_settings_message('error',$result['message']);
    }
  }
  return $output;
}

/**
 * Returns TRUE if the given absolute URL is a valid url for purging.
 *
 * @param $url
 * @return bool
 */
function instart_logic_valid_url($url) {
  return (bool) preg_match("
    /^                                                      # Start at the beginning of the text
    (?:https?):\/\/                                         # Look for http, https schemes
    (?:
      (?:[a-z0-9\-\.]|%[0-9a-f]{2})+                        # A domain name or a IPv4 address
      |(?:\[(?:[0-9a-f]{0,4}:)*(?:[0-9a-f]{0,4})\])         # or a well formed IPv6 address
    )
    (?::[0-9]+)?                                            # Server port number (optional)
    (?:[\/|\?]
      (?:[\w#!:\.\?\+=&@$'~*,;\/\(\)\[\]\-]|%[0-9a-f]{2})   # The path and query (optional)
    *)?
  $/xi", $url);
}

/**
 * Determine the type of a url. Determines whether it is literal or wildcard.
 *
 * @param string $url
 *    The url to verify.
 *
 * @return bool
 *    A boolean value indicating if the url is literal.
 */
function _instart_logic_get_url_type($url) {
  if (substr_count($url, '*') == 1 &&
    strpos($url, '*') == strlen($url) - 1
  ) {
    return 'PREFIX_LITERAL';
  }
  return 'EXACT_LITERAL';
}

/**
 * Purge all URLs on the external domain.
 *
 * @param $input
 * @return array
 */
function instart_logic_validate_purge_site($input) {
  $output = array();
  $domain_options = get_option( 'instart_logic_domain_settings' );
  $external_domain = $domain_options['instart_logic_external_domain'];
  $schemes = instart_logic_get_schemes();
  $valid_urls = array();
  foreach ($schemes as $scheme) {
    $url = $scheme . '://' . $external_domain . '/';
    $url_type = 'PREFIX_LITERAL';
    $valid_urls[$url] = $url_type;
  }
  $result = instart_logic_purge_urls($valid_urls,true);
  if ($result['status'] == 'success') {
    instart_logic_show_settings_message('success','All URLs purged.');
  } else {
    instart_logic_show_settings_message('error',$result['message']);
  }
  return $output;
}

/**
 * Show message on the settings page.
 */
function instart_logic_show_settings_message($status, $message = 'URLs purged') {
  $message = __($message);
  if ($status == 'success') {
    add_settings_error('', esc_attr('settings_updated'), $message, 'updated');
  } else {
    add_settings_error('', esc_attr('settings_updated'), $message, 'error');
  }
}

/**
 * Render the username widget.
 */
function instart_logic_username_render() {
  $options = get_option( 'instart_logic_user_settings' );
  ?>
  <input type='text' required name='instart_logic_user_settings[instart_logic_username]' value='<?php echo $options['instart_logic_username']; ?>'>
  <?php

}

/**
 * Render the password widget.
 */
function instart_logic_text_password_render() {
  $options = get_option( 'instart_logic_user_settings' );
  ?>
  <input type='password' required name='instart_logic_user_settings[instart_logic_password]' value='<?php echo $options['instart_logic_password']; ?>'>
  <?php

}

/**
 * Render the customername widget.
 */
function instart_logic_customername_render() {
  $options = get_option( 'instart_logic_user_settings' );
  ?>
  <input type='text' id="cn" required name='instart_logic_user_settings[instart_logic_customername]' value='<?php echo $options['instart_logic_customername']; ?>'>
  <br><label for="cn">The Customer Name is available in the customer portal under the account page.</label>
  <?php

}

/**
 * Render the domain name widget.
 */
function instart_logic_domain_render() {
  $options = get_option( 'instart_logic_domain_settings' );
  $domain = $_SERVER['HTTP_HOST'];
  if ($options['instart_logic_external_domain']) {
    $domain = $options['instart_logic_external_domain'];
  }
  ?>
  <input type='text' size="30" name='instart_logic_domain_settings[instart_logic_external_domain]' value='<?php echo $domain; ?>'>
  <?php

}

/**
 * Render the http checkbox.
 */
function instart_logic_http_render() {
  $http = '';
  $options = get_option( 'instart_logic_domain_settings' );
  if ($options['instart_logic_external_base_http']) {
    $http = 'checked="' . $options['instart_logic_external_base_http'] . '"';
  }
  ?>
  <input type='checkbox' name='instart_logic_domain_settings[instart_logic_external_base_http]' <?php echo $http; ?> >
  <?php

}

/**
 * Render the https checkbox.
 */
function instart_logic_https_render() {
  $ssl = '';
  $options = get_option( 'instart_logic_domain_settings' );
  if ($options['instart_logic_external_base_https']) {
    $ssl = 'checked="' . $options['instart_logic_external_base_https'] . '"';
  }
  ?>
  <input type='checkbox' name='instart_logic_domain_settings[instart_logic_external_base_https]' <?php echo $ssl; ?> >
  <?php

}

/**
 * Render the textarea for URLs to purge.
 */
function instart_logic_purge_url_render() {
  ?>
  <textarea name='instart_logic_purge_urls_settings[instart_logic_urls]' cols="60" rows="5"></textarea>
  <?php
}

/**
 * Render the label for URLs textarea.
 */
function instart_logic_purge_site_render() {
  echo __('<p>Purge all URLs for the domain from the Instart Logic cache. Use sparingly.</p>','default');
}

/**
 * Render the help text for the API tab.
 */
function instart_logic_settings_section_callback() {

  echo __( 'Please enter your Instart Logic credentials for API access. If you
  do not have a username contact Instart Logic.', 'default' );

}

/**
 * Render the help text for the external domain.
 */
function instart_logic_domain_settings_section_callback() {
  echo __( 'Enter the external domain of your site. Also indicate whether you
  site is accessible via http and/or https.', 'default' );
}

/**
 * Render the help text for the bulk URL purge.
 */
function instart_logic_purge_urls_section_callback() {

  echo __('Paste one or more URLs to purge. Each in a new line.');
  echo __('<ul>');
  echo __('<li>One URL per line</li>');
  echo __('<li>Absolute URLs only.</li>');
  echo __('<li>Enter either full URLs or URLs with a trailing swildcard.</li>');
  echo __('<li>The wildcard must be at the end of the pattern.</li>');
  echo __('</ul>');
  echo __('Examples: http://example.com/blog/topic1, http://example.com/blog/*');

}

/**
 * Render the settings page.
 */
function instart_logic_render_options_page() {
  if ( !current_user_can( "manage_options" ) )  {
    wp_die( __( "You do not have sufficient permissions to access this page." ) );
  }

  $active_tab = 'api_access';

  if( isset( $_GET[ 'tab' ] ) ) {
    $active_tab = $_GET[ 'tab' ];
  } // end if

  ?>
  <div class="wrap">
    <img src="<?php echo plugins_url( 'images/instart_logo.png', __FILE__ ); ?>">
    <h1>Instart Logic Integration</h1>
    <h2 class="nav-tab-wrapper">
      <a class="nav-tab <?php echo $active_tab == 'api_access' ? 'nav-tab-active' : ''; ?>" href="?page=instart_logic&tab=api_access">API Access</a>
      <a class="nav-tab <?php echo $active_tab == 'external_domain' ? 'nav-tab-active' : ''; ?>" href="?page=instart_logic&tab=external_domain">External Domain</a>
      <a class="nav-tab <?php echo $active_tab == 'purge' ? 'nav-tab-active' : ''; ?>" href="?page=instart_logic&tab=purge">Purge URLs</a>
      <a class="nav-tab <?php echo $active_tab == 'purge_site' ? 'nav-tab-active' : ''; ?>" href="?page=instart_logic&tab=purge_site">Purge All</a>
    </h2>
    <form action='options.php' method='post'>

      <?php
      if( $active_tab == 'api_access' ) {
        settings_fields('instart_api_page');
        do_settings_sections('instart_api_page');
        echo "(*) denotes a required field";
        submit_button();
      } else if ($active_tab == 'external_domain') {
        settings_fields('instart_domain_page');
        do_settings_sections('instart_domain_page');
        submit_button();
      } else if ($active_tab == 'purge') {
        settings_fields('instart_purge_urls_page');
        do_settings_sections('instart_purge_urls_page');
        submit_button('Purge');
      } else if ($active_tab == 'purge_site') {
        settings_fields('instart_purge_site_page');
        do_settings_fields('instart_purge_site_page','default');
        submit_button('Purge All URLs');
      }
      ?>

    </form>
  </div>
  <?php

}

/**
 * Show message when a post is created or updated.
 */
function instart_logic_show_purge_message() {

    $result = get_transient('instart_logic_purge_result');
    if ($result) {
      $urls = $result['urls'];
      if ($result['status'] == 'success') {
        foreach ($urls as $url) {
          ?>
          <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Instart Logic: Purged URL ' . $url); ?></p>
          </div>
          <?php
        }
      } else {
        $url = $urls[0];
        ?>
        <div class="notice notice-error is-dismissible">
          <p><?php esc_html_e('Instart Logic: Error - Could not purge URL ' . $url); ?></p>
          <p><?php _e($result['message']); ?></p>
        </div>
        <?php
      }
      delete_transient('instart_logic_purge_result');
    }
}

/**
 * Purge the current URL when a post is created or updated. This is a save_post
 * action callback.
 *
 * @param $post_id
 */
function instart_logic_purge_current_url($post_id) {
  $urls = array();
  $domain_options = get_option( 'instart_logic_domain_settings' );
  $external_domain = $domain_options['instart_logic_external_domain'];
  $external_url = _instart_logic_get_external_url(get_permalink(),$external_domain);
  $urls[$external_url] = 'EXACT_LITERAL';
  $result = instart_logic_purge_urls($urls);

  set_transient('instart_logic_purge_result',$result);
}

/**
 * Purge an array of URLs.
 *
 * @param $urls
 *    The array of URLs to purge
 * @param $absolute
 *    TRUE if the URLs are absolute
 * @return array
 *    An array with the result status and error message.
 */
function instart_logic_purge_urls($urls,$absolute = false) {
  $result = array();
  $schemes = array('http');
  $options = get_option( 'instart_logic_user_settings' );
  $username = $options['instart_logic_username'];
  $password = $options['instart_logic_password'];
  $customername = $options['instart_logic_customername'];
  if (!$absolute) {
    $schemes = instart_logic_get_schemes();
  }
  $api = new InstartLogic($username, $password, $customername);
  $full_url = '';
  $purged_urls = array();

  try {
    $session_id = _instart_logic_get_session($api);
  }
  catch (InstartLogicException $e) {
    $result['status'] = 'error';
    $result['message'] = $e->getMessage();
    return $result;
  }

  foreach ($urls as $url => $type) {
    foreach ($schemes as $scheme) {
      if ($absolute) {
        $full_url = $url;
      } else {
        $full_url = $scheme . $url;
      }
      $purge_urls = array(
        $full_url => $type,
      );
      try {
        $api->purgeUrls($purge_urls, $session_id);
      }
      catch (InstartLogicPurgeException $e) {
        $result['status'] = 'error';
        $result['message'] = $e->getMessage();
        $result['urls'] = array($full_url);
        return $result;
      }
      $purged_urls[] = $full_url;
    }
  }

  $result['status'] = 'success';
  $result['urls'] = $purged_urls;
  return $result;
}

/**
 * Return the selected schemes to use in purging.
 *
 * @return array
 *    An array of schemes to purge.
 */
function instart_logic_get_schemes() {
  $schemes = array();
  $options = get_option( 'instart_logic_domain_settings' );
  $external_base_http = (bool)$options['instart_logic_external_base_http'];
  $external_base_https = (bool)$options['instart_logic_external_base_https'];
  if ($external_base_http) {
    $schemes[] = 'http';
  }
  if ($external_base_https) {
    $schemes[] = 'https';
  }
  return $schemes;
}

/**
 * Utility to get a session. Creates session if needed.
 *
 * @param object $api
 *    API Instance.
 *
 * @return bool
 *    The session id or FALSE.
 */
function _instart_logic_get_session($api) {
  $session_id = get_option('instart_logic_session_id', '');
  if (!$session_id || !$api->checkSession($session_id)) {
    $session_id = _instart_logic_create_session($api);
  }
  return $session_id;
}

/**
 * Utility to create a new session.
 *
 * @param object $api
 *    API instance.
 *
 * @return bool
 *    The session id or FALSE.
 */
function _instart_logic_create_session($api) {
  $result = $api->createSession();
  $data = $result->data;
  $id = json_decode($data)->id;
  update_option('instart_logic_session_id', $id);
  return $id;
}

/**
 * Takes an internal URL and external domain and returns the external URL minus
 * the schema.
 *
 * @param $internal_url
 * @param $external_domain
 * @return string
 */
function _instart_logic_get_external_url($internal_url, $external_domain) {

  $path = trim(parse_url($internal_url, PHP_URL_PATH), '/');
  $url = '://' . $external_domain . '/' . $path;
  return $url;
}