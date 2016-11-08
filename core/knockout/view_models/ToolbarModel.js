/* globals  ko, iAjax, masterModel, PagedGridModel, SettingsModel, EditModel, INSTAPAGE_AJAXURL */
var ToolbarModel = function ToolbarModel() {
  var self = this;

  self.loadListPages = function loadListPages() {
    var element = document.getElementById('instapage-container');

    masterModel.messagesModel.clear();
    element.innerHTML = self.getLoader();

    iAjax.post(INSTAPAGE_AJAXURL, {action: 'loadListPages', apiTokens: masterModel.apiTokens}, function loadPageListCallback(responseJson) {
      var response = JSON.parse(responseJson);

      ko.cleanNode(element);
      element.innerHTML = response.html;

      if (Array.isArray(response.initialData)) {
        response = masterModel.prepareInitialData(response);
      }
      masterModel.pagedGridModel = new PagedGridModel(response.initialData);
      ko.applyBindings(masterModel.pagedGridModel, element);
      self.setActiveTab('listing-view');
    });
  };

  self.loadEditPage = function loadEditPage(item) {
    var post = null;
    var element = document.getElementById('instapage-container');

    masterModel.messagesModel.clear();
    element.innerHTML = self.getLoader();

    if (typeof item.id !== 'undefined') {
      post = {action: 'loadEditPage', apiTokens: masterModel.apiTokens, data: item};
    } else {
      post = {action: 'loadEditPage', apiTokens: masterModel.apiTokens};
    }

    iAjax.post(INSTAPAGE_AJAXURL, post, function loadEditPageCallback(responseJson) {
      var response = JSON.parse(responseJson);

      ko.cleanNode(element);
      element.innerHTML = response.html;
      masterModel.editModel = new EditModel(response.data);
      ko.applyBindings(masterModel.editModel, element);
      self.setActiveTab('edit-view');
    });
  };

  self.loadSettings = function loadSettings() {
    var element = document.getElementById('instapage-container');

    masterModel.messagesModel.clear();
    element.innerHTML = self.getLoader();

    iAjax.post(INSTAPAGE_AJAXURL, {action: 'loadSettings', apiTokens: masterModel.apiTokens}, function clearLogCallback(responseJson) {
      var response = JSON.parse(responseJson);

      ko.cleanNode(element);
      element.innerHTML = response.html;
      masterModel.settingsModel = new SettingsModel(response.initialData);
      ko.applyBindings(masterModel.settingsModel, element);
      self.setActiveTab('settings-view');
      masterModel.settingsModel.validateAllTokens();
    });
  };

  self.getLoader = function getLoader() {
    var html = '<div class="l-group__item page-loader">' +
      '<span class="c-loader c-loader--x-large"></span>' +
      '</div>';

    return html;
  };

  self.setActiveTab = function setActiveTab(tabClassName) {
    var tabs = document.getElementById('instapage-toolbar').getElementsByClassName('c-tab');
    var tabToChange = document.getElementById('instapage-toolbar').getElementsByClassName('c-tab ' + tabClassName);

    if (tabToChange.length) {
      tabToChange = tabToChange[0];
    } else {
      return;
    }

    for (var i = 0; i < tabs.length; ++i) {
      tabs[i].classList.remove('is-active');
    }

    tabToChange.classList.add('is-active');
    document.whitekitTabsInit();
  };
};
