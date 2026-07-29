import {
  ActionSheet,
  Button,
  Cell,
  CellGroup,
  Checkbox,
  CheckboxGroup,
  Col,
  Dialog,
  Empty,
  Field,
  Form,
  Icon,
  Image,
  List,
  Locale,
  NavBar,
  Picker,
  Popup,
  Progress,
  PullRefresh,
  Radio,
  RadioGroup,
  Row,
  Tab,
  Tabs,
  Tag,
  Toast,
  closeToast,
  showConfirmDialog,
  showDialog,
  showFailToast,
  showLoadingToast,
  showSuccessToast,
  showToast
} from 'vant'
import enUS from 'vant/es/locale/lang/en-US'
import 'vant/lib/index.css'

export default defineNuxtPlugin((nuxtApp) => {
  const components = [
    Row,
    Col,
    Image,
    Icon,
    Cell,
    CellGroup,
    Button,
    Tab,
    Tabs,
    Checkbox,
    CheckboxGroup,
    Progress,
    Tag,
    ActionSheet,
    NavBar,
    Dialog,
    Form,
    Field,
    Toast,
    List,
    PullRefresh,
    Popup,
    Empty,
    Picker,
    Radio,
    RadioGroup
  ]

  components.forEach(component => nuxtApp.vueApp.use(component))
  Locale.use('en-US', enUS)

  const normalizeMessageOptions = options => (typeof options === 'string' ? { message: options } : options)

  const toast = (options = {}) => {
    closeToast()
    return showToast(normalizeMessageOptions(options))
  }
  toast.loading = (options = {}) => {
    const instance = showLoadingToast({
      duration: 0,
      forbidClick: true,
      ...normalizeMessageOptions(options)
    })

    if (instance && !instance.clear && instance.close) {
      instance.clear = instance.close
    }

    return instance || { clear: closeToast }
  }
  toast.success = (options = {}) => showSuccessToast(normalizeMessageOptions(options))
  toast.fail = (options = {}) => showFailToast(normalizeMessageOptions(options))
  toast.clear = closeToast

  const dialog = {
    alert: (options = {}) => showDialog(normalizeMessageOptions(options)),
    confirm: (options = {}) => showConfirmDialog(normalizeMessageOptions(options))
  }

  nuxtApp.vueApp.config.globalProperties.$toast = toast
  nuxtApp.vueApp.config.globalProperties.$dialog = dialog
})
