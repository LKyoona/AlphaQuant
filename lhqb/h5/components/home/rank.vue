<template>
	<van-grid :column-num="3">
		<van-grid-item v-for="item in list" :key="item.id">
			<div class="name">{{ item.coin }}/{{ item.currency }}</div>
			<div class="price">{{ item.price }}</div>
			<div class="change" :class="{red: item.change < 0, green: item.change > 0}">
				{{ item.change > 0 ? `+${item.change}` : item.change }}%
			</div>
		</van-grid-item>
	</van-grid>
</template>

<script>
	import {
		mapState,
		mapActions
	} from 'vuex'
	import {
		Grid,
		GridItem
	} from 'vant'
let timer = null
let isDestroy = false
const POLL_INTERVAL = 15000
	export default {
		components: {
			[Grid.name]: Grid,
			[GridItem.name]: GridItem
		},
		computed: {
			...mapState({
				currency: ({
					currency
				}) => currency,
				list: ({
					ticker
				}) => ticker.list.slice(0, 3)
			})
		},
		created() {
			isDestroy = false
			this.loadData()
		},
		beforeUnmount() {
			isDestroy = true
			clearTimeout(timer)
		},
		methods: {
			...mapActions({
				getTickerList: 'ticker/getTickerList'
			}),
			loadData() {
				this.getTickerList({
					currency: this.currency.name,
					order: 'price',
					order_type: 'desc'
				}).finally(() => {
					if (isDestroy) {
						return
					}
					clearTimeout(timer)
					timer = setTimeout(() => {
						this.loadData()
					}, POLL_INTERVAL)
				})
			}
		}
	}
</script>

<style scoped lang="less">
	@import './home-theme.less';

	.name {
		font-size: 12px;
		font-weight: 700;
		color: @home-text;
	}

	.price {
		margin: 5px 0;
		font-size: 16px;
		color: #fff0ca;
	}

	.change {
		font-size: 12px;
		font-weight: 700;

		&.red {
			color: #ff8078;
		}

		&.green {
			color: #43d79a;
		}
	}

	:deep(.van-grid) {
		.home-panel();
		border-radius: 14px;
		background: linear-gradient(180deg, rgba(28, 20, 10, 0.96) 0%, rgba(18, 12, 6, 0.98) 100%);
	}

	:deep(.van-grid-item__content) {
		background: transparent;
		padding: 12px 8px;
	}

	@media (max-width: 480px) {
		.price {
			font-size: 15px;
		}
	}
</style>
