<?php
/**
 * @file
 * Contains classes for the Instart Logic service API.
 */

/**
 * Exception class for Instart Logic module.
 */
class InstartLogicException extends Exception {

}

/**
 * Exception class for session failure.
 */
class InstartLogicSessionException extends InstartLogicException {

}

/**
 * Exception class for account fetch failure.
 */
class InstartLogicGetAccountException extends InstartLogicException {

}

/**
 * Exception class for purge failure.
 */
class InstartLogicPurgeException extends InstartLogicException {

}

/**
 * Instart Logic API for WordPress.
 */
class InstartLogic {
  /**
   * Construct function for the Instart Logic class.
   *
   * @param string $username
   *    Instart Logic username.
   * @param string $password
   *    Instart Logic password.
   * @param string $customername
   *    Instart Logic customer name.
   */
  public function __construct($username, $password, $customername) {
    $this->username = $username;
    $this->password = $password;
    $this->customername = $customername;
    $this->base_url = 'https://api.instartlogic.com/' . $customername . '/v1';
    $this->auth_url = $this->base_url . '/sessions';
    $this->auth_key = 'Basic ' . base64_encode($username . ':' . $password);
    $this->purge_path = '/cache/purge';
    $this->account_path = '/account';
    $this->purge_url = $this->base_url . $this->purge_path;
    $this->account_url = $this->base_url . $this->account_path;
  }

  /**
   * Check if credentials are valid by creating a session.
   *
   * @return bool
   *    TRUE if credentials are validated.
   *
   * @throws InstartLogicSessionException
   *    Thrown when session creation fails.
   */
  public function createSession() {
    $headers = array();
    $headers['Content-Type'] = 'application/json';
    $headers['Authorization'] = $this->auth_key;

    $result = $this->query($this->auth_url, '', 'POST', $headers);
    if ($result->code != '201') {
      throw new InstartLogicSessionException('Error: Could not create session.');
    }
    else {
      return $result;
    }
  }

  /**
   * Retrieve the account information to validate the customer name.
   *
   * @param string $session_id
   *    The id of the session.
   *
   * @return array
   *    The server response with account information.
   *
   * @throws InstartLogicGetAccountException
   *    Thrown when getting account fails.
   */
  public function getAccount($session_id) {
    $headers = array();
    $headers['Cookie'] = 'authtoken=' . $session_id;
    $headers['Content-Type'] = 'application/json';
    $result = $this->query($this->account_url, '', 'GET', $headers);
    if ($result->code != '200') {
      throw new InstartLogicGetAccountException('Error: Could not validate customer name');
    }
    else {
      return $result;
    }
  }

  /**
   * Validate a session.
   *
   * @param string $id
   *    The id of the session.
   *
   * @return bool
   *    TRUE if session is valid.
   */
  public function checkSession($id) {
    $headers = array();
    $headers['Content-Type'] = 'application/json';
    $url = $this->auth_url . "/$id";
    $result = $this->query($url, '', 'GET', $headers);
    return $result->code == '200';
  }

  /**
   * Purge one or more urls.
   *
   * @param mixed $urls
   *    The urls to purge.
   * @param string $session_id
   *    The session id.
   *
   * @return array
   *    The array representing the response.
   *
   * @throws InstartLogicPurgeException
   *    Thrown when purging fails.
   */
  public function purgeUrls($urls, $session_id) {
    $purge_request = array();
    $purge_request['action'] = 'PURGE';
    $purge_request['uris'] = array();
    foreach ($urls as $url => $type) {
      $url_obj = new stdClass();
      $url_obj->match_mode = $type;
      $url_obj->uri_pattern = $url;
      $purge_request['uris'][] = $url_obj;
    }
    $json = json_encode(array('purge_request' => $purge_request));
    $headers['Content-Type'] = 'application/json';
    $headers['Cookie'] = 'authtoken=' . $session_id;
    $result = $this->query($this->purge_url, $json, 'POST', $headers);
    if ($result->code != '201') {
      $body = json_decode($result->data);
      $description = $body->description;
      $message = 'Error: ' . $result->message;
      if ($description) {
        $message .= '<br>Description: ' . $description;
      }
      throw new InstartLogicPurgeException($message);
    }
    else {
      return $result;
    }
  }

  /**
   * Performs http queries to Instart Logic API server.
   *
   * @param string $uri
   *   The uri to use for the request, appended to the host.
   * @param array $body
   *   (optional) Data to send with the request.
   * @param string $method
   *   (optional) The method to use for the request, defaults to GET.
   * @param array $headers
   *   (optional) An array of headers to send with the request.
   *
   * @return object
   */
  protected function query($uri, $body = array(), $method = 'POST', $headers = array()) {
    $options['headers'] = $headers;
    $options['body'] = $body;
    $return = new stdClass();
    $response = array();

    switch ($method) {
      case 'GET':
          $response = wp_remote_get($uri,$options);
        break;
      case 'POST':
          $response = wp_safe_remote_post($uri,$options);
        break;
    }

    $return->code = $response['response']['code'];
    $return->data = $response['body'];
    $return->message = $response['response']['message'];
    return $return;
  }

}
