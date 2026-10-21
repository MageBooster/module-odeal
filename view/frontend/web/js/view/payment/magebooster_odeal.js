define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, rendererList) {
    'use strict';

    rendererList.push({
        type: 'magebooster_odeal',
        component: 'MageBooster_Odeal/js/view/payment/method-renderer/magebooster_odeal'
    });

    return Component.extend({});
});
