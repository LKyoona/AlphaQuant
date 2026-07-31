<template>
  <div class="auth-page">
    <div class="auth-backdrop" :style="{ backgroundImage: `url(${sceneImage})` }"></div>
    <div class="auth-overlay"></div>
    <div class="auth-shell">
      <div class="auth-hero">
        <div class="hero-mark">AI Crypto Star</div>
        <h1>{{ $t('sign_up') }}</h1>
        <p>{{ $t('pageSign.register_intro') }}</p>
      </div>

      <div class="auth-card gold-card">
        <van-form class="register-form" label-width="5.6em" @submit="onSubmit">
          <van-field
            v-model="username"
            :label="$t('pageSign.email')"
            :placeholder="$t('pageSign.email')"
            :rules="[{ validator: isEmail, message: $t('pageSign.account_err') }]"
          />
          <van-field
            v-model="verification_code"
            clearable
            :label="$t('pageSign.valid_code')"
            :placeholder="$t('pageSign.valid_code')"
            :rules="[{ required: true, message: $t('pageSign.valid_code') }]"
          >
            <template #button>
              <van-button
                size="small"
                class="mini-btn"
                :class="{ 'is-cooling-down': times !== 60 }"
                :style="cooldownStyle"
                :loading="sendingCode"
                :disabled="times !== 60 || sendingCode"
                @click.prevent="handleGetCode"
              >
                <template v-if="times === 60">{{ $t('pageSign.send_code') }}</template>
                <template v-else>{{ times }}s</template>
              </van-button>
            </template>
          </van-field>
          <MailDeliveryNotice v-if="mailNoticeVisible" />
          <van-field
            v-model="password"
            type="password"
            :label="$t('pageSign.pwd')"
            :placeholder="$t('pageSign.pwd')"
            :rules="[{ required: true, message: $t('pageSign.pwd') }]"
          />
          <van-field
            v-model="confirm_password"
            type="password"
            :label="$t('pageSign.confirm_pwd')"
            :placeholder="$t('pageSign.pwd')"
            :rules="[{ required: true, message: $t('pageSign.confirm_pwd') }]"
          />
          <van-field
            v-model="invitation_code"
            :label="$t('pageSign.invitation_code')"
            :placeholder="$t('pageSign.invitation_code')"
            :rules="[{ required: true, message: $t('pageSign.invitation_code_required') }]"
          />
          <van-checkbox v-model="checked" icon-size="16" class="checkbox-link">
            {{ $t('pageSign.agreed') }}<a @click.stop="goAgreement">{{ $t('pageSign.agreement') }}</a>
          </van-checkbox>
          <van-button
            block
            native-type="submit"
            class="submit-btn gold-btn h-12 w-full"
            :loading="submitting"
            :disabled="!checked || submitting"
          >
            {{ $t('sign_up') }}
          </van-button>
          <div class="links">
            <nuxt-link to="/sign/login">{{ $t('pageSign.has_account') }}</nuxt-link>
          </div>
        </van-form>
      </div>
    </div>
  </div>
</template>

<script>
definePageMeta({
  layout: 'sign'
})

import { mapState, mapActions } from 'vuex'
import storage from '@/utils/storage'
import { isEmail } from '@/utils/validator'
import logo from '@/assets/images/login_logo.png'
import sceneImage from '@/assets/images/auth-crypto-scene.webp'
export default {
  data () {
    return {
      logo,
      sceneImage,
      username: '',
      password: '',
      confirm_password: '',
      verification_code: '',
      invitation_code: '',
      checked: false,
      times: 60,
      sendingCode: false,
      mailNoticeVisible: false,
      submitting: false
    }
  },
  computed: {
    ...mapState({
      initInfo: index => index.initInfo
    }),
    cooldownStyle () {
      const progress = this.times === 60 ? 0 : ((60 - this.times) / 60) * 100
      return { '--cooldown-progress': `${progress}%` }
    }
  },
  mounted () {
    const invitationCode = this.$route.query.invitation_code || this.$route.query.invite_code || ''
    if (invitationCode) {
      this.invitation_code = String(invitationCode).trim().toUpperCase()
    }
    let regInfo = storage.get('regInfo')
    if (regInfo) {
      regInfo = JSON.parse(regInfo)
      this.username = regInfo.username
      this.password = regInfo.password
      this.confirm_password = regInfo.confirm_password
      this.verification_code = regInfo.verification_code
      this.invitation_code = invitationCode || regInfo.invitation_code
      this.$nextTick(() => { storage.remove('regInfo') })
    }
    this.restoreCodeCooldown()
  },
  beforeUnmount () {
    clearInterval(this.timer)
  },
  methods: {
    ...mapActions({
      getCode: 'user/getCode',
      register: 'user/register'
    }),
    isEmail,
    onSubmit () {
      if (this.submitting) return false

      this.username = this.username.trim().toLowerCase()
      this.verification_code = this.verification_code.trim()
      if (this.password !== this.confirm_password) {
        this.$toast(this.$t('pageSign.pwd_err'))
        return false
      }
      if (!this.invitation_code) {
        this.$toast(this.$t('pageSign.invitation_code_required'))
        return false
      }
      const payload = {
        username: this.username,
        password: this.password,
        verification_code: this.verification_code,
        invitation_code: this.invitation_code
      }
      this.submitting = true
      this.$toast.loading()
      this.register(payload).then((res) => {
        this.$toast.clear()
        this.$toast(res.msg)
        this.$router.replace('/sign/login')
      }).catch((res) => {
        this.$toast.clear()
        this.$toast(res?.msg || res?.message || this.$t('pageSign.account_err'))
      }).finally(() => {
        this.submitting = false
      })
    },
    handleGetCode () {
      if (this.sendingCode || this.times !== 60) return

      this.username = this.username.trim().toLowerCase()
      if (!this.isEmail(this.username)) {
        this.$toast(this.$t('pageSign.account_err'))
        return
      }

      this.sendingCode = true
      this.$toast.loading()
      this.getCode(this.username).then(() => {
        this.$toast.clear()
        // A newly issued code invalidates every previous email code.
        this.verification_code = ''
        this.mailNoticeVisible = true
        this.$toast(this.$t('pageSign.code_sent_notice'))
        this.startCodeCooldown(Date.now() + 60000)
      }).catch((res) => {
        this.$toast.clear()
        this.$toast(res?.msg || res?.message || this.$t('pageSign.account_err'))
        const retryAfter = Number(res?.data?.retry_after || this.getRetryAfter(res?.msg || res?.message))
        if (retryAfter > 0) {
          this.startCodeCooldown(Date.now() + retryAfter * 1000)
        }
      }).finally(() => {
        this.sendingCode = false
      })
    },
    getRetryAfter (message = '') {
      const match = String(message).match(/(\d+)\s*秒/)
      return match ? Number(match[1]) : 0
    },
    restoreCodeCooldown () {
      let cooldown = storage.get('verificationCodeCooldown')
      if (!cooldown) return
      try {
        cooldown = JSON.parse(cooldown)
      } catch (error) {
        storage.remove('verificationCodeCooldown')
        return
      }
      if (Number(cooldown.expiresAt) <= Date.now()) {
        storage.remove('verificationCodeCooldown')
        return
      }
      if (!this.username && cooldown.account) {
        this.username = cooldown.account
      }
      this.startCodeCooldown(Number(cooldown.expiresAt), cooldown.account)
    },
    startCodeCooldown (expiresAt, account = this.username) {
      clearInterval(this.timer)
      storage.set('verificationCodeCooldown', JSON.stringify({
        account: String(account || '').trim().toLowerCase(),
        expiresAt
      }))
      const updateCountdown = () => {
        const remaining = Math.max(0, Math.ceil((expiresAt - Date.now()) / 1000))
        if (remaining <= 0) {
          clearInterval(this.timer)
          storage.remove('verificationCodeCooldown')
          this.times = 60
          return
        }
        this.times = remaining
      }
      updateCountdown()
      this.timer = setInterval(updateCountdown, 250)
    },
    goAgreement () {
      const regInfo = {
        username: this.username,
        password: this.password,
        confirm_password: this.confirm_password,
        verification_code: this.verification_code,
        invitation_code: this.invitation_code
      }
      storage.set('regInfo', JSON.stringify(regInfo))
      const target = this.initInfo.system_user_agreement
      if (target) {
        if (/^https?:\/\//i.test(target)) {
          window.location.href = target
          return
        }
        this.$router.push(target)
      }
    }
  }
}
</script>

<style scoped lang="less">
.auth-page {
  position: relative;
  min-height: 100%;
  padding: 22px 16px 28px;
  overflow: hidden;
  background: #0f1218;
}

.auth-backdrop,
.auth-overlay {
  position: absolute;
  inset: 0;
}

.auth-backdrop {
  background-position: center;
  background-size: cover;
  transform: scale(1.04);
}

.auth-overlay {
  background:
    linear-gradient(180deg, rgba(10, 12, 17, 0.22) 0%, rgba(10, 12, 17, 0.34) 22%, rgba(10, 12, 17, 0.64) 100%),
    linear-gradient(180deg, rgba(8, 10, 14, 0.08) 0%, rgba(8, 10, 14, 0.18) 100%);
}

.auth-shell {
  position: relative;
  z-index: 1;
  width: min(460px, 100%);
  margin: 0 auto;
  min-height: calc(100dvh - 50px);
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
}

.auth-hero {
  padding: 0 4px 18px;
  color: #fff6de;

  h1 {
    margin: 14px 0 8px;
    font-size: 28px;
    line-height: 1.2;
  }

  p {
    max-width: 340px;
    color: rgba(255, 245, 222, 0.82);
    font-size: 13px;
    line-height: 1.6;
  }
}

.hero-mark {
  display: inline-flex;
  align-items: center;
  padding: 5px 10px;
  margin-bottom: 12px;
  border: 1px solid rgba(255, 255, 255, 0.18);
  border-radius: 999px;
  background: rgba(20, 24, 33, 0.38);
  backdrop-filter: blur(8px);
  color: #f8df9d;
  font-size: 11px;
  font-weight: 700;
}

.auth-card {
  padding: 20px 16px 18px;
  border: 1px solid rgba(217, 176, 92, 0.2);
  border-radius: 18px;
  background: linear-gradient(180deg, rgba(28, 20, 10, 0.94) 0%, rgba(18, 12, 6, 0.96) 100%);
  box-shadow: 0 24px 50px rgba(8, 10, 14, 0.38);
  backdrop-filter: blur(18px);
}

.register-form {
  :deep(.van-field) {
    min-width: 0;
  }

  :deep(.van-field__label) {
    flex-shrink: 0;
    white-space: normal;
    line-height: 1.25;
  }

  :deep(.van-field__body) {
    min-width: 0;
  }
}

.checkbox-link {
  margin: 4px 0 14px;
  font-size: 12px;

  a {
    color: #f0c46e;
  }
}

.links {
  text-align: center;

  a {
    color: #f0c46e;
    font-size: 13px;
  }
}

.submit-btn {
  position: relative;
  height: 46px;
  margin: 6px 0 18px;
  padding: 0 18px;
  border: 1px solid rgba(217, 176, 92, 0.18);
  border-radius: 12px;
  background:
    linear-gradient(90deg, rgba(246, 204, 113, 0.18), rgba(255, 255, 255, 0) 26%, rgba(246, 204, 113, 0.12) 100%),
    linear-gradient(180deg, #8d5c1f 0%, #f1cd86 100%);
  box-shadow: 0 10px 20px rgba(0, 0, 0, 0.18), inset 0 1px 0 rgba(255, 236, 193, 0.4);
  color: #1a1208;
  letter-spacing: .02em;
  font-size: 15px;
  font-weight: 700;
  overflow: hidden;
}

.mini-btn {
  position: relative;
  height: 32px;
  padding: 0 12px;
  border: 1px solid rgba(217, 176, 92, 0.18);
  border-radius: 10px;
  background:
    linear-gradient(90deg, rgba(246, 204, 113, 0.14), rgba(255, 255, 255, 0) 30%, rgba(246, 204, 113, 0.1) 100%),
    linear-gradient(180deg, rgba(37, 25, 12, 0.98) 0%, rgba(22, 14, 7, 0.98) 100%);
  box-shadow: 0 6px 12px rgba(0, 0, 0, 0.14);
  color: #fff1cf;
  overflow: hidden;
}

.mini-btn.is-cooling-down {
  background:
    linear-gradient(90deg, rgba(240, 196, 110, 0.38) 0 var(--cooldown-progress), rgba(255, 255, 255, 0.04) var(--cooldown-progress) 100%),
    linear-gradient(180deg, rgba(37, 25, 12, 0.98) 0%, rgba(22, 14, 7, 0.98) 100%);
  color: #f7d889;
  transition: background 0.25s linear;
}

.submit-btn::before,
.mini-btn::before {
  content: '';
  position: absolute;
  inset: 1px;
  border: 1px solid rgba(255, 236, 193, 0.24);
  pointer-events: none;
}

.submit-btn::after,
.mini-btn::after {
  content: '';
  position: absolute;
  top: -1px;
  bottom: -1px;
  width: 18px;
  right: -1px;
  border-left: 1px solid rgba(217, 176, 92, 0.22);
  background:
    linear-gradient(180deg, rgba(255, 236, 193, 0.18), rgba(255, 236, 193, 0.08));
  clip-path: polygon(35% 0, 100% 0, 100% 100%, 0 100%);
  pointer-events: none;
}

:deep(.van-field) {
  margin-bottom: 12px;
  border: 1px solid rgba(245, 210, 128, 0.34);
  border-radius: 12px;
  background: rgba(255, 248, 234, 0.07);
}

:deep(.van-field__control) {
  color: #fff8e3;
  font-weight: 600;
}

:deep(.van-field__control::placeholder) {
  color: rgba(255, 236, 193, 0.78);
}

:deep(.van-field__control::-webkit-input-placeholder) {
  color: rgba(255, 236, 193, 0.78);
}

:deep(.van-field__label) {
  color: #ffde8c;
  font-weight: 700;
}

:deep(.van-field__error-message) {
  color: #ffcf6e;
}

:deep(.van-field__right-icon),
:deep(.van-field__button) {
  color: #fff0c2;
}

:deep(.van-button::before) {
  display: none;
}

@media (min-width: 768px) {
  .auth-page {
    padding: 42px 24px 40px;
  }

  .auth-shell {
    width: min(560px, 100%);
    justify-content: center;
    min-height: calc(100vh - 82px);
  }

  .auth-hero {
    padding-bottom: 24px;

    h1 {
      font-size: 34px;
    }

    p {
      max-width: 440px;
      font-size: 14px;
    }
  }

  .auth-card {
    padding: 28px 26px 24px;
    border-radius: 22px;
  }

  .auth-tab {
    margin-bottom: 22px;
  }

  :deep(.van-field) {
    margin-bottom: 14px;
  }

  .submit-btn {
    height: 50px;
    margin-bottom: 20px;
    font-size: 16px;
  }
}

@media (max-width: 767px) {
  .auth-shell {
    min-height: calc(100dvh - 50px);
    justify-content: flex-end;
  }

  .auth-hero {
    padding-bottom: 14px;

    h1 {
      font-size: 26px;
      word-break: break-word;
    }

    p {
      max-width: none;
    }
  }

  .auth-card {
    padding: 18px 14px 16px;
  }

  .register-form {
    :deep(.van-field) {
      flex-direction: column;
      align-items: stretch;
      padding-top: 10px;
      padding-bottom: 10px;
    }

    :deep(.van-field__label) {
      width: auto !important;
      margin-bottom: 6px;
      font-size: 12px;
      line-height: 1.2;
    }

    :deep(.van-field__value) {
      width: 100%;
    }

    :deep(.van-field__control) {
      width: 100%;
    }

    :deep(.van-field__button) {
      align-self: flex-end;
    }
  }

  .checkbox-link {
    align-items: flex-start;
    line-height: 1.45;
  }
}
</style>
