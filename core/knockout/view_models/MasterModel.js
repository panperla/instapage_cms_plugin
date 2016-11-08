/* globals  ko, iAjax, INSTAPAGE_AJAXURL, SettingsModel, MessagesModel, ToolbarModel, PagedGridModel, optionsInitialData */

var MasterModel = function MasterModel() {
  var self = this;

  self.settingsModel = null;
  self.editModel = null;
  self.messagesModel = new MessagesModel();
  self.toolbarModel = new ToolbarModel();
  self.apiTokens = null;
  self.prohibitedSlugs = null;
  self.updateApiTokens = function updateApiTokens(onSuccessFunction, onFailureFunction) {
    var post = {action: 'getApiTokens'};
    iAjax.post(INSTAPAGE_AJAXURL, post, function updateApiTokensCallback(responseJson) {
      var response = JSON.parse(responseJson);

      if (response.status === 'OK') {
        self.apiTokens = response.data;

        if (typeof onSuccessFunction === 'function') {
          onSuccessFunction();
        }
      } else {
        self.apiTokens = [];

        if (typeof onSuccessFunction === 'function') {
          onFailureFunction();
        }
      }
    });
  };
  self.prepareInitialData = function prepareInitialData(obj) {
    obj.initialData.forEach(function prepareInitialSats(element) {
      element.stats_cache = ko.observableArray();
      element.totalStats = ko.observable({
        visits: 0,
        conversions: 0,
        conversionRate: 0
      });
      element.statsLoaded = ko.observable(false);
      element.toDelete = ko.observable(false);
    });

    return obj;
  };

  ko.applyBindings(self.messagesModel, document.getElementById('messages-container'));
  ko.applyBindings(self.toolbarModel, document.getElementById('instapage-toolbar'));
};

var loadPageList = function loadPageList() {
  var post = {action: 'loadListPages', apiTokens: masterModel.apiTokens};
  iAjax.post(INSTAPAGE_AJAXURL, post, function loadPageListCallback(responseJson) {
    var response = JSON.parse(responseJson);

    if (response.status === 'OK') {
      var element = document.getElementById('instapage-container');

      ko.cleanNode(element);
      element.innerHTML = response.html;

      if (Array.isArray(response.initialData)) {
        response = masterModel.prepareInitialData(response);
      }
      masterModel.pagedGridModel = new PagedGridModel(response.initialData);
      ko.applyBindings(masterModel.pagedGridModel, element);
    } else {
      masterModel.messagesModel.addMessage(response.message, response.status);
    }
  });
};

var masterModel = new MasterModel();
masterModel.updateApiTokens(loadPageList, loadPageList);


