<template>
	<div class="revenue-page">
		<div class="revenue-shell">
			<van-nav-bar fixed placeholder left-arrow :title="$t('bill')" @click-left="$router.back()" />
			<div class="revenue-hero">
				<p class="eyebrow">{{ $t('revenuePage.overview') }}</p>
				<h2 class="hero-title">{{ $t('bill') }}</h2>
				<p class="hero-sub">{{ $t('revenuePage.intro') }}</p>
			</div>
			<div class="total-card" type="flex" gutter="20">
				<div class="total-item">
					<div class="label">{{ $t('revenuePage.today') }}</div>
					<div class="value">{{ $filters.numberFormat(Number(today_revenue).toFixed(6)) }}</div>
				</div>
				<div class="total-item">
					<div class="label">{{ $t('revenuePage.total') }}</div>
					<div class="value">{{ Number(total_revenue).toFixed(6) }}</div>
				</div>
			</div>
			<div class="list-panel">
				<van-pull-refresh v-model="refreshing" @refresh="onRefresh">
					<van-list v-model:loading="loading" :finished="finished" :finished-text="$t('finished_text')" @load="onLoad">
						<van-cell v-for="item in sortedOrderList" :key="item.id">
							<div class="robot-item">
								<div class="row">
									<div class="name">{{ item.market }}</div>
									<div class="profit">{{ $t('revenuePage.profit') }}：{{ $filters.numberFormat(Number(item.revenue), 8) }}</div>
								</div>
								<div class="row secondary">
									<div>{{ $t('platform') }}：{{ item.platform }}</div>
									<div class="time">{{ item.ctime }}</div>
								</div>
							</div>
						</van-cell>
					</van-list>
					<van-empty v-if="orderList.length === 0" :description="$t('empty.bill')" />
				</van-pull-refresh>
			</div>
		</div>
	</div>
</template>

<script>
	import {
		mapActions
	} from 'vuex'
	export default {
		data() {
			return {
				loading: false,
				finished: false,
				refreshing: false,
				orderList: [],
				offset: 0,
				limit: 20,
				today_revenue: 0,
				total_revenue: 0
			}
		},
		computed: {
			sortedOrderList() {
				return this.orderList
					.map((item, index) => ({
						item,
						index,
						time: this.getCtimeTimestamp(item.ctime)
					}))
					.sort((a, b) => {
						const aTime = a.time === null ? -Infinity : a.time
						const bTime = b.time === null ? -Infinity : b.time
						return bTime - aTime || a.index - b.index
					})
					.map(({ item }) => item)
			}
		},
		methods: {
			...mapActions({
				robotRevenue: 'robot/robotRevenue'
			}),
			getCtimeTimestamp(value) {
				if (value === null || value === undefined || value === '') {
					return null
				}

				if (typeof value === 'number' || /^\d+(\.\d+)?$/.test(String(value).trim())) {
					const numericValue = Number(value)
					if (!Number.isFinite(numericValue)) {
						return null
					}
					return numericValue < 100000000000 ? numericValue * 1000 : numericValue
				}

				const timestamp = Date.parse(String(value).trim().replace(/-/g, '/'))
				return Number.isNaN(timestamp) ? null : timestamp
			},
			loadList() {
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
					limit_begin: this.offset,
					limit_end: this.limit
				}
				this.robotRevenue(payload)
					.then(({
						data
					}) => {
						this.total_revenue = data.total_revenue
						this.today_revenue = data.today_revenue
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
			onLoad() {
				this.loadList()
			},
			onRefresh() {
				this.loadList()
			}
		}
	}
</script>

<style scoped lang="less">
	.revenue-page {
		position: relative;
		min-height: 100vh;
		padding: 12px 10px 24px;
		background:
			radial-gradient(circle at top left, rgba(246, 204, 113, 0.16), transparent 24%),
			radial-gradient(circle at 82% 8%, rgba(161, 118, 39, 0.12), transparent 18%),
			linear-gradient(180deg, #050402 0%, #110d07 42%, #181108 100%);
		color: #f7ecd2;
		overflow: hidden;
	}

	.revenue-page::before {
		content: '';
		position: absolute;
		inset: 0;
		background:
			linear-gradient(rgba(228, 191, 112, 0.04) 1px, transparent 1px),
			linear-gradient(90deg, rgba(228, 191, 112, 0.03) 1px, transparent 1px);
		background-size: 30px 30px;
		mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.34), transparent 72%);
		opacity: 0.5;
		pointer-events: none;
	}

	.revenue-shell {
		position: relative;
		z-index: 1;
		max-width: 1200px;
		margin: 0 auto;
	}

	.revenue-hero,
	.total-card,
	.list-panel {
		border: 1px solid rgba(217, 176, 92, 0.18);
		border-radius: 16px;
		background: linear-gradient(180deg, rgba(28, 20, 10, 0.96) 0%, rgba(18, 12, 6, 0.98) 100%);
		box-shadow: 0 18px 40px rgba(0, 0, 0, 0.34);
	}

	.revenue-hero {
		padding: 18px 16px 14px;
		margin-top: 12px;
	}

	.eyebrow {
		margin: 0 0 8px;
		color: #ddb46a;
		font-size: 11px;
		font-weight: 700;
	}

	.hero-title {
		margin: 0;
		color: #fff1cf;
		font-size: 22px;
		line-height: 1.2;
	}

	.hero-sub {
		margin: 10px 0 0;
		color: rgba(240, 227, 197, 0.7);
		font-size: 13px;
		line-height: 1.6;
	}

	.robot-item {
		font-size: 12px;
		color: rgba(240, 227, 197, 0.66);

		.row {
			display: flex;
			justify-content: space-between;
			align-items: center;
			gap: 12px;
		}

		.name {
			color: #fff1cf;
			font-size: 15px;
			font-weight: 700;
		}
	}

	.total-card {
		display: flex;
		gap: 12px;
		justify-content: space-between;
		align-items: center;
		margin-top: 12px;
		padding: 12px;
	}

	.total-item {
		position: relative;
		width: 50%;
		flex: 1;
		padding: 15px;
		border: 1px solid rgba(217, 176, 92, 0.18);
		border-radius: 12px;
		background:
			radial-gradient(circle at 15% 20%, rgba(248, 217, 148, 0.18), transparent 24%),
			linear-gradient(135deg, rgba(196, 143, 48, 0.18), transparent 45%),
			linear-gradient(145deg, #24180d, #3a2412);
		font-weight: 500;
		color: #ffefc8;
		overflow: hidden;

		&::before {
			content: "";
			position: absolute;
			top: 50%;
			left: 60%;
			width: 40vw;
			height: 40vw;
			border-radius: 50%;
			background-color: rgba(255, 255, 255, .2);
		}

		&::after {
			content: "";
			position: absolute;
			bottom: 50%;
			left: -30%;
			width: 20vw;
			height: 20vw;
			border-radius: 50%;
			background-color: rgba(255, 255, 255, .2);
		}

		.label {
			position: relative;
			color: rgba(255, 240, 204, 0.72);
		}

		.value {
			position: relative;
			margin-top: 5px;
			font-size: 1.4em;
		}
	}

	.list-panel {
		margin-top: 12px;
		overflow: hidden;
	}

	.profit {
		color: #f0c46e;
		font-weight: 700;
	}

	.secondary {
		margin-top: 8px;
	}

	.time {
		flex-shrink: 0;
	}

	:deep(.van-nav-bar) {
		background: rgba(17, 12, 6, 0.94);
	}

	:deep(.van-nav-bar::after) {
		border-color: rgba(217, 176, 92, 0.14);
	}

	:deep(.van-nav-bar__title),
	:deep(.van-icon),
	:deep(.van-nav-bar .van-nav-bar__text) {
		color: #fff1cf !important;
	}

	:deep(.van-cell) {
		background: transparent;
	}

	:deep(.van-cell::after) {
		left: 16px;
		right: 16px;
		border-color: rgba(217, 176, 92, 0.12);
	}

	:deep(.van-empty) {
		padding: 40px 0;
		background: transparent;
	}

	:deep(.van-empty__description),
	:deep(.van-list__finished-text) {
		color: rgba(240, 227, 197, 0.5);
	}
</style>
