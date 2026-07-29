<template>
	<div class="home-page">
		<div class="content-shell">
			<div v-if="!pageReady" class="home-skeleton">
				<div class="hero-card skeleton-block skeleton-hero"></div>
				<div class="side-stack">
					<div class="panel skeleton-block skeleton-panel"></div>
					<div class="panel skeleton-block skeleton-panel"></div>
					<div class="panel skeleton-block skeleton-panel"></div>
				</div>
			</div>
			<div v-else class="top-stage">
				<div class="hero-card">
					<div class="hero">
						<img src="../../assets/images/crypto-hero-generated.webp" alt="AI Crypto Star"
							class="hero-image" />
					</div>
				</div>

				<div class="side-stack">
					<div class="panel panel-banner">
						<div class="panel-head">
							<span>{{ $t('homeHero.featured') }}</span>
							<em>{{ $t('homeHero.live') }}</em>
						</div>
						<van-swipe v-if="banner.list && banner.list.length" class="my-swipe" :autoplay="3000"
							indicator-color="white">
							<van-swipe-item v-for="item in banner.list" :key="item.id">
								<van-image class="banner-image" fit="cover" :src="item.image"
									@click="viewDetail(item)" />
							</van-swipe-item>
						</van-swipe>
						<div v-else class="promo-fallback">
							<strong>AI Crypto Star</strong>
							<p>{{ $t('homeHero.intro') }}</p>
							<div class="promo-tags">
								<span>{{ $t('homeHero.quant') }}</span>
								<span>{{ $t('homeHero.futures') }}</span>
								<span>{{ $t('homeHero.spot') }}</span>
							</div>
						</div>
					</div>

					<div class="panel">
						<div class="panel-head panel-head-thin">
							<span>{{ $t('homeHero.platform_news') }}</span>
							<em>{{ $t('homeHero.latest') }}</em>
						</div>
						<notice></notice>
					</div>

					<div class="panel panel-rank">
						<div class="panel-head panel-head-thin">
							<span>{{ $t('homeHero.popular') }}</span>
							<em>{{ $t('homeHero.overview') }}</em>
						</div>
						<rank></rank>
					</div>
				</div>
			</div>

			<template v-if="pageReady">
				<div class="section-title">
					<span>{{ $t('homeHero.plaza') }}</span>
					<em>{{ $t('homeHero.picks') }}</em>
				</div>

				<ul class="strategy-grid">
					<li @click="show = true">
						<p>{{ $t('homeHero.grid') }} <img src="../../assets/images/li1.png" alt="" /></p>
						<span>{{ $t('homeHero.grid_desc') }}</span>
					</li>
					<li @click="show = true">
						<p>{{ $t('homeHero.tracking') }} <img src="../../assets/images/li2.png" alt="" /></p>
						<span>{{ $t('homeHero.tracking_desc') }}</span>
					</li>
					<li @click="show = true">
						<p>{{ $t('homeHero.hedge') }} <img src="../../assets/images/li3.png" alt="" /></p>
						<span>{{ $t('homeHero.hedge_desc') }}</span>
					</li>
					<li @click="show = true">
						<p>{{ $t('homeHero.balance') }} <img src="../../assets/images/li4.png" alt="" /></p>
						<span>{{ $t('homeHero.balance_desc') }}</span>
					</li>
				</ul>

				<menu-pic></menu-pic>
				<markets></markets>
			</template>
		</div>

		<div v-show="show" class="van-overlay" @click="show = false">
			<p>{{ $t('homeHero.coming') }}</p>
		</div>
	</div>
</template>

<script>
	definePageMeta({
		layout: 'navigation'
	})

	import {
		Swipe,
		SwipeItem
	} from 'vant'
	import {
		mapState,
		mapActions
	} from 'vuex'
	import notice from '@/components/home/notice'
	import rank from '@/components/home/rank'
	import menuPic from '@/components/home/menuPic'
	import markets from '@/components/home/markets'

	export default {
		components: {
			[Swipe.name]: Swipe,
			[SwipeItem.name]: SwipeItem,
			notice,
			rank,
			menuPic,
			markets
		},
		data() {
			return {
				show: false,
				pageReady: false
			}
		},
		computed: {
			...mapState({
				banner: index => index.banner
			})
		},
		async mounted() {
			await this.getBanner()
			this.pageReady = true
		},
		methods: {
			...mapActions({
				getBanner: 'getBanner'
			}),
			viewDetail(item) {
				const target = item.url || item.link || ''
				if (!target) {
					return
				}
				if (/^https?:\/\//i.test(target)) {
					window.location.href = target
					return
				}
				this.$router.push(target)
			}
		}
	}
</script>

<style scoped lang="less">
	.van-overlay {
		z-index: 11111;
		background: rgba(0, 0, 0, 0.35);
		backdrop-filter: blur(8px);

		p {
			width: 334px;
			height: 60px;
			line-height: 60px;
			margin: 34vh auto 0;
			color: #f8d989;
			text-align: center;
			background: linear-gradient(135deg, rgba(20, 18, 14, 0.95), rgba(72, 55, 23, 0.92));
			border: 1px solid rgba(248, 217, 137, 0.35);
			border-radius: 8px;
			box-shadow: 0 20px 60px rgba(0, 0, 0, 0.55);
		}
	}

	.home-page {
		position: relative;
		min-height: 100vh;
		padding: 12px 10px 18px;
		background:
			radial-gradient(circle at top left, rgba(246, 204, 113, 0.18), transparent 24%),
			radial-gradient(circle at 84% 10%, rgba(161, 118, 39, 0.12), transparent 18%),
			linear-gradient(180deg, #050402 0%, #110d07 42%, #181108 100%);
		color: #f7ecd2;
		overflow: hidden;
	}

	.home-page::before {
		content: '';
		position: absolute;
		inset: 0;
		background:
			linear-gradient(rgba(228, 191, 112, 0.05) 1px, transparent 1px),
			linear-gradient(90deg, rgba(228, 191, 112, 0.03) 1px, transparent 1px);
		background-size: 30px 30px;
		mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.32), transparent 72%);
		pointer-events: none;
		opacity: 0.55;
	}

	.home-page::after {
		content: '';
		position: absolute;
		inset: 0;
		background:
			linear-gradient(120deg, transparent 0%, rgba(213, 172, 88, 0.09) 46%, transparent 72%);
		pointer-events: none;
	}

	.content-shell {
		position: relative;
		z-index: 1;
		max-width: 1200px;
		margin: 0 auto;
	}

	.top-stage {
		display: flex;
		flex-direction: column;
		gap: 10px;
	}

	.hero-card,
	.panel {
		border: 1px solid rgba(217, 176, 92, 0.2);
		border-radius: 16px;
		background:
			linear-gradient(180deg, rgba(28, 20, 10, 0.96) 0%, rgba(18, 12, 6, 0.98) 100%);
		box-shadow:
			0 20px 46px rgba(0, 0, 0, 0.38),
			inset 0 1px 0 rgba(255, 236, 193, 0.05);
		overflow: hidden;
	}

	.hero {
		width: 100%;
		height: 188px;
		overflow: hidden;
		background: #06111f;
		line-height: 0;
	}

	.hero picture,
	.hero-image {
		display: block;
		width: 100%;
	}

	.hero-image {
		height: 100%;
		object-fit: cover;
		object-position: center;
	}

	.my-swipe {
		height: 145px;
		overflow: hidden;
		background: transparent;
	}

	.banner-image {
		width: 100%;
		height: 145px;
	}

	.van-swipe-item {
		font-size: 0;
	}

	.panel-head {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 12px 14px 8px;
		color: #fff1cf;
	}

	.panel-head span {
		font-size: 15px;
		font-weight: 700;
	}

	.panel-head em {
		color: #ddb46a;
		font-size: 10px;
		font-style: normal;
		letter-spacing: 0.08em;
	}

	.panel-head-thin {
		padding-bottom: 0;
	}

	.promo-fallback {
		display: flex;
		flex-direction: column;
		justify-content: center;
		height: 145px;
		padding: 16px;
		background:
			radial-gradient(circle at 16% 20%, rgba(247, 218, 152, 0.2), transparent 22%),
			linear-gradient(135deg, rgba(187, 136, 49, 0.16), transparent 44%),
			linear-gradient(145deg, #22170d, #382413);
		color: #fff0cb;
	}

	.promo-fallback strong {
		font-size: 20px;
		font-weight: 800;
	}

	.promo-fallback p {
		margin: 10px 0 0;
		color: rgba(255, 238, 201, 0.72);
		font-size: 12px;
		line-height: 1.5;
	}

	.promo-tags {
		display: flex;
		gap: 8px;
		margin-top: 14px;
	}

	.promo-tags span {
		padding: 4px 9px;
		border: 1px solid rgba(236, 197, 117, 0.26);
		border-radius: 999px;
		background: rgba(236, 197, 117, 0.1);
		font-size: 11px;
	}

	.section-title {
		display: flex;
		align-items: baseline;
		justify-content: space-between;
		margin: 16px 2px 10px;
	}

	.section-title span {
		color: #fff1cf;
		font-size: 17px;
		font-weight: 800;
	}

	.section-title em {
		color: #ddb46a;
		font-size: 11px;
		font-style: normal;
		letter-spacing: 0.08em;
	}

	.strategy-grid {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 10px;
		width: 100%;
		margin-top: 10px;
	}

	.strategy-grid li {
		min-height: 94px;
		padding: 12px 11px;
		box-sizing: border-box;
		border: 1px solid rgba(217, 176, 92, 0.18);
		border-radius: 12px;
		background:
			radial-gradient(circle at top right, rgba(248, 215, 144, 0.14), transparent 30%),
			linear-gradient(180deg, rgba(37, 25, 12, 0.98), rgba(22, 14, 7, 0.98));
		box-shadow: 0 14px 30px rgba(0, 0, 0, 0.26);
	}

	.strategy-grid p {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 6px;
		margin: 0 0 8px;
		color: #fff1cf;
		font-size: 14px;
		font-weight: 800;
	}

	.strategy-grid img {
		width: 24px;
		filter: sepia(0.9) saturate(1.3) brightness(1.05);
	}

	.home-skeleton {
		display: grid;
		grid-template-columns: minmax(0, 1.8fr) minmax(320px, 0.9fr);
		gap: 10px;
	}

	.skeleton-block {
		position: relative;
		overflow: hidden;
		border: 1px solid rgba(217, 176, 92, 0.14);
		background:
			linear-gradient(180deg, rgba(28, 20, 10, 0.92), rgba(18, 12, 6, 0.96));
	}

	.skeleton-block::before {
		content: '';
		position: absolute;
		inset: 0;
		background: linear-gradient(90deg, transparent 0%, rgba(255, 236, 193, 0.08) 50%, transparent 100%);
		transform: translateX(-100%);
		animation: skeleton-shimmer 1.4s infinite;
	}

	.skeleton-hero {
		height: 320px;
		border-radius: 16px;
	}

	.skeleton-panel {
		height: 160px;
		border-radius: 16px;
	}

	@keyframes skeleton-shimmer {
		to {
			transform: translateX(100%);
		}
	}

	.strategy-grid span {
		color: rgba(234, 220, 189, 0.66);
		font-size: 11px;
		line-height: 1.45;
	}

	:deep(.van-grid) {
		overflow: hidden;
		border: 1px solid rgba(217, 176, 92, 0.14);
		border-radius: 12px;
		background: rgba(24, 16, 8, 0.9);
	}

	:deep(.van-notice-bar) {
		margin: 0;
		border: 0;
		border-radius: 0;
		background: transparent !important;
		color: #f0e3c5 !important;
	}

	:deep(.van-grid-item__content) {
		background: transparent;
	}

	:deep(.van-hairline--top-bottom::after),
	:deep(.van-hairline-unset--top-bottom::after),
	:deep(.van-cell::after) {
		border-color: rgba(217, 176, 92, 0.12) !important;
	}

	:deep(.nav-pic),
	:deep(.title),
	:deep(.van-tabs),
	:deep(.van-tabs__nav),
	:deep(.van-pull-refresh),
	:deep(.van-cell),
	:deep(.van-empty) {
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
		color: #f0e3c5;
	}

	:deep(.van-empty__description) {
		color: rgba(240, 227, 197, 0.48);
	}

	@media (min-width: 768px) {
		.home-page {
			padding: 20px 16px 32px;
			background:
				radial-gradient(circle at top left, rgba(246, 204, 113, 0.19), transparent 26%),
				radial-gradient(circle at 85% 8%, rgba(161, 118, 39, 0.12), transparent 18%),
				linear-gradient(180deg, #050402 0%, #110d07 42%, #181108 100%);
		}

		.top-stage {
			display: grid;
			grid-template-columns: minmax(0, 1.8fr) minmax(320px, 0.9fr);
			align-items: stretch;
		}

		.side-stack {
			display: grid;
			grid-template-rows: 160px minmax(78px, auto) minmax(0, 1fr);
			gap: 12px;
		}

		.hero-card {
			height: 100%;
		}

		.hero {
			height: 320px;
		}

		.panel-banner,
		.panel-rank {
			min-height: 0;
		}

		.my-swipe,
		.section-title,
		.strategy-grid,
		:deep(.van-grid),
		:deep(.nav-pic),
		:deep(.title),
		:deep(.van-tabs),
		:deep(.van-pull-refresh) {
			max-width: none;
		}

		.my-swipe {
			height: 160px;
		}

		.banner-image {
			height: 160px;
		}

		.promo-fallback {
			height: 160px;
		}
	}

	@media (max-width: 767px) {
		.hero {
			height: 168px;
		}

		.panel-head {
			padding: 11px 12px 8px;
		}

		.strategy-grid {
			gap: 8px;
		}

		.strategy-grid li {
			min-height: 90px;
			padding: 11px 10px;
		}
	}

	@media (max-width: 480px) {
		.home-page {
			padding: 10px 8px 16px;
		}

		.top-stage,
		.strategy-grid {
			gap: 8px;
		}

		.hero {
			height: 156px;
		}

		.panel-head span {
			font-size: 14px;
		}

		.section-title span {
			font-size: 16px;
		}

		.strategy-grid {
			grid-template-columns: 1fr;
		}

		.strategy-grid li {
			min-height: 86px;
		}

		.home-skeleton {
			grid-template-columns: 1fr;
		}

		.skeleton-hero {
			height: 168px;
		}

		.skeleton-panel {
			height: 145px;
		}
	}
</style>
