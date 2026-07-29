import useClipboard from 'vue-clipboard3'

export default defineNuxtPlugin((nuxtApp) => {
  const { toClipboard } = useClipboard()

  nuxtApp.vueApp.directive('clipboard', {
    mounted(el, binding) {
      if (binding.arg === 'copy') {
        el.__clipboardText = binding.value
      }
      if (binding.arg === 'success') {
        el.__clipboardSuccess = binding.value
      }

      if (!el.__clipboardHandler) {
        el.__clipboardHandler = async () => {
          await toClipboard(el.__clipboardText || '')
          if (typeof el.__clipboardSuccess === 'function') {
            el.__clipboardSuccess()
          }
        }
        el.addEventListener('click', el.__clipboardHandler)
      }
    },
    updated(el, binding) {
      if (binding.arg === 'copy') {
        el.__clipboardText = binding.value
      }
      if (binding.arg === 'success') {
        el.__clipboardSuccess = binding.value
      }
    },
    unmounted(el) {
      el.removeEventListener('click', el.__clipboardHandler)
      delete el.__clipboardHandler
      delete el.__clipboardText
      delete el.__clipboardSuccess
    }
  })
})
