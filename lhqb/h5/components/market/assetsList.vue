<template>
  <van-pull-refresh
    v-model="isLoading"
    @refresh="onLoad"
  >
    <van-list
      :finished="finished"
      @load="onLoad"
    >
      <div
        v-if="!logged"
        class="market-login-hint"
      >
        <p class="hint-title">{{ $t('pageMarket.login_required') }}</p>
        <p class="hint-desc">{{ $t('pageMarket.login_required_desc') }}</p>
        <nuxt-link to="/sign/login" class="login-btn gold-btn">
          {{ $t('pageMarket.go_login') }}
        </nuxt-link>
      </div>
      <van-empty
        v-else-if="market.length === 0 && finished"
        :description="$t('empty.default')"
      />
      <template v-else>
        <van-cell
          v-for="item in market"
          :key="item.id"
          @click="goDetail(item)"
        >
          <div class="asset-item">
            <div class="center">
              <div class="name-row">
                <div class="name">
                  {{ item.market_name }}
                </div>
                <van-tag
                  v-if="findRobot(item.id) && findRobot(item.id).recycle_status == 1"
                  round
                  type="primary"
                  class="strategy-tag"
                >
                  {{ $t('cycle_strategy') }}
                </van-tag>
                <van-tag
                  v-if="findRobot(item.id) && findRobot(item.id).recycle_status == 0"
                  round
                  type="primary"
                  class="strategy-tag"
                >
                  {{ $t('single_strategy') }}
                </van-tag>
              </div>
              <div
                v-if="findRobot(item.id)"
                class="info-grid"
              >
                <div class="info-line">
                  <span class="info-label">{{ $t('position_amount') }}</span>
                  <span class="info-value">{{ $filters.numberFormat(Number(findRobot(item.id).first_order_value), 5) }} {{ item.money }}</span>
                </div>
                <div class="info-line">
                  <span class="info-label">{{ $t('average_price') }}</span>
                  <span class="info-value">{{ $filters.numberFormat(Number(findRobot(item.id).price), 5) }} {{ item.money }}</span>
                </div>
                <div class="info-line">
                  <span class="info-label">{{ $t('expected_return') }}</span>
                  <span
                    class="info-value"
                    :class="{
                      'profit-positive': Number(findRobot(item.id).revenue) > 0,
                      'profit-negative': Number(findRobot(item.id).revenue) < 0
                    }"
                  >
                    {{ $filters.numberFormat(Number(findRobot(item.id).revenue), 5) }} {{ item.money }}
                  </span>
                </div>
                <div class="info-line">
                  <span class="info-label">{{ $t('revue_rate') }}</span>
                  <span
                    class="info-value"
                    :class="{
                      'profit-positive': Number(findRobot(item.id).rate) > 0,
                      'profit-negative': Number(findRobot(item.id).rate) < 0
                    }"
                  >
                    {{ $filters.numberFormat(Number(findRobot(item.id).rate), 5) }}%
                  </span>
                </div>
                <div class="info-line info-line-wide">
                  <span class="info-label">{{ $t('number_positions') }}</span>
                  <span class="info-value">{{ getDeal(findRobot(item.id).values_str) }} {{ item.stock }}</span>
                </div>
              </div>
            </div>
            <div class="right">
              <template v-if="findRobot(item.id)">
                <span
                  class="status-text"
                  :class="isRobotEnabled(findRobot(item.id)) ? 'status-enabled' : 'status-disabled'"
                >
                  <i class="status-dot"></i>
                  {{ isRobotEnabled(findRobot(item.id)) ? $t('status.enabled') : $t('status.disabled') }}
                </span>
                <span class="detail-chip">
                  <van-icon name="arrow" class="detail-arrow" />
                </span>
              </template>
              <van-button
                v-else
                size="small"
                class="add-robot-btn gold-btn"
                @click="addRobot(item)"
              >
                <van-icon name="plus" class="btn-icon" />
                <span>{{ $t('add_robot') }}</span>
              </van-button>
            </div>
          </div>
        </van-cell>
      </template>
    </van-list>
  </van-pull-refresh>
</template>

<script>
import { mapState, mapActions, mapGetters } from 'vuex'
export default {
  props: {
    platform: {
      type: String,
      required: true
    }
  },
  data () {
    return {
      finished: false,
      isLoading: false
    }
  },
  computed: {
    ...mapState({
      robotData: ({ robot }) => robot.robotList,
      logged: ({ user }) => user.logged
    }),
    ...mapGetters({
      markets: 'robot/markets'
    }),
    market () {
      return this.markets(this.platform) || []
    }
  },
  mounted() {
    this.onLoad()
  },
  methods: {
    ...mapActions({
      marketList: 'robot/marketList',
      robotList: 'robot/robotList'
    }),
    loadData () {
      if (!this.logged) {
        this.finished = true
        this.isLoading = false
        return
      }
      this.finished = false
      this.marketList({
        platform: this.platform,
        type: 'spot'
      })
      this.finished = true
      this.isLoading = false
    },
    onLoad () {
      if (!this.logged) {
        this.finished = true
        this.isLoading = false
        return
      }
      this.loadData()
      this.robotList()
    },
    findRobot (id) {
      const marketId = String(id ?? '')
      return this.robotData.find(item => String(item.market_id ?? '') === marketId)
    },
    isRobotEnabled (robot) {
      return Number(robot && robot.status) === 1
    },
    goDetail (item) {
      if (this.findRobot(item.id)) {
        this.$nextTick(() => {
          this.$router.push({
            name: 'robot',
            query: { market_id: item.id }
          })
        })
      }
    },
    addRobot (item) {
      this.$router.push({
        name: 'robot-form',
        query: {
          type: 'create',
          platform: this.platform,
          data: JSON.stringify(item)
        }
      })
    },
    getDeal (values) {
      if (values) {
        const valueJson = JSON.parse(values)
        return Number(valueJson.deal_amount).toFixed(6) || '-'
      }
      return '-'
    },

    getValue (values) {
      if (values) {
        const valueJson = JSON.parse(values)

        return valueJson
      }
      return '-'
    }
  }
}
</script>

<style scoped lang="less">
.asset-item {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  padding: 6px 0;
  .left,
  .right {
    flex-shrink: 0;
  }
  .right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    justify-content: center;
    gap: 4px;
    margin-left: auto;
    min-height: 42px;
    text-align: right;
    font-size: 12px;
    color: rgba(240, 227, 197, 0.42);
  }
  .center {
    flex-grow: 1;
    min-width: 0;
  }

  .name-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
  }

  .name {
    color: #fff1cf;
    font-size: 15px;
    font-weight: 700;
    line-height: 1.35;
  }
  .info {
    margin-top: 8px;
    font-size: 12px;
    color: rgba(240, 227, 197, 0.68);
    display: flex;
    flex-wrap: wrap;
    gap: 3px 10px;
  }
  .btn {
    height: auto;
    padding: 5px;
  }
}

.strategy-tag {
  margin: 0 !important;
  padding: 0 8px;
  height: 22px;
  border: 1px solid rgba(255, 228, 170, 0.18);
  background: rgba(246, 204, 113, 0.08) !important;
  color: #ffe7a8 !important;
  font-size: 10px;
  font-weight: 700;
}

.info-grid {
  margin-top: 8px;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 6px 12px;
}

.info-line {
  min-width: 0;
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 10px;
  padding: 0;
  border: 0;
  background: transparent;
}

.info-line-wide {
  grid-column: 1 / -1;
}

.info-label {
  color: rgba(240, 227, 197, 0.58);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.04em;
  flex-shrink: 0;
}

.info-value {
  color: #fff1cf;
  font-size: 12px;
  font-weight: 700;
  line-height: 1.35;
  word-break: break-all;
}

.profit-positive {
  color: #1ec97f;
}

.profit-negative {
  color: #ff6b6b;
}

.status-text {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  min-height: 20px;
  padding: 2px 7px;
  border: 1px solid currentColor;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  white-space: nowrap;
}

.status-dot {
  width: 5px;
  height: 5px;
  flex-shrink: 0;
  border-radius: 50%;
  background: currentColor;
  box-shadow: 0 0 7px currentColor;
}

.status-enabled {
  color: #24d38a;
  background: rgba(30, 201, 127, 0.08);
}

.status-disabled {
  color: #ff7474;
  background: rgba(255, 107, 107, 0.08);
}

.detail-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  border: 0;
  border-radius: 50%;
  background: rgba(246, 204, 113, 0.04);
  box-shadow: none;
}

.detail-arrow {
  color: rgba(240, 196, 110, 0.76);
  font-size: 11px;
}

.add-robot-btn {
  height: 42px;
  min-width: 120px;
  padding: 0 18px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.02em;
  white-space: nowrap;
  border: 1px solid rgba(255, 228, 170, 0.18);
  background:
    radial-gradient(circle at top, rgba(255, 245, 213, 0.42), transparent 45%),
    linear-gradient(180deg, rgba(255, 249, 239, 0.98) 0%, rgba(243, 230, 205, 0.94) 100%);
  color: #2a1b0d !important;
  box-shadow:
    0 12px 24px rgba(0, 0, 0, 0.16),
    inset 0 1px 0 rgba(255, 255, 255, 0.45);
  transition:
    transform 0.14s ease,
    box-shadow 0.14s ease,
    filter 0.14s ease;
}

.add-robot-btn:active {
  transform: translateY(1px) scale(0.98);
  filter: brightness(0.98);
  box-shadow:
    0 8px 16px rgba(0, 0, 0, 0.12),
    inset 0 1px 0 rgba(255, 255, 255, 0.36);
}

.btn-icon {
  margin-right: 4px;
  font-size: 14px;
  color: #2a1b0d;
}

.market-login-hint {
  padding: 22px 16px 26px;
  text-align: center;
}

.hint-title {
  color: #fff1cf;
  font-size: 15px;
  font-weight: 700;
}

.hint-desc {
  margin-top: 8px;
  color: rgba(240, 227, 197, 0.68);
  line-height: 1.5;
}

.login-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-top: 16px;
  height: 38px;
  padding: 0 18px;
  border-radius: 999px;
  border: 1px solid rgba(255, 228, 170, 0.18);
  background:
    linear-gradient(180deg, rgba(246, 204, 113, 0.18) 0%, rgba(141, 92, 31, 0.92) 100%);
  color: #fff1cf;
  font-weight: 700;
}

:deep(.van-cell) {
  margin: 0;
  border-top: 1px solid rgba(217, 176, 92, 0.12);
  border-left: 0;
  border-right: 0;
  border-bottom: 0;
  border-radius: 0;
  padding: 14px 18px;
  background:
    radial-gradient(circle at top right, rgba(248, 215, 144, 0.14), transparent 30%),
    linear-gradient(180deg, rgba(37, 25, 12, 0.98), rgba(22, 14, 7, 0.98));
  box-shadow: none;
  transition:
    transform 0.14s ease,
    background 0.14s ease;
}

:deep(.van-cell:active) {
  transform: scale(0.997);
  background:
    radial-gradient(circle at top right, rgba(248, 215, 144, 0.18), transparent 30%),
    linear-gradient(180deg, rgba(42, 29, 14, 0.99), rgba(22, 14, 7, 0.99));
}

:deep(.van-cell::after) {
  display: none;
}

:deep(.van-empty) {
  padding: 32px 0 36px;
}

@media (max-width: 767px) {
  .asset-item {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 9px 10px;
  }

  .asset-item .center {
    display: contents;
  }

  .asset-item .name-row {
    grid-column: 1;
    grid-row: 1;
    min-width: 0;
  }

  .asset-item .name {
    font-size: 14px;
  }

  .asset-item .right {
	grid-column: 2;
	grid-row: 1;
	flex-direction: row;
	align-items: center;
    min-height: 0;
	gap: 6px;
  }

  .asset-item .info {
    margin-top: 6px;
  }

  .asset-item .info-grid {
	grid-column: 1 / -1;
	grid-row: 2;
	margin-top: 0;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 9px 12px;
	padding-top: 9px;
	border-top: 1px solid rgba(217, 176, 92, 0.1);
  }

  .asset-item .info-line {
	display: flex;
	flex-direction: column;
	align-items: flex-start;
	gap: 3px;
  }

  .asset-item .info-line-wide {
	grid-column: auto;
  }

  .asset-item .info-label {
	max-width: 100%;
	font-size: 9px;
	line-height: 1.25;
	white-space: normal;
  }

  .asset-item .info-value {
	font-size: 12px;
	line-height: 1.25;
	word-break: normal;
  }

  .status-text {
	padding: 2px 6px;
	font-size: 10px;
  }

  .detail-chip {
	width: 24px;
	height: 24px;
  }

  .add-robot-btn {
    height: 30px;
    min-width: 96px;
    padding: 0 10px;
    font-size: 11px;
  }

  :deep(.van-cell) {
	padding: 13px 14px;
  }
}
</style>
