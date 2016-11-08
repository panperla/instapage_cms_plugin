<?php
class SubaccountModel
{
  private static $subaccountModel = null;
  private $subaccount_tokens = null;
  
  public static function getInstance()
  {
    if( self::$subaccountModel === null )
    {
      self::$subaccountModel = new SubaccountModel();
    }

    return self::$subaccountModel;
  }

  public function getAllTokens()
  {
    if( $this->subaccount_tokens === null )
    {
      $tokens = InstapageHelper::getTokens();
      $account_keys = $this->getAccountBoundTokens();
      $this->subaccount_tokens = array_merge( $tokens, $account_keys );
    }

    return $this->subaccount_tokens;
  }

  public function getAccountBoundTokens()
  {
    $api = APIModel::getInstance();
    $user_token = InstapageHelper::getOption( 'plugin_hash' );
    $headers = array( 'usertoken' => $user_token );
    $response_json = $api->apiCall( 'page/get-account-keys', null, $headers );
    $response = json_decode( $response_json );
    $account_keys = array();

    if( !is_null( $response ) && $response->success )
    {
      $account_keys = $response->data->accountkeys;
    }

    return $account_keys;
  }

  public function getAccountBoundSubAccounts( $format = 'json' )
  {
    $api = APIModel::getInstance();
    $tokens = $this->getAccountBoundTokens();
    $headers = array( 'accountkeys' => InstapageHelper::getAuthHeader( $tokens ) );
    $response = json_decode( $api->apiCall( 'page/get-sub-accounts-list', null, $headers ) );
    $sub_accounts = @InstapageHelper::getVar( $response->data, null );

    if( $format == 'json' )
    {
      echo json_encode( (object) array( 
        'status' => 'OK', 
        'data' => $sub_accounts
      ) );
    }
    else
    {
      return $sub_accounts;
    }
  }

  public function setSubAccountsStatus( $status = 'connect', $tokens = null, $silent = false )
  {
    $api = APIModel::getInstance();
    $subaccount = SubaccountModel::getInstance();
    $post = InstapageHelper::getPostData();
    
    if( $tokens !== null )
    {
      $selected_subaccounts = $tokens;
    }
    else
    {
      $selected_subaccounts = InstapageHelper::getVar( $post->data->tokens, array() );  
    }
    
    
    if( count( $selected_subaccounts ) )
    {
      $tokens = $subaccount->getAllTokens();
      $headers = array( 'accountkeys' => InstapageHelper::getAuthHeader( $tokens ) );
      $data = array( 
        'accountkeys' => base64_encode( json_encode( $selected_subaccounts ) ),
        'status' => $status,
        'domain' => Connector::getHomeURL( false )
      );

      $response = json_decode( $api->apiCall( 'page/connection-status', $data, $headers ) );

      if( $silent )
      {
        return;
      }

      if( 
        !InstapageHelper::checkResponse( $response, null, false ) || 
        !$response->success || 
        !isset( $response->data->changed ) || 
        $response->data->changed != count( $selected_subaccounts )
      )
      {
        $actions = array();
        $action[ 0 ] = $status == 'connect' ? 'connected to' : 'disconnected from';
        $action[ 1 ] = $status == 'connect' ? 'connect' : 'disconnect';
        
        if( count( $selected_subaccounts ) > 1 )
        {
          $message = InstapageHelper::getVar( $response->message, Connector::lang( 'There was an error, selected subaccounts are not properly %s app. Try to %s subaccounts again.', $artion[ 0 ], $action[ 1 ] ) );
        }
        else
        {
          $message = InstapageHelper::getVar( $response->message, Connector::lang( 'There was an error, selected subaccount is not properly %s app. Try to %s subaccounts again.', $artion[ 0 ], $action[ 1 ] ) );
        }

        echo InstapageHelper::formatJsonMessage( $message, 'ERROR' );
      }
      else
      {
        $action = array();
        $action[ 0 ] = $status == 'connect' ? 'Selected subaccounts' : 'Subaccounts bound to your account';
        $action[ 1 ] = $status == 'connect' ? 'connected' : 'disconnected';

        if( count( $selected_subaccounts ) > 1 )
        {
          $message = InstapageHelper::getVar( $response->message, Connector::lang( '%s are %s.', $action[ 0 ], $action[ 1 ] ) );
        }
        else
        {
          $message = InstapageHelper::getVar( $response->message, Connector::lang( 'Selected subaccount is %s.', $action[ 1 ]) );
        }
        
        echo InstapageHelper::formatJsonMessage( $message );
      }
    }
    else
    {
      echo InstapageHelper::formatJsonMessage( Connector::lang( 'No subaccounts were connected.' ) );
    }
  }

  public function disconnectAccountBoundSubaccounts( $silent = false )
  {
    $sub_accounts = $this->getAccountBoundSubAccounts( 'array' );

    if( count( $sub_accounts ) )
    {
      $tokens = array();  

      foreach( $sub_accounts as $item )
      {
        $tokens[] = InstapageHelper::getVar( $item->accountkey, '' );
      }

      $this->setSubAccountsStatus( 'disconnect', $tokens, $silent );
    }
    else
    {
      if( !$silent )
      {
        echo InstapageHelper::formatJsonMessage( Connector::lang( 'Subaccounts bound to your account are dissconnected' ) );
      }
    }
  }
}
