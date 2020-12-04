import template from './satispay-payment-action-refund.html.twig';

const { Component, Mixin } = Shopware;

Component.register('satispay-payment-action-refund', {
    template,
    inject: [
        'SatispayPaymentService'
    ],

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        paymentResource: {
            type: Object,
            required: true
        },
        order: {
            type: Object,
            required: true
        }

    },
    data() {
        return {
            refundableAmount: 0,
            refundAmount: 0,
            isLoading: false
        };
    },
    created() {
        if (this.paymentResource) {
            this.refundableAmount = this.paymentResource.amount_unit / 100;
        }
    },
    methods: {

        handleErrorOnApi(errorResponse) {
            try {
                const message = errorResponse.response.data.error !== undefined ?
                    errorResponse.response.data.error
                    : this.$tc('satispay-payments.paymentDetails.modal.notification.error');
                this.createNotificationError({
                    title: this.$tc('satispay-payments.paymentDetails.errorPage.title'),
                    message: message,
                    autoClose: false
                });
            } finally {
                this.isLoading = false;
                this.showPaymentDetails = false;
                this.$emit('modal-close');
            }
        },
        handleApiResponse() {
            try {
                this.createNotificationSuccess({
                    title: this.$tc('global.default.success'),
                    message: this.$tc('satispay-payments.paymentDetails.modal.notification.success')
                });
            } finally {
                this.isLoading = false;
                this.showPaymentDetails = true;
                this.$emit('modal-close');
            }
        },
        refund() {
            this.isLoading = true;
            const lastTransactionIndex = this.order.transactions.length - 1;
            const satispayTransactionId = this.order.transactions[lastTransactionIndex].customFields.satispay_payment_id;
            const refundAmount = this.refundAmount === 0 ? this.refundableAmount : this.refundAmount;
            // noinspection JSUnresolvedFunction
            this.SatispayPaymentService.refundPayment(this.order.id, satispayTransactionId, refundAmount)
                .then(this.handleApiResponse)
                .catch(this.handleErrorOnApi);
        }
    }
});
