<template>
	<van-row class="nav-pic" gutter="8">
		<van-col :span="12">
			<nuxt-link to="/authorize" class="nav-item">
				<img :src="image1" alt="" class="nav-art" />
				<div class="nav-copy">
					<p class="title">{{ $t('homeMenu.item1.title') }}</p>
					<p class="sub">{{ $t('homeMenu.item1.sub') }}</p>
				</div>
			</nuxt-link>
		</van-col>

		<van-col :span="12">
			<nuxt-link to="/home/revenue" class="nav-item">
				<img :src="image2" alt="" class="nav-art" />
				<div class="nav-copy">
					<p class="title">{{ $t('homeMenu.item2.title') }}</p>
					<p class="sub">{{ $t('homeMenu.item2.sub') }}</p>
				</div>
			</nuxt-link>
		</van-col>
	</van-row>
</template>

<script>
	import image1 from '@/assets/images/home-authorize-gold.svg'
	import image2 from '@/assets/images/home-bill-gold.svg'

	export default {
		data() {
			return {
				image1,
				image2
			}
		}
	}
</script>

<style scoped lang="less">
	@import './home-theme.less';

	.nav-pic {
		padding: 6px 0 2px;
		margin-bottom: 8px;
		box-sizing: border-box;
	}

	.nav-item {
		display: block;
		width: 100%;
		height: 92px;
		position: relative;
		.home-panel();
		background:
			radial-gradient(circle at top right, rgba(250, 220, 150, 0.12), transparent 34%),
			linear-gradient(145deg, #20140a, #3a2412);
		overflow: hidden;

		&::before {
			content: '';
			position: absolute;
			inset: 0;
			background:
				linear-gradient(135deg, rgba(8, 5, 2, 0.44), rgba(8, 5, 2, 0.26) 34%, rgba(8, 5, 2, 0.56) 100%),
				radial-gradient(circle at top right, rgba(255, 220, 146, 0.05), transparent 34%);
			pointer-events: none;
			z-index: 1;
		}
	}

	.nav-copy {
		position: absolute;
		inset: 0;
		z-index: 2;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		padding: 10px 12px;
		text-align: center;
		background: linear-gradient(90deg, rgba(0, 0, 0, 0.24), rgba(0, 0, 0, 0.04) 48%, rgba(0, 0, 0, 0.22));
	}

	.title {
		margin: 0;
		font-size: 18px;
		font-weight: 900;
		color: #ffe39a !important;
		line-height: 1.05;
		letter-spacing: 0.02em;
		-webkit-text-stroke: 0.35px rgba(42, 23, 5, 0.72);
		text-shadow:
			0 0 12px rgba(255, 207, 103, 0.38),
			0 3px 14px rgba(0, 0, 0, 0.86),
			0 1px 0 rgba(0, 0, 0, 0.92);
	}

	.sub {
		margin-top: 6px;
		font-size: 12px;
		font-weight: 700;
		color: #fff6dc !important;
		line-height: 1.1;
		text-shadow:
			0 2px 10px rgba(0, 0, 0, 0.82),
			0 0 8px rgba(255, 214, 129, 0.22);
	}

	.nav-art {
		position: absolute;
		inset: -6px;
		width: calc(100% + 12px);
		height: calc(100% + 12px);
		object-fit: cover;
		object-position: center;
		opacity: 1;
		pointer-events: none;
		filter: brightness(1.12) saturate(1.06) contrast(1.08);
		transform: scale(1.06);
	}

	.nav-item:first-child .nav-art {
		filter: brightness(1.16) saturate(1.08) contrast(1.08);
	}

	.nav-item:last-child .nav-art {
		filter: brightness(1.1) saturate(1.04) contrast(1.1);
	}

	@media (max-width: 480px) {
		.nav-pic {
			margin-left: 0;
			margin-right: 0;
		}

		.nav-item {
			height: 88px;
		}

		.nav-art {
			inset: -8px;
			width: calc(100% + 16px);
			height: calc(100% + 16px);
		}

		.nav-copy {
			padding: 9px 10px;
		}

		.title {
			font-size: 17px;
		}

		.sub {
			font-size: 11px;
		}
	}
</style>
