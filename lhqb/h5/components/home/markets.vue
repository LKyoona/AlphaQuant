<template>
	<div class="markets-panel">
		<div class="title">{{ $t('robot') }}</div>
		<van-tabs v-model:active="active" swipeable animated sticky>
			<van-tab v-for="item in platform" :key="item.name">
				<template #title>{{ $t(item.label) }}</template>
				<van-pull-refresh v-model="isLoading" @refresh="loadData">
					<template v-if="robot_list[item.label] && robot_list[item.label].length > 0">
						<div v-for="robot in robot_list[item.label]" :key="robot.id">
							<van-cell class="asset-item" @click="goDetail(robot)">
								<div class="center">
									<div class="name">
										{{ robot.market_name }}
										<van-tag v-if="robot && robot.recycle_status == 1" round class="strategy-tag">
											{{ $t('cycle_strategy') }}
										</van-tag>
										<van-tag v-if="robot && robot.recycle_status == 0" round class="strategy-tag">
											{{ $t('single_strategy') }}
										</van-tag>
									</div>
									<div v-if="robot" class="info">
										<p>{{ $t('expected_return') }}：{{ $filters.numberFormat(Number(robot.revenue), 5) }}
											{{ robot.money }}</p>
										<p v-if="robot.show_msg" class="van-ellipsis">
											{{ $t('number_positions') }}：{{ getDeal(robot.values_str) }}
											{{ robot.stock }}
										</p>
									</div>
								</div>
							</van-cell>
						</div>
					</template>
					<van-empty v-else :description="$t('empty.run_robot')" />
				</van-pull-refresh>
			</van-tab>
		</van-tabs>
	</div>
</template>

<script>
	import {
		mapState,
		mapActions
	} from 'vuex'
	export default {
		data() {
			return {
				active: 0,
				isLoading: false,
				robot_list: {}
			}
		},
		computed: {
			...mapState({
				platform: ({
					robot
				}) => robot.platform,
				robots: ({
					robot
				}) => robot.robotList
			})
		},
		watch: {
			robots(values) {
				const list = values.filter(item => item.status === 1)
				this.platform.forEach((item) => {
					this.robot_list[item.label] = []
					for (const i in list) {
						if (list[i].platform === item.label) {
							this.robot_list[item.label].push(list[i])
						}
					}
				})
				this.$forceUpdate()
			}
		},
		mounted() {
			this.loadData()
		},
		methods: {
			...mapActions({
				robotList: 'robot/robotList'
			}),
			loadData() {
				this.robotList().then(() => {
					this.isLoading = false
				})
			},
			goDetail(item) {
				this.$nextTick(() => {
					this.$router.push({
						name: 'robot',
						query: {
							market_id: item.market_id
						}
					})
				})
			},
			getDeal(values) {
				if (values) {
					const valueJson = JSON.parse(values)
					return Number(valueJson.deal_amount).toFixed(6) || '-'
				}
				return '-'
			}
		}
	}
</script>

<style scoped lang="less">
	@import './home-theme.less';

	.title {
		padding: 12px 14px 10px;
		font-size: 17px;
		font-weight: 700;
		color: @home-text;
	}

	.markets-panel {
		.home-panel();
	}

	.asset-item {
		display: flex;
		justify-content: space-between;
		align-items: center;

		.left {
			flex-shrink: 0;
		}

		.center {
			flex-grow: 1;
			min-width: 0;
		}

		.name {
			color: @home-text;
			font-size: 16px;
			font-weight: 700;
		}

		.info {
			font-size: 12px;
			color: @home-text-soft;
		}
	}

	.strategy-tag {
		margin-left: 6px;
		border: 1px solid rgba(236, 197, 117, 0.28);
		background: rgba(236, 197, 117, 0.12);
		color: #ffefc8;
	}

	:deep(.van-tabs__wrap),
	:deep(.van-tabs__nav) {
		background: transparent !important;
	}

	:deep(.van-tab) {
		color: rgba(240, 227, 197, 0.58);
	}

	:deep(.van-tab--active) {
		color: #fff1cf;
	}

	:deep(.van-tabs__line) {
		background: linear-gradient(90deg, #8d5c1f, #f1cd86) !important;
	}

	:deep(.van-cell) {
		background: transparent;
		color: #f0e3c5;
	}

	:deep(.van-cell::after) {
		left: 16px;
		right: 16px;
		border-color: rgba(217, 176, 92, 0.1);
	}

	:deep(.van-empty) {
		background: transparent;
	}

	:deep(.van-empty__description) {
		color: @home-text-muted;
	}

	@media (max-width: 480px) {
		.title {
			padding: 11px 12px 8px;
			font-size: 16px;
		}

		.asset-item .name {
			font-size: 15px;
		}

		.asset-item .info {
			font-size: 11px;
		}
	}
</style>
