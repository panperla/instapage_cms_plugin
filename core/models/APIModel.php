<?php
class APIModel
{
  private static $apiModel = null;
  
  public static function getInstance()
  {
    if( self::$apiModel === null )
    {
      self::$apiModel = new APIModel();
    }

    return self::$apiModel;
  }

  public function remotePost( $url, $data = array(), $headers = array() )
  {
    return Connector::getSelectedConnector()->remotePost( $url, $data, $headers );
  }

  public function enterpriseCall( $action, $data = array(), $cookies = false )
  {
    $integration = Connector::getSelectedConnector()->name;
    $url = INSTAPAGE_ENTERPRISE_ENDPOINT . '/' . $action;
    $data[ 'integration' ] = $integration;
    $data[ 'useragent' ] = $_SERVER[ 'HTTP_USER_AGENT' ];
    $data[ 'ip' ] = $_SERVER[ 'REMOTE_ADDR' ];
    $data[ 'cookies' ] = $cookies;
    $data[ 'custom' ] = @InstapageHelper::getVar( $_GET[ 'custom' ], null );
    $data[ 'variant' ] = @InstapageHelper::getVar( $_GET[ 'variant' ], null );
    $data[ 'tags' ] = $_GET;
    
    $response = $this->remotePost( $url , $data );

    InstapageHelper::writeDiagnostics( $url, 'Enterprise call URL');
    InstapageHelper::writeDiagnostics( $data, 'Enterprise call data');
    InstapageHelper::writeDiagnostics( $response, 'Enterprise call response');
    
    return $response;
  }

  public function apiCall( $action, $data = array(), $headers = array(), $method = 'POST' )
  {
    $integration = Connector::getSelectedConnector()->name;
    $url = INSTAPAGE_APP_ENDPOINT . '/' . $action;
    $headers[ 'integration' ] = $integration;;
    $response = Connector::getSelectedConnector()->remoteRequest( $url, $data, $headers, $method );

    InstapageHelper::writeDiagnostics( $method . ' : ' . $url, 'API ' . $action . ' URL' );
    InstapageHelper::writeDiagnostics( $data, 'API ' . $action . ' data' );
    InstapageHelper::writeDiagnostics( $headers, 'API ' . $action . ' headers' );
    InstapageHelper::writeDiagnostics( $response, 'API ' . $action . ' response' );

    return isset( $response[ 'body' ] ) ? $response[ 'body' ] : null;
  }

  public function authorise( $email, $password )
  {
    $data = array( 'email' => $email, 'password' => $password );
    $response = $this->apiCall( 'page', $data );
  
    return $response;
  }
}
