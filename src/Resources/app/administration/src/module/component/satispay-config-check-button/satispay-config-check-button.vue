<template>
  <mt-button class="sw-plugin-config__activate-action"
             @click="onActivateClickedButton" @disabled="isLoading"
  >{{ $tc('satispay-config.activateButton') }}
  </mt-button>
</template>

<script>
export default {
  inject: ['SatispayConfigApiService'],
  data() {
    return {
      isLoading: false
    }
  },
  mixins: [
    Shopware.Mixin.getByName('notification')
  ],
  methods: {
    handleErrorOnApi(errorResponse) {
      try {
        const message = errorResponse.response?.data?.error ?? this.$tc('satispay-config.notification.error');
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
}
</script>