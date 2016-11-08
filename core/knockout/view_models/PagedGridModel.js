/* globals  ko, iAjax, INSTAPAGE_AJAXURL, masterModel */

var PagedGridModel = function PagedGridModel(items) {
  var self = this;
  var GridViewModel = ko.simpleGrid.viewModel;
  self.originalItems = items ? ko.observableArray(items) : ko.observableArray();
  self.query = ko.observable('');
  self.items = ko.computed(function filterItems() {
    var search = self.query().toLowerCase();

    return ko.utils.arrayFilter(self.originalItems(), function checkItem(item) {
      return ((typeof item.slug !== 'undefined' && item.slug.toLowerCase().indexOf(search) >= 0) || (typeof item.title !== 'undefined' && item.title.toLowerCase().indexOf(search) >= 0));
    });
  }, self);

  self.gridViewModel = new GridViewModel({
    data: self.items,
    pageSize: 10
  });
  self.getStats = function getstats() {
    var pages = [];

    self.gridViewModel.itemsOnCurrentPage().forEach(function getPageId(element) {
      this.push(element.instapage_id);
    }, pages);

    var post = {action: 'getStats', apiTokens: masterModel.apiTokens, data: {pages: pages}};
    iAjax.post(INSTAPAGE_AJAXURL, post, function getStatsCallback(responseJson) {
      var response = JSON.parse(responseJson);

      if (response.status === 'OK' && response.data) {
        self.gridViewModel.itemsOnCurrentPage().forEach( function updateStats(element) {
          element.stats_cache(response.data[element.instapage_id]);
          element.totalStats(self.getTotalStats(element.stats_cache()));
          element.statsLoaded(true);
        });
      }
    });
  };

  self.gridViewModel.itemsOnCurrentPage.subscribe(self.getStats);

  self.askForDeleteConfirmation = function askForDeleteConfirmation(item) {
    item.toDelete(true);
  };

  self.cancelDelete = function cancelDelete(item) {
    item.toDelete(false);
  };

  self.deletePage = function deletePage(item) {
    var post = {action: 'deletePage', apiTokens: masterModel.apiTokens, data: {id: item.id}};

    iAjax.post(INSTAPAGE_AJAXURL, post, function loadEditPageCallback(responseJson) {
      var response = JSON.parse(responseJson);
      var removeIndex = null;

      masterModel.messagesModel.addMessage(response.message, response.status);
      self.originalItems.remove(item);

      if (masterModel.prohibitedSlugs !== null) {
        masterModel.prohibitedSlugs.forEach( function findItemToRemove(arrayItem, index) {
          if (item.slug === arrayItem.slug) {
            removeIndex = index;
          }
        });

        if (removeIndex !== null) {
          masterModel.prohibitedSlugs.splice(removeIndex, 1);
        }
      }
    });
  };

  self.getTotalStats = function getTotalStats(variations) {
    var stats = {visits: 0, conversions: 0};
    var precision = 100;

    if (typeof variations !== 'undefined') {
      variations.forEach( function getTotals(element) {
        this.visits += element.visits;
        this.conversions += element.conversions;
      }, stats);
    }

    stats.conversionRate = stats.visits ? stats.conversions / stats.visits * 100 : 0;
    stats.conversionRate = Math.round(stats.conversionRate * precision) / precision;

    return stats;
  };

  self.getStats();
};
