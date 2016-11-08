<?php
class AjaxController
{
  private static $ajaxController = null;

  public static function getInstance()
  {
    if( self::$ajaxController === null )
    {
      self::$ajaxController = new AjaxController();
    }

    return self::$ajaxController;
  }

  public function doAction( $action, $data = null )
  {
    InstapageHelper::writeDiagnostics( $action, 'AJAX Action');
    
    if( !Connector::currentUserCanManage() )
    {
      echo InstapageHelper::formatJsonMessage( Connector::lang( 'You don\'t have permission to perform that action.' ), 'ERROR' );
      exit;
    }

    switch( $action )
    {
      case 'loginUser':
        $this->loginUser();
      break;
      
      case 'getApiTokens':
        $this->getApiTokens();
      break;

      case 'connectSubAccounts':
        $subaccount = SubaccountModel::getInstance();
        $subaccount->setSubAccountsStatus( 'connect' );
      break;

      case 'disconnectSubAccounts':
        $subaccount = SubaccountModel::getInstance();
        $subaccount->setSubAccountsStatus( 'disconnect' );
      break;

      case 'disconnectAccountBoundSubaccounts':
        $subaccount = SubaccountModel::getInstance();
        $subaccount->disconnectAccountBoundSubaccounts();
      break;

      case 'getAccountBoundSubAccounts':
        $subaccount = SubaccountModel::getInstance();
        $subaccount->getAccountBoundSubAccounts();
      break;

      case 'updateOptions':
        if( InstapageHelper::updateOptions( $data ) !== false )
        {
          echo InstapageHelper::formatJsonMessage( Connector::lang( 'Configuration updated' ), 'OK' );  
        }
        else
        {
          echo InstapageHelper::formatJsonMessage( Connector::lang( 'There was an error during configuration save' ), 'ERROR' );   
        }
      break;
      
      case 'getOptions':
        echo json_encode( InstapageHelper::getOptions() );
      break;
      
      case 'getLog':
        $this->getLog();
      break;

      case 'clearLog':
        $log = DebugLogModel::getInstance();
        $log->clear();
        echo InstapageHelper::formatJsonMessage( Connector::lang( 'Log cleared' ), 'OK' );
      break;

      case 'getMasterToken':
        $this->getMasterToken();
      break;

      case 'loadListPages':
        $this->loadListPages();
      break;

      case 'loadEditPage':
        $this->loadEditPage();
      break;

      case 'getLandingPages':
        $this->getLandingPages();
      break;

      case 'getStats':
        $this->getStats();
      break;

      case 'publishPage':
        $this->publishPage();
      break;

      case 'deletePage':
        $this->deletePage();
      break;

      case 'loadSettings':
        echo json_encode( (object) array( 
          'status' => 'OK', 
          'html' => InstapageHelper::loadTemplate( 'settings', false ),
          'initialData' => InstapageHelper::getOptions()
        ) );
      break;

      case 'getProhibitedSlugs':
        $data = Connector::getSelectedConnector()->getProhibitedSlugs();
        echo json_encode( (object) array( 
          'status' => 'OK', 
          'data' => $data
        ) );
      break;

      case 'validateToken':
        $this->validateToken();
      break;

      case 'migrateDeprecatedData':
        $data = Connector::getSelectedConnector()->getDeprecatedData();
        $page = PageModel::getInstance();
        $raport = $page->migrateDeprecatedData( $data );
        $raport_str = implode( '<br />', $raport );
        echo InstapageHelper::formatJsonMessage( $raport_str );
      break;

      default:
        echo InstapageHelper::formatJsonMessage( Connector::lang( 'Unsupported AjaxController action' ), 'ERROR' );
    }
  }

  private function loginUser()
  {
    $api = APIModel::getInstance();
    $post = InstapageHelper::getPostData();
    $email = InstapageHelper::getVar( $post->data->email, '' );
    $password = InstapageHelper::getVar( $post->data->password, '' );
    $response = json_decode( $api->authorise( $email, $password ) );

    if( !InstapageHelper::checkResponse( $response, null, false ) || !$response->success )
    {
      $message = InstapageHelper::getVar( $response->message, '' );
      echo InstapageHelper::formatJsonMessage( $message, 'ERROR' );
      return false;
    }
    else
    {
      echo json_encode( (object) array(
        'status' => 'OK',
        'data' => (object) $response->data
      ) );
    }
  }

  private function validateToken()
  {
    $api = APIModel::getInstance();
    $post = InstapageHelper::getPostData();
    $token = InstapageHelper::getVar($post->data->token, null);
    $headers = array( 'accountkeys' => InstapageHelper::getAuthHeader( array( $token ) ) );
    $response = json_decode( $api->apiCall( 'page/get-sub-accounts-list', null, $headers ) );
    $sub_account = @InstapageHelper::getVar( $response->data, null );
    
    if( !InstapageHelper::checkResponse( $response, null, false) || !$response->success || count( $sub_account ) == 0 )
    {
      echo json_encode( (object) array( 
        'status' => 'OK', 
        'valid' => false
      ) );
    }
    else
    {
      echo json_encode( (object) array( 
        'status' => 'OK', 
        'valid' => true
      ) );
    }
  }

  private function getLog()
  {
    $log = DebugLogModel::getInstance();
    $sitename_sanitized = Connector::getSitename( true ) ;
    try
    { 
      $data = $log->getLogHTML();
      echo json_encode( (object) array( 
        'status' => 'OK', 
        'data' => $data,
        'sitename' => $sitename_sanitized
      ) );
    }
    catch( Exception $e )
    {
      echo InstapageHelper::formatJsonMessage( $e->getMessage(), 'ERROR' );
    }
  }

  private function getApiTokens()
  {
    $subaccount = SubaccountModel::getInstance();
    $tokens = $subaccount->getAllTokens();
    echo json_encode( (object) array(  
      'status' => 'OK', 
      'data' => $tokens
    ) );
  }

  private function loadEditPage()
  {
    $api = APIModel::getInstance();
    $subaccount = SubaccountModel::getInstance();
    $post = InstapageHelper::getPostData();
    InstapageHelper::writeDiagnostics( $post, 'Edit page POST');
    $tokens = InstapageHelper::getVar( $post->apiTokens, false );

    if( !$tokens )
    {
      $tokens = $subaccount->getAllTokens();
    }

    $page_data = null;
    $sub_accounts = null;
    $data = array();

    if( isset( $post->data->id ) )
    {
      $page_data = $post->data;
      $data[ 'pages' ] = array( $post->data->instapage_id );

    }
    
    $headers = array( 'accountkeys' => InstapageHelper::getAuthHeader( $tokens ) );
    $response = json_decode( $api->apiCall( 'page/get-sub-accounts-list', $data, $headers ) );

    if( InstapageHelper::checkResponse( $response ) )
    {
      $sub_accounts = $response->data;
    }
    else
    {
      return false;
    }

    $initialData = array( 'subAccounts' => $sub_accounts, 'page' => $page_data );
    InstapageHelper::writeDiagnostics( $initialData, 'Edit page initialData');

    echo json_encode( (object) array( 
      'status' => 'OK', 
      'html' => InstapageHelper::loadTemplate( 'edit', false ),
      'data' => (object) $initialData
    ) );
  }

  private function loadListPages()
  {
    $request_limit = 300;
    $post = InstapageHelper::getPostData();
    $page = PageModel::getInstance();
    InstapageHelper::writeDiagnostics( $post, 'List page POST');    
    $api = APIModel::getInstance();
    $subaccount = SubaccountModel::getInstance();
    $local_pages_array = $page->getAll( array( 'id', 'instapage_id', 'slug', 'type', 'stats_cache' ) );
    
    //WP Legacy code - automatic migration
    if( !count( $local_pages_array ) && Connector::isWP() && Connector::getSelectedConnector()->legacyArePagesPresent() )
    {
      $data = Connector::getSelectedConnector()->getDeprecatedData();
      $page = PageModel::getInstance();
      $page->migrateDeprecatedData( $data );
      $local_pages_array = $page->getAll( array( 'id', 'instapage_id', 'slug', 'type', 'stats_cache' ) );
    }
   
    $pages = array();
    
    foreach( $local_pages_array as &$page_object )
    {
      $page_object->stats_cache = json_decode($page_object->stats_cache);
      $pages[] = $page_object->instapage_id;
    }

    $tokens = InstapageHelper::getVar( $post->apiTokens, false );

    if( !$tokens )
    {
      $tokens = $subaccount->getAllTokens();
    }

    $headers = array( 'accountkeys' => InstapageHelper::getAuthHeader( $tokens ) );
    $responses = array();
    $success = true;

    for( $i = 0; $i * $request_limit < count( $pages ); ++$i )
    {
      $data_slice = array_slice( $pages, $i * $request_limit, $request_limit );
      $data = array( 'pages' => $data_slice );
      $response_json = $api->apiCall( 'page/list', $data, $headers, 'GET' );
      $response = json_decode( $response_json );

      if( InstapageHelper::checkResponse( $response ) )
      {
        $responses[] = $response->data;
      }
      else
      {
        $success = false;
        break;
      }
    }

    if( $success )
    {
      $merged_response = array();

      foreach( $responses as $r )
      {
        $merged_response = array_merge( $merged_response, $r );
      }

      $page->mergeListPagesResults( $local_pages_array, $merged_response );
      InstapageHelper::writeDiagnostics( $local_pages_array, 'List page array');
      echo json_encode( (object) array( 
        'status' => 'OK', 
        'html' => InstapageHelper::loadTemplate( 'listing', false ),
        'initialData' => $local_pages_array
      ) );
    }
    else
    {
      return false;
    }
  }

  private function getLandingPages()
  {
    $api = APIModel::getInstance();
    $post = InstapageHelper::getPostData();
    $tokens = array( $post->data->subAccountToken );
    $headers = array( 'accountkeys' => InstapageHelper::getAuthHeader( $tokens ) );
    $response_json = $api->apiCall( 'page/list', null, $headers );
    $response =json_decode( $response_json );
    $page = PageModel::getInstance();
    $published_pages = $page->getAll( array( 'instapage_id' ) );
    $self_instapage_id = @InstapageHelper::getVar( $post->data->selfInstapageId, null );

    if( InstapageHelper::checkResponse( $response ) )
    {
      if( is_array( $response->data ) )
      {
        foreach( $response->data as $key => $returned_page )
        {
          foreach( $published_pages as $published_page )
          {
            if( $returned_page->id != $self_instapage_id && $returned_page->id == $published_page->instapage_id )
            {
              unset( $response->data[ $key ] );
              break;
            }
          }
        }

        $response->data = array_values( $response->data );
      }
      else
      {
        $response->data = array();
      }

      echo json_encode( (object) array( 
        'status' => 'OK', 
        'data' => $response->data
      ) );
    }
    else
    {
      return false;
    }
  }

  private function getStats()
  {
    $post = InstapageHelper::getPostData();
    $page = PageModel::getInstance();
    $api = APIModel::getInstance();
    $subaccount = SubaccountModel::getInstance();
    $pages = InstapageHelper::getVar( $post->data->pages, array() );

    if( !count( $pages ) )
    {
      InstapageHelper::writeDiagnostics( 'Stats cond', 'No pages in request' );
      echo json_encode( (object) array( 
        'status' => 'OK', 
        'data' => array()
      ) );

      return true;
    }

    $cached_stats = $page->getPageStatsCache( $pages );
    InstapageHelper::writeDiagnostics( $cached_stats, 'Cached stats');
    $pages_without_stats = array();

    foreach( $pages as $instapage_id )
    {
      if( !isset( $cached_stats[ $instapage_id ] ) )
      {
        $pages_without_stats[] = $instapage_id;
      }
    }

    if( empty( $pages_without_stats ) )
    {
      echo json_encode( (object) array( 
        'status' => 'OK', 
        'data' => $cached_stats
      ) );

      return true;
    }

    $tokens = InstapageHelper::getVar( $post->apiTokens, false );

    if( !$tokens )
    {
      $tokens = $subaccount->getAllTokens();
    }

    $headers = array( 'accountkeys' => InstapageHelper::getAuthHeader( $tokens ) );
    $data = array( 'pages' => $pages_without_stats );
    $response_json = $api->apiCall( 'page/stats', $data, $headers );
    $response =json_decode( $response_json );

    if( InstapageHelper::checkResponse( $response ) )
    {
      $stats = (array) InstapageHelper::getVar( $response->data, array() );
      $page->savePageStatsCache( $stats );
      
      if( count( $stats ) )
      {
        $stats = array_merge( $cached_stats, $stats );
      }
      else
      {
        $stats = $cached_stats;
      }

      echo json_encode( (object) array( 
        'status' => 'OK', 
        'data' => $stats
      ) );
    }
    else
    {
      return false;
    }
  }

  private function publishPage()
  {
    $page = PageModel::getInstance();
    $post = InstapageHelper::getPostData();
    $data = $post->data;

    echo $page->publishPage( $data );
  }

  private function deletePage()
  {
    $page = PageModel::getInstance();
    $api = APIModel::getInstance();
    $subaccount = SubaccountModel::getInstance();
    $post = InstapageHelper::getPostData();
    $result = $page->get( $post->data->id, array( 'instapage_id' ) );
    $instapage_id = $result->instapage_id;
    $tokens = InstapageHelper::getVar( $post->apiTokens, false );

    if( !$tokens )
    {
      $tokens = $subaccount->getAllTokens();
    }

    $data = array(
      'page' => $instapage_id,
      'url' => '',
      'publish' => 0
    );
    $headers = array( 'accountkeys' => InstapageHelper::getAuthHeader( $tokens ) );
    $response = json_decode( $api->apiCall( 'page/edit', $data, $headers ) );

    $message = '';

    if( !InstapageHelper::checkResponse( $response, null, false) || !$response->success )
    {
      $message .= Connector::lang( 'Page that you are removing (Instapage ID: %s) doesn\'t exist in your Instapage application\'s dashboard. It could have been deleted from app or created by another user. Deleting this page won\'t affect Instapage application\'s dashboard.', $instapage_id );

      if( isset( $response->message ) && $response->message !== '' )
      {
        $message .= Connector::lang( ' Instapage app response: ' . $response->message );
      }
    }

    if( isset( $post->data->id ) && $page->delete( $post->data->id ) )
    {
      if( $message )
      {
        echo InstapageHelper::formatJsonMessage( $message );
      }
      else
      {
        echo InstapageHelper::formatJsonMessage( Connector::lang( 'Page deleted successfully.' ) );
      }
      
      return true;
    }
    else
    {
      echo InstapageHelper::formatJsonMessage( Connector::lang( 'There was a database error during page delete process.' ), 'ERROR' );
      
      return false;
    }
  }
}
