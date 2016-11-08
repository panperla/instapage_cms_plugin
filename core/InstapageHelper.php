<?php
class InstapageHelper
{
  public static function loadTemplate( $tmpl, $print = true )
  {
    $template_file = INSTAPAGE_PLUGIN_PATH . '/views/' . $tmpl . '.php';

    if( file_exists( $template_file ) )
    {
      if( !$print )
      {
        ob_start();
      }

      require( $template_file );

      if( !$print )
      {
        $contents = ob_get_contents();
        ob_end_clean();
        
        return $contents;
      }
    }
    else
    {
      throw new Exception( Connector::lang( 'Template file (%s) not found', $template_file ) );
    }
  }

  public static function initMessagesSystem()
  {
    self::loadTemplate( 'messages' );
  }

  public static function getMenuIcon()
  {
    return 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz48IURPQ1RZUEUgc3ZnIFBVQkxJQyAiLS8vVzNDLy9EVEQgU1ZHIDEuMS8vRU4iICJodHRwOi8vd3d3LnczLm9yZy9HcmFwaGljcy9TVkcvMS4xL0RURC9zdmcxMS5kdGQiPjxzdmcgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB3aWR0aD0iMTUiIGhlaWdodD0iMTUuOTY5IiB2aWV3Qm94PSIwIDAgMTUgMTUuOTY5Ij4NCiAgPGRlZnM+DQogICAgPHN0eWxlPg0KICAgICAgLmNscy0xIHsNCiAgICAgICAgZmlsbDogI2ZmZjsNCiAgICAgICAgZmlsbC1ydWxlOiBldmVub2RkOw0KICAgICAgfQ0KICAgIDwvc3R5bGU+DQogIDwvZGVmcz4NCiAgPHBhdGggaWQ9Il8xNi1sb2dvLnN2ZyIgZGF0YS1uYW1lPSIxNi1sb2dvLnN2ZyIgY2xhc3M9ImNscy0xIiBkPSJNMTIuMDEyLDkuMzE4YTAuODM5LDAuODM5LDAsMCwwLS45NTguODVWMjEuNjk0YTAuODM4LDAuODM4LDAsMCwwLC45ODcuODQ1bDAuMDkxLS4wMTlWOS4zMzdaTTkuOTU3LDEwLjU5M0EwLjgzOSwwLjgzOSwwLDAsMCw5LDExLjQ0M3Y5YTAuODM3LDAuODM3LDAsMCwwLC45ODcuODQ1bDAuMDkxLS4wMThWMTAuNjEyWk0yMy4yNjMsOS41MTNsLTkuMDgyLTEuNWEwLjg4OCwwLjg4OCwwLDAsMC0xLjAxNC45VjIzLjA4N2EwLjg4NywwLjg4NywwLDAsMCwxLjA0NC44OTRsOS4wODEtMS42OTRBMC45LDAuOSwwLDAsMCwyNCwyMS4zOTRWMTAuNDEzYTAuOSwwLjksMCwwLDAtLjczOC0wLjlNMjIuNDc2LDIwLjNhMC42NzcsMC42NzcsMCwwLDEtLjUuNjc5bC01Ljk1NiwxYTAuNjQyLDAuNjQyLDAsMCwxLS43MzctMC42NzlWMTAuNzM5QTAuNjQ1LDAuNjQ1LDAsMCwxLDE2LDEwLjA1NWw1Ljk1NiwwLjc4OWEwLjY3NCwwLjY3NCwwLDAsMSwuNTIxLjY4NFYyMC4zWiIgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoLTkgLTgpIi8+DQo8L3N2Zz4=';
  }

  public static function initAjaxURL()
  {
    echo '<script>var INSTAPAGE_AJAXURL = \'' . Connector::getAjaxURL() . '\';</script>';
  }

  public static function getOptions( $configOnly = false )
  {
    $db = DBModel::getInstance();
    
    if( $configOnly )
    {
      $sql = 'SELECT config FROM ' . $db->optionsTable;
      $row = $db->getRow( $sql );

      if( isset( $row->config ) )
      {
        return json_decode( $row->config );
      }

      return new stdClass;
    }
    else
    {
      $sql = 'SELECT * FROM ' . $db->optionsTable;
      $options = $db->getRow( $sql );

      if( isset( $options->config ) )
      {
        $options->config = json_decode( $options->config );

        return $options;  
      }
      
      return new stdClass;
    }
  }

  public static function getOption( $name, $default = false )
  {
    $options = self::getOptions();
    
    if( in_array( $name, array( 'plugin_hash', 'user_name', 'api_keys' ) ) )
    {
      return self::getVar( $options->$name, $default );
    }
    else
    {
      return self::getVar( $options->config->$name, $default );
    }
  }

  public static function updateOptions( $data )
  {
    $userName = @InstapageHelper::getVar( $data->userName, null );
    $userToken = @InstapageHelper::getVar( $data->userToken, null );

    if( $userName === null )
    {
      $userName = @InstapageHelper::getVar( $data->user_name, null );
    }

    if( $userToken === null )
    {
      $userToken = @InstapageHelper::getVar( $data->plugin_hash, null );
    }

    $configJson = !empty( $data->config ) ? json_encode( $data->config ) : '';
    $db = DBModel::getInstance();
    $sql = 'INSERT INTO ' . $db->optionsTable . ' VALUES(1, %s, %s, %s, %s) ON DUPLICATE KEY UPDATE plugin_hash = %s, api_keys = %s, user_name = %s, config = %s';
    
    return $db->query( $sql, $userToken, '', $userName, $configJson, $userToken, '', $userName, $configJson );
  }

  public static function getTokens()
  {
    $config = self::getOptions( true );
    $tokens = array();

    if( !isset( $config->tokens ) || !is_array( $config->tokens ) )
    {
      return array();
    }

    foreach( $config->tokens as $token )
    {
      $tokens[] = $token->value;      
    }

    return $tokens;
  }

  public static function getVar( &$value, $default = false )
  {
    return isset( $value ) ? $value : $default;
  }

  public static function isCustomParamPresent()
  {
    $excluded_params = array
    (
      's' => null,
      'post_type' => null,
      'preview' => 'true'
    );

    $custom_params_option = explode( '|', stripslashes( self::getOption( 'customParams', '' ) ) );
    $custom_params = array();
    $param_arr = null;
    $key = null;
    $value = null;

    foreach( $custom_params_option as $param )
    {
      $param_arr = explode( '=', $param );
      $key = isset( $param_arr[ 0 ] ) ? $param_arr[ 0 ] : null;
      $value = isset( $param_arr[ 1 ] ) ? str_replace( '"', '', $param_arr[ 1 ] ) : null;
      $custom_params[ $key ] = $value;
    }

    if( count( $custom_params ) )
    {
      $excluded_params = array_merge( $excluded_params, $custom_params );
    }

    foreach( $excluded_params as $key => $value )
    {
      if(
        ( $key !== null && $value == null && isset( $_GET[ $key ] )  ) ||
        ( $key !== null && $value !== null && isset( $_GET[ $key ] ) && $_GET[ $key ] == $value )
      )
      {
        return true;
      }
    }
    
    return false;
  }

  public static function prepareUrlForUpdate( $url )
  {
    return trim( str_replace( array( 'http://', 'https://' ), '', $url ), '/' );
  }

  public static function extractSlug( $home_url )
  {
    $uri_segments = explode( '?', $_SERVER[ 'REQUEST_URI' ] );
    self::writeDiagnostics( $uri_segments, 'checkCustomUrl: $uri_segments' );
    $path = trim( parse_url( $home_url, PHP_URL_PATH ), '/' );
    $segment = trim( $uri_segments[ 0 ], '/' );

    if( $path )
    {
      $pos = strpos( $segment, $path  );
      
      if( $pos !== false )
      {
        $segment = substr_replace( $segment, '', $pos, strlen( $path ) );
      }
    }

    $slug = trim( $segment, '/' );
    self::writeDiagnostics( $slug, 'checkCustomUrl: $slug' );

    return $slug;
  }

  public static function getPostData()
  {
    return isset( $_POST[ 'data' ] ) ?  json_decode( urldecode( $_POST[ 'data' ] ) ) : null;
  }

  public static function formatJsonMessage( $text, $status = 'OK' )
  {
    self::writeDiagnostics( $text, 'Message' );

    return json_encode( (object) array( 'status' => $status, 'message' => $text ) );
  }

  public static function checkResponse( $response, $message = '', $print = true )
  {
    if( is_null( $response ) )
    {
      $msgText = Connector::lang( 'Can\'t decode API response. %s', $message );
      if( $print )
      {
        echo self::formatJsonMessage( $msgText, 'ERROR' );
      }
      else
      {
        InstapageHelper::writeDiagnostics( $msgText, 'message' );
      }

      return false;
    }
    
    if( !$response->success )
    {
      $text = @self::getVar( $response->message, '' );
      
      if( $print )
      {
        if( $text )
        {
          echo self::formatJsonMessage( Connector::lang( $text ), 'ERROR' );
        }
        else
        {
          echo self::formatJsonMessage( Connector::lang( 'API returned an error. %s', $message ), 'ERROR' );  
        }
      }
      else
      {
        InstapageHelper::writeDiagnostics( $text, 'message' );
      }

      return false;
    }

    return true;
  }

  public static function writeDiagnostics( $value, $name = '' )
  {
    $log = DebugLogModel::getInstance();

    if( $log->isDiagnosticMode() )
    {
      $log->write( $value, $name );
    }
  }

  public static function writeLog( $value, $name = '' )
  {
    $log = DebugLogModel::getInstance();
    $log->write( $value );
  }

  public static function getAuthHeader( $tokens )
  {
    self::writeDiagnostics( $tokens, 'Decoded tokens');

    return base64_encode( json_encode( $tokens ) ); 
  }

  public static function httpResponseCode( $code = 200 )
  {
    if( function_exists( 'http_response_code' ) )
    {
      http_response_code( $code );

      return;
    }

    if( $code === NULL )
    {
      $code = 200;
    }
      
    switch( $code )
    {
      case 100: $text = 'Continue'; break;
      case 101: $text = 'Switching Protocols'; break;
      case 200: $text = 'OK'; break;
      case 201: $text = 'Created'; break;
      case 202: $text = 'Accepted'; break;
      case 203: $text = 'Non-Authoritative Information'; break;
      case 204: $text = 'No Content'; break;
      case 205: $text = 'Reset Content'; break;
      case 206: $text = 'Partial Content'; break;
      case 300: $text = 'Multiple Choices'; break;
      case 301: $text = 'Moved Permanently'; break;
      case 302: $text = 'Moved Temporarily'; break;
      case 303: $text = 'See Other'; break;
      case 304: $text = 'Not Modified'; break;
      case 305: $text = 'Use Proxy'; break;
      case 400: $text = 'Bad Request'; break;
      case 401: $text = 'Unauthorized'; break;
      case 402: $text = 'Payment Required'; break;
      case 403: $text = 'Forbidden'; break;
      case 404: $text = 'Not Found'; break;
      case 405: $text = 'Method Not Allowed'; break;
      case 406: $text = 'Not Acceptable'; break;
      case 407: $text = 'Proxy Authentication Required'; break;
      case 408: $text = 'Request Time-out'; break;
      case 409: $text = 'Conflict'; break;
      case 410: $text = 'Gone'; break;
      case 411: $text = 'Length Required'; break;
      case 412: $text = 'Precondition Failed'; break;
      case 413: $text = 'Request Entity Too Large'; break;
      case 414: $text = 'Request-URI Too Large'; break;
      case 415: $text = 'Unsupported Media Type'; break;
      case 500: $text = 'Internal Server Error'; break;
      case 501: $text = 'Not Implemented'; break;
      case 502: $text = 'Bad Gateway'; break;
      case 503: $text = 'Service Unavailable'; break;
      case 504: $text = 'Gateway Time-out'; break;
      case 505: $text = 'HTTP Version not supported'; break;
      default: $code = 200; $text = 'OK'; break;
    }
    
    $protocol = ( isset( $_SERVER[ 'SERVER_PROTOCOL' ] ) ? $_SERVER[ 'SERVER_PROTOCOL' ] : 'HTTP/1.1' );
    header( $protocol . ' ' . $code . ' ' . $text );
    $GLOBALS[ 'http_response_code' ] = $code;
  }
}
