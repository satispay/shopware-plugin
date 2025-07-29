import template from './satispay-payment-detail.html.twig';
import '../../component/satispay-payment-action';

const { Component, Mixin, Context } = Shopware;
const Criteria = Shopware.Data.Criteria;

Component.register('satispay-payment-detail', {
    template,

    inject: [
        'SatispayPaymentService',
        'repositoryFactory'
    ],
    mixins: [
        Mixin.getByName('notification')
    ],


    data() {
        return {
            isLoading: true,
            showPaymentDetails: false,
            order: null,
            satispayTransactionId: null,
            paymentTransactionData: null,
            currency: null,
            status: null,
            flow: null,
            type: null
        };
    },
    computed: {
        showError() {
            return this.isLoading === false && this.showPaymentDetails === false;
        },
        amount() {
            if (!this.paymentTransactionData) {
                return 0;
            }
            return this.paymentTransactionData.amount_unit / 100;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        handleErrorOnApi(errorResponse) {
            try {
                this.createNotificationError({
                    title: this.$tc('satispay-payments.paymentDetails.errorPage.title'),
                    message: errorResponse.response.data.errors[0].detail,
                    autoClose: false
                });
            } finally {
                this.isLoading = false;
                this.showPaymentDetails = false;
            }
        },
        createdComponent() {
            const orderId = this.$route.params.id;
            const orderRepository = this.repositoryFactory.create('order');
            const orderCriteria = new Criteria(1, 1);
            orderCriteria.getAssociation('transactions').addSorting(Criteria.sort('createdAt'));
            orderRepository.get(orderId, Context.api, orderCriteria).then((order) => {
                this.order = order;

                const lastTransactionIndex = order.transactions.length - 1;

                if (order.transactions[lastTransactionIndex].customFields === null ||
                    typeof order.transactions[lastTransactionIndex].customFields.satispay_payment_id === 'undefined') {
                    this.isLoading = false;
                    this.showPaymentDetails = false;

                    return;
                }

                const satispayTransactionId = order.transactions[lastTransactionIndex].customFields.satispay_payment_id;
                this.showPaymentDetails = true;


                this.SatispayPaymentService.getPaymentDetails(this.order.id, satispayTransactionId).then((payment) => {
                    this.paymentTransactionData = payment;
                    this.status = this.paymentTransactionData.status;
                    this.currency = this.paymentTransactionData.currency;
                    this.flow = this.paymentTransactionData.flow;
                    this.type = this.paymentTransactionData.type;
                    this.isLoading = false;
                }).catch(this.handleErrorOnApi);
            }).catch(this.handleErrorOnApi);
        }
    }
});
