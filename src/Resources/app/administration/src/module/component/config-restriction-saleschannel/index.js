const { Component, Mixin, Defaults } = Shopware;
const { Criteria } = Shopware.Data;
Component.register('satispay-config-restriction-saleschannel', {
    template: ' ', // we need content to be created
    inject: ['repositoryFactory'],

    computed: {
        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },
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
            const fields = document.querySelectorAll('input[name^="Satispay.config"],.sw-plugin-config__save-action,.sw-plugin-config__activate-action');

            if(currentSalesChannelId) {
                const criteria = new Criteria();
                criteria.setPage(1);
                criteria.setLimit(500);
                criteria.addSorting(Criteria.sort('sales_channel.name', 'ASC'));
                criteria.addFilter(Criteria.equals('typeId', Defaults.storefrontSalesChannelTypeId));
                criteria.addFilter(Criteria.equals('id', currentSalesChannelId));

                this.salesChannelRepository.search(criteria, Shopware.Context.api, fields).then((response) => {
                    if(response && response.length == 0)
                    {
                        fields.forEach(el => {
                            el.setAttribute('disabled', 'disabled');
                        });
                    } else {
                        fields.forEach(el => {
                            el.removeAttribute('disabled');
                        });
                    }
                });
            } else {
                fields.forEach(el => {
                    el.removeAttribute('disabled');
                });
            }
        },

        pluginConfigData() {
            let config = this.$parent.$parent.$parent.actualConfigData;
            if (config) {
                return this.$parent.$parent.$parent;
            }

            // in SW6.3.4 it's one step above
            return this.$parent.$parent.$parent.$parent;
        }
    }

})
