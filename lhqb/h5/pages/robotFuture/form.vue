<template>
  <div>
    <van-nav-bar
      :border="false"
      :title="$t('pageRobot.robot_setup')"
      left-arrow
      @click-left="$router.back()"
    />
    <van-form label-width="32%">
      <van-field
        :value="market"
        readonly
        clickable
        :label="$t('pageRobot.trade_area_future')"
        :placeholder="$t('pageRobot.trade_area')"
        :rules="[{ required: true }]"
        style="background:#F5F6F7;color:#333;font-weight: 700;"
        @click="onMarket"
      />
      <h4 class="title"><a href="#" onclick="window.open('https://docs.qq.com/doc/DT0JwT3RodG9PdWtB', '_blank'); window.location.href=window.location.href">参数示例</a></h4>
      <van-row class="preset">
        <van-col>
          <van-button
            block
            size="small"
            :type="preset === 1 ? 'primary' : 'default'"
            @click="changePreset(1)"
          >
            稳健
          </van-button>
        </van-col>
        <van-col>
          <!-- <van-button
            block
            size="small"
            :type="preset === 2 ? 'primary' : 'default'"
            @click="changePreset(2)"
          >
            自定义
          </van-button> -->
        </van-col>
      </van-row>

      <van-field name="radio" label="方向">
        <template #input>
          <van-radio-group v-model="strategy" direction="horizontal">
            <van-radio :name="1">做多</van-radio>
            <van-radio :name="2">做空</van-radio>
          </van-radio-group>
        </template>
      </van-field>

      <van-field name="radio" label="杠杆倍数" right-icon="question-o" @click-right-icon="ganggan">
        <template #input>
          <van-radio-group v-model="leverageType" direction="horizontal">
            <van-radio :name="5" class="rad">5</van-radio>
            <van-radio :name="10" class="rad">10</van-radio>
            <van-radio :name="20" class="rad">20</van-radio>
          </van-radio-group>
        </template>
      </van-field>

      <van-field
        v-model="first_order_value"
        :label="$t('first_order_amount') + '(' + money + ')'"
        :placeholder="$t('first_order_amount')"
        :rules="[{ required: true }]"
        :label-width="130"
      />
      <van-field
        v-model="order_beishu"
        :label="$t('safety_volume_scale') "
        :placeholder="$t('safety_volume_scale_info')"
        :rules="[{ required: true }]"
        :label-width="130"
        right-icon="question-o"
        @click-right-icon="readme1"
      />

      <van-field
        v-model="max_order_count"
        :label="$t('number_of_safety_orders')"
        :placeholder="$t('number_of_safety_orders')"
        :rules="[{ required: true }]"
        :label-width="130"
      />

      <van-field
        v-model="deviation_of_orders"
        :label="$t('deviation_of_orders')"
        :placeholder="$t('deviation_of_orders_info')"
        :rules="[{ required: true }]"
        :label-width="130"
        right-icon="question-o"
        @click-right-icon="readme2"
      />
      <van-field
        v-model="deviation_of_orders_scale"
        :label="$t('deviation_of_orders_scale')"
        :placeholder="$t('deviation_of_orders_scale_info')"
        :rules="[{ required: true }]"
        :label-width="130"
        right-icon="question-o"
        @click-right-icon="readme3"
      />

      <div v-if="formType === 'create'" class="tips">
        {{ $t('pageRobotFutureForm.tip') }}
      </div>
      <div style="padding: 16px;">
        <van-button round block type="info" @click="onSubmit">
          确定
        </van-button>
      </div>
    </van-form>
    <van-popup v-model:show="marketPicker" position="bottom">
      <van-picker
        show-toolbar
        :columns="marketLists"
        value-key="market_name"
        @confirm="onConfirm"
        @cancel="marketPicker = false"
      />
    </van-popup>
  </div>
</template>

<script>
import { mapState, mapGetters, mapActions } from 'vuex'
export default {
  data() {
    return {
      strategy: 1,
      leverageType: 20,
      preset: 1,
      formType: 'create',
      marketPicker: false,
      market: 'BNB',
      money: '',
      deviation_of_orders_scale: '1.15',
      deviation_of_orders: '1',
      order_beishu: '1.1',
      platform: 'binance',
      robot_id: '',
      market_id: '',
      first_order_value: '20',
      max_order_count: 10,
      stop_profit_rate: '',
      stop_profit_callback_rate: '',
      cover_rate: '',
      cover_callback_rate: '',
      show: false,
      listInput: []
    }
  },
  computed: {
    ...mapState({
      initInfo: index => index.initInfo,
      robotData: ({ robotfuture }) => robotfuture.robotList,
      thirdLoginEnabled: ({ thirdLoginEnabled }) => thirdLoginEnabled
    }),
    ...mapGetters({
      markets: 'robotfuture/markets'
    }),
    marketLists() {
      return this.markets(this.platform) || []
    }
  },

  created() {
    this.formType = this.$route.query.type
    if (this.formType === 'edit') {
      const robot = (this.robot = this.robotData.find(
        item => this.$route.query.robot_id === item.id
      ))
      // console.log(1,robot)
      this.$nextTick(() => {
        this.platform = robot.platform
        this.leverageType = robot.leverageType
        this.market = robot.market_name
        this.market_id = robot.market_id
        this.robot_id = robot.id
        this.first_order_value = robot.first_order_value
        this.order_beishu = robot.order_beishu
        this.max_order_count = robot.max_order_count
        this.deviation_of_orders = robot.deviation_of_orders
        this.deviation_of_orders_scale = robot.deviation_of_orders_scale
        this.money = robot.money
        this.strategy = robot.strategy
        this.leverageType = robot.leverage_type
      })
    } else {
      const markets = JSON.parse(this.$route.query.data)
      this.platform = this.$route.query.platform
      this.market = markets.market_name
      this.market_id = markets.id
      this.money = markets.money
    }

    this.marketList({
      platform: this.platform,
      type: 'future'
    })
  },
  methods: {
    readme() {
      const url = 'https://docs.qq.com/doc/DT0JwT3RodG9PdWtB'
      window.open(url)
    },
    readme1() {
      this.$dialog
        .alert({
          message: '默认1,即等额补单\n比如首单额度20U,补仓倍数1.5,补单额度即\n20*1.5=30U\n30*1.5=45U\n45*1.5=67.5U \n以此类推'
        })
        .then()
    },
    readme2() {
      this.$dialog
        .alert({
          message: '补仓价格间距，默认1，即1%\n比如首单开仓币价为100,补仓间距1%,补单价格即\n100*99%=99\n99*99%=98.01\n 98.01*99%=97.02\n以此类推'
        })
        .then()
    },
    readme3() {
      this.$dialog
        .alert({
          message: '补仓间距，默认1，即1%\n比如补仓间距是1.5%,幅度系数是2,那么补单百分比即\n1.5*2°=1.5\n 1.5+1.5*2¹=4.5\n 4.5+1.5*2²=10.5 \n 10.5+1.5*2³=22.5 \n 22.5+1.5*2⁴=46.5\n即累计前面跌幅，补仓间距指数增长\n以此类推'
        })
        .then()
    },
    ganggan() {
      this.$dialog
        .alert({
          message: '目前最高仅支持20倍，币安规则\n开通合约60天以后才能使用更高倍杠杆'
        })
        .then()
    },
    ...mapActions({
      marketList: 'robotfuture/marketList',
      robotCreate: 'robotfuture/robotCreate',
      robotEdit: 'robotfuture/robotEdit'
    }),
    onMarket() {
      if (this.formType === 'create') {
        this.marketPicker = true
      }
    },
    onSubmit() {
      const check = /^\d+(\.\d+)?$/
      if (this.first_order_value == '' || !check.test(this.first_order_value)) {
        this.$toast('首单额度不能为空或参数非法')
        return
      }
      if (this.market == 'BTC/USDT' && this.first_order_value < 50) {
        this.$toast('创建BTC交易对 首单金额 必须 >= 50U')
        return
      }
      if (this.max_order_count == '' || !check.test(this.max_order_count)) {
        this.$toast('补仓次数不能为空或参数非法')
        return
      }
      if (this.order_beishu == '' || !check.test(this.order_beishu)) {
        this.$toast('补仓倍数不能为空或参数非法')
        return
      }
      if (this.deviation_of_orders == '' || !check.test(this.deviation_of_orders)) {
        this.$toast('补仓价格间距不能为空或参数非法')
        return
      }
      if (this.deviation_of_orders_scale == '' || !check.test(this.deviation_of_orders_scale)) {
        this.$toast('幅度系数不能为空或参数非法')
        return
      }
      const payload = {
        platform: this.platform,
        market_id: this.market_id,
        pair: this.market,
        strategy: this.strategy,
        first_order_value: this.first_order_value,
        order_beishu: this.order_beishu,
        max_order_count: this.max_order_count,
        deviation_of_orders: this.deviation_of_orders,
        deviation_of_orders_scale: this.deviation_of_orders_scale,
        leverage_type: this.leverageType
      }
      if (this.formType === 'edit') {
        payload.robot_id = this.robot_id
        payload.rebuild = 1
      }
      console.log(payload)
      const msg = this.calculate_price(payload)
      // console.log(msg)
      this.$dialog
        .confirm({
          message: msg
        })
        .then((res) => {
          const promise =
      this.formType === 'create' ? this.robotCreate(payload) : this.robotEdit(payload)
          promise
            .then((res) => {
              this.$toast(res.msg)
              this.$router.back()
            })
            .catch(({ msg }) => this.$toast(msg))
        })
    },
    calculate_price(payload) {
      const { first_order_value, order_beishu, max_order_count, deviation_of_orders, deviation_of_orders_scale, leverage_type } = payload
      const deviation = []
      deviation.unshift(parseFloat(deviation_of_orders).toFixed(2))
      const price = []
      price.unshift(parseFloat(first_order_value).toFixed(2))
      for (let index = 0; index < max_order_count - 1; index++) {
        const pre_price = price[index]
        const tmp_price = pre_price * order_beishu
        price.push(parseFloat(tmp_price).toFixed(2))
        const pre_deviation = parseFloat(deviation[index])
        const tmp_deviation = pre_deviation + deviation_of_orders * deviation_of_orders_scale ** (index + 1)
        deviation.push(parseFloat(tmp_deviation).toFixed(2))
      }
      let content = '策略预览\n\n补仓次数 , 幅度比例 , 成交数量（单位U）\n'
      let mount = 0.0
      for (let index = 0; index < price.length; index++) {
        mount = mount + parseFloat(price[index])
        const msg = '' + (index + 1) + '  ,  ' + deviation[index] + '%  ,  ' + price[index] + '\n'
        content = content + msg
      }
      const actual = mount / parseFloat(leverage_type)
      const msg = '\n预计: 持仓金额' + parseFloat(mount).toFixed(2) + '/' + leverage_type + '(杠杆倍数)' + '\n保证金' + parseFloat(actual).toFixed(2)
      content = content + msg
      return content
    },
    onConfirm(value) {
      this.market = value.market_name
      this.market_id = value.id
      this.marketPicker = false
    },
    changePreset(index) {
      this.preset = index
      if (index === 1) {
        this.leverageType = 20
        this.max_order_count = ''
      } else if (index === 2) {
        this.max_order_count = this.max_order_count
        this.stop_profit_rate = this.stop_profit_rate
        this.stop_profit_callback_rate = this.stop_profit_callback_rate
        this.cover_rate = this.cover_rate
        this.cover_callback_rate = this.cover_callback_rate
      }
    }
  }
}
</script>

<style lang="less" scoped>
.title {
  padding: 10px 15px;
  font-size: 16px;line-height: 1;
  background: rgba(255, 248, 234, 0.04);
  color: #fff1cf;
  text-align: center;
}
.rad {
  margin-top: 8px;
}
.preset {
  padding: 0 10px 10px;
  display: flex;
  justify-content: space-around;
  background: rgba(255, 248, 234, 0.04);

  .van-col {
    flex: 1;
    margin: 0 5px;
  }
  .van-button--primary{
    background: linear-gradient(135deg, #8d5c1f, #f1cd86);
  }
.van-cell {
  background: red;
}
}
.tips {
  padding: 10px 15px;
  font-size: 12px;
  text-align: center;
}
.setting{
  background: #1678FF;
  .van-button__content{
    color:#fff;
  }
}
.link{
  background: #1678FF;
  .van-button__content{
    color:#fff;
  }
}
.list_box {
  padding: 10px;
  box-sizing: border-box;
.van-button--info{
  width: 340px;
  height: 42px;
  color: #fff;
  font-style: 16px;
  line-height: 42px;
  text-align: center;
  margin:10px auto;
  border-radius: 20px;
}
.listInput{
    overflow: hidden;
    li{
      padding: 0 10px;
      line-height: 20px;
      font-style: 12px;
      border-bottom: 1px solid #999;
      overflow: hidden;
      .list_span{
        float: left;
      }
      .list_input{
        border: none  !important;
        float: right !important;
      }
      .list_i{
        float: right !important;
       }
    }
  }
}
.box_{
    position: fixed;
    width: 100%;
    height: 100%;
    background: rgb(51,51,50,0.8);
    z-index: 11111111;
    top: 0;
    >div{
background: linear-gradient(180deg, rgba(28, 20, 10, 0.98) 0%, rgba(18, 12, 6, 1) 100%);
    }
}
</style>
