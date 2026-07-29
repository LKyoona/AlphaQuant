<template>
  <div class="boot-page">
    <div class="boot-backdrop"></div>
    <div class="boot-overlay"></div>
    <div class="boot-card">
      <div class="boot-mark">AI Crypto Star</div>
      <div class="boot-title">{{ $t('loading') || 'Loading...' }}</div>
      <div class="boot-spinner"></div>
      <div class="boot-tip">{{ bootTip }}</div>
    </div>
  </div>
</template>

<script>
import { mapActions } from 'vuex'
import { tokenStorage } from '@/utils/storage'
export default {
  data () {
    return {
      authToken: this.$route.query.token,
      bootTip: 'Preparing secure session...'
    }
  },
  created () {
    this.needAuth()
  },
  methods: {
    ...mapActions({
      openAuthLogin: 'user/openAuthLogin'
    }),
    needAuth () {
      const homeHref = this.$router.resolve('/home').href
	  const loginHref = this.$router.resolve('/sign/login').href
      if (this.authToken) {
        this.bootTip = 'Verifying authorization...'
        this.$toast.loading('身份验证中...')
        this.openAuthLogin({ token: this.authToken })
          .then(() => {
            this.bootTip = 'Loading account data...'
            this.$toast('授权登录成功')
            window.location.replace(homeHref)
          })
          .catch(({ msg }) => {
            this.bootTip = msg || 'Authorization failed'
            this.$toast(msg)
          })
      } else {
		const accessToken = tokenStorage.get() || ''
		this.bootTip = accessToken ? 'Redirecting to dashboard...' : 'Redirecting to sign in...'
		window.location.replace(accessToken ? homeHref : loginHref)
      }
    }
  }
}
</script>

<style scoped lang="less">
.boot-page {
  position: relative;
  min-height: 100dvh;
  overflow: hidden;
  background:
    radial-gradient(circle at top left, rgba(246, 204, 113, 0.14), transparent 26%),
    radial-gradient(circle at 80% 18%, rgba(161, 118, 39, 0.1), transparent 18%),
    linear-gradient(180deg, #050402 0%, #110d07 42%, #181108 100%);
}

.boot-backdrop,
.boot-overlay {
  position: absolute;
  inset: 0;
}

.boot-backdrop {
  background:
    radial-gradient(circle at 50% 30%, rgba(255, 216, 140, 0.08), transparent 34%),
    radial-gradient(circle at 50% 70%, rgba(246, 204, 113, 0.04), transparent 42%);
}

.boot-overlay {
  background: linear-gradient(180deg, rgba(5, 4, 2, 0.24) 0%, rgba(5, 4, 2, 0.54) 100%);
}

.boot-card {
  position: relative;
  z-index: 1;
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 24px;
  text-align: center;
}

.boot-mark {
  display: inline-flex;
  align-items: center;
  padding: 5px 10px;
  margin-bottom: 16px;
  border: 1px solid rgba(255, 255, 255, 0.16);
  border-radius: 999px;
  background: rgba(20, 24, 33, 0.42);
  color: #f8df9d;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.boot-title {
  color: #fff1cf;
  font-size: 18px;
  font-weight: 800;
  letter-spacing: 0.02em;
}

.boot-spinner {
  width: 32px;
  height: 32px;
  margin: 18px 0 14px;
  border-radius: 50%;
  border: 2px solid rgba(240, 196, 110, 0.22);
  border-top-color: #f0c46e;
  animation: boot-spin 0.8s linear infinite;
}

.boot-tip {
  color: rgba(255, 241, 207, 0.72);
  font-size: 13px;
  line-height: 1.5;
}

@keyframes boot-spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>
