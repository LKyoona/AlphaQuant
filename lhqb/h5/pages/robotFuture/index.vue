<template>
  <div>
    <van-nav-bar
      :border="false"
      :title="robot.market_name || $t('pageRobot.title')"
      right-text=""
      left-arrow
      @click-left="$router.back()"
      @click-right="$router.push('/robot/order?id=' + robot.id)"
    />
    <div
      v-if="hasRobot"
      class="top"
    >
      <h3 class="title">
        {{ robot.market_name }}
        <van-tag
          v-if="robot.recycle_status == 1"
          round
          color="#ff8447"
          type="primary"
        >
          {{ $t('cycle_strategy') }}
        </van-tag>
        <van-tag
          v-if="robot.recycle_status == 0"
          round
          color="#ff8447"
          type="primary"
        >
          {{ $t('single_strategy') }}
        </van-tag>
      </h3>
    </div>
    <div
      v-else
      class="empty-state"
    >
      <div class="loading-spinner"></div>
    </div>
    <div class="title-block">{{ $t('pageRobot.strategic_operations') }}</div>
    <van-grid
      column-num="2"
      :border="false"
    >
      <van-grid-item
        v-if="robot.status === 0"
        @click="onEnable"
      >
        <van-image
          width="50"
          :src="icons.icon1"
        />{{ $t('pageRobot.start') }}
      </van-grid-item>
      <van-grid-item
        v-else
        @click="onDisable"
      >
        <van-image
          width="50"
          :src="icons.icon3"
        />{{ $t('pageRobot.pause') }}
      </van-grid-item>
      <van-grid-item @click="goEdit">
        <van-image
          width="50"
          :src="icons.icon2"
        />{{ $t('pageRobot.trade_setup') }}
      </van-grid-item>
    </van-grid>
    <div class="tips">
      操作变更后将在两分钟内生效，请勿重复操作并耐心等待
    </div>
  </div>
</template>

<script>
import { Grid, GridItem } from 'vant'
import { mapGetters, mapActions } from 'vuex'
import iconStart from '@/assets/images/jiaoyi6.png'
import iconEdit from '@/assets/images/jiaoyi3.png'
import iconPause from '@/assets/images/jiaoyi5.png'
export default {
  components: {
    [Grid.name]: Grid,
    [GridItem.name]: GridItem
  },
  data () {
    return {
      market_id: '',
      robot: {},
      icons: {
        icon1: iconStart,
        icon2: iconEdit,
        icon3: iconPause
      },
      showPopover: false,
      account: {},
      last: ''
    }
  },
  computed: {
    ...mapGetters({
      robotFind: 'robotfuture/robot'
    }),
    hasRobot () {
      return Boolean(this.robot && this.robot.id)
    }
  },
  created () {
    this.market_id = JSON.parse(this.$route.query.market_id)
    this.robotList()
      .then(() => {
        this.robot = this.robotFind(this.market_id) || {}
      })
  },
  methods: {
    ...mapActions({
      robotList: 'robotfuture/robotList',
      robotEnable: 'robotfuture/robotEnable',
      robotDisable: 'robotfuture/robotDisable',
      robotClean: 'robotfuture/robotClean'
    }),
    goEdit () {
      this.$dialog
        .confirm({
          message: this.$t('pageRobot.dialog_edit') + '？'
        })
        .then(() => {
          this.$router.push({
            name: 'robotFuture-form',
            query: {
              type: 'edit',
              robot_id: this.robot.id
            }
          })
        })
    },
    onEnable () {
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
      this.$dialog
        .confirm({
          message: this.$t('pageRobot.dialog_future_pause') + '？'
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
  }
}
</script>

<style scoped lang="less">
:deep(.van-popover__action){
  width: 134px;
}
:deep(.van-popover__action){
  width: 134px;
}

.top,
.block1,
.new-msg {
  border: 1px solid rgba(217, 176, 92, 0.16);
  border-radius: 16px;
}

.top {
  padding: 15px;
  background:
    radial-gradient(circle at top right, rgba(248, 215, 144, 0.14), transparent 30%),
    linear-gradient(180deg, rgba(37, 25, 12, 0.98), rgba(22, 14, 7, 0.98));
  .title {
    color: #fff1cf;
    font-size: 1.5em;
    margin-bottom: 15px;
    .van-tag {
      font-weight: normal;
    }
  }
  .van-col {
    padding: 10px 10px 10px 0;
    color: #f0e3c5;
  }
  .label {
    opacity: 0.8;
    margin-bottom: 5px;
  }
  .value {
    font-size: 16px;
  }
}
.tips {
  padding: 10px 15px;
  font-size: 12px;
  text-align: center;
}
.empty-state {
  min-height: 40vh;
  display: flex;
  align-items: center;
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
.title-block {
  display: flex;
  align-items: center;
  padding: 10px 15px;
  font-weight: 500;
  font-size: 1em;
  &::before {
    content: '';
    width: 0.25em;
    height: 1em;
    margin-right: 10px;
    background-color: @themeColor;
  }
}
.block1 {
  background:
    linear-gradient(180deg, rgba(28, 20, 10, 0.96) 0%, rgba(18, 12, 6, 0.98) 100%);
  padding: 10px 15px;
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
  background:
    linear-gradient(180deg, rgba(28, 20, 10, 0.96) 0%, rgba(18, 12, 6, 0.98) 100%);
  padding: 10px 15px;
  color: #f0e3c5;
}
.link {
  float: right;
  margin-top: -39px;
  line-height: 39px;
  padding: 0 15px;
  font-size: 12px;
  display: flex;
  align-items: center;
  color: #f0c46e;
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
