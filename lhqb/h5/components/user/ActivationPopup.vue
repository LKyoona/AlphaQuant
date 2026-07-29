<template>
  <van-dialog
    v-model="showCodePop"
    :title="$t('pageActivationPopup.title')"
    :confirm-button-text="$t('get')"
    show-cancel-button
    @confirm="onActive"
  >
    <van-cell-group>
      <van-field
        v-model="activationCode"
        :label="$t('key')"
        label-width="4em"
        :placeholder="$t('please')"
      />
    </van-cell-group>
  </van-dialog>
</template>

<script>
import { mapActions } from 'vuex'
export default {
  data () {
    return {
      showCodePop: false,
      activationCode: ''
    }
  },
  methods: {
    ...mapActions({
      cdkeyActive: 'user/cdkeyActive'
    }),
    onActive () {
      this.$dialog
        .confirm({
          title: this.$t('pageActivationPopup.confirm_title'),
          message: `${this.$t('pageActivationPopup.confirm_msg')}\n${this.activationCode}`
        })
        .then(() => {
          this.$toast.loading()
          this.cdkeyActive({ keys: this.activationCode }).then(({ msg }) => {
            this.$toast(msg || this.$t('pageActivationPopup.success'))
          })
            .catch(({ msg }) => {
              this.$toast(msg)
            })
        })
    }
  }
}
</script>

<style>

</style>
