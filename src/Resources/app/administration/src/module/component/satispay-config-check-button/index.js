import template from './satispay-config-check-button.html.twig';
import SatispayConfigApiService from '../../api/satispay-config.api.service';

const { Component, Mixin } = Shopware;


Component.register('satispay-config-check-button', {
    template,
    inject: ['SatispayConfigApiService'],
    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isLoading: false
        };
    },

    methods: {
        handleErrorOnApi(errorResponse) {
            try {
                const message = errorResponse.response.data.error !== undefined ? errorResponse.response.data.error : this.$tc('satispay-config.notification.error');
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: message,
                    autoClose: false
                });
            } finally {
                this.isLoading = false;
            }
        },
        handleApiResponse() {
            try {
                this.createNotificationSuccess({
                    title: this.$tc('global.default.success'),
                    message: this.$tc('satispay-config.notification.success')
                });
            } finally {
                this.isLoading = false;
                window.location.reload();
            }
        },
        onActivateClickedButton() {
            this.isLoading = true;
            this.SatispayConfigApiService.activate()
                .then(this.handleApiResponse)
                .catch(this.handleErrorOnApi);
        }
    }
});

