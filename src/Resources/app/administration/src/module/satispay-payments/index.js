
import './config';
import './page/satispay-payment-detail';
import './component/satispay-payment-action';


import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('satispay-payment', {
    type: 'plugin',
    name: 'SatispayPayments',
    title: 'satispay-payments.general.mainMenuItemGeneral',
    description: 'satispay-payments.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#333',
    icon: 'default-action-settings',

    snippets: {
        'en-GB': enGB
    },

    routeMiddleware(next, currentRoute) {
        if (currentRoute.name === 'sw.order.detail') {
            currentRoute.children.push({
                component: 'satispay-payment-detail',
                name: 'satispay.payment.detail',
                isChildren: true,
                path: '/sw/order/satispay/detail/:id',
                meta: {
                    parentPath: 'sw.order.index'
                }
            });
        }
        next(currentRoute);
    }
});
