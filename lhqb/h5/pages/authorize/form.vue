<template>
  <div class="authorize-form-page">
    <div class="authorize-form-shell">
      <van-nav-bar
        fixed
        placeholder
        :title="$t(platforms[active].label)"
        left-arrow
        @click-left="$router.back()"
      />
      <div class="form-hero">
        <p class="eyebrow">{{ $t('pageAuthorizeForm.settings') }}</p>
        <h2 class="hero-title">{{ $t(platforms[active].label) }}</h2>
        <p class="hero-sub">{{ $t('pageAuthorizeForm.intro') }}</p>
      </div>

      <div class="form-panel">
        <van-form @submit="onSubmit">
          <van-field
            v-model="api_key"
            label="Api Key"
            :placeholder="$t('pageAuthorizeForm.api_key_placeholder')"
            :rules="[{ required: true }]"
          />
          <van-field
            v-model="secret_key"
            label="Secret Key"
            :placeholder="$t('pageAuthorizeForm.secret_key_placeholder')"
            :rules="[{ required: true }]"
          />
          <van-field
            v-if="currentPlatform.requiresPassphrase"
            v-model="passphrase"
            label="Passphrase"
            :placeholder="$t('pageAuthorizeForm.passphrase_placeholder')"
            :rules="[{ required: true }]"
          />
          <div class="submit-wrap">
            <van-button
              block
              native-type="submit"
              class="submit-btn"
              :loading="submitting"
              :disabled="submitting"
            >
              {{ $t('actions.import') }}
            </van-button>
          </div>
        </van-form>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState, mapActions } from 'vuex'
export default {
  data () {
    return {
      api_key: '',
      secret_key: '',
      passphrase: '',
      submitting: false
    }
  },
  created () {
    this.syncPlatformForm()
  },
  computed: {
    ...mapState({
      platforms: ({ authorize }) => authorize.platform
    }),
    active () {
      const index = Number(this.$route.query.active || 0)
      if (Number.isNaN(index) || index < 0 || index >= this.platforms.length) {
        return 0
      }
      return index
    },
    platform () {
      return this.currentPlatform.label
    },
    currentPlatform () {
      return this.platforms[this.active] || this.platforms[0]
    }
  },
  watch: {
    active () {
      this.syncPlatformForm()
    }
  },
  methods: {
    ...mapActions({
      editApiAccount: 'authorize/editApiAccount',
      setApiInfo: 'authorize/setApiInfo'
    }),
    syncPlatformForm () {
      const p = this.currentPlatform
      if (!p) {
        return
      }
      this.api_key = p.api_key || ''
      this.secret_key = p.secret_key || ''
      this.passphrase = p.passphrase || ''
    },
    onSubmit () {
      if (this.submitting) {
        return
      }
      this.submitting = true
      const payload = {
        platform: this.platform,
        api_key: this.api_key,
        secret_key: this.secret_key,
        passphrase: this.currentPlatform.requiresPassphrase ? this.passphrase : ''
      }
      this.$toast.loading()
      this.editApiAccount(payload).then((res) => {
        this.$toast.clear()
        this.setApiInfo([this.active, Object.assign({}, this.currentPlatform, payload, { status: 1 })])
        this.$toast(res.msg)
        this.$router.back()
      }).catch(({ msg }) => {
        this.$toast.clear()
        this.$toast(msg)
      }).finally(() => {
        this.submitting = false
      })
    }
  }
}
</script>

<style scoped lang="less">
.authorize-form-page {
  position: relative;
  min-height: 100vh;
  padding: 12px 10px 24px;
  background:
    radial-gradient(circle at top left, rgba(246, 204, 113, 0.18), transparent 24%),
    radial-gradient(circle at 84% 10%, rgba(161, 118, 39, 0.12), transparent 18%),
    linear-gradient(180deg, #050402 0%, #110d07 42%, #181108 100%);
  color: #f7ecd2;
  overflow: hidden;
}

.authorize-form-page::before {
  content: '';
  position: absolute;
  inset: 0;
  background:
    linear-gradient(rgba(228, 191, 112, 0.05) 1px, transparent 1px),
    linear-gradient(90deg, rgba(228, 191, 112, 0.03) 1px, transparent 1px);
  background-size: 30px 30px;
  mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.32), transparent 72%);
  pointer-events: none;
  opacity: 0.55;
}

.authorize-form-shell {
  position: relative;
  z-index: 1;
  max-width: 1200px;
  margin: 0 auto;
}

.form-hero,
.form-panel {
  border: 1px solid rgba(217, 176, 92, 0.18);
  border-radius: 16px;
  background: linear-gradient(180deg, rgba(28, 20, 10, 0.96) 0%, rgba(18, 12, 6, 0.98) 100%);
  box-shadow: 0 18px 40px rgba(0, 0, 0, 0.34);
}

.form-hero {
  padding: 18px 16px 14px;
  margin-top: 12px;
}

.eyebrow {
  margin: 0 0 8px;
  color: #ddb46a;
  font-size: 11px;
  font-weight: 700;
}

.hero-title {
  margin: 0;
  color: #fff1cf;
  font-size: 22px;
  line-height: 1.2;
}

.hero-sub {
  margin: 10px 0 0;
  color: rgba(240, 227, 197, 0.68);
  font-size: 13px;
  line-height: 1.6;
}

.form-panel {
  margin-top: 12px;
  overflow: hidden;
}

.submit-wrap {
  padding: 16px;
}

.submit-btn {
  height: 46px;
  border: 1px solid rgba(217, 176, 92, 0.2);
  border-radius: 12px;
  background: linear-gradient(135deg, #21180f 0%, #4a3615 100%);
  color: #f4d78b;
  font-size: 15px;
  font-weight: 700;
}

:deep(.van-cell) {
  display: block;
  background: transparent;
}

:deep(.van-cell::after) {
  left: 16px;
  right: 16px;
  border-color: rgba(217, 176, 92, 0.12);
}

:deep(.van-field__label) {
  color: rgba(240, 227, 197, 0.7);
}

:deep(.van-field__control) {
  color: #fff1cf;
}

:deep(.van-button::before) {
  display: none;
}
</style>
