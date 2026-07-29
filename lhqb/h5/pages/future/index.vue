<template>
  <div>
    <div safe-area-inset-top>
      <van-row
        type="flex"
        justify="space-between"
        align="center"
        class="page-header"
      >
        <van-col class="page-header-left">
          <h2 class="page-title">{{ $t('pageFuture.title') }} <a style="color:red" href="https://docs.qq.com/doc/DT1F6QXBPbVpMd0Rw" target="_blank">说明</a></h2>
        </van-col>
      </van-row>
    </div>
    <van-tabs
      v-model="active"
      swipeable
      animated
      sticky
    >
      <van-tab
        v-for="item in platform"
        :key="item.name"
      >
        <template #title>{{ $t(item.label) }}</template>
        <template v-if="isBalanceLoading">
          <div class="amount amount-loading">
            <div class="loading-spinner"></div>
          </div>
        </template>
        <template v-else-if="hasBalance">
          <div class="amount">{{ $t('pageFuture.balance') }} {{ $filters.numberFormat(Number(account || 0), 2) }} USDT</div>
        </template>
        <template v-else>
          <div class="amount">
            <p>{{ $t('pageFuture.contract_balance') }}{{ $t('pageFuture.balance') }} 0 USDT, {{ $t('pageFuture.not') }}</p>
            <nuxt-link to="/authorizeFuture">{{ $t('pageFuture.add') }}</nuxt-link>
          </div>
        </template>
        <assets-list :platform="item.label" ></assets-list>
      </van-tab>
    </van-tabs>
  </div>
</template>

<script>
definePageMeta({ layout: 'navigation' })

import { mapState, mapActions } from 'vuex'
import assetsList from '@/components/future/assetsList'
export default {
  components: {
    assetsList
  },
  data () {
    return {
      active: 0,
      account: null,
      isBalanceLoading: false
    }
  },
  computed: {
    ...mapState({
      platform: ({ robot }) => (robot.platform || []).filter(item => item.name == '币安')
    }),
    currentPlatform () {
      return this.platform && this.platform[this.active] ? this.platform[this.active] : null
    },
    hasBalance () {
      return this.balanceValue !== null && this.balanceValue !== undefined
    },
    balanceValue () {
      if (this.account === null || this.account === undefined) {
        return null
      }
      if (typeof this.account === 'number' || typeof this.account === 'string') {
        return this.account
      }
      const free = this.account.free || {}
      return this.account.USDT || free.USDT || this.account.free || this.account.balance || this.account.total || null
    }
  },
  watch: {
    active (newVal) {
      this.loadBalance(newVal)
    }
  },
  mounted() {
    this.loadBalance(this.active)
  },
  methods: {
    ...mapActions({
      apiAccountBalance: 'authorize/apiAccountFutureBalance'
    }),
    loadBalance (index) {
      const platform = this.platform && this.platform[index]
      if (!platform || !platform.label) {
        this.account = null
        this.isBalanceLoading = false
        return
      }
      this.isBalanceLoading = true
      this.account = null
      this.apiAccountBalance({ platform: platform.label }).then((res) => {
        const data = res && res.data ? res.data : null
        this.account = data && data.free !== undefined ? data.free : data
      }).catch((res) => {
        this.account = null
        if (res && res.msg) {
          this.$toast(res.msg)
        }
      }).finally(() => {
        this.isBalanceLoading = false
      })
    }
  }
}
</script>

<style lang="less" scoped>
.page-header {
  min-height: 56px;
  padding: 16px 16px 8px;
  margin: 12px 10px 0;
  border: 1px solid rgba(217, 176, 92, 0.18);
  border-radius: 16px;
  background: linear-gradient(180deg, rgba(28, 20, 10, 0.96) 0%, rgba(18, 12, 6, 0.98) 100%);
  box-shadow: 0 18px 40px rgba(0, 0, 0, 0.34);
  &-right .van-icon {
    display: inline-block;
    vertical-align: middle;
  }
  &-title {
    display: flex;
    align-items: center;
    font-size: 22px;
    line-height: 1;
    color: #fff1cf;
  }
}

.amount {
  margin: 0 10px;
  padding: 12px 15px;
  min-height: 52px;
  font-size: 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border: 1px solid rgba(217, 176, 92, 0.16);
  border-radius: 12px;
  background:
    radial-gradient(circle at top right, rgba(248, 215, 144, 0.12), transparent 30%),
    linear-gradient(180deg, rgba(37, 25, 12, 0.98), rgba(22, 14, 7, 0.98));
  color: rgba(240, 227, 197, 0.68);
  a {
    color: #f0c46e;
    font-weight: 700;
  }
  p {
    color: inherit;
  }
}

.amount-loading {
  justify-content: center;
}

.loading-spinner {
  width: 22px;
  height: 22px;
  border: 2px solid rgba(240, 196, 110, 0.22);
  border-top-color: #f0c46e;
  border-radius: 50%;
  animation: balance-spin 0.8s linear infinite;
}

@keyframes balance-spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.page-header-left {
  margin: auto;
}

:deep(.van-tabs__wrap),
:deep(.van-tabs__nav),
:deep(.van-tabs__content) {
  background: transparent !important;
}

:deep(.van-tab) {
  color: rgba(240, 227, 197, 0.58);
}

:deep(.van-tab--active) {
  color: #fff1cf;
  font-weight: 700;
}

:deep(.van-tabs__line) {
  background: linear-gradient(90deg, #8d5c1f, #f1cd86) !important;
}
</style>
