<template>
  <div class="settings-page">
    <van-nav-bar
      :title="$t('settingsPage.title')"
      left-arrow
      @click-left="$router.back()"
    />
    <van-form
      label-width="9em"
      @submit="onSubmit"
    >
      <van-field
        v-model="old_password"
        type="password"
        :label="$t('settingsPage.old')"
        :placeholder="`${$t('settingsPage.please')}${$t('settingsPage.old')}`"
        :rules="[{ required: true, message: `${$t('settingsPage.please')}${$t('settingsPage.old')}` }]"
      />
      <van-field
        v-model="password"
        type="password"
        :label="$t('settingsPage.new')"
        :placeholder="`${$t('settingsPage.please')}${$t('settingsPage.new')}`"
        :rules="[{ required: true, message: `${$t('settingsPage.please')}${$t('settingsPage.new')}` }]"
      />
      <van-field
        v-model="confirm_password"
        type="password"
        :label="$t('settingsPage.confirm')"
        :placeholder="`${$t('settingsPage.please')}${$t('settingsPage.confirm')}`"
        :rules="[{ required: true, message: `${$t('settingsPage.please')}${$t('settingsPage.confirm')}` }]"
      />
      <div style="margin: 15px">
        <van-button
          block
          type="info"
          native-type="submit"
          class="submit"
        >
          {{ $t('actions.submit') }}
        </van-button>
      </div>
    </van-form>
  </div>
</template>

<script>
import { mapActions } from 'vuex'
export default {
  data () {
    return {
      old_password: '',
      password: '',
      confirm_password: ''
    }
  },
  methods: {
    ...mapActions({
      changePwd: 'user/changePwd'
    }),
    onSubmit () {
      this.changePwd({
        old_password: this.old_password,
        password: this.password,
        confirm_password: this.confirm_password
      }).then((res) => {
        this.$toast(res.msg)
        this.$router.back()
      })
    }
  }
}
</script>

<style scoped lang="less">
</style>
