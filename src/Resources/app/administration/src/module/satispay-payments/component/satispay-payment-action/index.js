import template from './satispay-payment-actions.html.twig';
import './extension/satispay-payment-action-refund';

const { Component } = Shopware;

Component.register('satispay-payment-actions', {
    template,

    data() {
        return {
            showModal: false
        };
    },

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
    computed: {
        notRefundable() {
            const lastTransactionIndex = this.order.transactions.length - 1;
            const transaction = this.order.transactions[lastTransactionIndex];
            const technicalName = transaction.stateMachineState.technicalName;
            const refundableStates = ['paid', 'paid_partially', 'refunded'];
            const canRefund = refundableStates.indexOf(technicalName) > -1;
            return (this.paymentResource.amount_unit <= 0 || this.paymentResource.status != 'ACCEPTED' || !canRefund);
        }
    }
});
