<template>
	<div class="market-page">
		<div class="market-shell">
			<div safe-area-inset-top>
				<div class="market-hero">
					<p class="eyebrow">{{ $t('pageMarket.market_title') }}</p>
					<van-row type="flex" justify="space-between" align="center" class="page-header">
						<van-col class="page-header-left">
							<h2 class="page-title">{{ $t('nav.exchange') }}</h2>
						</van-col>
					</van-row>
					<p class="hero-sub">{{ $t('pageMarket.market_intro') }}</p>
				</div>
			</div>

			<div v-if="pageReady" class="market-panel">
				<template v-if="!logged">
					<div class="market-login-hint">
						<p class="hint-title">{{ $t('pageMarket.login_required') }}</p>
						<p class="hint-desc">{{ $t('pageMarket.login_required_desc') }}</p>
						<nuxt-link to="/sign/login" class="login-btn gold-btn">
							{{ $t('pageMarket.go_login') }}
						</nuxt-link>
					</div>
				</template>
				<template v-else-if="hasPlatform && !loadError">
					<van-tabs v-model:active="active" swipeable animated sticky>
						<van-tab v-for="item in platform" :key="item.name">
							<template #title>{{ $t(item.label) }}</template>
							<template v-if="!isPlatformAuthorized(item.label)">
								<div class="api-connect-card">
									<crypto-network-globe />
									<div class="api-connect-copy">
										<h3>{{ $t('pageMarket.not') }}</h3>
									</div>
									<nuxt-link to="/authorize" class="api-connect-btn">
										<span>{{ $t('pageMarket.add') }}</span>
										<van-icon name="arrow" />
									</nuxt-link>
									</div>
							</template>
							<template v-else-if="hasBalance">
								<div class="amount amount-has">
									<div class="amount-copy">
										<span>{{ $t('pageMarket.balance') }}</span>
										<strong>{{ $filters.numberFormat(Number(balanceValue || 0), 8) }} USDT</strong>
										<p class="amount-note">{{ $t('pageMarket.market_title') }}</p>
									</div>
								</div>
							</template>
							<template v-else-if="isBalanceLoading">
								<div class="amount amount-empty">
									<div class="balance-loading">
										<div class="loading-spinner"></div>
									</div>
								</div>
							</template>
							<template v-else>
								<div class="amount amount-empty">
									<div class="amount-copy">
										<span>{{ $t('pageMarket.balance') }}</span>
										<p>{{ $t('pageMarket.balance_empty') }}</p>
									</div>
								</div>
							</template>
							<assets-list
								v-if="isPlatformAuthorized(item.label)"
								:platform="item.label"
							></assets-list>
						</van-tab>
					</van-tabs>
				</template>
				<div
					v-else-if="loadError"
					class="market-error"
				>
					<p class="error-title">{{ $t('pageMarket.system_error') }}</p>
					<p class="error-desc">{{ $t('pageMarket.system_error_desc') }}</p>
					<button class="retry-btn" @click="retryLoad">{{ $t('pageMarket.retry') }}</button>
				</div>
				<div
					v-else
					class="market-empty"
				>
					{{ $t('empty.default') }}
				</div>
			</div>
			<div v-else class="market-panel market-skeleton">
				<div class="skeleton-hero-line"></div>
				<div class="skeleton-balance"></div>
				<div class="skeleton-list">
					<div class="skeleton-row" v-for="item in 3" :key="item"></div>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
	definePageMeta({
		layout: 'navigation'
	})

	import {
		mapState,
		mapActions
	} from 'vuex'
	import assetsList from '@/components/market/assetsList'
	import CryptoNetworkGlobe from '@/components/market/CryptoNetworkGlobe'
	export default {
		components: {
			assetsList,
			CryptoNetworkGlobe
		},
		data() {
			return {
				active: 0,
				account: null,
				loadError: false,
				isLoading: false,
				isBalanceLoading: false,
				pageReady: false,
				platformAccess: {}
			}
		},
		computed: {
			...mapState({
				// platform: ({ robot }) => robot.platform
				platform: ({
					authorize
				}) => authorize.platform,
				logged: ({
					user
				}) => user.logged
			})
			,
			currentPlatform () {
				return this.platform && this.platform[this.active] ? this.platform[this.active] : null
			},
			hasPlatform () {
				return Array.isArray(this.platform) && this.platform.length > 0
			},
			hasBalance () {
				return this.balanceValue !== null && this.balanceValue !== undefined
			},
			balanceValue () {
				if (!this.account) {
					return null
				}
				if (typeof this.account === 'number' || typeof this.account === 'string') {
					return this.account
				}
				const free = this.account.free || {}
				if (this.account.USDT !== undefined && this.account.USDT !== null) {
					return this.account.USDT
				}
				if (free.USDT !== undefined && free.USDT !== null) {
					return free.USDT
				}
				if (this.account.free !== undefined && this.account.free !== null) {
					return this.account.free
				}
				if (this.account.balance !== undefined && this.account.balance !== null) {
					return this.account.balance
				}
				if (this.account.total !== undefined && this.account.total !== null) {
					return this.account.total
				}
				return null
			}
		},
		watch: {
			active(newVal) {
				const label = this.resolvePlatformLabel(newVal)
				if (this.pageReady && this.isPlatformAuthorized(label)) {
					this.loadBalance(newVal)
				}
			}
		},
		async mounted() {
			await this.initializeMarket()
		},
		methods: {
			...mapActions({
				apiAccountBalance: 'authorize/apiAccountBalance',
				getApiAccount: 'authorize/getApiAccount',
				setApiInfo: 'authorize/setApiInfo'
			}),
			async initializeMarket () {
				this.pageReady = false
				this.loadError = false
				this.account = null
				this.platformAccess = {}
				if (!this.logged) {
					this.pageReady = true
					return
				}
				try {
					await this.syncPlatformAuth()
					const label = this.resolvePlatformLabel(this.active)
					if (this.isPlatformAuthorized(label)) {
						await this.loadBalance(this.active)
					}
				} catch (error) {
					this.loadError = true
				} finally {
					this.pageReady = true
				}
			},
			async syncPlatformAuth () {
				if (!this.logged || !this.hasPlatform) {
					return
				}
				const tasks = (this.platform || []).map(async (item, index) => {
					try {
						const res = await this.getApiAccount({ platform: item.label })
						const info = this.normalizeApiInfo(res.data)
						this.setApiInfo([index, info])
						this.platformAccess = {
							...this.platformAccess,
							[item.label]: this.canLoadPlatform(info)
						}
					} catch (error) {
						if (!this.isMissingApiError(error)) {
							throw error
						}
						this.platformAccess = {
							...this.platformAccess,
							[item.label]: false
						}
					}
				})
				await Promise.all(tasks)
			},
			normalizeApiInfo (data) {
				const info = data || {}
				const nested = info.account || info.info || {}
				return Object.assign({}, nested, info)
			},
			resolvePlatformLabel (index) {
				const item = this.platform && this.platform[index]
				return item && item.label ? item.label : ''
			},
			hasApi (info) {
				return Boolean(
					info && (
						info.api_key ||
						info.secret_key ||
						info.has_api ||
						info.is_bind ||
						Number(info.bind_status) === 1 ||
						[-1, 0, 1].includes(Number(info.status))
					)
				)
			},
			canLoadPlatform (info) {
				return this.hasApi(info) && Number(info.status) !== -1
			},
			isMissingApiError (error) {
				const message = String(error && (error.msg || error.message) || '')
				return Boolean(error && error.code !== undefined) && /api|key|授权|绑定|添加/i.test(message)
			},
			isPlatformAuthorized (label) {
				return Boolean(label && this.platformAccess[label])
			},
			loadBalance (index) {
				if (!this.logged) {
					this.account = null
					this.loadError = false
					this.isBalanceLoading = false
					return Promise.resolve()
				}
				const label = this.resolvePlatformLabel(index)
				if (!label) {
					this.account = null
					this.loadError = false
					this.isBalanceLoading = false
					return Promise.resolve()
				}
				if (!this.isPlatformAuthorized(label)) {
					this.account = null
					this.isBalanceLoading = false
					return Promise.resolve()
				}
				this.loadError = false
				this.isBalanceLoading = true
				this.account = null
				return this.apiAccountBalance({ platform: label })
					.then((res) => {
						const data = res && res.data ? res.data : null
						this.account = data && data.free !== undefined ? data.free : data
					})
					.catch((res) => {
						this.account = null
						this.loadError = true
						if (res && res.msg) {
							this.$toast(res.msg)
						}
					})
					.finally(() => {
						this.isBalanceLoading = false
					})
			},
			retryLoad () {
				this.initializeMarket()
			}
		}
	}
</script>

<style lang="less" scoped>
	.market-page {
		position: relative;
		min-height: 100vh;
		padding: 12px 10px 24px;
		background:
			radial-gradient(circle at top left, rgba(246, 204, 113, 0.18), transparent 24%),
			radial-gradient(circle at 84% 10%, rgba(161, 118, 39, 0.12), transparent 18%),
			linear-gradient(180deg, #050402 0%, #110d07 42%, #181108 100%);
		color: #f7ecd2;
		overflow: hidden;
	}

	.market-page::before {
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

	.market-shell {
		position: relative;
		z-index: 1;
		max-width: 1200px;
		margin: 0 auto;
	}

	.market-hero {
		padding: 18px 16px 14px;
		border: 1px solid rgba(217, 176, 92, 0.18);
		border-radius: 16px;
		background: linear-gradient(180deg, rgba(28, 20, 10, 0.96) 0%, rgba(18, 12, 6, 0.98) 100%);
		box-shadow: 0 18px 40px rgba(0, 0, 0, 0.34);
	}

	.eyebrow {
		margin: 0 0 8px;
		color: #ddb46a;
		font-size: 11px;
		font-weight: 700;
	}

	.page-header {
		height: 46px;
		padding: 0;

		&-right .van-icon {
			display: inline-block;
			vertical-align: middle;
		}

		&-title {
			display: flex;
			align-items: center;
			font-size: 22px;
			line-height: 1;
			color: #fff1cf;
		}
	}

	.hero-sub {
		margin: 0;
		color: rgba(240, 227, 197, 0.68);
		font-size: 13px;
		line-height: 1.6;
	}

	.market-panel {
		margin-top: 12px;
		border: 1px solid rgba(217, 176, 92, 0.18);
		border-radius: 16px;
		background: linear-gradient(180deg, rgba(28, 20, 10, 0.96) 0%, rgba(18, 12, 6, 0.98) 100%);
		box-shadow: 0 18px 40px rgba(0, 0, 0, 0.34);
		overflow: hidden;
	}

	.market-error,
	.market-empty {
		margin: 0;
		padding: 18px 16px;
		border-top: 1px solid rgba(217, 176, 92, 0.12);
		color: rgba(240, 227, 197, 0.7);
		text-align: center;
	}

	.market-skeleton {
		padding: 16px;
	}

	.skeleton-hero-line,
	.skeleton-balance,
	.skeleton-row {
		position: relative;
		overflow: hidden;
		border-radius: 14px;
		background: rgba(255, 248, 234, 0.06);
		border: 1px solid rgba(217, 176, 92, 0.14);
	}

	.skeleton-hero-line::before,
	.skeleton-balance::before,
	.skeleton-row::before {
		content: '';
		position: absolute;
		inset: 0;
		background: linear-gradient(90deg, transparent 0%, rgba(255, 236, 193, 0.08) 50%, transparent 100%);
		transform: translateX(-100%);
		animation: skeleton-shimmer 1.4s infinite;
	}

	.skeleton-hero-line {
		height: 42px;
		margin-bottom: 14px;
	}

	.skeleton-balance {
		height: 92px;
		margin-bottom: 14px;
	}

	.skeleton-list {
		display: grid;
		gap: 10px;
	}

	.skeleton-row {
		height: 112px;
	}

	@keyframes skeleton-shimmer {
		to {
			transform: translateX(100%);
		}
	}

	.market-login-hint {
		padding: 26px 16px 28px;
		border-top: 1px solid rgba(217, 176, 92, 0.12);
		text-align: center;
	}

	.hint-title {
		color: #fff1cf;
		font-size: 15px;
		font-weight: 700;
	}

	.hint-desc {
		margin-top: 8px;
		color: rgba(240, 227, 197, 0.68);
		line-height: 1.5;
	}

	.login-btn {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		margin-top: 16px;
		height: 38px;
		padding: 0 18px;
		border-radius: 999px;
		border: 1px solid rgba(255, 228, 170, 0.18);
		background:
			linear-gradient(180deg, rgba(246, 204, 113, 0.18) 0%, rgba(141, 92, 31, 0.92) 100%);
		color: #fff1cf;
		font-weight: 700;
	}

	.market-error {
		background:
			radial-gradient(circle at top right, rgba(248, 215, 144, 0.08), transparent 30%),
			linear-gradient(180deg, rgba(28, 20, 10, 0.96) 0%, rgba(18, 12, 6, 0.98) 100%);
	}

	.error-title {
		color: #fff1cf;
		font-size: 15px;
		font-weight: 700;
	}

	.error-desc {
		margin-top: 8px;
		line-height: 1.5;
	}

	.balance-loading {
		display: flex;
		align-items: center;
		gap: 10px;
		flex: 1;
		min-width: 0;
		color: #f0e3c5;
		font-size: 12px;
		font-weight: 700;
		justify-content: center;
		min-height: 24px;
	}

	.loading-spinner {
		width: 22px;
		height: 22px;
		border: 2px solid rgba(240, 196, 110, 0.22);
		border-top-color: #f0c46e;
		border-radius: 50%;
		animation: balance-spin 0.8s linear infinite;
	}

	@keyframes balance-spin {
		from {
			transform: rotate(0deg);
		}
		to {
			transform: rotate(360deg);
		}
	}

	.retry-btn {
		margin-top: 14px;
		height: 38px;
		padding: 0 18px;
		border: 1px solid rgba(255, 228, 170, 0.18);
		border-radius: 999px;
		background: linear-gradient(180deg, rgba(246, 204, 113, 0.18) 0%, rgba(141, 92, 31, 0.92) 100%);
		color: #fff1cf;
		font-weight: 700;
	}

	.api-connect-card {
		position: relative;
		isolation: isolate;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		min-height: 300px;
		padding: 38px 24px 42px;
		text-align: center;
		overflow: hidden;
		background:
			radial-gradient(circle at 50% 28%, rgba(224, 177, 84, 0.16), transparent 30%),
			linear-gradient(180deg, rgba(27, 19, 9, 0.96), rgba(12, 8, 4, 0.98));
	}

	.api-connect-card::before {
		content: '';
		position: absolute;
		z-index: -1;
		width: 360px;
		height: 360px;
		border: 1px solid rgba(235, 196, 115, 0.06);
		border-radius: 50%;
		box-shadow: 0 0 80px rgba(191, 129, 40, 0.08);
	}

	.api-connect-copy {
		max-width: 430px;
	}

	.api-connect-copy h3 {
		margin: 0;
		color: #fff2d2;
		font-size: 21px;
		font-weight: 800;
		line-height: 1.35;
	}

	.api-connect-btn {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 10px;
		min-width: 150px;
		height: 44px;
		margin-top: 24px;
		padding: 0 22px;
		border: 1px solid rgba(255, 230, 174, 0.42);
		border-radius: 12px;
		background: linear-gradient(135deg, #9c6723 0%, #edc779 52%, #a66f29 100%);
		box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.38), 0 12px 28px rgba(114, 70, 15, 0.28);
		color: #1b1004;
		font-size: 14px;
		font-weight: 800;
		transition: transform .18s ease, filter .18s ease;
	}

	.api-connect-btn:active {
		transform: translateY(1px) scale(.98);
		filter: brightness(.94);
	}

	.amount {
		padding: 16px 18px;
		min-height: 88px;
		font-size: 12px;
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 14px;
		background:
			radial-gradient(circle at top right, rgba(248, 215, 144, 0.16), transparent 32%),
			linear-gradient(180deg, rgba(37, 25, 12, 0.98), rgba(22, 14, 7, 0.98));
		color: rgba(240, 227, 197, 0.68);
		box-shadow:
			inset 0 1px 0 rgba(255, 255, 255, 0.04),
			0 10px 24px rgba(0, 0, 0, 0.12);

		span {
			display: block;
			color: rgba(240, 227, 197, 0.64);
			font-size: 11px;
			font-weight: 700;
			letter-spacing: .06em;
			text-transform: uppercase;
		}

		strong {
			display: block;
			margin-top: 6px;
			color: #fff5df;
			font-size: 26px;
			font-weight: 800;
			line-height: 1.05;
			letter-spacing: 0.01em;
		}

		a {
			color: #f0c46e;
			font-weight: 700;
		}
	}

	.amount-has {
		align-items: stretch;
	}

	.amount-copy {
		min-width: 0;
		flex: 1;
		display: flex;
		flex-direction: column;
		justify-content: center;
	}

	.amount-note {
		margin-top: 8px;
		color: rgba(240, 227, 197, 0.56);
		font-size: 11px;
		line-height: 1.4;
	}

	.amount-empty {
		gap: 18px;

		.amount-copy {
			min-width: 0;
			flex: 1;
		}

		p {
			margin-top: 6px;
			color: rgba(240, 227, 197, 0.68);
			line-height: 1.5;
		}
	}

	:deep(.van-tabs__nav) {
		background: transparent;
	}

	:deep(.van-tab) {
		color: rgba(240, 227, 197, 0.58);
	}

	:deep(.van-tab--active) {
		color: #fff1cf;
		font-weight: 700;
	}

	:deep(.van-tabs__line) {
		background: linear-gradient(90deg, #8d5c1f, #f1cd86);
	}

	:deep(.van-tabs__content) {
		background: transparent;
	}

	@media (max-width: 767px) {
		.market-page {
			min-height: 100dvh;
			padding: 0;
		}

		.market-shell {
			width: 100%;
		}

		.market-hero,
		.market-panel {
			border-left: 0;
			border-right: 0;
			border-radius: 0;
		}

		.market-hero {
			padding: max(14px, env(safe-area-inset-top)) 14px 12px;
			border-top: 0;
			box-shadow: 0 8px 24px rgba(0, 0, 0, 0.24);
		}

		.page-header {
			height: 36px;
		}

		.page-title {
			font-size: 20px;
		}

		.hero-sub {
			font-size: 12px;
			line-height: 1.45;
		}

		.market-panel {
			margin-top: 8px;
		}

		.api-connect-card {
			min-height: 270px;
			padding: 32px 20px 36px;
		}

		.api-connect-copy h3 {
			font-size: 18px;
		}

		.api-connect-btn {
			width: min(220px, 78vw);
			height: 46px;
		}

		.amount {
			padding: 14px 14px;
			min-height: 0;
			gap: 10px;
		}

		.amount-has {
			flex-direction: column;
			align-items: stretch;
		}

		.amount-empty {
			align-items: center;
		}

		.amount strong {
			font-size: clamp(20px, 6vw, 24px);
			word-break: break-word;
		}

		.amount-note {
			display: none;
		}

		.market-skeleton {
			padding: 14px;
		}

		.skeleton-row {
			height: 96px;
		}
	}
</style>
