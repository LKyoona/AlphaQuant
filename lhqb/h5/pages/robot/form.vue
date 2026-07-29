<template>
	<div>
		<van-nav-bar :border="false" :title="$t('pageRobot.robot_setup')" left-arrow @click-left="$router.back()" />
		<van-form label-width="32%">
			<van-field :value="market" readonly clickable :label="$t('pageRobot.trade_area')"
				:placeholder="$t('pageRobot.trade_area')" :rules="[{ required: true }]" class="field-card"
				@click="onMarket" />
			<h3 class="title">{{ $t('robotForm.preset') }}</h3>
			<van-row class="preset">
				<van-col>
					<van-button block size="small" class="preset-btn" :class="{ active: preset === 3 }"
						@click="changePreset(3)">
						{{ $t('robotForm.steady') }}
					</van-button>
				</van-col>
				<van-col>
					<van-button block size="small" class="preset-btn" :class="{ active: preset === 4 }"
						@click="changePreset(4)">
						{{ $t('robotForm.custom') }}
					</van-button>
				</van-col>
			</van-row>
			<van-field v-model="first_order_value" :label="$t('first_order_amount') + '(' + money + ')'"
				:placeholder="$t('robotForm.first')" :rules="[{ required: true }]" />

			<van-field name="radio" label="">
				<template #input>
					<van-radio-group v-model="checked" direction="horizontal">
						<van-radio :name="1">{{ $t('robotForm.multiple') }}</van-radio>
						<van-radio :name="2">{{ $t('robotForm.equal') }}</van-radio>
						<van-radio :name="3">
							{{ $t('robotForm.difference') }}
						</van-radio>
					</van-radio-group>
				</template>
			</van-field>

			<van-field v-model="max_order_count" :label="$t('number_of_orders')" :placeholder="$t('robotForm.orders')"
				:rules="[{ required: true }]" />

			<van-field v-model="number" :label="$t('number_of_type')" :placeholder="$t('robotForm.times')"
				:rules="[{ required: true }]" />
			<van-field v-model="stop_profit_rate" :label="$t('take_profit_ratio') + '(%)'"
				:placeholder="$t('robotForm.take_profit')" :rules="[{ required: true }]" />
			<van-field v-model="stop_profit_callback_rate" :label="$t('take_profit_retracement') + '(%)'"
				:placeholder="$t('robotForm.retracement')" :rules="[{ required: true }]" />
			<!-- <van-field v-model="cover_rate" :label="$t('cover_down') + '(%)'" :placeholder="$t('cover_down')" :rules="[{ required: true }]"
      /> -->
			<van-field readonly :label="$t('cover_down') + '(%)'" :rules="[{ required: true }]">
				<template #button>
					<van-button size="small" class="setting" @click="showSetting()">
						{{ $t('setting') }}
					</van-button>
				</template>
			</van-field>
			<van-field v-model="cover_callback_rate" :label="$t('cover_pullback') + '(%)'"
				:placeholder="$t('robotForm.pullback')" :rules="[{ required: true }]" />
			<van-field name="radio" :label="$t('pageRobot.strategy_type')">
				<template #input>
					<van-radio-group v-model="recycle_status" direction="horizontal">
						<van-radio :name="1">{{ $t('cycle_strategy') }}</van-radio>
						<van-radio :name="0">{{ $t('single_strategy') }}</van-radio>
					</van-radio-group>
				</template>
			</van-field>
			<van-field v-if="formType === 'create' && CHEKC_CDKEY" v-model="cd_key" :label="$t('cdkey')"
				:placeholder="$t('robotForm.cdkey')" :rules="[{ required: true }]" />
			<div v-if="formType === 'create'" class="tips">
				{{ $t('pageRobotForm.tip') }}：{{ startupMinDisplay }}
				{{ initInfo.quant_revenue_type === '2' ? initInfo.system_balance_name : 'USDT' }}
			</div>
			<div class="submit-wrap">
				<van-button round block class="submit-btn" @click="onSubmit">
					{{ $t('actions.submit') }}
				</van-button>
			</div>
		</van-form>
		<van-popup v-model:show="marketPicker" position="bottom">
			<van-picker show-toolbar :columns="marketLists" value-key="market_name" @confirm="onConfirm"
				@cancel="marketPicker = false" />
		</van-popup>
		<van-popup v-model:show="show" round close-on-popstate class="list_box">
			<div class="list-panel">
				<div class="list-head">
					<div>
						<p class="list-title">{{ $t('cover_down') }}</p>
						<p class="list-subtitle">{{ $t('robotForm.cover_tip') }}</p>
					</div>
					<span class="list-cancel" @click="show = false">{{ $t('actions.cancel') }}</span>
				</div>
				<ul class="listInput">
					<li v-for="(item,index) in listInput" :key="index" class="list">
						<div class="list-row-head">
							<span v-if="index===0" class="list_span">{{ $t('robotForm.cover_first') }}</span>
							<span v-else
								class="list_span">{{ $t('robotForm.cover_step', { count: item.count }) }}</span>
							<label class="list_input_label">{{ $t('robotForm.cover_label') }}</label>
						</div>
						<div class="list-input-wrap">
							<div class="list-input-box">
								<input v-model="item.input" class="list_input" min="0.01" max="50" type="number"
									:placeholder="$t('robotForm.cover_input')" @input="handleInput($event,item)">
								<i class="list_i">%</i>
							</div>
						</div>
					</li>
				</ul>
				<div class="list-actions">
					<div class="van-button--info" @click="buttoninfo">{{ $t('actions.confirm') }}</div>
				</div>
			</div>
		</van-popup>
	</div>
</template>

<script>
	import {
		mapState,
		mapGetters,
		mapActions
	} from 'vuex'
	import {
		CHEKC_CDKEY
	} from '@/config/index'
	export default {
		i18n: {
			messages: {
				zh: {
					tip: '启动机器人最小余额',
					cover_tip: '设置每次补仓触发时的跌幅百分比',
					cover_step: '第{count}次补仓',
					cover_label: '跌幅设置',
					cover_input: '请输入跌幅'
				},
				en: {
					tip: 'Start robot minimum balance',
					cover_tip: 'Set the drop percentage that triggers each safety order',
					cover_step: 'Safety order {count}',
					cover_label: 'Drop setting',
					cover_input: 'Enter drop percentage'
				}
			}
		},
		data() {
			return {
				checked: 2,
				CHEKC_CDKEY,
				preset: 4,
				formType: 'create',
				marketPicker: false,
				market: '',
				money: '',
				platform: 'huobi',
				robot_id: '',
				market_id: '',
				first_order_value: '100',
				max_order_count: '',
				stop_profit_rate: '',
				number: 0,
				stop_profit_callback_rate: '',
				cover_rate: '',
				cover_callback_rate: '',
				recycle_status: 1,
				cd_key: '',
				show: false,
				listInput: []
			}
		},
		computed: {
			...mapState({
				initInfo: index => index.initInfo,
				robotList: ({
					robot
				}) => robot.robotList,
				thirdLoginEnabled: ({
					thirdLoginEnabled
				}) => thirdLoginEnabled
			}),
			...mapGetters({
				markets: 'robot/markets'
			}),
			startupMinDisplay() {
				return this.initInfo.quant_startup_min || this.initInfo.quant_start_min || 0
			},
			marketLists() {
				return this.markets(this.platform) || []
			}
		},

		async created() {
			this.formType = this.$route.query.type
			if (this.formType === 'edit') {
				if (!this.robotList.length) {
					try {
						await this.fetchRobotList()
					} catch (error) {
						this.$toast(error && error.msg ? error.msg : this.$t('empty.robot'))
						this.$router.back()
						return
					}
				}
				const robotId = String(this.$route.query.robot_id || '')
				const robot = (this.robot = this.robotList.find(
					item => String(item.id ?? '') === robotId
				)) || null
				if (!robot) {
					this.$toast(this.$t('empty.robot'))
					this.$router.back()
					return
				}
				this.platform = robot.platform
				this.checked = robot.c_type
				this.market = robot.market_name
				this.market_id = robot.market_id
				this.robot_id = robot.id
				this.first_order_value = robot.first_order_value
				this.max_order_count = robot.max_order_count
				this.stop_profit_rate = robot.stop_profit_rate
				this.number = robot.number
				this.stop_profit_callback_rate = robot.stop_profit_callback_rate
				this.cover_rate = robot.cover_rate
				this.cover_callback_rate = robot.cover_callback_rate
				this.recycle_status = robot.recycle_status
				this.money = robot.money
				this.listInput = []
			} else {
				let markets = {}
				try {
					markets = JSON.parse(this.$route.query.data || '{}')
				} catch (error) {
					markets = {}
				}
				this.platform = this.$route.query.platform
				this.market = markets.market_name
				this.market_id = markets.id
				this.money = markets.money
			}

			this.marketList({
				platform: this.platform,
				type: 'spot'
			})
		},
		methods: {
			resetCustomFields() {
				this.max_order_count = ''
				this.stop_profit_rate = ''
				this.number = ''
				this.stop_profit_callback_rate = ''
				this.cover_rate = ''
				this.cover_callback_rate = ''
				this.listInput = []
			},
			showSetting() {
				this.listInput = []
				if (!this.cover_rate) {
					if (this.max_order_count > 0) {
						for (let i = 0; i < this.max_order_count; i++) {
							const obj = {}
							obj.count = ''
							obj.input = ''
							this.listInput.push(obj)
						}
						this.listInput.map((item, index) => {
							item.count = index + 1
						})
					}
				} else {
					this.listInput = []
					let obj = {}
					try {
						obj = JSON.parse(this.cover_rate)
					} catch (error) {
						obj = {}
					}
					const arr = []
					for (const key in obj) {
						const obj1 = {}
						obj1.count = ''
						obj1.input = obj[key]
						arr.push(obj1)
					}
					this.listInput = arr
					this.listInput.map((item, index) => {
						item.count = index + 1
					})

					const obj2 = obj
					const arr2 = []
					for (const key in obj2) {
						const obj1 = {}
						obj1.count = ''
						obj1.input = obj2[key]
						arr2.push(obj1)
					}

					if (arr2.length != this.max_order_count) {
						for (let i = 0; i < this.max_order_count - arr2.length; i++) {
							const obj = {}
							obj.count = ''
							obj.input = ''
							this.listInput.push(obj)
						}
					}
					this.listInput.map((item, index) => {
						item.count = index + 1
					})
				}

				// if (this.max_order_count > 0) {
				//   for (let i = 0; i < this.max_order_count; i++) {
				//     const obj = {}
				//     obj.count = ''
				//     obj.input = ''
				//     this.listInput.push(obj)
				//   }
				// }

				// for (let i = 0; i < this.listInput; i++) {
				//   console.log(this.listInput[i])
				//   this.listInput[i].count = i + 1
				// }

				console.log(this.listInput)
				this.show = true

				// for (let i = 0; i < this.cover_rate; i++) {
				//   console.log(this.listInput)
				// }
				// } else {
				//   const obj = JSON.parse(this.cover_rate)
				//   const arr = []
				//   let num = 0
				//   for (const key in obj) {
				//     const obj1 = {}
				//     obj1.count = num += 1
				//     obj1.input = obj[key]
				//     arr.push(obj1)
				//   }
				//   this.listInput = arr
				//   console.log(this.listInput)
				//   this.show = true
				// }
			},
			buttoninfo() {
				console.log(this.listInput)
				this.show = false
			},
			handleInput(e, item) {
				// e.target.value = (e.target.value.match(/^\d*(.?\d{0,1})/g)[0]) || null
				if (item.input > 100) {
					item.input = 100
					return
				}
				if (item.input === '' || item.input === null || item.input === undefined) {
					return
				}
				// if (item.input < 0) {
				//   item.input = 0.01
				//   return
				// }
				// item.input = (Math.floor(item.input * 100) / 100)
				item.input = String(item.input).match(/^\d*(.?\d{0,2})/g)[0]
			},
			...mapActions({
				marketList: 'robot/marketList',
				fetchRobotList: 'robot/robotList',
				robotCreate: 'robot/robotCreate',
				robotEdit: 'robot/robotEdit'
			}),
			onMarket() {
				if (this.formType === 'create') {
					this.marketPicker = true
				}
			},
			onSubmit() {
				let flag = false
				for (const key in this.listInput) {
					if (this.listInput[key].input !== '' && this.listInput[key].input > 0) {
						flag = true
					} else {
						this.$toast(this.$t('robotForm.cover_required'))
						flag = false
						return
					}
				}
				if (flag) {
					this.$toast.loading()
					const payload = {
						platform: this.platform,
						market_id: this.market_id,
						first_order_value: this.first_order_value,
						max_order_count: this.max_order_count,
						stop_profit_rate: this.stop_profit_rate,
						number: this.number,
						stop_profit_callback_rate: this.stop_profit_callback_rate,
						cover_rate: this.listInput,
						c_type: this.checked,
						// 补仓
						// cover_rate: this.cover_rate,
						cover_callback_rate: this.cover_callback_rate,
						recycle_status: this.recycle_status,
						cd_key: this.cd_key
					}
					if (this.formType === 'edit') {
						payload.robot_id = this.robot_id
					}
					const promise =
						this.formType === 'create' ? this.robotCreate(payload) : this.robotEdit(payload)
					promise
						.then(async (res) => {
							this.$toast(res.msg)
							if (this.formType === 'edit') {
								await this.fetchRobotList().catch(() => {})
							}
							this.$router.back()
						})
						.catch(({
							msg
						}) => this.$toast(msg))
				} else {
					this.$toast(this.$t('robotForm.cover_required'))
				}
			},
			onConfirm(value) {
				this.market = value.market_name
				this.market_id = value.id
				this.marketPicker = false
			},
			changePreset(index) {
				this.preset = index
				if (index === 3) {
					this.max_order_count = '17'
					this.stop_profit_rate = '1.1'
					this.number = '10'
					this.stop_profit_callback_rate = '0.1'
					this.cover_rate =
						'{"1":"1","2":"2","3":"3","4":"4","5":"5","6":"6","7":"7","8":"8","9":"9","10":"10","11":"13","12":"16","13":"19","14":"21","15":"26","16":"31","17":"41"}'
					this.cover_callback_rate = '0.3'
				} else if (index === 4) {
					this.resetCustomFields()
				}
			}
		}
	}
</script>

<style lang="less" scoped>
	.title {
		padding: 10px 15px;
		font-size: 14px;
		line-height: 1;
		background: rgba(255, 248, 234, 0.04);
		color: #fff4d6;
		font-weight: 700;
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
	}

	.field-card {
		background: linear-gradient(180deg, rgba(28, 20, 10, 0.96), rgba(18, 12, 6, 0.98));
	}

	.preset-btn {
		border: 1px solid rgba(255, 228, 170, 0.14);
		background: rgba(255, 248, 234, 0.05);
		color: #f0e3c5;
	}

	.preset-btn.active {
		background: linear-gradient(135deg, #8d5c1f, #f1cd86);
		color: #2a1b0d;
		font-weight: 700;
	}

	.tips {
		padding: 10px 15px;
		font-size: 12px;
		color: rgba(240, 227, 197, 0.72);
	}

	.setting {
		border: 1px solid rgba(255, 228, 170, 0.18);
		background: linear-gradient(135deg, rgba(141, 92, 31, 0.96), rgba(241, 205, 134, 0.96));
		color: #2a1b0d;
	}

	.submit-wrap {
		padding: 18px 16px 16px;
	}

	.submit-btn {
		height: 44px;
		border: 1px solid rgba(255, 228, 170, 0.18);
		background: linear-gradient(135deg, rgba(141, 92, 31, 0.96), rgba(241, 205, 134, 0.96));
		color: #2a1b0d;
		font-weight: 700;
	}

	.list_box {
		width: min(92vw, 460px);
		border-radius: 24px;
		overflow: hidden;
		background: transparent;
	}

	.list-panel {
		padding: 16px 16px 18px;
		border: 1px solid rgba(255, 228, 170, 0.14);
		border-radius: 24px;
		background:
			linear-gradient(180deg, rgba(31, 22, 11, 0.985), rgba(17, 12, 6, 0.995)),
			radial-gradient(circle at top, rgba(241, 205, 134, 0.12), transparent 55%);
		box-shadow: 0 24px 60px rgba(0, 0, 0, 0.42);
		max-height: min(78vh, 680px);
		overflow: hidden;
	}

	.list-head {
		display: flex;
		justify-content: space-between;
		align-items: center;
		gap: 12px;
		margin-bottom: 12px;
	}

	.list-title {
		color: #fff1cf;
		font-size: 18px;
		font-weight: 700;
		line-height: 1.1;
	}

	.list-cancel {
		color: #f0c46e;
		font-size: 13px;
		font-weight: 600;
		flex-shrink: 0;
	}

	.list-subtitle {
		margin-top: 5px;
		color: rgba(255, 228, 170, 0.62);
		font-size: 12px;
		line-height: 1.4;
	}

	.listInput {
		display: grid;
		gap: 10px;
		max-height: min(52vh, 440px);
		overflow: auto;
		padding-right: 2px;
	}

	.listInput li {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
		padding: 12px;
		line-height: 18px;
		font-size: 12px;
		border: 1px solid rgba(255, 228, 170, 0.1);
		border-radius: 16px;
		background: linear-gradient(135deg, rgba(255, 248, 234, 0.045), rgba(255, 248, 234, 0.02));
		color: rgba(240, 227, 197, 0.72);
	}

	.list-row-head {
		display: grid;
		gap: 4px;
		min-width: 0;
	}

	.list_span {
		flex-shrink: 0;
		color: #fff1cf;
		font-size: 14px;
		font-weight: 700;
	}

	.list-input-wrap {
		display: flex;
		align-items: center;
		justify-content: flex-end;
		flex-shrink: 0;
	}

	.list_input_label {
		color: rgba(255, 228, 170, 0.62);
		font-size: 11px;
		letter-spacing: 0.04em;
	}

	.list-input-box {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 6px;
		width: 132px;
		min-height: 40px;
		padding: 0 10px;
		border: 1px solid rgba(255, 228, 170, 0.18);
		border-radius: 12px;
		background: rgba(255, 248, 234, 0.08);
		box-shadow: inset 0 1px 0 rgba(255, 228, 170, 0.05);
	}

	.list_input {
		width: 74px;
		border: none !important;
		background: transparent;
		text-align: right;
		color: #fff1cf;
		font-size: 14px;
		font-weight: 700;
	}

	.list_i {
		color: #ddb46a;
		flex-shrink: 0;
	}

	.van-button--info {
		width: 100%;
		height: 44px;
		border-radius: 999px;
		border: 1px solid rgba(255, 228, 170, 0.18);
		background: linear-gradient(135deg, rgba(141, 92, 31, 0.96), rgba(241, 205, 134, 0.96));
		color: #2a1b0d;
		font-size: 14px;
		font-weight: 700;
		line-height: 44px;
		text-align: center;
		box-shadow: 0 10px 28px rgba(141, 92, 31, 0.18);
	}

	.list-actions {
		margin-top: 14px;
	}

	:deep(.van-form) {
		background: linear-gradient(180deg, rgba(20, 14, 8, 0.96), rgba(14, 10, 6, 0.98));
	}

	:deep(.van-field) {
		background: transparent;
		padding: 16px 14px;
		border-bottom: 1px solid rgba(255, 228, 170, 0.08);
	}

	:deep(.van-field__button) {
		margin-left: 12px;
	}

	:deep(.van-field__label) {
		color: rgba(255, 228, 170, 0.58);
		font-size: 13px;
		font-weight: 500;
	}

	:deep(.van-field__value),
	:deep(.van-field__body),
	:deep(.van-field__control) {
		color: #fff1cf;
		font-size: 15px;
		font-weight: 600;
	}

	:deep(.van-field__control::placeholder) {
		color: #fff1cf;
		opacity: 0.9;
	}

	:deep(.van-field__control::-webkit-input-placeholder) {
		color: #fff1cf;
		opacity: 0.9;
	}

	:deep(.van-radio-group) {
		gap: 18px;
	}

	:deep(.van-radio__label) {
		color: rgba(255, 228, 170, 0.74);
		font-size: 13px;
	}

	:deep(.van-radio__icon--checked .van-badge__wrapper) {
		color: #f0c46e;
	}

	:deep(.van-picker__cancel),
	:deep(.van-picker__confirm) {
		color: #f0c46e;
	}

	:deep(.van-picker-column__item) {
		color: #f0e3c5;
	}

	:deep(.van-nav-bar__title) {
		color: #fff1cf !important;
		font-size: 16px;
		font-weight: 700;
		letter-spacing: .04em;
		text-shadow: 0 0 18px rgba(241, 205, 134, 0.16);
	}

	.box_ {
		position: fixed;
		width: 100%;
		height: 100%;
		background: rgba(51, 51, 50, 0.8);
		z-index: 11111111;
		top: 0;

		>div {
			background: linear-gradient(180deg, rgba(28, 20, 10, 0.98) 0%, rgba(18, 12, 6, 1) 100%);
			color: #f0e3c5;
		}
	}

	@media (max-width: 767px) {
		.title {
			padding: 10px 12px;
		}

		.preset {
			padding: 0 8px 10px;
		}

		.tips {
			padding: 10px 12px;
		}

		.submit-wrap {
			padding: 14px 12px 16px;
		}

		.list_box {
			padding: 0;
		}

		.list-panel {
			padding: 14px 12px calc(14px + env(safe-area-inset-bottom));
			max-height: 78vh;
		}

		.list_box {
			width: calc(100vw - 24px);
		}

		.listInput {
			gap: 8px;
			max-height: 50vh;
		}

		.listInput li {
			padding: 11px 10px;
			gap: 10px;
		}

		.list_span {
			font-size: 13px;
		}

		.list_input {
			width: 72px;
		}

		.list-input-box {
			width: 122px;
			min-height: 38px;
		}
	}
</style>
