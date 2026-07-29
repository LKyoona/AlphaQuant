<template>
  <div class="auth-page">
    <div class="auth-backdrop" :style="{ backgroundImage: `url(${sceneImage})` }"></div>
    <div class="auth-overlay"></div>
    <div class="auth-shell">
      <div class="auth-hero">
        <div class="hero-mark">AI Crypto Star</div>
        <h1>{{ $t('login') }}</h1>
        <p>{{ $t('pageSign.login_intro') }}</p>
      </div>

      <div class="auth-card gold-card">
        <div class="auth-tab rounded-xl border border-[#f5d280]/20 bg-white/5 p-1.5">
          <span :class="{ active: active === 1 }" @click="active = 1">{{ $t('pageSign.login_pwd') }}</span>
          <span :class="{ active: active === 2 }" @click="active = 2">{{ $t('pageSign.login_code') }}</span>
        </div>

        <van-form v-if="active === 1" class="sign-form" label-width="5.8em" @submit="onSubmit">
          <van-field
            v-model="username"
            :label="$t('pageSign.account')"
            :placeholder="$t('pageSign.account')"
            :rules="[{ required: true, message: $t('pageSign.account_err') }]"
          />

          <van-field
            v-model="password"
            type="password"
            :label="$t('pageSign.pwd')"
            :placeholder="$t('pageSign.password_placeholder')"
            :rules="[{ required: true, message: $t('pageSign.password_placeholder') }]"
          />

          <van-button
            block
            native-type="submit"
            class="submit-btn gold-btn h-12 w-full"
            :loading="submitting"
            :disabled="submitting"
          >{{ $t('login') }}</van-button>
        </van-form>

        <van-form v-else class="sign-form" label-width="5.8em" @submit="onSubmit">
          <van-field
            v-model="username"
            :label="$t('pageSign.email')"
            :placeholder="$t('pageSign.email_placeholder')"
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
                class="mini-btn gold-btn h-8 px-3 text-xs"
                :disabled="times !== 60"
                @click.prevent="handleGetCode"
              >
                <template v-if="times === 60">{{ $t('pageSign.send_code') }}</template>
                <template v-else>{{ times }}s</template>
              </van-button>
            </template>
          </van-field>
          <van-button
            block
            native-type="submit"
            class="submit-btn gold-btn h-12 w-full"
            :loading="submitting"
            :disabled="submitting"
          >{{ $t('login') }}</van-button>
        </van-form>

        <div class="links">
          <nuxt-link to="/sign/register">{{ $t('pageSign.free_reg') }}</nuxt-link>
          <div class="divider"></div>
          <nuxt-link to="/sign/forget">{{ $t('pageSign.forget_pwd') }}</nuxt-link>
        </div>
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
import logo from '@/assets/images/login_logo.png'
import sceneImage from '@/assets/images/auth-crypto-scene.webp'
export default {
  data () {
    return {
      logo,
      sceneImage,
      active: 1,
      username: '',
      password: '',
      verification_code: '',
      times: 60,
      submitting: false,
    }
  },
  methods: {
    ...mapActions({
      getCode: 'user/getCode',
      login: 'user/login'
    }),
    isEmail,
    handleGetCode () {
      this.$toast.loading()
      this.getCode(this.username)
        .then(({ msg }) => {
          this.$toast.clear()
          this.$toast(msg)
          this.getTime()
        })
        .catch(({ msg }) => {
          this.$toast.clear()
          this.$toast(msg)
        })
    },
    onSubmit () {
      if (this.submitting) return

      const payload = { username: this.username, device_type: 'web' }
      if (this.active === 1) {
        payload.password = this.password
      } else {
        payload.verification_code = this.verification_code
      }
      this.submitting = true
      this.$toast.loading()
      this.login([this.active, payload])
        .then((res) => {
          this.$toast.clear()
          this.$toast(res.msg)
          const homeHref = this.$router.resolve('/home').href
          window.location.replace(homeHref)
        })
        .catch((res) => {
          this.$toast.clear()
          this.$toast(res?.msg || res?.message || this.$t('pageSign.account_err'))
        })
        .finally(() => {
          this.submitting = false
        })
    },
    getTime () {
      this.timer = setInterval(() => {
        this.times--
        if (this.times === 0) {
          clearInterval(this.timer)
          this.times = 60
        }
      }, 1000)
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
    linear-gradient(180deg, rgba(10, 12, 17, 0.3) 0%, rgba(10, 12, 17, 0.38) 26%, rgba(10, 12, 17, 0.62) 100%),
    linear-gradient(180deg, rgba(8, 10, 14, 0.1) 0%, rgba(8, 10, 14, 0.2) 100%);
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

.auth-tab {
  display: flex;
  gap: 8px;
  padding: 4px;
  margin-bottom: 18px;
  border: 1px solid rgba(217, 176, 92, 0.16);
  border-radius: 12px;
  background: rgba(255, 244, 220, 0.04);
  box-shadow: inset 0 1px 0 rgba(255, 236, 193, 0.05);

  span {
    flex: 1;
    height: 40px;
    line-height: 40px;
    border-radius: 0;
    color: rgba(240, 227, 197, 0.58);
    text-align: center;
    font-size: 14px;
    cursor: pointer;
    transition: all .18s ease;
  }

  .active {
    border: 1px solid rgba(217, 176, 92, 0.2);
    background: linear-gradient(180deg, rgba(246, 204, 113, 0.14) 0%, rgba(246, 204, 113, 0.04) 100%);
    box-shadow: 0 6px 14px rgba(0, 0, 0, 0.18);
    color: #fff1cf;
    font-weight: 700;
  }
}

.links {
  display: flex;
  justify-content: center;
  align-items: center;
  margin-top: 6px;

  a {
    color: #f0c46e;
    font-size: 13px;
  }
}

.divider {
  width: 1px;
  height: 14px;
  margin: 0 12px;
  background: #e6decf;
  opacity: 0.35;
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

  .auth-tab {
    gap: 6px;
    margin-bottom: 16px;

    span {
      height: 38px;
      line-height: 38px;
      font-size: 13px;
    }
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

  .links {
    flex-wrap: wrap;
    row-gap: 8px;
  }
}
</style>
