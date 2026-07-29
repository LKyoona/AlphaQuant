<template>
	<div class="settings-page">
		<van-nav-bar :title="$t('settingsPage.title')" left-arrow @click-left="$router.back()" />
		<van-cell-group>
			<van-cell :title="$t('settingsPage.info')" is-link to="/user/settings/personal" />
			<van-cell v-if="userInfo.user_email" :title="$t('settingsPage.bind_email')" :value="userInfo.user_email" />
			<van-cell v-else-if="!thirdLoginEnabled" :title="$t('settingsPage.bind_email')" is-link to="/user/settings/bindEmail" />
			<van-cell v-if="!thirdLoginEnabled" :title="$t('settingsPage.pwd_login')" is-link to="/user/settings/changePwd" />
		</van-cell-group>
		<div class="btn">
			<van-button type="danger" block @click="exitLogin">
				{{ $t('settingsPage.exit') }}
			</van-button>
		</div>
	</div>
</template>

<script>
	import {
		mapState,
		mapActions
	} from 'vuex'
export default {
		computed: {
			...mapState({
				userInfo: ({
					user
				}) => user.userInfo,
				thirdLoginEnabled: ({
					thirdLoginEnabled
				}) => thirdLoginEnabled
			})
		},
		methods: {
			...mapActions({
				logOut: 'user/logOut'
			}),
			exitLogin() {
				this.$dialog.confirm({
					className: 'settings-dialog',
					title: this.$t('settingsPage.tip'),
					message: this.$t('settingsPage.tip_msg') + '？'
				}).then(() => {
					this.$toast.loading()
					this.logOut().then(() => {
						this.$toast.clear()
						this.$router.replace('/sign/login')
					}).catch(() => {
						this.$toast.clear()
						this.$router.replace('/sign/login')
					})
				}).catch(() => {})
			}
		}
	}
</script>

<style scoped lang="less">
	.settings-page {
		min-height: 100vh;
		padding-bottom: 24px;
		background:
			radial-gradient(circle at 92% 8%, rgba(239, 190, 91, 0.1), transparent 28%),
			linear-gradient(180deg, rgba(25, 17, 9, 0.98), rgba(9, 7, 4, 0.98));
	}

	.btn {
		margin: 15px;
	}

	:deep(.settings-dialog) {
		position: fixed !important;
		top: 50% !important;
		left: 50% !important;
		transform: translate(-50%, -50%) !important;
		margin: 0 !important;
	}

	:deep(.van-dialog) {
		width: min(92vw, 420px);
		border-radius: 20px;
		overflow: hidden;
		border: 1px solid rgba(227, 182, 79, 0.18);
		background: linear-gradient(180deg, #20150b 0%, #120c07 100%);
		box-shadow: 0 24px 60px rgba(0, 0, 0, 0.45);
	}

	:deep(.van-dialog__header) {
		color: #e7c36d;
		font-weight: 600;
		background: transparent;
	}

	:deep(.van-dialog__content) {
		background: transparent;
		color: #f3dca1;
	}

	:deep(.van-dialog__message) {
		color: #f3dca1;
		line-height: 1.6;
	}

	:deep(.van-dialog__footer .van-button) {
		font-weight: 600;
	}

	:deep(.van-dialog__footer .van-button--default) {
		color: #bca06b;
	}

	:deep(.van-dialog__footer .van-button--primary) {
		color: #f1d690;
		background: transparent;
		border-color: rgba(227, 182, 79, 0.18);
	}

	:deep(.van-dialog__footer) {
		background: rgba(10, 7, 4, 0.78);
	}

	:deep(.van-dialog__footer::before),
	:deep(.van-dialog__confirm::after) {
		border-color: rgba(227, 182, 79, 0.12);
	}

	@media (min-width: 768px) {
		.settings-page {
			max-width: 420px;
			margin: 0 auto;
			box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.05);
		}

		.btn {
			margin: 24px 20px;
		}
	}
</style>
