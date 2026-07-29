export default defineNuxtPlugin(() => {
  const setViewportHeight = () => {
    const vh = window.innerHeight * 0.01
    document.documentElement.style.setProperty('--app-vh', `${vh}px`)
  }

  setViewportHeight()

  window.addEventListener('resize', setViewportHeight, { passive: true })
  window.addEventListener('orientationchange', setViewportHeight, { passive: true })
})
