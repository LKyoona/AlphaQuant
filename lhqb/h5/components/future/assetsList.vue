<template>
  <van-pull-refresh
    v-model="isLoading"
    @refresh="onLoad"
  >
    <van-list
      :finished="finished"
      @load="onLoad"
    >
      <van-empty
        v-if="market.length === 0 && finished"
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
              <div class="name">
                {{ item.market_name }}
                <van-tag v-if="findRobot(item.id) && findRobot(item.id).strategy==1"
                         plain
                         type="success"
                >
                  做多
                </van-tag>
                <van-tag v-if="findRobot(item.id) && findRobot(item.id).strategy==2"
                         plain
                         type="danger"
                >
                  做空
                </van-tag>
                    <van-tag v-if="recommendPair(item.market_name)"
                         plain
                         type="primary"
                >
                  推荐
                </van-tag>
              </div>
            </div>
            <div class="right">
              <template v-if="findRobot(item.id)">
                <span v-if="findRobot(item.id) && findRobot(item.id).status === 0" style="color:red">{{ $t('status.disabled') }}</span>
                <span v-if="findRobot(item.id) && findRobot(item.id).status === 1" style="color:green">{{ $t('status.enabled') }}</span>
                <van-icon name="arrow" />
              </template>
              <van-button
                v-else
                size="small"
                style="border: none"
                @click="addRobot(item)"
              >
                <span style="display: inline-block; vertical-align: middle">{{ $t('add_robot') }}</span>
                <van-icon
                  style="vertical-align: middle"
                  name="plus"
                />
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
  mounted() {
    this.onLoad()
  },
  computed: {
    ...mapState({
      recommendList: ({ robotfuture }) => robotfuture.recommendList,
      futureList: ({ robotfuture }) => robotfuture.futureList,
      robotData: ({ robotfuture }) => robotfuture.robotList
    }),
    ...mapGetters({
      markets: 'robot/markets'
    }),
    market () {
      const future_markets = this.markets(this.platform).filter(item => this.futureList.includes(item.market_name))
      const sorted_markets = []
      const other_markets = []
      Object.keys(future_markets).forEach((key) => {
        const value = future_markets[key]
        if (this.findRobot(value.id)) {
          if (this.findRobotStatus(value.id) === 1) {
            sorted_markets.unshift(value)
          } else {
            sorted_markets.push(value)
          }
        } else {
          other_markets.push(value)
        }
      })
      const all_markets = sorted_markets.concat(other_markets)
      return all_markets || []
    }
  },
  methods: {
    ...mapActions({
      marketList: 'robotfuture/marketList',
      robotList: 'robotfuture/robotList'
    }),
    loadData () {
      this.finished = false
      this.marketList({
        platform: this.platform,
        type: 'future'
      })
      this.finished = true
      this.isLoading = false
    },
    onLoad () {
      this.loadData()
      this.robotList()
    },
    recommendPair(pair){
      return this.recommendList.includes(pair)
    },
    findRobot (id) {
      return this.robotData.filter(item => item.market_id === id)[0]
    },
    findRobotStatus (id) {
      return this.robotData.filter(item => item.market_id === id)[0].status
    },
    goDetail (item) {
      if (this.findRobot(item.id)) {
        this.$nextTick(() => {
          this.$router.push({
            name: 'robotFuture',
            query: { market_id: item.id }
          })
        })
      }
    },
    addRobot (item) {
      this.$router.push({
        name: 'robotFuture-form',
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
  align-items: center;
  .left,
  .right {
    flex-shrink: 0;
  }
  .right {
    text-align: right;
    font-size: 12px;
    color: #888888;
  }
  .center {
    flex-grow: 1;
    min-width: 0;
  }

  .name {
    color: #333333;
    font-size: 16px;
    font-weight: 500;
  }
  .info {
    font-size: 12px;
    color: #999;
  }
  .btn {
    height: auto;
    padding: 5px;
  }
}
</style>
