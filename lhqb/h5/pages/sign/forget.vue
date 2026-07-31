<template>
  <div class="auth-page">
    <div class="auth-backdrop" :style="{ backgroundImage: `url(${sceneImage})` }"></div>
    <div class="auth-overlay"></div>
    <div class="auth-shell">
      <div class="auth-hero">
        <div class="hero-mark">{{ $t('pageSign.account_security') }}</div>
        <h1>{{ $t('pageSign.forget_pwd') }}</h1>
        <p>{{ $t('pageSign.forget_intro') }}</p>
      </div>

      <div class="auth-card gold-card">
        <van-form class="sign-form" label-width="5.8em" @submit="onSubmit">
          <van-field
            v-model="username"
            :label="$t('pageSign.email')"
            :placeholder="$t('pageSign.email_placeholder')"
            :rules="[{ validator: isEmail }]"
          />
          <van-field
            v-model="verification_code"
            clearable
            :label="$t('pageSign.valid_code')"
            :placeholder="$t('pageSign.valid_code')"
            :rules="[{ required: true }]"
          >
            <template #button>
              <van-button
                size="small"
                class="mini-btn gold-btn h-8 px-3 text-xs"
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
            :placeholder="$t('pageSign.password_placeholder')"
            :rules="[{ required: true }]"
          />
          <van-field
            v-model="confirm_password"
            type="password"
            :label="$t('pageSign.confirm_pwd')"
            :placeholder="$t('pageSign.password_placeholder')"
            :rules="[{ required: true }]"
          />
          <van-button block native-type="submit" class="submit-btn gold-btn h-12 w-full">{{ $t('actions.submit') }}</van-button>
        </van-form>
      </div>
    </div>
  </div>
</template>

<script>
definePageMeta({
  layout: 'sign'
})

import { mapActions } from 'vuex'
import { isEmail } from '@/utils/validator'
import sceneImage from '@/assets/images/auth-crypto-scene.webp'
export default {
  data () {
    return {
      sceneImage,
      username: '',
      password: '',
      confirm_password: '',
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
      getCode: 'user/getCode',
      login: 'user/login',
      forgetPwd: 'user/forgetPwd'
    }),
    isEmail,
    handleGetCode () {
      if (this.sendingCode || this.times !== 60) return
      this.username = this.username.trim().toLowerCase()
      if (!this.isEmail(this.username)) {
        this.$toast(this.$t('pageSign.account_err'))
        return
      }
      this.sendingCode = true
      this.$toast.loading()
      this.getCode(this.username)
        .then(() => {
          this.$toast.clear()
          this.mailNoticeVisible = true
          this.$toast(this.$t('pageSign.code_sent_notice'))
          this.getTime()
        })
        .catch(({ msg }) => {
          this.$toast.clear()
          this.$toast(msg)
        })
        .finally(() => {
          this.sendingCode = false
        })
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
      if (this.password !== this.confirm_password) {
        this.$toast(this.$t('pageSign.pwd_err'))
        return false
      }
      const payload = {
        username: this.username,
        password: this.password,
        verification_code: this.verification_code
      }
      this.$toast.loading()
      this.forgetPwd(payload).then((res) => {
        this.$toast.clear()
        this.$toast(res.msg)
        this.$router.replace('/sign/login')
      }).catch(({ msg }) => {
        this.$toast.clear()
        this.$toast(msg)
      })
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
    linear-gradient(180deg, rgba(10, 12, 17, 0.24) 0%, rgba(10, 12, 17, 0.36) 22%, rgba(10, 12, 17, 0.66) 100%),
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
    max-width: 320px;
    color: rgba(255, 245, 222, 0.8);
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

.submit-btn {
  position: relative;
  height: 46px;
  margin-top: 6px;
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

.sign-form {
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

:deep(.van-field__control) {
  flex: 1;
  min-width: 0;
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
      max-width: 420px;
      font-size: 14px;
    }
  }

  .auth-card {
    padding: 28px 26px 24px;
    border-radius: 22px;
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

  .sign-form {
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
}
</style>
