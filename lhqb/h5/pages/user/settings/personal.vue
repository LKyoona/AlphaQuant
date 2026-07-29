<template>
	<div class="settings-page">
	<van-nav-bar :title="$t('settingsPage.title')" left-arrow @click-left="$router.back()" />
	<van-cell-group>
		<van-uploader :after-read="afterRead">
			<van-cell :title="$t('settingsPage.head')" is-link>
					<template>
						<van-image class="header" :src="userInfo.avatar" />
					</template>
				</van-cell>
			</van-uploader>
		<van-cell :title="$t('settingsPage.nick')" :value="userInfo.user_nickname" is-link @click="openNickPop" />
		<van-cell :title="$t('settingsPage.signature')" :value="userInfo.signature" is-link @click="openSignPop" />
		</van-cell-group>
		<van-dialog class="settings-dialog" v-model:show="showNickPop" :title="$t('settingsPage.edit_nick')" show-cancel-button
			@confirm="handleSave('user_nickname')">
			<van-field v-model="user_nickname" class="dialog-input" input-align="center" :placeholder="$t('settingsPage.please')"
				clearable />
		</van-dialog>
		<van-dialog class="settings-dialog" v-model:show="showSignPop" :title="$t('settingsPage.edit_sign')" show-cancel-button
			@confirm="handleSave('signature')">
			<van-field v-model="signature" class="dialog-input dialog-textarea" rows="3" autosize type="textarea"
				maxlength="20" :placeholder="$t('settingsPage.edit_sign')" show-word-limit />
		</van-dialog>
	</div>
</template>

<script>
	import {
		Uploader
	} from 'vant'
	import {
		mapState,
		mapActions
	} from 'vuex'
	import defaultAvatar from '@/assets/images/header.png'

	export default {
		components: {
			[Uploader.name]: Uploader
		},
		data() {
			return {
				avatar: defaultAvatar,
				user_nickname: '',
				signature: '',
				showNickPop: false,
				showSignPop: false
			}
		},
		computed: {
			...mapState({
				userInfo({
					user
				}) {
					return user.userInfo || {}
				}
			})
		},
		watch: {
			userInfo(value) {
				this.user_nickname = value.user_nickname || ''
				this.signature = value.signature || ''
			}
		},
		methods: {
			...mapActions({
				upload: 'upload',
				editUserInfo: 'user/editUserInfo',
				getUserInfo: 'user/getUserInfo'
			}),
			afterRead(file) {
				const toast = this.$toast.loading()
				this.upload(file.file).then((res) => {
					this.avatar = res.data.url
					this.$nextTick(() => {
						this.handleSave('avatar')
					})
				}).finally(() => {
					toast.clear()
				})
			},
			openNickPop() {
				this.user_nickname = this.userInfo.user_nickname || ''
				this.showNickPop = true
			},
			openSignPop() {
				this.signature = this.userInfo.signature || ''
				this.showSignPop = true
			},
			handleSave(name) {
				const value = this[name]
				if (name !== 'avatar' && !value) {
					this.$toast(this.$t('please'))
					return
				}

				const toast = this.$toast.loading()
				this.editUserInfo({
					[name]: value
				}).then((res) => {
					this.$toast(res.msg)
					if (name === 'user_nickname') {
						this.showNickPop = false
					}
					if (name === 'signature') {
						this.showSignPop = false
					}
					return this.getUserInfo()
				}).finally(() => {
					toast.clear()
				})
			}
		}
	}
</script>

<style scoped lang="less">
	.settings-page {
		min-height: 100vh;
		background:
			radial-gradient(circle at 92% 8%, rgba(239, 190, 91, 0.1), transparent 28%),
			linear-gradient(180deg, rgba(25, 17, 9, 0.98), rgba(9, 7, 4, 0.98));
	}

	.header {
		float: right;
		width: 24px;
		height: 24px;
		border-radius: 50%;
		overflow: hidden;
	}

	:deep(.van-uploader) {
		display: block;

		.van-uploader__input-wrapper {
			flex: 1;
		}
	}

	:deep(.van-cell__title) {
		width: 5em;
		flex: 0 0 auto;
	}


	:deep(.settings-dialog .dialog-input.van-cell) {
		width: calc(100% - 40px);
		margin: 18px 20px 22px;
		padding: 14px 18px;
		border: 1px solid rgba(227, 182, 79, 0.34);
		border-radius: 16px;
		background: rgba(255, 255, 255, 0.035);
		box-shadow: none;
	}

	:deep(.settings-dialog .dialog-textarea.van-cell) {
		margin-bottom: 18px;
		padding-bottom: 10px;
	}

	:deep(.settings-dialog .dialog-input.van-cell::after) {
		display: none;
	}

	:deep(.settings-dialog .dialog-input .van-field__body),
	:deep(.settings-dialog .dialog-input .van-field__value),
	:deep(.settings-dialog .dialog-input .van-field__control) {
		border: 0 !important;
		outline: 0 !important;
		background: transparent !important;
		box-shadow: none !important;
	}

	:deep(.settings-dialog .dialog-input .van-field__control) {
		color: #f5ead7;
		resize: none;
	}

	:deep(.settings-dialog .dialog-input .van-field__control::placeholder) {
		color: rgba(245, 234, 215, 0.42);
	}

	:deep(.settings-dialog .dialog-input .van-field__word-limit) {
		color: rgba(245, 234, 215, 0.45);
		background: transparent;
	}

	@media (min-width: 768px) {
		.settings-page {
			max-width: 420px;
			margin: 0 auto;
			box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.04);
		}
	}
</style>
