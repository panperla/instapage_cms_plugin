<?php
class PageModel
{
  private static $pageModel = null;
  //cache duration in minutes
  private static $statCacheDuration = 15;
  
  public static function getInstance()
  {
    if( self::$pageModel === null )
    {
      self::$pageModel = new PageModel();
    }

    return self::$pageModel;
  }

  public function update( $data )
  {
    $id = InstapageHelper::getVar( $data->id, 0 );
    $instapage_id = InstapageHelper::getVar( $data->landingPageId, null );
    $type = InstapageHelper::getVar( $data->type );
    $slug = InstapageHelper::getVar( $data->slug );
    $db = DBModel::getInstance();
    $sql = 'INSERT INTO ' . $db->pagesTable . ' VALUES(%s, %s, %s, %s, NOW(), \'\', NULL) ON DUPLICATE KEY UPDATE instapage_id = %s, slug = %s, type = %s, time = NOW(), stats_cache = \'\', stats_cache_expires = NULL';
    
    if( $db->query( $sql, $id, $instapage_id, $slug, $type, $instapage_id, $slug, $type ) )
    {
      return ( $id == 0 ) ? $db->lastInsertId() : $id;
    }
    else
    {
      return false;
    }
  }

  public function getAll( $fields = array( '*' ), $conditions = array() )
  {
    $db = DBModel::getInstance();
    $sql = 'SELECT ' . implode( ', ', $fields ) . ' FROM ' . $db->pagesTable;

    if( count( $conditions ) )
    {
      $sql .= ' WHERE ' . implode( ' AND ', $conditions );
    }

    return $db->getResults( $sql );
  }

  public function get( $id, $fields = array( '*' ) )
  {
    $db = DBModel::getInstance();
    $sql = 'SELECT ' . implode( ', ', $fields ) . ' FROM ' . $db->pagesTable . ' WHERE id = \'' . $id . '\'';

    return $db->getRow( $sql );
  }

  public function getBySlug( $slug, $fields = array( '*' ) )
  {
    $db = DBModel::getInstance();
    $sql = 'SELECT ' . implode( ', ', $fields ) . ' FROM ' . $db->pagesTable . ' WHERE slug = %s AND type=\'page\'';

    return $db->getRow( $sql, $slug );
  }

  public function getByType( $type, $slug = '', $fields = array( '*' ) )
  {
    $db = DBModel::getInstance();
    $sql = 'SELECT ' . implode( ', ', $fields ) . ' FROM ' . $db->pagesTable . ' WHERE type = %s';

    if( $slug )
    {
      $sql = $sql . ' AND slug = %s';

      return $db->getRow( $sql, $type, $slug );
    }
    else
    {
      return $db->getRow( $sql, $type );
    }
  }

  public function getByInstapageId( $instapage_id, $fields = array( '*' ) )
  {
    $db = DBModel::getInstance();
    $sql = 'SELECT ' . implode( ', ', $fields ) . ' FROM ' . $db->pagesTable . ' WHERE instapage_id = ' . $instapage_id;
    
    return $db->getResults( $sql );
  }

  public function getPageStatsCache( $ids )
  {
    if( !is_array($ids) || !count( $ids ) )
    {
      return null;
    }

    $db = DBModel::getInstance();

    foreach( $ids as &$item )
    {
      $item = intval( $item );
    }

    $ids_set = implode(', ', $ids );
    $expire_in_seconds = self::$statCacheDuration * 60;
    $sql = 'SELECT instapage_id, stats_cache FROM ' . $db->pagesTable . ' WHERE instapage_id IN(' . $ids_set . ') AND stats_cache_expires + ' . $expire_in_seconds . ' > ' . time();
    $results = $db->getResults( $sql );
    $stats = array();
    
    if( $results )
    {
      foreach( $results as &$item )
      {
        $stats[ $item->instapage_id ] = json_decode( $item->stats_cache );
      }

      return $stats;
    }

    return array();
  }

  public function publishPage( $data )
  {
    $api = APIModel::getInstance();
    $subaccount = SubaccountModel::getInstance();
    $url = $data->slug ? Connector::getHomeURL() . '/' . $data->slug : Connector::getHomeURL();
    $url = InstapageHelper::prepareUrlForUpdate( $url );
    $tokens = InstapageHelper::getVar( $post->apiTokens, false );
    $success = true;

    if( !$tokens )
    {
      $tokens = $subaccount->getAllTokens();
    }

    $old_page_id = InstapageHelper::getVar( $data->id, null );
    $new_instapage_id = InstapageHelper::getVar( $data->landingPageId, null );
    
    if( $old_page_id )
    {
      $old_page = $this->get( $old_page_id, array( 'instapage_id' ) );
      
      if( $old_page->instapage_id != $new_instapage_id )
      {
        $api_data = array(
          'page' => $old_page->instapage_id,
          'url' => '',
          'publish' => 0
        );
        $headers = array( 'accountkeys' => InstapageHelper::getAuthHeader( $tokens ) );
        $response_json = $api->apiCall( 'page/edit', $api_data, $headers );
        $response = json_decode( $response_json );

        if( !InstapageHelper::checkResponse( $response, null, false) || !$response->success )
        {
          $success = false;
        }
      }
    }
    
    if( $success )
    {
      $api_data = array(
        'page' => $data->landingPageId,
        'url' => $url,
        'publish' => 1
      );
      $headers = array( 'accountkeys' => InstapageHelper::getAuthHeader( $tokens ) );
      $response_json = $api->apiCall( 'page/edit', $api_data, $headers );
      $response = json_decode( $response_json );
    }

    if( !$success || !InstapageHelper::checkResponse( $response, null, false) || !$response->success )
    {
      if( isset( $response->message ) && $response->message !== '' )
      {
        return InstapageHelper::formatJsonMessage( Connector::lang( $response->message ), 'ERROR' );
      }
      else
      {
        return InstapageHelper::formatJsonMessage( Connector::lang( 'There was an error during page update process.' ), 'ERROR' );
      }
      
      return false;
    }

    $updated_id = $this->update( $data );

    if( $updated_id )
    {
      return json_encode( (object) array( 
        'status' => 'OK',
        'message' => Connector::lang( 'Page updated successfully.' ), 
        'updatedId' => $updated_id 
      ) );
    }
    else
    {
      return InstapageHelper::formatJsonMessage( Connector::lang( 'There was a database error during page update process.' ), 'ERROR' );
    }
  }

  public function migrateDeprecatedData( $data )
  {
    InstapageHelper::writeDiagnostics( $data, 'Migration data' );
    $raport = array();
    
    if( !is_array( $data ) || !count( $data ) )
    {
      return $raport;
    }
    
    foreach( $data as $deprecated_page )
    {
      if( $deprecated_page->type == 'home' )
      {
        $deprecated_page->slug = '';
      }

      $landing_pages_by_id = $this->getByInstapageId( $deprecated_page->landingPageId );
      $landing_pages_by_slug = null;
      $landing_pages_by_type = null;

      if( count( $landing_pages_by_id ) )
      {
        $new_landing_page = array_pop( $landing_pages_by_id );
        $raport[] = Connector::lang( 'Old version of page (slug: %s, Instapage ID: %s) is present in new database (slug: %s) and won\'t be migrated.', $deprecated_page->slug, $deprecated_page->landingPageId, $new_landing_page->slug );

        continue;
      }

      if( $deprecated_page->slug && $deprecated_page->type == 'page' )
      {
        $landing_pages_by_slug = $this->getBySlug( $deprecated_page->slug );
      }

      if( $landing_pages_by_slug )
      {
        $new_landing_page = $landing_pages_by_slug;
        $raport[] = Connector::lang( 'Slug: %s is already taken in new database. Old page (slug: %s, Instapage ID: %s) won\'t be migrated.', $deprecated_page->slug, $deprecated_page->slug, $deprecated_page->landingPageId );

        continue;
      }

      if( $deprecated_page->type !== 'page' )
      {
        $landing_pages_by_type = $this->getByType( $deprecated_page->type );
      }

      if( $landing_pages_by_type )
      {
        $new_landing_page = $landing_pages_by_type;
        $raport[] = Connector::lang( 'One %s page already exists in new database. Old page (slug: %s, Instapage ID: %s) won\'t be migrated.', $deprecated_page->type, $deprecated_page->slug, $deprecated_page->landingPageId );

        continue;
      }

      if( $this->update( $deprecated_page ) ) {
        $raport[] = Connector::lang( 'Old version of page (slug: %s, Instapage ID: %s) successfully migrated.', $deprecated_page->slug, $deprecated_page->landingPageId );
      }
      else
      {
        $raport[] = Connector::lang( 'Old version of page (slug: %s, Instapage ID: %s) cannot be migrated due to database error.', $deprecated_page->slug, $deprecated_page->landingPageId );
      }
    }

    InstapageHelper::writeDiagnostics( $raport, 'Migration raport' );

    return $raport;
  }

  public function savePageStatsCache( $data )
  {
    $db = DBModel::getInstance();

    foreach( $data as $key => $item )
    {
      $sql = 'UPDATE ' . $db->pagesTable . ' SET stats_cache = %s, stats_cache_expires = ' . time() . ' WHERE instapage_id = %s';
      $db->query( $sql, json_encode( $item ), $key  );
    }
  }

  public function getHomepage( $fields = array( '*' ) )
  {
    $db = DBModel::getInstance();
    $sql = 'SELECT ' . implode( ', ', $fields ) . ' FROM ' . $db->pagesTable . ' WHERE type=\'home\'';
    
    return $db->getRow( $sql );
  }

  public function get404( $fields = array( '*' ) )
  {
    $db = DBModel::getInstance();
    $sql = 'SELECT ' . implode( ', ', $fields ) . ' FROM ' . $db->pagesTable . ' WHERE type=\'404\'';

    return $db->getRow( $sql, $slug );
  }

  public function mergeListPagesResults( &$local_data, $app_data )
  {
    foreach( $local_data as &$local_item )
    {
      $instapage_id = $local_item->instapage_id;
      $app_item = $this->getPageFromArray( $instapage_id, $app_data );

      if( !is_null( $app_item ) )
      {
        $local_item->screenshot = $app_item->screenshot;
        $local_item->title = $app_item->title;
        $local_item->subaccount = $app_item->subaccount;
      }
    }
  }

  public function display( $instapage_id, $forced_status = null )
  {
    $api = APIModel::getInstance();
    $query_sufix = '';
    $cookies = $_COOKIE;

    if( $_SERVER[ 'QUERY_STRING' ] )
    {
      $query_sufix = '?' . $_SERVER[ 'QUERY_STRING' ];
    }

    if( is_array( $cookies ) && count( $cookies ) )
    {
      $cookies_we_need = array( "instapage-variant-{$instapage_id}" );

      foreach( $cookies as $key => $value )
      {
        if( !in_array( $key, $cookies_we_need ) )
        {
          unset( $cookies[ $key ] );
        }
      }
    }

    $result = $api->enterpriseCall( 'server/view-by-id/' . $instapage_id . $query_sufix, null, $cookies );
    $instapage_variant = @InstapageHelper::getVar( $result[ 'headers' ][ 'instapage-variant' ], null );

    if( !empty( $instapage_variant ) )
    {
      setcookie
      (
        "instapage-variant-{$instapage_id}",
        $instapage_variant,
        strtotime( '+12 month' )
      );
    }

    if( $forced_status )
    {
      $status = $forced_status;
    }
    else
    {
      $status = InstapageHelper::getVar( $result[ 'response' ][ 'code' ], 200 );  
    }
    
    $html = InstapageHelper::getVar( $result[ 'body' ] );
    $html = $this->disableCloudFlareScriptReplace( $html );
    $html = $this->fixHtmlHead( $html );
    
    if( $html )
    {
      ob_start();
      InstapageHelper::httpResponseCode( $status );
      print $html;
      ob_end_flush();
      die();
    }
    else
    {
      return false;
    }
  }

  public function delete( $id )
  {

    $db = DBModel::getInstance();
    $sql = 'DELETE FROM ' . $db->pagesTable . ' WHERE id = %s';
    
    return $db->query( $sql, $id );
  }

  private function getPageFromArray( $id, $array )
  {
    if( is_array( $array ) )
    {
      foreach( $array as $item )
      {
        if( $item->id == $id )
        {
          return $item;
        }
      }
    }
    
    return null;
  }

  public function getRandomSlug( $prefix = true )
  {
    $random_prefix = 'random-url-';
    $random_sufix_set = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $random_sufix_length = 10;
    $random_string = '';

    for ( $i = 0; $i < $random_sufix_length; $i++ )
    {
      $random_string .= $random_sufix_set[ rand( 0, strlen( $random_sufix_set ) - 1 ) ];
    }

    return $prefix ? $random_prefix . $random_string : $random_string;
  }

  private function disableCloudFlareScriptReplace( $html )
  {
    $pattern = '/(<script )(type="text\/javascript")?(.*?)>/';

    return preg_replace( $pattern, "$1$2 data-cfasync=\"false\" $3>", $html );
  }

  public function fixHtmlHead( $html )
  {
    $useProxy = InstapageHelper::getOption( 'crossOrigin', false );

    if ( $useProxy )
    {
      $html = str_replace( 'PROXY_SERVICES', str_replace( array( 'http://', 'https://' ), array( '//', '//' ), Connector::getHomeURL() ) ."/instapage-proxy-services?url=", $html );
    }

    $searchArray = array(
      '<meta name="iy453p9485yheisruhs5" content="" />',
      '<meta name="robots" content="noindex, nofollow" />'
    );

    if( strpos( $html, $searchArray[ 0 ] ) !== false )
    {
      $html = str_replace( $searchArray, '', $html );
    }

    return $html;
  }
}
