<?php
class ServicesModel
{
  private static $servicesModel = null;
  
  public static function getInstance()
  {
    if( self::$servicesModel === null )
    {
      self::$servicesModel = new ServicesModel();
    }

    return self::$servicesModel;
  }

  public function isServicesRequest()
  {
    if( strpos( $_SERVER[ 'REQUEST_URI' ], 'instapage-proxy-services' ) !== false )
    {
      InstapageHelper::writeDiagnostics( $_SERVER[ 'REQUEST_URI' ], 'Proxy services URL' );

      return true;
    }

    return false;
  }

  public function stripSlashesGpc( &$value )
  {
    $value = stripslashes( $value );
  }

  public function processProxyServices()
  {
    $api = APIModel::getInstance();
    $url = @InstapageHelper::getVar( $_GET[ 'url' ], '' );

    if( strpos( $url, 'ajax/pageserver/email' ) === false )
    {
      throw new Exception( 'Unsupported endpoint: ' . $url );
    }

    $url = INSTAPAGE_PROXY_ENDPOINT . $url;
    
    array_walk_recursive( $_POST, array( $this, 'stripSlashesGpc' ) );

    if ( isset( $_POST ) && !empty( $_POST ) )
    {
      $_POST[ 'user_ip' ] = $_SERVER[ 'REMOTE_ADDR' ];
    }

    $data = $_POST;
    $data[ 'ajax' ] = 1;
    $response = $api->remotePost( $url, $data );

    if( isset( $response[ 'response' ][ 'code' ] ) && $response[ 'response' ][ 'code' ] !== 200 )
    {
      $this->disableCrossOriginProxy();
      $matches = array();
      $pattern = '/email\/(\d*)/';
      preg_match( $pattern, $url, $matches );
      $instapage_id = isset( $matches[ 1 ] ) ? $matches[ 1 ] : 0;
    }

    InstapageHelper::writeDiagnostics( $url, 'Proxy services URL' );
    InstapageHelper::writeDiagnostics( $data, 'Proxy data' );
    InstapageHelper::writeDiagnostics( $response, 'Proxy response' );

    $status = @InstapageHelper::getVar( $response->status );
    $response_code = @InstapageHelper::getVar( $response[ 'response' ][ 'code' ], 200 );
    
    if ( $status === 'ERROR' )
    {
      $error_message = @InstapageHelper::getVar( $response->message );

      if ( !empty( $error_message ) )
      {
        throw new Exception( $error_message );
      }
      else
      {
        throw new Exception( '500 Internal Server Error' );
      }
    }

    ob_start();
    ob_end_clean();
    header( 'Content-Type: text/json; charset=UTF-8' );
    echo trim( @InstapageHelper::getVar( $response[ 'body' ], '') );
    status_header( $response_code );

    exit;
  }

  private function disableCrossOriginProxy()
  {
    $options = InstapageHelper::getOptions();
    $options->config->crossOrigin = 0;
    InstapageHelper::updateOptions( $options );
  }

  private function notifyCustomerSupport( $instapage_id )
  {
    $to = INSTAPAGE_SUPPORT_EMAIL;
    $subject = Connector::lang( 'Instapage WP Plugin: Problem with Cross-Origin Proxy' );
    $message = Connector::lang( 'There is a problem with sending leads.' ) . "\n";
    $message .= Connector::lang( 'Domain: ' ) . Connector::getHomeURL() . "\n";
    $pageModel = PageModel::getInstance();
    
    if( $instapage_id )
    {
      $message .= Connector::lang( 'Page ID: ' ) . $instapage_id . "\n";
      $pages = $pageModel->getByInstapageId( $instapage_id, array( 'slug' ) );
      
      foreach( $pages as $item )
      {
        $message .= Connector::lang( 'Page URL: ' ) . Connector::getHomeURL() . '/' . $item->slug . "\n";
      }

      $message .= 'Cross-Origin proxy automatically disabled.';
    }

    InstapageHelper::writeDiagnostics( $message, 'Proxy Services Disabled' );
    Connector::mail( $to, $subject, $message );
  }
}
