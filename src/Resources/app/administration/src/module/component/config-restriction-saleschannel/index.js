const { Component, Defaults } = Shopware;
const { Criteria } = Shopware.Data;
Component.register('satispay-config-restriction-saleschannel', {
    template: ' ', // we need content to be created
    inject: ['repositoryFactory'],

    computed: {
        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        }
    },
    created() {
        this.checkAndHideSetting();
    },

    updated() {
        this.checkAndHideSetting();
    },

    methods: {
        checkAndHideSetting() {
            const currentSalesChannelId = this.pluginConfigData().currentSalesChannelId;
            const activatedCodeSandbox = document.querySelector(
                '.sw-system-config--field-satispay-config-sandbox-activated-code'
            );
            const activatedCodeSandboxInheritanceSwitchOff = activatedCodeSandbox.querySelector(
                'div.sw-inheritance-switch'
            );
            if (activatedCodeSandboxInheritanceSwitchOff) {
                activatedCodeSandboxInheritanceSwitchOff.hidden = true;
            }
            const activatedCodeLive = document.querySelector(
                '.sw-system-config--field-satispay-config-live-activated-code');
            const activatedCodeLiveInheritanceSwitchOff = activatedCodeLive.querySelector(
                'div.sw-inheritance-switch'
            );
            if (activatedCodeLiveInheritanceSwitchOff) {
                activatedCodeLiveInheritanceSwitchOff.hidden = true;
            }
            const fields = document.querySelectorAll(
                'input[name^="Satispay.config"],.sw-plugin-config__save-action,.sw-plugin-config__activate-action'
            );
            if (currentSalesChannelId) {
                const criteria = new Criteria();
                criteria.setPage(1);
                criteria.setLimit(500);
                criteria.addSorting(Criteria.sort('sales_channel.name', 'ASC'));
                criteria.addFilter(Criteria.equals('typeId', Defaults.storefrontSalesChannelTypeId));
                criteria.addFilter(Criteria.equals('id', currentSalesChannelId));

                this.salesChannelRepository.search(criteria, Shopware.Context.api, fields).then((response) => {
                    if (response && response.length === 0) {
                        fields.forEach(el => {
                            if (el.id !== 'Satispay.config.liveActivatedCode'
                                && el.id !== 'Satispay.config.sandboxActivatedCode') {
                                el.setAttribute('disabled', 'disabled');
                            }
                        });
                    } else {
                        fields.forEach(el => {
                            if (el.id !== 'Satispay.config.liveActivatedCode'
                                && el.id !== 'Satispay.config.sandboxActivatedCode') {
                                el.removeAttribute('disabled');
                            }
                        });
                    }
                });
            } else {
                fields.forEach(el => {
                    if (el.id !== 'Satispay.config.liveActivatedCode'
                        && el.id !== 'Satispay.config.sandboxActivatedCode') {
                        el.removeAttribute('disabled');
                    }
                });
            }

            const isSandbox = document.querySelectorAll('input[name="Satispay.config.sandbox"]')[0].checked;
            const activationCodeLive = document.querySelector('.sw-system-config--field-satispay-config-live-activation-code');
            const activationCodeSandbox = document.querySelector('.sw-system-config--field-satispay-config-sandbox-activation-code');
            if (isSandbox) {
                activatedCodeLive.hidden = true;
                activationCodeLive.hidden = true;
                activationCodeSandbox.hidden = false;
                activatedCodeSandbox.hidden = false;
            } else {
                activatedCodeSandbox.hidden = true;
                activationCodeSandbox.hidden = true;
                activationCodeLive.hidden = false;
                activatedCodeLive.hidden = false;
            }
        },

        pluginConfigData() {
            const config = this.$parent.$parent.$parent.actualConfigData;
            if (config) {
                return this.$parent.$parent.$parent;
            }

            // in SW6.3.4 it's one step above
            return this.$parent.$parent.$parent.$parent;
        }
    }

});
