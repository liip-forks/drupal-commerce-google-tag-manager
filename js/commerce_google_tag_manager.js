/**
 * @file
 * Defines Javascript behaviors for the commerce_google_tag_manager module.
 */

(function (Drupal, drupalSettings) {

    Drupal.behaviors.datalayer_push = {
      attach: function (context, settings) {
        if (!drupalSettings) {
          return;
        }

        var cgtmSettings = drupalSettings.commerceGoogleTagManager || {};
        var url = cgtmSettings.eventsUrl;
        var dataLayerVariable = cgtmSettings.dataLayerVariable;

        if (!dataLayerVariable || !window.hasOwnProperty(dataLayerVariable)) {
          return;
        }

        var dataLayer = window[dataLayerVariable];

        fetch(url).then((data) => {
          if (data && data.length) {
            data.forEach(function(eventData) {
              dataLayer.push(eventData);
            });
          }
        });
      }
    };
})(Drupal, drupalSettings);
