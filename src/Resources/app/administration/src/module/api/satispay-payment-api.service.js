const ApiService = Shopware.Classes.ApiService;

class SatispayPaymentService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'satispay') {
        super(httpClient, loginService, apiEndpoint);
    }

    getPaymentDetails(orderId, paymentId) {
        const apiRoute = `_action/${this.getApiBasePath()}/payment-details/${orderId}/${paymentId}`;
        return this.httpClient.get(
            apiRoute,
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    refundPayment(orderId, paymentId, refundAmount) {
        const apiRoute = `_action/${this.getApiBasePath()}/refund-payment/${orderId}/${paymentId}`;

        return this.httpClient.post(
            apiRoute,
            {
                refundAmount: refundAmount
            },
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }
}

export default SatispayPaymentService;
