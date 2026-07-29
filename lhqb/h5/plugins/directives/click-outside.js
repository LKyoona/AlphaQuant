function bind(el, binding) {
  unbind(el)
  const callback = binding.value

  if (typeof callback !== 'function') {
    return
  }

  let initialMacrotaskEnded = false
  setTimeout(() => {
    initialMacrotaskEnded = true
  }, 0)

  el[HANDLER] = (ev) => {
    const path = ev.path || (ev.composedPath ? ev.composedPath() : undefined)
    if (
      initialMacrotaskEnded &&
      (path ? !path.includes(el) : !el.contains(ev.target))
    ) {
      callback(ev)
    }
  }

  document.documentElement.addEventListener('click', el[HANDLER], false)
}

function unbind(el) {
  if (el[HANDLER]) {
    document.documentElement.removeEventListener('click', el[HANDLER], false)
    delete el[HANDLER]
  }
}

const HANDLER = '_click_outside_handler'

export default {
  mounted: bind,
  updated(el, binding) {
    if (binding.value === binding.oldValue) {
      return
    }
    bind(el, binding)
  },
  unmounted: unbind
}
