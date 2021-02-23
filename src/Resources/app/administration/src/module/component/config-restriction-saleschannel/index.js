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
            const fieldsConfigurationShopwareOldVersion = document.querySelectorAll('.sw-block-field');
            let activatedCodeSandbox = document.querySelector(
                '.sw-system-config--field-satispay-config-sandbox-activated-code'
            );
            if(!activatedCodeSandbox && typeof fieldsConfigurationShopwareOldVersion[4] !== 'undefined') {
                activatedCodeSandbox = fieldsConfigurationShopwareOldVersion[4];
            }
            if(activatedCodeSandbox) {
                const activatedCodeSandboxInheritanceSwitchOff = activatedCodeSandbox.querySelector(
                    'div.sw-inheritance-switch'
                );
                if (activatedCodeSandboxInheritanceSwitchOff) {
                    activatedCodeSandboxInheritanceSwitchOff.hidden = true;
                }
            }

            let activatedCodeLive = document.querySelector(
                '.sw-system-config--field-satispay-config-live-activated-code');
            if(!activatedCodeLive && typeof fieldsConfigurationShopwareOldVersion[2] !== 'undefined') {
                activatedCodeLive = fieldsConfigurationShopwareOldVersion[2];
            }
            if(activatedCodeLive) {
                const activatedCodeLiveInheritanceSwitchOff = activatedCodeLive.querySelector(
                    'div.sw-inheritance-switch'
                );
                if (activatedCodeLiveInheritanceSwitchOff) {
                    activatedCodeLiveInheritanceSwitchOff.hidden = true;
                }
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
            let activationCodeSandbox = document.querySelector('.sw-system-config--field-satispay-config-sandbox-activation-code');
            if(!activationCodeSandbox && typeof fieldsConfigurationShopwareOldVersion[3] !== 'undefined') {
                activationCodeSandbox = fieldsConfigurationShopwareOldVersion[3];
            }
            let activationCodeLive = document.querySelector('.sw-system-config--field-satispay-config-live-activation-code');
            if(!activationCodeLive && typeof fieldsConfigurationShopwareOldVersion[1] !== 'undefined') {
                activationCodeLive = fieldsConfigurationShopwareOldVersion[1];
            }

            if(activationCodeLive && activatedCodeSandbox) {
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
