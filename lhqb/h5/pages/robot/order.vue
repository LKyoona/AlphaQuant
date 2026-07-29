<template>
  <div>
    <van-nav-bar
      :border="false"
      :title="$t('pageRobot.order')"
      left-arrow
      @click-left="$router.back()"
    />
    <van-pull-refresh
      v-model="refreshing"
      @refresh="onRefresh"
    >
      <van-list
        v-model="loading"
        :finished="finished"
        :finished-text="$t('finished_text')"
        @load="onLoad"
      >
        <van-cell
          v-for="item in orderList"
          :key="item.id"
          class="order-cell"
        >
          <div class="robot-item">
            <div class="hd">
              <div class="name">{{ item.stock }} / {{ item.money }}</div>
              <div
                v-if="item.side == 1"
                class="status status-sell"
              >{{ $t('sell_out') }}</div>
              <div
                v-else
                class="status status-buy"
              >{{ $t('buy_in') }}</div>
            </div>
            <div class="info">
              <div>
                {{ $t('turnover') }}<span>{{ $filters.numberFormat(Number(item.deal_money), 8) }} {{ item.money }}</span>
              </div>
              <div>
                {{ $t('number_deals') }}<span>{{ $filters.numberFormat(Number(item.deal_amount), 8) }} {{ item.stock }}</span>
              </div>
              <div>
                {{ $t('price') }}<span>{{ $filters.numberFormat(Number(item.price), 8) }} {{ item.money }}</span>
              </div>
              <div>
                {{ $t('closing_time') }}<span>{{ item.ctime }}</span>
              </div>
            </div>
          </div>
        </van-cell>
      </van-list>
      <van-empty
        v-if="orderList.length === 0"
        :description="$t('empty.order')"
      />
    </van-pull-refresh>
  </div>
</template>

<script>
import { mapActions } from 'vuex'
export default {
  data () {
    return {
      robot_id: this.$route.query.id,
      loading: false,
      finished: false,
      refreshing: false,
      orderList: [],
      offset: 0,
      limit: 20
    }
  },
  methods: {
    ...mapActions({
      robotOrder: 'robot/robotOrder'
    }),
    loadList () {
      if (this.refreshing) {
        this.orderList = []
        this.offset = 0
        this.finished = false
        if (this.loading) {
          this.loading = false
          return
        }
      }
      if (this.loading) {
        this.refreshing = false
      }
      const payload = {
        robot_id: this.robot_id,
        limit_begin: this.offset,
        limit_end: this.limit
      }
      this.robotOrder(payload)
        .then(({ data }) => {
          const list = data.data
          if (list.length < this.limit) {
            this.finished = true
          } else {
            this.offset += this.limit
          }
          this.orderList = this.orderList.concat(list)
        })
        .finally(() => {
          this.loading = false
          this.refreshing = false
        })
    },
    onLoad () {
      this.loadList()
    },
    onRefresh () {
      this.loadList()
    }
  }
}
</script>
<style lang="less" scoped>
:deep(.van-cell){
  margin: 10px 12px;
  padding: 0;
  border: 1px solid rgba(217, 176, 92, 0.14);
  border-radius: 14px;
  background: linear-gradient(180deg, rgba(28, 20, 10, 0.96) 0%, rgba(18, 12, 6, 0.98) 100%);
  overflow: hidden;
}
.robot-item {
  overflow: hidden;
  border-radius: 14px;
}
.robot-item .hd {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 14px 8px;
}
.robot-item .name {
  font-weight: 700;
  color: #fff1cf;
}
.robot-item .status {
  font-size: 12px;
  font-weight: 700;
}
.status-sell {
  color: #ff8b6b;
}
.status-buy {
  color: #86d69f;
}
.robot-item .info {
  padding: 10px 14px 14px;
  background: linear-gradient(180deg, rgba(37, 25, 12, 0.92), rgba(22, 14, 7, 0.96));
  line-height: 1.8;
  font-size: 12px;
  color: rgba(240, 227, 197, 0.72);
}
.robot-item .info span {
  float: right;
  background-color: transparent;
}
.robot-item div {
  background-color: transparent;
  font: inherit;
  color: inherit;
}

@media (max-width: 767px) {
  :deep(.van-cell){
    margin: 8px 10px;
  }

  .robot-item .hd {
    padding: 12px 12px 6px;
  }

  .robot-item .info {
    padding: 10px 12px 12px;
  }
}
</style>
