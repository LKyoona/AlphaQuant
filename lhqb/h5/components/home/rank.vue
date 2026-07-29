<template>
  <section class="ticker-board" :class="{ 'is-refreshing': refreshing }">
    <div class="ticker-status">
      <span class="live-indicator">
        <i></i>
        {{ $t('homeTicker.live') }}
      </span>
      <span class="update-time">
        {{ refreshing ? $t('homeTicker.updating') : `${$t('homeTicker.updated')} ${lastUpdatedText}` }}
      </span>
    </div>

    <div v-if="initialLoading" class="ticker-skeleton">
      <div v-for="item in 5" :key="item" class="ticker-skeleton-card"></div>
    </div>

    <div v-else-if="items.length" class="ticker-grid">
      <article v-for="item in items" :key="item.id || `${item.coin}-${item.currency}`" class="ticker-card">
        <div class="ticker-head">
          <span class="coin-mark">{{ coinInitial(item.coin) }}</span>
          <div class="pair-name">
            <strong>{{ item.coin }}</strong>
            <small>/{{ item.currency }}</small>
          </div>
        </div>

        <div class="ticker-main">
          <div class="price" :class="item.flash ? `flash-${item.flash}` : ''">
            {{ formatPrice(item.price) }}
          </div>
          <svg class="sparkline" viewBox="0 0 80 26" preserveAspectRatio="none" aria-hidden="true">
            <path
              :d="sparkPath(item)"
              fill="none"
              :class="changeValue(item) < 0 ? 'spark-negative' : 'spark-positive'"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
        </div>

        <div class="ticker-foot">
          <span>{{ $t('homeTicker.change24h') }}</span>
          <strong :class="changeValue(item) < 0 ? 'negative' : 'positive'">
            {{ formatChange(item.change) }}
          </strong>
        </div>
      </article>
    </div>

    <div v-else class="ticker-empty">{{ $t('homeTicker.empty') }}</div>
  </section>
</template>

<script>
import { mapState, mapActions } from 'vuex'

const POLL_INTERVAL = 6000

export default {
  data () {
    return {
      items: [],
      refreshing: false,
      initialLoading: true,
      lastUpdatedAt: 0,
      refreshTimer: null,
      flashTimer: null,
      destroyed: false
    }
  },
  computed: {
    ...mapState({
      currency: ({ currency }) => currency,
      tickerList: ({ ticker }) => ticker.list
    }),
    lastUpdatedText () {
      if (!this.lastUpdatedAt) {
        return '--:--:--'
      }
      return new Date(this.lastUpdatedAt).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
      })
    }
  },
  watch: {
    tickerList: {
      immediate: true,
      handler (values) {
        this.applyTickerData(values)
      }
    },
    'currency.name' () {
      this.loadData()
    }
  },
  mounted () {
    document.addEventListener('visibilitychange', this.handleVisibilityChange)
    this.loadData()
  },
  beforeUnmount () {
    this.destroyed = true
    document.removeEventListener('visibilitychange', this.handleVisibilityChange)
    window.clearTimeout(this.refreshTimer)
    window.clearTimeout(this.flashTimer)
  },
  methods: {
    ...mapActions({
      getTickerList: 'ticker/getTickerList'
    }),
    async loadData () {
      if (this.refreshing || this.destroyed || (typeof document !== 'undefined' && document.hidden)) {
        return
      }
      window.clearTimeout(this.refreshTimer)
      this.refreshing = true
      try {
        await this.getTickerList({
          currency: this.currency.name,
          order: 'price',
          order_type: 'desc'
        })
        this.lastUpdatedAt = Date.now()
      } catch (error) {
        // Keep the last successful market snapshot visible during transient failures.
      } finally {
        this.refreshing = false
        this.initialLoading = false
        if (!this.destroyed) {
          this.refreshTimer = window.setTimeout(() => this.loadData(), POLL_INTERVAL)
        }
      }
    },
    applyTickerData (values) {
      const nextValues = Array.isArray(values) ? values.slice(0, 5) : []
      const previous = new Map(this.items.map(item => [String(item.id || `${item.coin}-${item.currency}`), item]))
      this.items = nextValues.map((item) => {
        const key = String(item.id || `${item.coin}-${item.currency}`)
        const oldItem = previous.get(key)
        const oldPrice = oldItem ? Number(oldItem.price) : Number(item.price)
        const nextPrice = Number(item.price)
        return {
          ...item,
          flash: nextPrice > oldPrice ? 'up' : nextPrice < oldPrice ? 'down' : ''
        }
      })
      window.clearTimeout(this.flashTimer)
      this.flashTimer = window.setTimeout(() => {
        this.items = this.items.map(item => ({ ...item, flash: '' }))
      }, 850)
    },
    handleVisibilityChange () {
      if (!document.hidden) {
        this.loadData()
      } else {
        window.clearTimeout(this.refreshTimer)
      }
    },
    coinInitial (coin) {
      return String(coin || '?').slice(0, 1).toUpperCase()
    },
    changeValue (item) {
      return Number(item && item.change) || 0
    },
    formatPrice (value) {
      const price = Number(value)
      if (!Number.isFinite(price)) {
        return '--'
      }
      const digits = Math.abs(price) >= 1 ? 2 : 6
      return price.toLocaleString('en-US', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits
      })
    },
    formatChange (value) {
      const change = Number(value) || 0
      return `${change > 0 ? '+' : ''}${change.toFixed(2)}%`
    },
    sparkPath (item) {
      const magnitude = Math.min(Math.abs(this.changeValue(item)), 10)
      if (this.changeValue(item) < 0) {
        return `M1 5 L14 ${8 + magnitude / 3} L27 7 L40 15 L54 13 L66 20 L79 ${21 + magnitude / 4}`
      }
      return `M1 ${21 + magnitude / 4} L14 18 L27 20 L40 11 L54 14 L66 7 L79 ${4 + magnitude / 5}`
    }
  }
}
</script>

<style scoped lang="less">
@import './home-theme.less';

.ticker-board {
  position: relative;
  min-width: 0;
}

.ticker-status {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 9px 12px 8px;
  border-bottom: 1px solid rgba(217, 176, 92, .1);
  color: @home-text-muted;
  font-size: 10px;
}

.live-indicator {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #e8bf6d;
  font-weight: 800;
  letter-spacing: .08em;
}

.live-indicator i {
  position: relative;
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #41d995;
  box-shadow: 0 0 8px rgba(65, 217, 149, .68);
}

.live-indicator i::after {
  content: '';
  position: absolute;
  inset: -4px;
  border: 1px solid rgba(65, 217, 149, .42);
  border-radius: 50%;
  animation: live-pulse 1.8s ease-out infinite;
}

.update-time {
  white-space: nowrap;
}

.ticker-grid,
.ticker-skeleton {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
}

.ticker-card {
  position: relative;
  min-width: 0;
  padding: 12px 12px 11px;
  overflow: hidden;
  background:
    radial-gradient(circle at 84% 12%, rgba(239, 192, 99, .09), transparent 34%),
    linear-gradient(180deg, rgba(31, 22, 11, .72), rgba(14, 9, 4, .78));
}

.ticker-card + .ticker-card {
  border-left: 1px solid rgba(217, 176, 92, .13);
}

.ticker-head {
  display: flex;
  align-items: center;
  gap: 7px;
  min-width: 0;
}

.coin-mark {
  display: grid;
  flex: 0 0 25px;
  place-items: center;
  width: 25px;
  height: 25px;
  border: 1px solid rgba(244, 205, 126, .32);
  border-radius: 50%;
  background: linear-gradient(145deg, rgba(235, 190, 101, .24), rgba(78, 48, 13, .72));
  color: #f6cf7c;
  font-size: 11px;
  font-weight: 900;
  box-shadow: inset 0 1px 0 rgba(255, 248, 218, .18), 0 5px 12px rgba(0, 0, 0, .24);
}

.pair-name {
  display: flex;
  align-items: baseline;
  min-width: 0;
  white-space: nowrap;
}

.pair-name strong {
  color: #fff1cf;
  font-size: 12px;
  font-weight: 800;
}

.pair-name small {
  color: rgba(240, 227, 197, .48);
  font-size: 9px;
  font-weight: 700;
}

.ticker-main {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 6px;
  margin-top: 10px;
}

.price {
  min-width: 0;
  color: #fff2d2;
  font-size: clamp(14px, 1.15vw, 18px);
  font-variant-numeric: tabular-nums;
  font-weight: 800;
  line-height: 1;
  letter-spacing: -.02em;
}

.flash-up {
  animation: flash-up .8s ease;
}

.flash-down {
  animation: flash-down .8s ease;
}

.sparkline {
  width: 48px;
  height: 22px;
  opacity: .82;
}

.sparkline path {
  stroke-width: 2;
  vector-effect: non-scaling-stroke;
}

.spark-positive {
  stroke: #42d99a;
  filter: drop-shadow(0 0 3px rgba(66, 217, 154, .35));
}

.spark-negative {
  stroke: #ff6f67;
  filter: drop-shadow(0 0 3px rgba(255, 111, 103, .3));
}

.ticker-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 6px;
  margin-top: 9px;
  font-size: 9px;
}

.ticker-foot span {
  color: rgba(240, 227, 197, .42);
}

.ticker-foot strong {
  padding: 2px 5px;
  border-radius: 5px;
  font-size: 10px;
  font-variant-numeric: tabular-nums;
}

.positive {
  color: #46dfa0;
  background: rgba(38, 180, 121, .1);
}

.negative {
  color: #ff7a72;
  background: rgba(219, 72, 66, .1);
}

.ticker-skeleton-card {
  height: 110px;
  background: linear-gradient(100deg, rgba(255, 239, 201, .03) 20%, rgba(255, 239, 201, .09) 42%, rgba(255, 239, 201, .03) 64%);
  background-size: 220% 100%;
  animation: ticker-shimmer 1.4s linear infinite;
}

.ticker-skeleton-card + .ticker-skeleton-card {
  border-left: 1px solid rgba(217, 176, 92, .1);
}

.ticker-empty {
  display: grid;
  min-height: 105px;
  place-items: center;
  color: @home-text-muted;
  font-size: 12px;
}

.is-refreshing .live-indicator i {
  background: #f0c46e;
  box-shadow: 0 0 10px rgba(240, 196, 110, .72);
}

@keyframes live-pulse {
  from { opacity: .8; transform: scale(.45); }
  to { opacity: 0; transform: scale(1.45); }
}

@keyframes ticker-shimmer {
  to { background-position: -220% 0; }
}

@keyframes flash-up {
  0%, 100% { color: #fff2d2; text-shadow: none; }
  35% { color: #53efad; text-shadow: 0 0 12px rgba(70, 223, 160, .5); }
}

@keyframes flash-down {
  0%, 100% { color: #fff2d2; text-shadow: none; }
  35% { color: #ff827a; text-shadow: 0 0 12px rgba(255, 111, 103, .45); }
}

@media (max-width: 767px) {
  .ticker-status {
    padding: 9px 14px;
  }

  .ticker-grid,
  .ticker-skeleton {
    grid-template-columns: minmax(0, 1fr);
  }

  .ticker-card {
	display: grid;
	grid-template-columns: minmax(86px, .9fr) minmax(104px, 1.15fr) minmax(58px, .55fr);
	align-items: center;
	gap: 8px;
	padding: 11px 14px;
	background:
	  radial-gradient(circle at 92% 50%, rgba(239, 192, 99, .08), transparent 32%),
	  linear-gradient(90deg, rgba(31, 22, 11, .72), rgba(14, 9, 4, .78));
  }

  .ticker-card + .ticker-card {
	border-top: 1px solid rgba(217, 176, 92, .13);
	border-left: 0;
  }

  .ticker-main,
  .ticker-foot {
	margin-top: 0;
  }

  .ticker-main {
	align-items: center;
  }

  .ticker-foot {
	align-items: flex-end;
	flex-direction: column;
	gap: 3px;
  }

  .sparkline {
	width: 34px;
  }
}

@media (max-width: 420px) {
  .ticker-card {
	grid-template-columns: minmax(78px, .9fr) minmax(92px, 1.1fr) minmax(54px, .55fr);
	padding-inline: 10px;
  }

  .coin-mark {
    flex-basis: 22px;
    width: 22px;
    height: 22px;
  }

  .pair-name strong {
    font-size: 11px;
  }

  .price {
    font-size: 13px;
  }

  .sparkline {
	width: 28px;
  }
}

@media (max-width: 340px) {
  .sparkline {
	display: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  .live-indicator i::after,
  .ticker-skeleton-card,
  .flash-up,
  .flash-down {
    animation: none;
  }
}
</style>
