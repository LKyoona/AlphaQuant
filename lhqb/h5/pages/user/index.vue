<template>
	<div class="user-page">
		<div class="user-shell">
			<div class="user-hero">
				<div safe-area-inset-top>
					<van-row type="flex" justify="space-between" align="center" class="page-header">
						<van-col class="page-header-left">
							<p class="eyebrow">{{ $t('pageUser.account_center') }}</p>
							<h2 class="page-title">{{ $t('nav.user') }}</h2>
						</van-col>
						<van-col v-if="logged" class="page-header-right">
							<div class="header-actions">
								<button class="language-btn is-hidden" type="button" @click="toggleLocale" aria-hidden="true" tabindex="-1">
									<van-icon name="underway-o" />
									<span>{{ locale === 'zh' ? '中' : (locale === 'pt_br' ? 'PT' : 'EN') }}</span>
								</button>
								<nuxt-link to="/user/settings" class="settings-btn">
									<van-icon name="setting-o" color="#8e8678" size="20" />
								</nuxt-link>
							</div>
						</van-col>
					</van-row>
				</div>
				<van-row v-if="logged" type="flex" justify="space-between" align="center" gutter="20" class="user-info">
					<van-col style="flex: 1; min-width: 0">
						<van-row type="flex" align="center">
							<van-col>
								<van-image class="avatar" width="60" :src="userInfo.avatar"></van-image>
							</van-col>
							<van-col style="flex: 1; min-width: 0">
								<div class="user-nickname">{{ userInfo.user_nickname }}</div>
								<div class="user-account">{{ userInfo.signature }}</div>
								<div v-if="BUY_PACKAGE" class="user-tip">
									<template v-if="userInfo.vip_deadline > 0">
										{{ `${$t('pageUser.tip1')}：${ format(userInfo.vip_deadline, '{y}-{m}-{d}') }` }}
									</template>
									<template v-else>
										{{ $t('pageUser.tip2') }}
									</template>
								</div>
							</van-col>
						</van-row>
					</van-col>
					<van-col>
						<div v-if="hasCDKey" class="user-links" @click="$refs.cdkey.showCodePop = true">
							<van-icon name="coupon-o" />
							{{ $t('cdkey') }}
						</div>
					</van-col>
				</van-row>
				<van-row v-else class="user-info" type="flex" align="center" @click="$router.push('/sign/login')">
					<van-col>
						<van-icon class="avatar" name="user-circle-o" size="60" />
					</van-col>
					<van-col>
						<div class="user-nickname">{{ $t('pageUser.login') }}</div>
						<div class="user-account">{{ $t('pageUser.welcome') }}</div>
					</van-col>
				</van-row>
			</div>

			<van-cell-group class="list-group">
				<van-cell icon="records" :title="$t('pageUser.history')" is-link
					@click="handleLink('/user/caleandar')" />
				<van-cell v-if="hasCDKey" icon="points" :title="$t('pageUser.cdkey')" is-link
					@click="handleLink('/user/activation')" />
			</van-cell-group>

			<van-cell-group v-if="0" class="list-group">
				<van-cell icon="manager" :title="$t('pageUser.safety_lv')" is-link
					@click="handleLink('/user/verified')" />
				<van-cell icon="youzan-shield" :title="$t('pageUser.google_valid')" is-link
					@click="handleLink('/user/googleValid')" />
			</van-cell-group>

			<van-cell-group class="list-group">
				<van-cell icon="qr" :title="$t('pageUser.invite')" is-link @click="handleLink('/user/invite')" />
				<van-cell icon="newspaper-o" :title="$t('pageUser.news')" is-link @click="handleLink('/news')" />
				<van-cell icon="chat-o" :title="$t('pageUser.contact')" is-link @click="goHelp()" />
			</van-cell-group>
			<activation-popup ref="cdkey" />
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
	import ActivationPopup from '@/components/user/ActivationPopup'
	import {
		format
	} from '@/utils/time'
	import {
		BUY_PACKAGE,
		CHEKC_CDKEY
	} from '@/config/index'

	export default {
		components: {
			ActivationPopup
		},
		data() {
			return {
				BUY_PACKAGE,
				hasCDKey: CHEKC_CDKEY,
				activationCode: ''

			}
		},
		computed: {
			...mapState({
				logged: ({
					user
				}) => user.logged,
				userInfo: ({
					user
				}) => user.userInfo,
				initInfo: index => index.initInfo,
				locale: state => state.locale
			})
		},
		methods: {
			...mapActions({
				setLang: 'setLang'
			}),
				toggleLocale() {
					const locale = this.locale === 'zh' ? 'en' : (this.locale === 'en' ? 'pt_br' : 'zh')
				this.$i18n.locale = locale
				if (this.$root && this.$root.$i18n) {
					this.$root.$i18n.locale = locale
				}
				this.setLang(locale)
				this.$nextTick(() => {
					this.$forceUpdate()
				})
			},
			format,
			handleLink(path) {
				if (this.logged) {
					this.$router.push(path)
				} else {
					this.$router.push('/sign/login')
				}
			},
			goHelp() {
				const target = this.initInfo.system_customer_service
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
	.user-page {
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

	.user-page::before {
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

	.user-shell {
		position: relative;
		z-index: 1;
		max-width: 1200px;
		margin: 0 auto;
	}

	.user-hero {
		border: 1px solid rgba(217, 176, 92, 0.18);
		border-radius: 16px;
		background: linear-gradient(180deg, rgba(28, 20, 10, 0.96) 0%, rgba(18, 12, 6, 0.98) 100%);
		box-shadow: 0 18px 40px rgba(0, 0, 0, 0.34);
	}

	.page-header {
		min-height: 46px;
		padding: 16px 16px 0;
		margin-bottom: 8px;

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

	.header-actions {
		display: flex;
		align-items: center;
		gap: 8px;
	}

	.language-btn {
		display: inline-flex;
		align-items: center;
		gap: 5px;
		height: 34px;
		padding: 0 10px;
		border: 1px solid rgba(240, 196, 110, 0.42);
		border-radius: 17px;
		background: linear-gradient(135deg, rgba(240, 196, 110, 0.2), rgba(79, 51, 17, 0.72));
		color: #f5d48c;
		font-size: 12px;
		font-weight: 700;
		cursor: pointer;
		-webkit-tap-highlight-color: transparent;

		.van-icon {
			font-size: 16px;
		}
	}

	.language-btn.is-hidden {
		display: none;
	}

	.eyebrow {
		margin: 0 0 8px;
		color: #ddb46a;
		font-size: 11px;
		font-weight: 700;
	}

	.van-cell-group {
		margin-top: 12px;
		border: 1px solid rgba(217, 176, 92, 0.18);
		border-radius: 16px;
		overflow: hidden;
		background: linear-gradient(180deg, rgba(28, 20, 10, 0.96) 0%, rgba(18, 12, 6, 0.98) 100%);
		box-shadow: 0 18px 40px rgba(0, 0, 0, 0.34);
	}

	.user-info {
		padding: 12px 16px 18px;
		font-size: 14px;
		line-height: 1;

		.avatar {
			display: block;
			width: 60px;
			height: 60px;
			border-radius: 50%;
			margin-right: 15px;
			overflow: hidden;
		}
	}

	.user-nickname {
		font-size: 18px;
		font-weight: 500;
		color: #fff1cf;
	}

	.user-account {
		margin-top: 6px;
		color: rgba(240, 227, 197, 0.68);
	}

	.user-tip {
		font-size: 12px;
		margin-top: 5px;
		color: #f0c46e;
	}

	.user-links {
		min-width: 72px;
		padding: 10px 8px;
		border: 1px solid rgba(217, 176, 92, 0.18);
		border-radius: 12px;
		background:
			radial-gradient(circle at top right, rgba(248, 215, 144, 0.12), transparent 30%),
			linear-gradient(180deg, rgba(37, 25, 12, 0.98), rgba(22, 14, 7, 0.98));
		text-align: center;
		font-size: 12px;
		color: #f0c46e;

		.van-icon {
			font-size: 18px;
			display: block;
			margin-bottom: 5px;
		}
	}

	:deep(.van-cell) {
		background: transparent;
	}

	:deep(.van-cell::after) {
		left: 16px;
		right: 16px;
		border-color: rgba(217, 176, 92, 0.12);
	}

	:deep(.van-dialog__content) {
		padding: 30px 15px;

		.van-cell-group {
			margin-top: 0;
		}
	}

	.settings-btn {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 34px;
		height: 34px;
		border: 1px solid rgba(217, 176, 92, 0.18);
		border-radius: 50%;
		background:
			radial-gradient(circle at top right, rgba(248, 215, 144, 0.12), transparent 30%),
			linear-gradient(180deg, rgba(37, 25, 12, 0.98), rgba(22, 14, 7, 0.98));
	}
</style>
