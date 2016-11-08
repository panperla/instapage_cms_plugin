  <h2 class="ui-subtitle" data-bind="click:getConfig"><?php echo Connector::lang( 'Instapage Plugin Settings' ); ?></h2>
  <div class="instapage-content ui-sub-section">
    <div class="login-form c-section l-space-tertiary">
      <h3 class="ui-subtitle"><?php echo Connector::lang( 'Please log into your Instapage account' ); ?></h3>
      <form>
        <div data-bind="visible: !userToken()">
          <div class="l-grid">
            <div class="l-grid__cell l-grid__cell--1/3">
              <div class="c-form-text-item" data-bind="css: {'has-danger': !isLoginAndPasswordValid()}">
                <input class="c-form-text-item__field" data-bind="value: email" type="textbox" />
                <label class="c-form-text-item__label"><?php echo Connector::lang( 'Instapage User Email' ); ?></label>
                <div class="c-form-text-item__bar"></div>
              </div>
            </div>
            <div class="l-grid__cell l-grid__cell--1/3">
              <div class="c-form-text-item" data-bind="css: {'has-danger': !isLoginAndPasswordValid()}">
                <input class="c-form-text-item__field" data-bind="value: password" type="password" />
                <label class="c-form-text-item__label"><?php echo Connector::lang( 'Instapage Password' ); ?></label>
                <div class="c-form-text-item__bar"></div>
              </div>
            </div>
            <div class="l-grid__cell l-grid__cell--1/3">
              <button class="fx-ripple-effect c-button c-button--regular c-button--action" data-bind='click: loginUser'><?php echo Connector::lang( 'Login' ); ?></button>
            </div>
          </div>
        </div>

        <div data-bind="visible: userToken()">
          <div>
            <?php echo Connector::lang( 'You are logged in as' ); ?> 
            <span data-bind="text: email"></span>
          </div>
          <div class="l-space-ver-tertiary">
            <button class="fx-ripple-effect c-button c-button--regular c-button--action l-space-top-tertiary" data-bind='click: disconnectAndLogout'><?php echo Connector::lang( 'Logout' ); ?></button>
          </div>
        </div>
        <div data-bind="visible: !hideConnectedTokens() && connectedTokens().length">
          <h3><?php echo Connector::lang( 'Select which subaccounts you would like to connect with %s site.', Connector::getCMSName() ); ?></h3>
          <ul class="c-list" data-bind="template: {name: 'subaccounts-connection-template', foreach: connectedTokens}">
          </ul>
          <button class="fx-ripple-effect c-button c-button--regular c-button--action c-button--small" data-bind="click: connectSelectedSubAccounts">
            <span class="c-button__text"><?php echo Connector::lang( 'Connect to %s', Connector::getCMSName() ); ?></span>
          </button>
          <button class="fx-ripple-effect c-button c-button--regular c-button--small" data-bind="click: closeConnectionSection">
            <span class="c-button__text"><?php echo Connector::lang( 'Close' ); ?></span>
          </button>
        </div>
        
        <div class="tokens-container">
          <h4 class="ui-subtitle"><?php echo Connector::lang( 'Client tokens' ); ?></h4>
          <div class="l-grid">
            <div class="l-grid__cell l-grid__cell--2/3">
              <div class="c-form-text-item" data-bind="css: {'has-danger': !isTokenToAddValid()}">
                <input class="c-form-text-item__field" data-bind="value:tokenToAdd, valueUpdate: 'input'" type="textbox" />
                <label class="c-form-text-item__label"><?php echo Connector::lang( 'Add token' ); ?></label>
                <div class="c-form-text-item__bar"></div>
                <span class="c-form-text-item__info" data-bind="visible: !isTokenToAddValid()" >
                  <span><?php echo Connector::lang( 'This token is invalid.' ); ?></span>
                  <i class="material-icons c-form-text-item__info-icon"><?php echo Connector::lang( 'error' ); ?></i>
                </span>
              </div>
            </div>
            <div class="l-grid__cell l-grid__cell--1/3">
              <button class="fx-ripple-effect c-button c-button--regular c-button--action" data-bind="enable: tokenToAdd().length > 0, click: addToken" type="button" ><?php echo Connector::lang( 'Add token' ); ?></button>
            </div>
          </div>

          <table class="c-table">
            <caption class="c-table__caption">
              <div class="l-group l-group--block l-group--ver-center l-group--hor-space-between c-table__caption-row">
                <div class="l-group__item c-table__caption-cell">
                  <span class="c-table__caption-title"><?php echo Connector::lang( 'Tokens' ); ?></span>
                </div>
              </div>
            </caption>

            <thead class="c-table__head">
              <tr>
                <th class="c-table__cell c-table__cell--left"><?php echo Connector::lang( 'Token' ); ?></th>
                <th class="c-table__cell c-table__cell--right"><?php echo Connector::lang( 'Remove' ); ?></th>
              </tr>
            </thead>

            <tbody class="c-table__body" data-bind="foreach: config.tokens">
              <tr>
                <td class="c-table__cell c-table__cell--left">
                  <div class="c-form-text-item" data-bind="css: {'has-danger': valid() < 0, 'has-success': valid() > 0}">
                    <input class="c-form-text-item__field" data-bind="value: value, valueUpdate: 'input', css: {'is-not-empty': value}" type="textbox" size="50" readonly="readonly"/>
                    <label class="c-form-text-item__label"><?php echo Connector::lang( 'Add token' ); ?></label>
                    <div class="c-form-text-item__bar"></div>
                    <span class="c-form-text-item__info" data-bind="visible: valid() < 0" >
                      <span><?php echo Connector::lang( 'This token is invalid.' ); ?></span>
                      <i class="material-icons c-form-text-item__info-icon"><?php echo Connector::lang( 'error' ); ?></i>
                    </span>
                  </div>
                </td>
                <td class="c-table__cell c-table__cell--right">
                  <button class="fx-ripple-effect c-button c-button--regular c-button--action" data-bind="click: $root.removeToken"><?php echo Connector::lang( 'Remove token' ); ?></button>
                </td>
              </tr>
            </tbody>

          </table>
        </div>
      </form>
    </div>

    <form class="c-section l-space-tertiary">
      <div class="cross-origin-form ui-section ">
        <h3 class="ui-subtitle"><?php echo Connector::lang( 'Cross-origin proxy services' ); ?></h3>
        <label class="c-mark">
          <input class="c-mark__input" data-bind="checked: config.crossOrigin, click: autoSaveConfig" type="checkbox" >
          <i class="c-mark__icon c-mark__icon--checkbox material-icons"><?php echo Connector::lang( 'check' ); ?></i>
          <span class="c-mark__label"><?php echo Connector::lang( 'Uncheck this if you have problems with sending submissions from landing page' ); ?></span>
        </label>
      </div>

      <div class="diagnostic-form ui-section">
        <h3 class="ui-subtitle"><?php echo Connector::lang( 'Diagnostics' ); ?></h3>
        <p class="l-space-bottom-tertiary"><?php echo Connector::lang( 'Diagnostic tool helps our customer support and developers to track and eliminate some most problematic issues. Do not turn this option on if unnecessary, it can increase your page loading time.' ); ?></p>
        <label class="c-mark">
          <input class="c-mark__input" data-bind="checked: config.diagnostics, click: autoSaveConfig" type="checkbox" >
          <i class="c-mark__icon c-mark__icon--checkbox material-icons"><?php echo Connector::lang( 'check' ); ?></i>
          <span class="c-mark__label"><?php echo Connector::lang( 'Turn on diagnostics.' ); ?></span>
        </label>

        <div class="l-space-ver-tertiary" data-bind="visible: config.diagnostics">
          <p><?php echo Connector::lang( 'Current number of entries in Instapage log: ' ); ?></p>
        </div>
        <button class="fx-ripple-effect c-button c-button--regular c-button--small c-button--action" data-bind="click: downloadLog">
          <span class="c-button__text"><?php echo Connector::lang( 'Download diagnostic data' ); ?></span>
        </button>
        <button class="fx-ripple-effect c-button c-button--regular c-button--small c-button--action" data-bind="click: clearLog"><?php echo Connector::lang( 'Clear log' ); ?>
        </button>
      </div>
      <div class="custom-params-form ui-section">
        <h3 class="ui-subtitle"><?php echo Connector::lang( 'Custom parameters' ); ?></h3>
        <p class='l-space-bottom-primary'><?php echo Connector::lang( 'Instapage plugin won\'t display a landing page if one of given parameters is present in URL.' ); ?></p>
        <div class="c-form-text-item">
          <input class="c-form-text-item__field" data-bind="value: config.customParams, valueUpdate: 'input', css: {'is-not-empty': config.customParams}, event: {blur: autoSaveConfig}" type="textbox" size="90">
          <label class="c-form-text-item__label"><?php echo Connector::lang( 'Add parameters' ); ?></label>
          <div class="c-form-text-item__bar"></div>
        </div>
        <br>
        <span><?php echo Connector::lang( 'List of custom parameters, separated by "|", e.g. post_type=movie|page_id|keyword' ); ?></span>
      </div>
      <div class="custom-params-form ui-section">
        <h3 class="ui-subtitle"><?php echo Connector::lang( 'Landing page migration' ); ?></h3>
        <p class='l-space-bottom-primary'><?php echo Connector::lang( 'Click "Migrate" button to migrate your landing pages from old version of our plugin. Old plugin does not have to be activated.' ); ?></p>
        <button class="fx-ripple-effect c-button c-button--regular c-button--small c-button--action" data-bind="click: migrateDeprecatedData">
          <span class="c-button__text"><?php echo Connector::lang( 'Migrate' ); ?></span>
        </button>
      </div>
    </form>
  </div>

<script type="text/html" id="subaccounts-connection-template">
  <li class="c-list-item">
    <span class="c-list-item__content">
      <label class="c-mark">
        <input type="checkbox" class="c-mark__input" data-bind="checked: checked, click: $root.toggleAllSubaccounts">
        <i class="c-mark__icon c-mark__icon--checkbox material-icons"><?php echo Connector::lang( 'check' ); ?></i>
        <span class="c-mark__label" data-bind="text: name"></span>
      </label>
    </span>
  </li>
</script>