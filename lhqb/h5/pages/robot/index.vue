<template>
  <div>
    <van-nav-bar
      :border="false"
      :title="robot.market_name || $t('pageRobot.title')"
      :right-text="$t('pageRobot.order')"
      left-arrow
      @click-left="$router.back()"
      @click-right="goOrder"
    />

    <template v-if="hasRobot">
      <div class="top gold-card">
        <h3 class="title">
          {{ robot.market_name || $t('pageRobot.title') }}
          <van-tag
            v-if="robot.recycle_status == 1"
            class="robot-tag"
            type="primary"
          >
            {{ $t('cycle_strategy') }}
          </van-tag>
          <van-tag
            v-if="robot.recycle_status == 0"
            class="robot-tag"
            type="primary"
          >
            {{ $t('single_strategy') }}
          </van-tag>
        </h3>

        <div
          v-if="robot.values"
          class="preview-grid"
        >
          <div class="preview-card">
            <div class="label">{{ $t('position_amount') }}({{ robot.money }})</div>
            <div class="value">{{ $filters.numberFormat(Number(robot.values.deal_money || 0), 6) }}</div>
          </div>
          <div class="preview-card">
            <div class="label">{{ $t('average_price') }}</div>
            <div class="value">{{ $filters.numberFormat(Number(robot.price || 0), 6) }}</div>
          </div>
          <div
            class="preview-card preview-card-clickable"
            @click="goOrder"
          >
            <div class="label">{{ $t('number_calls') }}</div>
            <div class="value">{{ robot.values.order_count ? robot.values.order_count - 1 : 0 }}</div>
          </div>
          <div class="preview-card">
            <div class="label">{{ $t('number_positions') }}({{ robot.stock }})</div>
            <div class="value">{{ $filters.numberFormat(Number(robot.values.deal_amount || 0), 6) }}</div>
          </div>
          <div class="preview-card">
            <div class="label">{{ $t('now_price') }}(USDT)</div>
            <div class="value">{{ $filters.numberFormat(Number(last || 0), 6) }}</div>
          </div>
          <div class="preview-card">
            <div class="label">{{ $t('profit_loss') }}</div>
            <div
              class="value profit-value"
              :class="{
                'profit-positive': Number(robot.revenue) > 0,
                'profit-negative': Number(robot.revenue) < 0
              }"
            >
              {{ $filters.numberFormat(Number(robot.revenue), 6) }}{{ robot.money }}
            </div>
          </div>
        </div>
      </div>

      <div class="title-block title-row">
        <span>{{ $t('latest_log') }}</span>
        <nuxt-link
          class="link log-link"
          :to="'/robot/log?id=' + robot.id"
        >
          {{ $t('all_log') }}
          <van-icon name="arrow" />
        </nuxt-link>
      </div>
      <div class="new-msg gold-card-lite">
        <p
          v-if="robot.show_msg"
          class="van-ellipsis"
        >
          {{ robot.show_msg }}
        </p>
        <span
          v-else
          style="color: #888"
        >{{ $t('empty.log') }}</span>
      </div>

      <div class="title-block">{{ $t('pageRobot.account_related') }}</div>
      <van-row class="block1 gold-card-lite">
        <template v-if="accountLoading">
          <van-col
            :span="24"
            class="account-loading-wrap"
          >
            <div class="loading-spinner"></div>
          </van-col>
        </template>
        <template v-else>
          <van-col :span="24">
            {{ $t('pageRobot.available', [robot.stock || '-']) }}：<span>{{
              $filters.numberFormat(Number((account && account[robot.stock]) || 0), 8) }}</span>
          </van-col>
          <van-col :span="24">
            {{ $t('pageRobot.available', [robot.money || '-']) }}：<span>{{
              $filters.numberFormat(Number((account && account[robot.money]) || 0), 7) }}</span>
          </van-col>
        </template>
      </van-row>

      <!-- <div class="title-block">{{ $t('pageRobot.strategy_related') }}</div>
      <van-row class="block1">
        <van-col :span="12">
          {{ $t('first_order_amount') }} <span>{{ robot.first_order_value }}</span>
        </van-col>
        <van-col :span="12">
          {{ $t('number_of_orders') }} <span>{{ robot.max_order_count }}</span>
        </van-col>
        <van-col :span="12">
          {{ $t('take_profit_ratio') }} <span>{{ robot.stop_profit_rate }}%</span>
        </van-col>
        <van-col :span="12">
          {{ $t('take_profit_retracement') }}
          <span>{{ robot.stop_profit_callback_rate }}%</span>
        </van-col>
        <van-col :span="12">
          {{ $t('cover_down') }} <span v-for="(item,index) in robot.newcover_rate" :key="index">{{ item }}%&nbsp;</span>
        </van-col>
        <van-col :span="12">
          {{ $t('cover_pullback') }} <span>{{ robot.cover_callback_rate }}%</span>
        </van-col>
      </van-row> -->

      <div class="title-block">{{ $t('pageRobot.strategic_operations') }}</div>
      <van-grid
        column-num="3"
        :border="false"
        class="ops-grid"
      >
        <van-grid-item
          v-if="robot.status === 0"
          class="ops-item"
          @click="onEnable"
        >
          <div class="ops-card">
            <div class="ops-icon-wrap">
              <van-image
                width="34"
                height="34"
                fit="contain"
                :src="icons.icon1"
              />
            </div>
            <div class="ops-text">{{ $t('pageRobot.start') }}</div>
          </div>
        </van-grid-item>
        <van-grid-item
          v-else
          class="ops-item"
          @click="onDisable"
        >
          <div class="ops-card">
            <div class="ops-icon-wrap">
              <van-image
                width="34"
                height="34"
                fit="contain"
                :src="icons.icon3"
              />
            </div>
            <div class="ops-text">{{ $t('pageRobot.pause') }}</div>
          </div>
        </van-grid-item>
        <van-grid-item
          class="ops-item"
          @click="goEdit"
        >
          <div class="ops-card">
            <div class="ops-icon-wrap">
              <van-image
                width="34"
                height="34"
                fit="contain"
                :src="icons.icon2"
              />
            </div>
            <div class="ops-text">{{ $t('pageRobot.trade_setup') }}</div>
          </div>
        </van-grid-item>
        <van-grid-item
          class="ops-item"
          @click="onClean"
        >
          <div class="ops-card danger">
            <div class="ops-icon-wrap">
              <van-image
                width="34"
                height="34"
                fit="contain"
                :src="icons.icon4"
              />
            </div>
            <div class="ops-text">{{ $t('pageRobot.clearance_sell') }}</div>
          </div>
        </van-grid-item>
      </van-grid>
    </template>

    <div
      v-else-if="robotLoading"
      class="empty-state loading-state"
    >
      <div class="loading-spinner"></div>
    </div>

    <div
      v-else
      class="empty-state"
    >
      {{ $t('empty.log') }}
    </div>
  </div>
</template>

<script>
import { Grid, GridItem, Popover } from 'vant'
import { mapGetters, mapActions } from 'vuex'
import iconStart from '@/assets/images/jiaoyi6.png'
import iconEdit from '@/assets/images/jiaoyi3.png'
import iconPause from '@/assets/images/jiaoyi5.png'
import iconClean from '@/assets/images/jiaoyi4.png'
export default {
  components: {
    [Grid.name]: Grid,
    [GridItem.name]: GridItem,
    [Popover.name]: Popover
  },
  data () {
    return {
      market_id: '',
      robot: {},
      icons: {
        icon1: iconStart,
        icon2: iconEdit,
        icon3: iconPause,
        icon4: iconClean
      },
      showPopover: false,
      account: {},
      accountLoading: false,
      robotLoading: true,
      last: ''
    }
  },
  computed: {
    ...mapGetters({
      robotFind: 'robot/robot'
    }),
    hasRobot () {
      return Boolean(this.robot && this.robot.id)
    }
  },
  created () {
    try {
      this.market_id = JSON.parse(this.$route.query.market_id)
    } catch (e) {
      this.market_id = this.$route.query.market_id || ''
    }
    const robotRequest = this.robotFind(this.market_id)
      ? Promise.resolve()
      : this.robotList()
    robotRequest.then(() => {
      this.robot = this.robotFind(this.market_id) || {}
      if (!this.robot || !this.robot.id) {
        return
      }
      let obj = {}
      try {
        obj = JSON.parse(this.robot.cover_rate || '{}')
      } catch (e) {
        obj = {}
      }
      const arr = []
      for (const key in obj) {
        // console.log(obj[key])
        arr.push(obj[key])
      }
      this.robot.newcover_rate = arr
      this.accountLoading = true
      this.apiAccountBalance({ platform: this.robot.platform }).then((res) => {
        this.account = res && res.data && res.data.free ? res.data.free : {}
      }).catch((res) => {
        this.$toast(res.msg)
      }).finally(() => {
        this.accountLoading = false
      })
      this.publicTicker({
        exchange: this.robot.platform,
        market: this.robot.market_name,
        currency: 'USD'
      }).then((res) => {
        this.last = res && res.data ? res.data.last : ''
      }).catch((res) => {
        this.$toast(res.msg)
      })
    }).catch((error) => {
      this.$toast(error && error.msg ? error.msg : this.$t('empty.log'))
    }).finally(() => {
      this.robotLoading = false
    })
  },
  methods: {
    ...mapActions({
      robotList: 'robot/robotList',
      robotEnable: 'robot/robotEnable',
      robotDisable: 'robot/robotDisable',
      robotClean: 'robot/robotClean',
      apiAccountBalance: 'authorize/apiAccountBalance',
      publicTicker: 'robot/publicTicker'
    }),
    goEdit () {
      if (!this.robot || !this.robot.id) {
        return
      }
      this.$router.push({
        name: 'robot-form',
        query: {
          type: 'edit',
          robot_id: this.robot.id
        }
      })
    },
    goOrder () {
      if (!this.robot || !this.robot.id) {
        return
      }
      this.$router.push('/robot/order?id=' + this.robot.id)
    },
    onEnable () {
      if (!this.robot || !this.robot.id) {
        return
      }
      this.$dialog
        .confirm({
          message: this.$t('pageRobot.dialog_enable') + '？'
        })
        .then((res) => {
          this.$toast.loading()
          this.robotEnable({ robot_id: this.robot.id }).then((res) => {
            this.$toast(res.msg)
            this.robotList()
            this.$nextTick(() => {
              this.robot = this.robotFind(this.market_id)
              this.robot.status = 1
            })
          }).catch((res) => {
            this.$toast(res.msg)
          })
        })
    },
    onDisable () {
      if (!this.robot || !this.robot.id) {
        return
      }
      this.$dialog
        .confirm({
          message: this.$t('pageRobot.dialog_pause') + '？'
        })
        .then((res) => {
          this.$toast.loading()
          this.robotDisable({ robot_id: this.robot.id }).then((res) => {
            this.$toast(res.msg)
            this.robotList()
            this.$nextTick(() => {
              this.robot = this.robotFind(this.market_id)
              this.robot.status = 0
            })
          }).catch((res) => {
            this.$toast(res.msg)
          })
        })
    },
    onClean () {
      if (!this.robot || !this.robot.id) {
        return
      }
      this.$dialog
        .confirm({
          message: this.$t('pageRobot.dialog_sell') + '？'
        })
        .then((res) => {
          this.$toast.loading()
          this.robotClean({ robot_id: this.robot.id }).then((res) => {
            this.$toast(res.msg)
            this.$router.back()
          })
        })
    }
  }
}
</script>

<style scoped lang="less">
:deep(.van-popover__action) {
  width: 134px;
}
.top {
  margin: 12px;
  padding: 16px;
  border: 1px solid rgba(217, 176, 92, 0.22);
  background: linear-gradient(180deg, rgba(37, 25, 12, 0.98), rgba(22, 14, 7, 0.98));
  .title {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin: 0 0 14px;
    color: #fff1cf;
    font-size: 1.5em;
    .van-tag {
      font-weight: normal;
    }
  }
  .van-col {
    padding: 10px 12px 10px 0;
    color: #f0e3c5;
  }
  .label {
    margin-bottom: 5px;
    opacity: 0.8;
  }
  .value {
    font-size: 16px;
  }
}
.preview-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}
.preview-card {
  min-width: 0;
  padding: 12px 10px;
  border: 1px solid rgba(255, 228, 170, 0.12);
  border-radius: 14px;
  background:
    radial-gradient(circle at top, rgba(255, 239, 196, 0.1), transparent 42%),
    linear-gradient(180deg, rgba(255, 248, 234, 0.04), rgba(255, 248, 234, 0.015));
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.06),
    0 8px 20px rgba(0, 0, 0, 0.08);
}
.preview-card-clickable {
  cursor: pointer;
  transition:
    transform 0.14s ease,
    background 0.14s ease,
    box-shadow 0.14s ease;
}
.preview-card-clickable:active {
  transform: scale(0.99);
  background:
    radial-gradient(circle at top, rgba(255, 239, 196, 0.14), transparent 42%),
    linear-gradient(180deg, rgba(255, 248, 234, 0.06), rgba(255, 248, 234, 0.02));
}
.preview-card .label {
  margin-bottom: 6px;
  color: rgba(240, 227, 197, 0.72);
  font-size: 12px;
  line-height: 1.35;
}
.preview-card .value {
  color: #fff1cf;
  font-size: 15px;
  font-weight: 700;
  line-height: 1.35;
  word-break: break-all;
}
.profit-value {
  font-variant-numeric: tabular-nums;
}
.profit-positive {
  color: #1ec97f;
}
.profit-negative {
  color: #ff6b6b;
}
.title-block {
  display: flex;
  align-items: center;
  padding: 12px 16px 8px;
  color: #fff1cf;
  font-size: 1em;
  font-weight: 500;
  &::before {
    width: 3px;
    height: 16px;
    margin-right: 10px;
    background: @themeColor;
    content: '';
  }
}
.block1,
.new-msg {
  margin: 0 12px 12px;
  padding: 12px 16px;
  border: 1px solid rgba(217, 176, 92, 0.18);
  background: linear-gradient(180deg, rgba(28, 20, 10, 0.96), rgba(18, 12, 6, 0.98));
}
.block1 {
  color: rgba(240, 227, 197, 0.68);
  .van-col {
    margin: 10px 0;
  }
  span {
    color: @themeColor;
    font-size: 16px;
  }
}
.new-msg {
  color: #f0e3c5;
}
.account-loading-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 48px;
}
.loading-spinner {
  width: 22px;
  height: 22px;
  border: 2px solid rgba(240, 196, 110, 0.22);
  border-top-color: #f0c46e;
  border-radius: 50%;
  animation: balance-spin 0.8s linear infinite;
}
.title-row {
  justify-content: space-between;
  padding-bottom: 8px;
}
.log-link {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  color: #d9b05c;
  font-size: 13px;
  font-weight: normal;
}
.log-link:active {
  opacity: 0.65;
}
.robot-tag {
  border: 1px solid rgba(255, 228, 170, 0.22);
  background: rgba(246, 204, 113, 0.08);
  color: #fff1cf;
}
.gold-card-lite {
  box-shadow: none;
}
.ops-grid {
  margin: 0 12px 12px;
  overflow: hidden;
  border: 1px solid rgba(217, 176, 92, 0.12);
  background:
    radial-gradient(circle at top, rgba(255, 226, 161, 0.06), transparent 38%),
    linear-gradient(180deg, rgba(26, 19, 10, 0.92), rgba(17, 12, 6, 0.95));
  box-shadow:
    0 12px 26px rgba(0, 0, 0, 0.16),
    inset 0 1px 0 rgba(255, 245, 214, 0.04);
}
.ops-item {
  background: transparent;
  color: #ffe7a8;
}
:deep(.ops-grid .van-grid-item) {
  background: transparent !important;
}
:deep(.ops-grid .van-grid-item__content) {
  min-height: 104px;
  padding: 0;
  border: 0;
  background: transparent !important;
  color: #ffe7a8;
  font-size: 14px;
  font-weight: 700;
  transition:
    transform 0.14s ease,
    background 0.14s ease,
    box-shadow 0.14s ease;
}
:deep(.ops-grid .van-grid-item__content::after) {
  border-color: rgba(217, 176, 92, 0.12);
}
:deep(.ops-grid .van-grid-item__content:active) {
  background: rgba(246, 204, 113, 0.08) !important;
  transform: scale(0.985);
}
:deep(.ops-grid .van-image) {
  display: block;
}
.ops-card {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 12px 8px;
  border-radius: 14px;
  background:
    radial-gradient(circle at top, rgba(255, 237, 195, 0.16), transparent 45%),
    linear-gradient(180deg, rgba(255, 248, 234, 0.04), rgba(255, 248, 234, 0.015));
}
.ops-card.danger {
  background:
    radial-gradient(circle at top, rgba(255, 205, 162, 0.16), transparent 45%),
    linear-gradient(180deg, rgba(255, 248, 234, 0.04), rgba(255, 248, 234, 0.015));
}
.ops-icon-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border: 1px solid rgba(255, 228, 170, 0.16);
  border-radius: 12px;
  background: rgba(255, 248, 234, 0.05);
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.16),
    0 10px 20px rgba(0, 0, 0, 0.14);
}
.ops-text {
  color: #ffe7a8;
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.02em;
  text-align: center;
}
.empty-state {
  margin: 12px;
  padding: 18px 16px;
  border: 1px solid rgba(217, 176, 92, 0.18);
  background: linear-gradient(180deg, rgba(28, 20, 10, 0.96), rgba(18, 12, 6, 0.98));
  color: rgba(240, 227, 197, 0.68);
  text-align: center;
}
.loading-state {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 96px;
}
:deep(.van-tag),
:deep(.van-grid-item__content),
:deep(.van-image__img) {
  border-radius: 0 !important;
}
@media (max-width: 767px) {
  .top,
  .block1,
  .new-msg,
  .ops-grid {
    margin-right: 10px;
    margin-left: 10px;
  }

  .preview-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
  }

  .preview-card {
    padding: 10px 9px;
    border-radius: 12px;
  }

  .preview-card .label {
    font-size: 11px;
  }

  .preview-card .value {
    font-size: 13px;
  }

  :deep(.ops-grid .van-grid-item__content) {
    min-height: 96px;
  }

  .ops-card {
    padding: 10px 6px;
    gap: 6px;
  }

  .ops-icon-wrap {
    width: 38px;
    height: 38px;
  }

  .ops-text {
    font-size: 12px;
  }
}
@media (max-width: 420px) {
  .preview-grid {
    grid-template-columns: 1fr;
  }
}
@keyframes balance-spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>
