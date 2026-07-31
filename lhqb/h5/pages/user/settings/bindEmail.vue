<template>
  <div class="settings-page">
    <van-nav-bar :title="$t('settingsPage.title')" left-arrow @click-left="$router.back()" />
    <van-form @submit="onSubmit">
      <van-field
        v-model="username"
        :label="$t('settingsPage.email')"
        :placeholder="$t('settingsPage.email_please')"
        :rules="[{ required: true, message: $t('settingsPage.email_please') }]"
      />
      <van-field
        v-model="verification_code"
        clearable
        :label="$t('settingsPage.code')"
        :placeholder="$t('settingsPage.code_please')"
        :rules="[{ required: true, message: $t('settingsPage.code_please') }]"
      >
        <template #button>
          <van-button size="small" type="primary" block @click.prevent="handleGetCode">{{ $t('settingsPage.send') }}</van-button>
        </template>
      </van-field>
      <MailDeliveryNotice v-if="mailNoticeVisible" />
      <div style="margin: 15px">
        <van-button block type="info" native-type="submit" class="submit">
          {{ $t('actions.submit') }}
        </van-button>
      </div>
    </van-form>
  </div>
</template>

<script>
import { mapActions } from 'vuex'
import { isEmail } from '@/utils/validator'
export default {
  data () {
    return {
      username: '',
      verification_code: '',
      times: 60,
      mailNoticeVisible: false
    }
  },
  methods: {
    ...mapActions({
      bindEmail: 'user/bindEmail',
      getUserInfo: 'user/getUserInfo',
      getCode: 'user/getCode'
    }),
    handleGetCode () {
      if (isEmail(this.username)) {
        this.getCode(this.username)
          .then(() => {
            this.mailNoticeVisible = true
            this.$toast(this.$t('pageSign.code_sent_notice'))
            this.getTime()
          })
          .catch(({ msg }) => {
            this.$toast(msg)
          })
      } else {
        this.$toast('邮箱号格式不正确')
      }
    },
    getTime () {
      this.timer = setInterval(() => {
        this.times--
        if (this.times === 0) {
          clearInterval(this.timer)
          this.times = 60
        }
      }, 1000)
    },
    onSubmit () {
      this.bindEmail().then(() => {
        this.getUserInfo()
        this.$router.back()
      })
    }
  }
}
</script>
