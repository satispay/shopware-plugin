import SatispayConfigApiService
    from '../api/satispay-config.api.service';

import SatispayPaymentService from '../api/satispay-payment-api.service';
// noinspection JSUnresolvedFunction
Shopware.Application.addServiceProvider('SatispayConfigApiService', container => {
    const initContainer = Shopware.Application.getContainer('init');
    return new SatispayConfigApiService(initContainer.httpClient, container.loginService);
});
// noinspection JSUnresolvedFunction
Shopware.Application.addServiceProvider('SatispayPaymentService', container => {
    const initContainer = Shopware.Application.getContainer('init');
    return new SatispayPaymentService(initContainer.httpClient, container.loginService);
});
