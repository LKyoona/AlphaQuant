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
          <van-button
            size="small"
            type="primary"
            block
            :loading="sendingCode"
            :disabled="times !== 60 || sendingCode"
            @click.prevent="handleGetCode"
          >
            <template v-if="times === 60">{{ $t('settingsPage.send') }}</template>
            <template v-else>{{ times }}s</template>
          </van-button>
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
      sendingCode: false,
      mailNoticeVisible: false
    }
  },
  beforeUnmount () {
    clearInterval(this.timer)
  },
  methods: {
    ...mapActions({
      bindEmail: 'user/bindEmail',
      getUserInfo: 'user/getUserInfo',
      getCode: 'user/getCode'
    }),
    handleGetCode () {
      if (this.sendingCode || this.times !== 60) return
      this.username = this.username.trim().toLowerCase()
      if (isEmail(this.username)) {
        this.sendingCode = true
        this.getCode(this.username)
          .then(() => {
            this.mailNoticeVisible = true
            this.$toast(this.$t('pageSign.code_sent_notice'))
            this.getTime()
          })
          .catch(({ msg }) => {
            this.$toast(msg)
          })
          .finally(() => {
            this.sendingCode = false
          })
      } else {
        this.$toast('邮箱号格式不正确')
      }
    },
    getTime () {
      clearInterval(this.timer)
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
