<?php
use Drupal\Core\Database\Database as Database;
use Drupal\Core\Database\Connection as Connection;
use Drupal\Core\Site\Settings as Settings;

class InstapageDrupal8Connector
{
  var $name = 'drupal';

  public function getCMSName()
  {
    return 'Drupal';
  }

  public function currentUserCanManage()
  {
    return true;
  }

  private function prepareFunctionArgs( $args = array() )
  {
    if( isset( $args[ 0 ] ) && is_array( $args[ 0 ] ) )
    {
      $args = $args[ 0 ];
    }

    return $args;
  }

  public function query( $sql )
  {
    $args = func_get_args();
    array_shift( $args );
    $args = $this->prepareFunctionArgs( $args );

    try
    {
      $statement = $this->prepare( $sql );

      if( count( $args ) )
      {
        return $statement->execute( $args );
      }
      else
      {
        return $statement->execute();
      }
      
    }
    catch( Exception $e )
    {
      error_log( $e->getMessage() );

      return false;
    }
  }

  public function lastInsertId()
  {
    $sql = 'SELECT LAST_INSERT_ID() as last_insert_id';
    $result = $this->getRow( $sql );
    
    return isset( $result->last_insert_id ) ? $result->last_insert_id : false;
  }

  public function prepare( $sql )
  {
    $sql = str_replace( array( '\'%s\'', '%s' ), '?', $sql );
    $connection = Database::getConnection();

    return $connection->prepare( $sql );
  }

  public function getRow( $sql )
  {
    $args = func_get_args();
    array_shift( $args );
    $args = $this->prepareFunctionArgs( $args );

    try
    {
      $statement = $this->prepare( $sql );

      if( count( $args ) )
      {
        $statement->execute( $args );
      }
      else
      {
        $statement->execute();
      }

      return $statement->fetch( PDO::FETCH_OBJ );
      
    }
    catch( Exception $e )
    {
      error_log( $e->getMessage() );

      return false;
    }
  }

  public function getResults( $sql )
  {
    $args = func_get_args();
    array_shift( $args );
    $args = $this->prepareFunctionArgs( $args );

    try
    {
      $statement = $this->prepare( $sql );

      if( count( $args ) )
      {
        $statement->execute( $args );
      }
      else
      {
        $statement->execute();
      }

      return $statement->fetchAll( PDO::FETCH_OBJ );
      
    }
    catch( Exception $e )
    {
      error_log( $e->getMessage() );

      return false;
    }
  }
  
  public function getDBPrefix()
  {
    $connection_key = Database::getConnection()->getKey();
    $settings = Database::getConnectionInfo( $connection_key );

    if( !isset( $settings[ 'prefix' ] ) && is_array( $settings ) )
    {
      $settings = array_pop( $settings );
    }

    if( isset( $settings[ 'prefix' ] ) && is_array( $settings[ 'prefix' ] ) )
    {
      $settings[ 'prefix' ] = array_pop( $settings[ 'prefix' ] );
    }

    return isset( $settings[ 'prefix' ] ) ? $settings[ 'prefix' ] : '';
  }

  public function getCharsetCollate()
  {
    return 'COLLATE utf8mb4_general_ci';
  }

  public function remoteRequest( $url, $data, $headers = array(), $method = 'POST' )
  {
    try
    {
      if( $method == 'POST' && ( !is_array( $data ) || !count( $data ) ) )
      {
        $data = array( 'ping' => true );
        InstapageHelper::writeDiagnostics( $data, 'Request (' . $method . ') data empty. Ping added.' );
      }

      $form_params = $data;

      if( $method == 'GET' && is_array( $data ) )
      {
        $data_string = http_build_query( $data, '', '&' );
        $url .= '?' . urldecode( $data_string );
        $form_params = array();
      }
    
      $client = \Drupal::httpClient();
      $request = $client->request(
        $method,
        $url, 
        array(
          'allow_redirects' => array(
            'max' => 5
          ),
          'connect_timeout' => 45,
          'synchronous' => true,
          'cookies' => array(),
          'version' => '1.0',
          'form_params' => $form_params,
          'headers' => $headers
        )
      );

      if( $request->getStatusCode() === 200 )
      {
        return $this->prepareResponse( $request );
      }
      else
      {
        return array( 'status' => 'ERROR', 'message' => Connector::lang( 'Request failed with status %s.', $request->getStatusCode() ) );
      }
    }
    catch( Exception $e )
    {
      return array( 'status' => 'ERROR', 'message' => Connector::lang( 'Request failed.' ) );
    }
  }

  public function remotePost( $url, $data, $headers = array() )
  {
    return $this->remoteRequest( $url, $data, $headers, 'POST' );
  }

  public function remoteGet( $url, $data, $headers = array() )
  {
    return $this->remoteRequest( $url, $data, $headers, 'GET' );
  }

  private function prepareResponse( $request )
  {
    return array(
      'body' => (string) $request->getBody(),
      'status' => $request->getReasonPhrase(),
      'code' => $request->getStatusCode()
    );
  }

  public function getSiteURL( $protocol = true )
  {
    $url = $_SERVER[ 'HTTP_HOST' ];

    if( $protocol )
    {
      if( isset( $_SERVER[ 'HTTPS' ] ) && $_SERVER[ 'HTTPS' ] === 'on' )
      {
        $url = 'https://' . $url;
      }
      else
      {
        $url = 'http://' . $url;
      }
    }

    return $url;
  }

  public function getHomeURL( $protocol = true )
  {
    
    $url = $this->getSiteURL( $protocol );

    return $url;
  }

  public function getAjaxURL()
  {
    return $this->getSiteURL() . '/index.php?action=instapage_ajax_call';
  }

  public function lang()
  {
    $arguments = func_get_arg( 0 );
    
    if( !count( $arguments ) )
    {
      return null;
    }

    $text = $arguments[ 0 ];
    $variables = array_slice( $arguments, 1 );

    if( !count( $variables ) )
    {
      return $text;
    }

    return vsprintf( $text, $variables );
  }

  public function initPlugin()
  {
    $action = InstapageHelper::getVar( $_GET[ 'action' ], '' );
    
    if( $action == 'instapage_ajax_call' )
    {
      $this->ajaxCallback();
    }
    else
    {
      InstapageHelper::writeDiagnostics( $_SERVER[ 'REQUEST_URI' ], 'Instapage plugin initiated. REQUEST_URI' );
      $this->checkProxy();
      $this->checkHomepage();
      $this->checkCustomUrl();
    }
  }

  public function removePlugin()
  {
    $subaccount = SubaccountModel::getInstance();
    $db = DBModel::getInstance();
    $subaccount->disconnectAccountBoundSubaccounts( true );
    $db->removePluginTables();
  }
  
  public function loadPluginDashboard()
  {
    InstapageHelper::loadTemplate( 'messages' );
    InstapageHelper::loadTemplate( 'toolbar' );
    InstapageHelper::loadTemplate( 'base' );
  }

  public function ajaxCallback()
  {
    Connector::ajaxCallback();
  }

  public function isLoginPage()
  {
    $request_url = $_SERVER[ 'REQUEST_URI' ];
    
    if( strpos( $request_url, '/user' ) === 0 || $_GET[ 'q' ] == 'user' )
    {
      return true;
    }
    
    return false;
  }

  public function isAdmin()
  {
    $request_url = $_SERVER[ 'REQUEST_URI' ];
    
    if( strpos( $request_url, '/admin' ) === 0 || $_GET[ 'q' ] == 'admin' )
    {
      return true;
    }

    return false;
  }

  
  public function checkPage( $type, $slug = '' )
  {
    if( !$this->isHtmlReplaceNecessary() )
    {
      return;
    }

    $page = PageModel::getInstance();
    $result = $page->getByType( $type, $slug, array( 'instapage_id' ) );

    if( !$result )
    {
      return;
    }

    $instapage_id = $result->instapage_id;
    $page->display( $instapage_id );
  }

  public function checkHomepage()
  {
    $home_url = str_replace( array( 'http://', 'https://' ), '', rtrim( $this->getHomeURL(), '/' ) );
    $home_url_segments = explode( '/', $home_url );
    $uri_segments = explode( '?', $_SERVER[ 'REQUEST_URI' ] );
    $uri_segments = explode( '/', rtrim( $uri_segments[ 0 ], '/' ) );

    if(
      ( count( $uri_segments ) !== count( $home_url_segments ) ) || 
      ( count( $home_url_segments ) > 1 && $home_url_segments[ 1 ] != $uri_segments[ 1 ] )
    )
    {
      return false;
    }

    $this->checkPage( 'home' );
    
    return true;
  }

  public function check404()
  {
    if( is_404() )
    {
      $this->checkPage( '404' );  

      return true;
    }

    return false;
  }

  public function checkCustomUrl()
  {
    $slug = InstapageHelper::extractSlug( $this->getHomeURL() );
    
    if( $slug )
    {
      $this->checkPage( 'page', $slug );
    }

    return true;
  }

  public function checkProxy()
  {
    $services = ServicesModel::getInstance();

    if( $services->isServicesRequest() )
    {
      try
      {
        $services->processProxyServices();

        return;
      }
      catch( Exception $e )
      {
        echo $e->getMessage();
      }
    }
  }

  public function getProhibitedSlugs()
  {
    $result = array_merge( $this->getPostSlugs(), Connector::getLandingPageSlugs() );

    return $result;
  }

  public function getOptionsDebugHTML()
  {
    return '';
  }

  public function getPluginsDebugHTML()
  {
    return '';
  }

  public function getSitename( $sanitized = false )
  {
    $sitename = \Drupal::config( 'system.site' )->get( 'name' );

    if( $sanitized )
    {
      return mb_strtolower( str_replace( ' ', '-', $sitename ) );
    }

    return $sitename;
  }

  public function mail( $to, $subject, $message, $headers = '', $attachments = array() )
  {
    $mailManager = \Drupal::service( 'plugin.manager.mail' );
    $module = 'instapage_cms_plugin';
    $key = 'custom_email';
    $params[ 'message' ] = $message;
    $params[ 'subject' ] = $subject;
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $send = true;

    return $mailManager->mail( $module, $key, $to, $langcode, $params, NULL, $send );
  }

  public function getDeprecatedData()
  {
    $config = \Drupal::config( 'instapage.pages' );
    $pages = $config->get( 'instapage_pages' );
    $results = array();

    foreach( $pages as $key => $slug )
    {
      $page_obj = new stdClass;
      $page_obj->id = 0;
      $page_obj->landingPageId = $key;
      $page_obj->slug = $slug;
      $page_obj->type = 'page';
      $results[] = $page_obj;
    }

    return $results;
  }

  public function escapeHTML( $html )
  {
    return \Drupal\Component\Utility\Html::escape( $html );
  }

  private function getPostSlugs()
  {
    $edit_url = $this->getSiteURL();
    $db_prefix = $this->getDBPrefix();
    $sql = 'SELECT pid AS id, SUBSTRING( alias, 2 ) AS slug, CONCAT(\'' . $edit_url . '\', source, \'/edit\') AS editUrl FROM ' . $db_prefix . 'url_alias';
    $results = $this->getResults( $sql );

    return $results;
  }

  private function isHtmlReplaceNecessary()
  {
    if( $this->isAdmin() || $this->isLoginPage() || InstapageHelper::isCustomParamPresent() )
    {
      return false;
    }

    return true;
  }
}
