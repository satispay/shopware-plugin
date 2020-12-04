import template from './sw-order-detail.html.twig';

const { Component, Context } = Shopware;
const Criteria = Shopware.Data.Criteria;


Component.override('sw-order-detail', {
    template,

    inject: [
        'repositoryFactory'
    ],

    data() {
        return {
            isSatispayPayment: false
        };
    },

    created() {
        this.initializeSatispay();
    },

    methods: {
        initializeSatispay() {
            const orderId = this.orderId;
            // noinspection JSUnresolvedVariable
            const orderRepository = this.repositoryFactory.create('order');
            const orderCriteria = new Criteria(1, 1);
            orderCriteria.getAssociation('transactions').addSorting(Criteria.sort('createdAt'));
            orderRepository.get(orderId, Context.api, orderCriteria).then((order) => {
                const lastTransactionIndex = order.transactions.length - 1;

                if (order.transactions[lastTransactionIndex].customFields === null ||
                    typeof order.transactions[lastTransactionIndex].customFields.satispay_payment_id === 'undefined') {
                    this.isSatispayPayment = false;
                    return;
                }
                this.isSatispayPayment = true;
            });
        }
    }
});

