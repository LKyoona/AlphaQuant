<template>
  <div>
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
        >
          <div class="robot-item">
            <div class="hd">
              <div class="name">{{ item.stock }} / {{ item.money }}</div>
              <div
                v-if="item.side == 1"
                class="status"
                style="color: red; opacity: 0.8"
              >
                {{ $t('sell_out') }}
              </div>
              <div
                v-else
                class="status"
                style="color: green"
              >
                {{ $t('buy_in') }}
              </div>
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
    loadRobotOrder () {
      if (this.refreshing) {
        this.offset = 0
        this.orderList = []
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
        limit_begin: this.offset,
        limit_end: this.limit
      }
      this.robotOrder(payload)
        .then(({ data }) => {
          const list = data.data
          if (list.length < this.limit) {
            this.finished = true
          } else {
            this.finished = false
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
      this.loadRobotOrder()
    },
    onRefresh () {
      this.loadRobotOrder()
    }
  }
}
</script>
<style lang="less" scoped>
:deep(.van-cell){
  margin: 10px 0;
}
.robot-item {
  overflow: hidden;
}
.robot-item .hd {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 10px 5px;
}
.robot-item .name {
  font-weight: 500;
}
.robot-item .status {
  font-size: 12px;
}
.robot-item .info {
  padding: 10px;
  background-color: rgba(0, 0, 0, 0.03);
  line-height: 1.8;
  font-size: 12px;
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
</style>
