import template from './satispay-payment-actions.html.twig';
import './extension/satispay-payment-action-refund';

const { Component } = Shopware;

Component.register('satispay-payment-actions', {
    template,

    inject: [
        'repositoryFactory',
    ],

    data() {
        return {
            showModal: false
        };
    },

    props: {
        paymentResource: {
            type: Object,
            required: true,
        },
    },

    computed: {
        order() {
            return Shopware.Store.get('swOrderDetail').order;
        },

        orderTransaction() {
            const lastTransactionIndex = this.order.transactions.length - 1;
            return this.order.transactions[lastTransactionIndex];
        },

        isStateRefundable() {
            const refundableStates = ['paid', 'paid_partially', 'refunded'];
            return refundableStates.indexOf(this.orderTransaction.stateMachineState.technicalName) > -1;
        },

        notRefundable() {
            const canRefund = this.isStateRefundable;
            return (this.paymentResource.amount_unit <= 0 || this.paymentResource.status != 'ACCEPTED' || !canRefund);
        }
    },

    watch: {
        order: {
            immediate: true,
        },
    },
});
