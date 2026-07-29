<template>
  <div class="activation-page">
    <van-nav-bar
      :title="$t('pageActivation.title')"
      left-arrow
      :right-text="$t('pageActivation.buy')"
      @click-left="$router.back()"
      @click-right="showPwd = true"
    />
    <van-pull-refresh v-model="refreshing" @refresh="onRefresh">
      <van-list v-model:loading="loading" :finished="finished" :finished-text="$t('finished_text')" @load="onLoad">
        <div v-for="item in list" :key="item.id" class="key-item">
          <van-row type="flex" justify="space-between" align="center">
            <van-col class="title">{{ $t('pageActivation.key') }}：{{ item.keys }}</van-col>
            <van-col v-if="!item.used">
              <van-icon
                name="description"
                v-clipboard:copy="item.keys"
                v-clipboard:success="onCopy"
                size="16"
              />
            </van-col>
          </van-row>
          <van-row type="flex" justify="space-between" align="center">
            <van-col class="time">{{ $t('pageActivation.time') }}：{{ $filters.timeFormat(item.ctime) }}</van-col>
            <van-col>
              <span v-if="item.used" class="status">{{ $t('pageActivation.state') }}({{ $t('pageActivation.robot') }} ID:{{ item.qrobot_id }})</span>
            </van-col>
          </van-row>
        </div>
      </van-list>
    </van-pull-refresh>
    <!-- <password-confirm :show="showPwd" @close="showPwd = false" @confrim="buyCdkey" /> -->
    <van-dialog v-model:show="showPwd" :title="$t('pageActivation.buy_title')" show-cancel-button @confirm="buyCdkey">
      <div class="dialog-1">
        <van-cell>
          <span class="label">{{ $t('pageActivation.buy_count') }}</span>
          <span>1</span>
        </van-cell>
        <van-cell>
          <span class="label">{{ $t('pageActivation.trade_pwd') }}</span>
          <input v-model="pwd" type="password" :placeholder="$t('pageActivation.trade_pwd_placeholder')">
        </van-cell>
        <div class="tip">支付：<span>{{ initInfo.cdkey_price }}</span> USDT</div>
      </div>
    </van-dialog>
  </div>
</template>

<script>
import { mapState, mapActions } from 'vuex'
import PasswordConfirm from '@/components/common/PasswordConfirm'
export default {
  components: { PasswordConfirm },
  data () {
    return {
      show: true,
      showPwd: false,
      loading: false,
      finished: false,
      refreshing: false,
      list: [],
      pwd: ''
    }
  },
  computed: {
    ...mapState({
      initInfo: index => index.initInfo
    })
  },
  methods: {
    ...mapActions({
      cdkeyList: 'user/cdkeyList',
      cdkeyActive: 'user/cdkeyActive',
      cdkeyBuy: 'user/cdkeyBuy'
    }),
    onLoad () {
      this.cdkeyList()
        .then((res) => {
          this.list = res.data
        })
        .finally(() => {
          this.loading = false
          this.finished = true
        })
    },
    onRefresh () {
      this.finished = false
      this.loading = true
      this.onLoad()
    },
    onCopy () {
      this.$toast(this.$t('actions.copy_success'))
    },
    buyCdkey (password) {
      this.showPwd = false
      this.$toast.loading()
      password = password || this.pwd
      this.cdkeyBuy({ password })
        .then((res) => {
          this.$toast(res.msg)
          this.onRefresh()
        })
        .catch(({ msg }) => {
          this.$toast(msg)
        })
    }
  }
}
</script>

<style scoped lang="less">
.activation-page {
  min-height: 100vh;
  padding-bottom: 24px;
  background: transparent;
}

.key-item {
  margin: 10px 15px;
  padding: 10px;
  border: 1px solid rgba(217, 176, 92, 0.16);
  border-radius: 12px;
  background:
    radial-gradient(circle at top right, rgba(248, 215, 144, 0.14), transparent 30%),
    linear-gradient(180deg, rgba(37, 25, 12, 0.98), rgba(22, 14, 7, 0.98));
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.18);
  line-height: 30px;
  color: #f0e3c5;
}
.title {
  font-weight: 500;
  color: #fff1cf;
}
.time {
  color: rgba(240, 227, 197, 0.56);
  font-size: 12px;
}
.status {
  font-size: 12px;
  color: #ff8078;
  opacity: 0.9;
}
.btn {
  display: block;
  height: auto;
  padding: 5px 10px;
}
.dialog-1 {
  padding: 20px 10px;
  .van-cell__value {
    display: flex;align-items: center;
  }
  .label {
    width: 5em;
    flex-shrink: 0;
  }
  input {
    border: none;
    flex-grow: 1;
    min-width: 0;
    color: #f0e3c5;
    background: transparent;
  }
  .tip {
    text-align: right;
    padding: 10px 16px;
    font-size: 12px;
    color: rgba(240, 227, 197, 0.68);
    span {
      color: #f0c46e;
    }
  }
}
</style>
