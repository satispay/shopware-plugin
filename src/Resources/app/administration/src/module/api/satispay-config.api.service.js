const ApiService = Shopware.Classes.ApiService;

// noinspection JSUnresolvedFunction
class SatispayConfigApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'satispay') {
        super(httpClient, loginService, apiEndpoint);
    }

    activate() {
        const apiRoute = `_action/${this.getApiBasePath()}/activate`;
        return this.httpClient
            .get(apiRoute,
                {
                    headers: this.getBasicHeaders()
                })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default SatispayConfigApiService;
