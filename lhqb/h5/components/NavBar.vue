<template>
	<div class="nav-wrap">
		<nav class="app-tabbar" :key="locale">
			<nuxt-link
				v-for="item in visibleItems"
				:key="item.to"
				:to="item.to"
				class="nav-item"
				:class="{ active: item.index === active, pressing: pressing === item.to }"
				@click="handleNavClick(item)"
				@touchstart="pressing = item.to"
				@touchend="releasePress"
				@touchcancel="releasePress"
			>
				<van-icon :name="item.icon" class="nav-icon" />
				<span>{{ item.label ? $t(item.label) : item.labelText }}</span>
			</nuxt-link>
		</nav>
	</div>
</template>

<script>
import { mapState } from 'vuex'
	import {
		Icon
	} from 'vant'
export default {
	components: {
		[Icon.name]: Icon
	},
		computed: {
			...mapState({
				locale: state => state.locale
			}),
			visibleItems() {
				return this.items.filter(item => !item.hidden)
			}
		},
		data() {
			return {
				active: 0,
				pressing: '',
				items: [{
						index: 0,
						to: '/home',
						icon: 'home-o',
						label: 'nav.home'
					},
					{
						index: 1,
						to: '/market',
						icon: 'gold-coin-o',
						label: 'nav.exchange'
					},
					{
						index: 2,
						to: '/ticker',
						icon: 'chart-trending-o',
						label: 'nav.ticker',
						hidden: true //暂时隐藏
					},
					{
						index: 3,
						to: '/future',
						icon: 'chart-trending-o',
						labelText: '合约策略',
						hidden: true //暂时隐藏
					},
					{
						index: 4,
						to: '/user',
						icon: 'user-o',
						label: 'nav.user'
					}
				]
			}
		},
		methods: {
			handleNavClick(item) {
				this.pressing = item.to
				window.setTimeout(() => {
					this.pressing = ''
				}, 160)
			},
			releasePress() {
				this.pressing = ''
			}
		},
		watch: {
			$route: {
				immediate: true,
				handler(route) {
					const path = route.path || ''
					if (path.startsWith('/market')) {
						this.active = 1
					} else if (path.startsWith('/ticker')) {
						this.active = 2
					} else if (path.startsWith('/future')) {
						this.active = 3
					} else if (path.startsWith('/user')) {
						this.active = 4
					} else {
						this.active = 0
					}
				}
			}
		}
	}
</script>

<style scoped lang="less">
	.nav-wrap {
		position: fixed;
		left: 0;
		right: 0;
		bottom: 0;
		z-index: 1000;
		height: calc(var(--bottom-nav-height, 82px) + env(safe-area-inset-bottom));
		background: transparent;
		pointer-events: none;
	}

	.app-tabbar {
		pointer-events: auto;
		display: grid;
		grid-template-columns: repeat(3, 1fr);
		align-items: center;
		gap: 8px;
		height: 100%;
		padding: 8px 10px max(8px, env(safe-area-inset-bottom));
		box-sizing: border-box;
		border: 0;
		background: transparent;
		box-shadow: none;
	}

	.nav-item {
		appearance: none;
		position: relative;
		justify-self: center;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		gap: 4px;
		width: min(100%, 104px);
		height: 58px;
		border: 1px solid rgba(217, 176, 92, 0.13);
		border-radius: 18px;
		background: linear-gradient(180deg, rgba(31, 23, 13, 0.9), rgba(15, 11, 7, 0.94));
		box-shadow: 0 5px 12px rgba(0, 0, 0, 0.16), inset 0 1px 0 rgba(255, 236, 193, 0.04);
		color: rgba(240, 227, 197, 0.62);
		font-size: 12px;
		font-weight: 700;
		cursor: pointer;
		-webkit-tap-highlight-color: transparent;
		touch-action: manipulation;
		user-select: none;
		transition:
			transform 0.12s ease,
			border-color 0.18s ease,
			background 0.18s ease,
			box-shadow 0.18s ease,
			color 0.18s ease;
		will-change: transform;
	}

	.nav-item:active,
	.nav-item.pressing {
		transform: translateY(2px) scale(0.97);
		box-shadow: 0 2px 5px rgba(0, 0, 0, 0.16), inset 0 2px 4px rgba(0, 0, 0, 0.2);
	}

	.nav-icon {
		font-size: 20px;
		color: rgba(240, 227, 197, 0.62);
		transition: color 0.18s ease, transform 0.18s ease, filter 0.18s ease;
	}

	.nav-item span {
		line-height: 1;
		white-space: nowrap;
	}

	.nav-item.active {
		border-color: rgba(241, 205, 134, 0.46);
		background:
			radial-gradient(circle at 50% 0%, rgba(255, 228, 170, 0.2), transparent 46%),
			linear-gradient(180deg, rgba(83, 61, 26, 0.96), rgba(38, 26, 12, 0.98));
		box-shadow:
			0 7px 16px rgba(0, 0, 0, 0.24),
			inset 0 1px 0 rgba(255, 236, 193, 0.18),
			inset 0 -1px 0 rgba(0, 0, 0, 0.28);
		color: #fff1cf;
		font-weight: 800;
	}

	.nav-item.active .nav-icon {
		color: #f7cc73;
		transform: translateY(-1px);
		filter: drop-shadow(0 0 6px rgba(247, 204, 115, 0.22));
	}

	@media (max-width: 767px) {
		.app-tabbar {
			gap: 6px;
			padding-right: 8px;
			padding-left: 8px;
		}

		.nav-item {
			width: min(100%, 92px);
			height: 54px;
			border-radius: 16px;
			font-size: 11px;
		}

		.nav-icon {
			font-size: 19px;
		}
	}

	@media (min-width: 768px) {
		.nav-wrap {
			left: 50%;
			right: auto;
			width: min(460px, calc(100vw - 48px));
			transform: translateX(-50%);
		}
	}
</style>
