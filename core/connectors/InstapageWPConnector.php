<?php

class InstapageWPConnector
{
  var $name = 'wordpress';

  public function getCMSName()
  {
    return 'WordPress';
  }

  public function currentUserCanManage()
  {
    return current_user_can( 'manage_options' );
  }

  public function query( $sql )
  {
    global $wpdb;

    $args = func_get_args();
    array_shift( $args );
    $sql = $this->prepare( $sql, $args );
    
    return $wpdb->query( $sql );
  }

  public function lastInsertId()
  {
    global $wpdb;

    return $wpdb->insert_id;
  }

  public function prepare( $sql, $args = array() )
  {
    global $wpdb;

    if( isset( $args[ 0 ] ) && is_array( $args[ 0 ] )  )
    {
      $args = $args[ 0 ];
    }

    if( count( $args ) )
    {
      return $wpdb->prepare( $sql, $args );  
    }
    else
    {
      return $sql;
    }
  }

  public function getRow( $sql )
  {
    global $wpdb;

    $args = func_get_args();
    array_shift( $args );
    $sql = $this->prepare( $sql, $args );
    
    return $wpdb->get_row( $sql, 'OBJECT' );
  }

  public function getResults( $sql )
  {
    global $wpdb;

    $args = func_get_args();
    array_shift( $args );
    $sql = $this->prepare( $sql, $args );
    
    return $wpdb->get_results( $sql, 'OBJECT' );
  }
  
  public function getDBPrefix()
  {
    global $wpdb;
    
    return $wpdb->prefix;
  }

  public function getCharsetCollate()
  {
    global $wpdb;
    
    return $wpdb->get_charset_collate();
  }

  public function remoteRequest( $url, $data, $headers = array(), $method = 'POST' )
  {
    $body = is_array( $data ) ? $data : (array) $data;
    
    if( $method == 'POST' && ( !is_array( $body ) || !count( $body ) ) )
    {
      $body = array( 'ping' => true );
      InstapageHelper::writeDiagnostics( $body, 'Request (' . $method . ') data empty. Ping added.' );
    }

    if( $method == 'GET' && is_array( $data ) )
    {
      $data_string = http_build_query( $body, '', '&' );
      $url .= '?' . urldecode( $data_string );
      $body = null;
      InstapageHelper::writeDiagnostics( $url, 'GET Request URL' );
    }
    
    $args = array(
      'method' => $method,
      'timeout' => 45,
      'redirection' => 5,
      'httpversion' => '1.0',
      'blocking' => true,
      'headers' => $headers,
      'body' => $body,
      'cookies' => array()
    );

    switch( $method )
    {
      case 'POST': 
        $response = wp_remote_post( $url, $args );
        break;

      case 'GET':
        $response = wp_remote_get( $url, $args );
        break;

      default: 
        $response = null;
    }

    if ( is_wp_error( $response ) )
    {
      return (object) array( 'status' => 'ERROR', 'message' => $response->get_error_message() );
    }
    else
    {
      return $response;
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

  public function getSiteURL( $protocol = true )
  {
    $url = get_site_url();
    
    if( !$protocol )
    {
      $url = str_replace( array( 'http://', 'https://' ), '', $url );
    }

    return $url;
  }

  public function getHomeURL( $protocol = true )
  {
    $url = get_home_url();

    if( !$protocol )
    {
      $url = str_replace( array( 'http://', 'https://' ), '', $url );
    }

    return $url;
  }

  public function getAjaxURL()
  {
    return admin_url( 'admin-ajax.php' ) . '?action=instapage_ajax_call';
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
      return __( $text );
    }

    return vsprintf( __( $text ), $variables );
  }

  public function initPlugin()
  {
    InstapageHelper::writeDiagnostics( $_SERVER[ 'REQUEST_URI' ], 'Instapage plugin initiated. REQUEST_URI' );

    if( $this->isInstapagePluginDashboard() )
    {
      add_action( 'admin_enqueue_scripts', array( $this, 'addAdminJS' ) );
      add_action( 'admin_enqueue_scripts', array( $this, 'addAdminCSS' ) );
    }

    add_action( 'admin_menu', array( $this, 'addInstapageMenu' ), 5 );
    add_action( 'wp_ajax_instapage_ajax_call', array( $this, 'ajaxCallback' ) );
    add_action( 'wp_ajax_nopriv_instapage_ajax_call', array( $this, 'ajaxCallback' ) );
    add_action( 'init', array( $this, 'checkProxy' ), 1 );
    add_action( 'wp', array( $this, 'checkHomepage' ), 1 );
    add_action( 'wp', array( $this, 'checkCustomUrl' ), 1 );
    add_action( 'template_redirect', array( $this, 'check404' ), 1 );
    register_uninstall_hook( INSTAPAGE_PLUGIN_FILE, array( 'InstapageWPConnector', 'removePlugin' ) );

    //TODO - remove this to verify SSL
    add_filter( 'https_ssl_verify', '__return_false' );
  }

  public function removePlugin()
  {
    $subaccount = SubaccountModel::getInstance();
    $db = DBModel::getInstance();
    $subaccount->disconnectAccountBoundSubaccounts( true );
    $db->removePluginTables();
  }
  
  public function addInstapageMenu()
  {
    $icon_svg = InstapageHelper::getMenuIcon();

    $admin_page = add_menu_page(
      __( 'Instapage: General settings' ), 
      __( 'Instapage' ), 
      'manage_options', 
      'instapage_dashboard', 
      array( $this, 'loadPluginDashboard' ),
      $icon_svg, 
      30
    );
  }

  public function addAdminJS()
  {
    $js_dir = $this->getSiteURL() . '/wp-content/plugins/' . INSTAPAGE_PLUGIN_DIR_NAME . '/assets/js';
    $knockout_dir = $this->getSiteURL() . '/wp-content/plugins/' . INSTAPAGE_PLUGIN_DIR_NAME . '/knockout';
    $language_file = $this->getSiteURL() . '/wp-content/plugins/' . INSTAPAGE_PLUGIN_DIR_NAME . '/assets/lang/' . Connector::getSelectedLanguage() . '.js';

    wp_register_script( 'instapage-dictionry', $language_file, null, false, true );
    wp_register_script( 'instapage-lang', $js_dir . '/ILang.js', null, false, true );
    wp_register_script( 'instapage-knokout', $knockout_dir . '/core/knockout-3.4.0.js', null, false, true );
    wp_register_script( 'instapage-knokout-simple-grid', $knockout_dir . '/core/knockout.simpleGrid.3.0.js', null, false, true );
    wp_register_script( 'instapage-download', $js_dir . '/download.js', null, false, true );
    wp_register_script( 'instapage-ajax', $js_dir . '/IAjax.js', null, false, true );
    wp_register_script( 'instapage-paged-grid-model', $knockout_dir . '/view_models/PagedGridModel.js', null, false, true );
    wp_register_script( 'instapage-edit-model', $knockout_dir . '/view_models/EditModel.js', null, false, true );
    wp_register_script( 'instapage-settings-model', $knockout_dir . '/view_models/SettingsModel.js', null, false, true );
    wp_register_script( 'instapage-messages-model', $knockout_dir . '/view_models/MessagesModel.js', null, false, true );
    wp_register_script( 'instapage-toolbar-model', $knockout_dir . '/view_models/ToolbarModel.js', null, false, true );
    wp_register_script( 'instapage-master-model', $knockout_dir . '/view_models/MasterModel.js', null, false, true );
    
    wp_enqueue_script( 'instapage-dictionry' );
    wp_enqueue_script( 'instapage-lang' );
    wp_enqueue_script( 'instapage-knokout' );
    wp_enqueue_script( 'instapage-knokout-simple-grid' );
    wp_enqueue_script( 'instapage-ajax' );
    wp_enqueue_script( 'instapage-download' );
    wp_enqueue_script( 'instapage-paged-grid-model' );
    wp_enqueue_script( 'instapage-edit-model' );
    wp_enqueue_script( 'instapage-settings-model' );
    wp_enqueue_script( 'instapage-messages-model' );
    wp_enqueue_script( 'instapage-toolbar-model' );
    wp_enqueue_script( 'instapage-master-model' );

    // UI KIT
    wp_register_script( 'instapage-mrwhite-jquery', 'https://code.jquery.com/jquery-2.2.4.min.js', null, false, true );
    wp_register_script( 'instapage-mrwhite', $js_dir . '/mrwhite.js', null, false, true );
    wp_register_script( 'instapage-dropdowns', $js_dir . '/dropdowns.js', null, false, true );
    wp_register_script( 'instapage-expand-collapse', $js_dir . '/expand-collapse.js', null, false, true );
    wp_register_script( 'instapage-input', $js_dir . '/input.js', null, false, true );
    wp_register_script( 'instapage-jq-hoverintent', $js_dir . '/jq.hoverintent.js', null, false, true );
    wp_register_script( 'instapage-jquery-tmpl-min', $js_dir . '/jquery.tmpl.min.js', null, false, true );
    wp_register_script( 'instapage-ripple', $js_dir . '/ripple.js', null, false, true );
    wp_register_script( 'instapage-select2-min', $js_dir . '/select2.min.js', null, false, true );
    wp_register_script( 'instapage-snack-bars', $js_dir . '/snack-bars.js', null, false, true );
    wp_register_script( 'instapage-tabs', $js_dir . '/tabs.js', null, false, true );

    wp_enqueue_script( 'instapage-mrwhite-jquery' );
    wp_enqueue_script( 'instapage-mrwhite' );
    wp_enqueue_script( 'instapage-dropdowns' );
    wp_enqueue_script( 'instapage-expand-collapse' );
    wp_enqueue_script( 'instapage-input' );
    wp_enqueue_script( 'instapage-jq-hoverintent' );
    wp_enqueue_script( 'instapage-jquery-tmpl-min' );
    wp_enqueue_script( 'instapage-ripple' );
    wp_enqueue_script( 'instapage-select2-min' );
    wp_enqueue_script( 'instapage-snack-bars' );
    wp_enqueue_script( 'instapage-tabs' );
  }

  public function addAdminCSS()
  {

    $css_dir = $this->getSiteURL() . '/wp-content/plugins/' . INSTAPAGE_PLUGIN_DIR_NAME . '/assets/css';
    wp_enqueue_style( 'instapage-mrwhite-reset', $css_dir . '/mrwhite-reset.css' );
    wp_enqueue_style( 'instapage-mrwhite-ui-kit', $css_dir . '/mrwhite-ui-kit.css' );
    wp_enqueue_style( 'instapage-general', $css_dir . '/general.css' );
  }

  public function loadPluginDashboard()
  {
    InstapageHelper::initAjaxURL();
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
    $pagenow = InstapageHelper::getVar( $GLOBALS[ 'pagenowfff' ], 'undefined' );

    return in_array( $pagenow, array( 'wp-login.php', 'wp-register.php' ) );
  }

  
  public function checkPage( $type, $slug = '' )
  {
    if( !$this->isHtmlReplaceNecessary() )
    {
      return;
    }

    $page = PageModel::getInstance();
    $result = $page->getByType( $type, $slug, array( 'instapage_id' ) );
    $instapage_id = 0;

    if( !$result )
    {
      if( $this->legacyArePagesPresent() )
      {
        $instapage_id = $this->legacyGetPage( $slug );
      }
    }
    else
    {
      $instapage_id = $result->instapage_id;
    }
    
    if( $instapage_id )
    {
      $page->display( $instapage_id );
    }
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
    $result = array_merge( $this->getPostSlugs(), $this->getTermSlugs(), Connector::getLandingPageSlugs() );
    return $result;
  }

  public function getOptionsDebugHTML()
  {
    $necessary_options = array(
      'siteurl',
      'home',
      'permalink_structure',
      'blog_charset',
      'template',
      'db_version',
      'initial_db_version'
    );

    foreach( $necessary_options as $opt )
    {
      $options[ $opt ] = get_option( $opt, 'n/a' );
    }

    $view = ViewModel::getInstance();
    $view->init( INSTAPAGE_PLUGIN_PATH .'/templates/log_options.php' );
    $view->rows = $options;

    return $view->fetch();
  }

  public function getPluginsDebugHTML()
  {
    $all_plugins = get_plugins();
    $view = ViewModel::getInstance();
    $view->init( INSTAPAGE_PLUGIN_PATH . '/templates/log_plugins.php' );
    $view->rows = $all_plugins;

    return $view->fetch();
  }

  public function getSitename( $sanitized = false )
  {
    $sitename = get_bloginfo( 'name' );

    return ( $sanitized ) ? sanitize_title( $sitename ) : $sitename;
  }

  public function mail( $to, $subject, $message, $headers = '', $attachments = array() )
  {
    return wp_mail( $to, $subject, $message, $headers, $attachments );
  }

  public function getDeprecatedData()
  {
    global $wpdb;

    //type == 'page'
    $sql = "SELECT {$wpdb->posts}.ID, {$wpdb->postmeta}.meta_key, {$wpdb->postmeta}.meta_value FROM {$wpdb->posts} INNER JOIN {$wpdb->postmeta} ON ( {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id) WHERE ({$wpdb->posts}.post_type = %s) AND ({$wpdb->posts}.post_status = 'publish') AND ({$wpdb->postmeta}.meta_key IN ('instapage_my_selected_page', 'instapage_name', 'instapage_my_selected_page', 'instapage_slug'))";

    $rows = $this->getResults( $sql, 'instapage_post' );
    $posts = array();

    foreach ( $rows as $k => $row )
    {
      if ( !array_key_exists( $row->ID, $posts ) )
      {
        $posts[ $row->ID ] = array();
      }

      $posts[ $row->ID ][ $row->meta_key ] = $row->meta_value;
    }

    $results = array();

    foreach( $posts as $post )
    {
      $page_obj = new stdClass;
      $page_obj->id = 0;
      $page_obj->landingPageId = $post[ 'instapage_my_selected_page' ];
      $page_obj->slug = $post[ 'instapage_slug' ];
      $page_obj->type = 'page';
      $results[] = $page_obj;
    }

    //type == 'home'
    $front_page_id = get_option( 'instapage_front_page_id', false );

    if( $front_page_id )
    {
      $page_obj = new stdClass;
      $page_obj->id = 0;
      $page_obj->landingPageId = $front_page_id;
      $page_obj->slug = '';
      $page_obj->type = 'home';
      $results[] = $page_obj;
    }

    //type == '404'
    $not_found_id = get_option( 'instapage_404_page_id', false );

    if( $not_found_id )
    {
      $page = PageModel::getInstance();
      $page_obj = new stdClass;
      $page_obj->id = 0;
      $page_obj->landingPageId = $not_found_id;
      $page_obj->slug = $page->getRandomSlug();
      $page_obj->type = '404';
      $results[] = $page_obj;
    }

    return $results;
  }

  public function escapeHTML( $html )
  {
    return esc_html( $html );
  }

  public function legacyArePagesPresent()
  {
    global $wpdb;

    $sql = "SELECT COUNT({$wpdb->posts}.ID) AS page_count FROM {$wpdb->posts} WHERE {$wpdb->posts}.post_type = %s AND {$wpdb->posts}.post_status = 'publish'";
    $row = $wpdb->get_row( $wpdb->prepare( $sql, 'instapage_post' ) );

    if( isset( $row->page_count ) && $row->page_count > 0 )
    {
      return true;
    }

    return false;
  }

  public function legacyGetPage( $slug )
  {
    global $wpdb;

    $sql = "SELECT {$wpdb->posts}.ID, {$wpdb->postmeta}.meta_key, {$wpdb->postmeta}.meta_value FROM {$wpdb->posts} INNER JOIN {$wpdb->postmeta} ON ( {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id) WHERE ({$wpdb->posts}.post_type = %s) AND ({$wpdb->posts}.post_status = 'publish') AND ({$wpdb->postmeta}.meta_key IN ('instapage_my_selected_page', 'instapage_name', 'instapage_my_selected_page', 'instapage_slug'))";

    $rows = $wpdb->get_results( $wpdb->prepare( $sql, 'instapage_post' ) );
    $posts = array();

    foreach ( $rows as $k => $row )
    {
      if ( !array_key_exists( $row->ID, $posts ) )
      {
        $posts[ $row->ID ] = array();
      }

      $posts[ $row->ID ][ $row->meta_key ] = $row->meta_value;
    }

    foreach ( $posts as $k => $post )
    {
      if( isset( $post[ 'instapage_slug' ] ) && $post[ 'instapage_slug' ] == $slug )
      {
        return isset( $post[ 'instapage_my_selected_page' ] ) ? $post[ 'instapage_my_selected_page' ] : 0;
      }
    }

    return 0;
  }

  private function getPostSlugs()
  {
    $edit_url = $this->getSiteURL() . '/wp-admin/post.php?action=edit&post=';
    $db_prefix = $this->getDBPrefix();
    $sql = 'SELECT ID AS id, post_name AS slug, CONCAT(\'' . $edit_url . '\', ID) AS editUrl FROM ' . $db_prefix . 'posts WHERE post_type = \'post\' AND post_name <> \'\'';
    $results = $this->getResults( $sql );

    return $results;
  }

  private function getTermSlugs()
  {
    $edit_url_1 = $this->getSiteURL() . '/wp-admin/edit-tags.php?action=edit&post_type=post&taxonomy=';
    $edit_url_2 = '&tag_ID=';
    $db_prefix = $this->getDBPrefix();
    $sql = 'SELECT t.term_id AS id, t.slug AS slug, CONCAT(\'' . $edit_url_1 . '\', tt.taxonomy, \'' . $edit_url_2 . '\', t.term_id) AS editUrl ' . 
    'FROM ' . $db_prefix . 'terms t LEFT JOIN ' . $db_prefix . 'term_taxonomy tt ON t.term_id = tt.term_id ' . 
    'WHERE ( tt.taxonomy = \'category\' OR tt.taxonomy = \'post_tag\' )' . 
    'AND t.slug <> \'\'';
    $results = $this->getResults( $sql );

    return $results;
  }

  private function isHtmlReplaceNecessary()
  {
    if( is_admin() || $this->isLoginPage() || InstapageHelper::isCustomParamPresent() )
    {
      return false;
    }

    return true;
  }

  private function isInstapagePluginDashboard()
  {
    if( isset( $_REQUEST[ 'page' ] ) && $_REQUEST[ 'page' ] == 'instapage_dashboard' )
    {
      return true;
    }

    return false;
  }
}
