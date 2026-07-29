<template>
  <div class="globe-stage" :class="{ 'is-fallback': fallbackMode }" aria-hidden="true">
    <div class="radar-ring radar-ring-one"></div>
    <div class="radar-ring radar-ring-two"></div>
    <canvas ref="canvas" class="globe-canvas"></canvas>
	<div class="fallback-globe"><span></span></div>
	<div class="crypto-symbols">
	  <span class="crypto-chip chip-btc"></span>
	  <span class="crypto-chip chip-eth"></span>
	  <span class="crypto-chip chip-usdt"></span>
	  <span class="crypto-chip chip-star"></span>
	</div>
	<div class="traffic-layer">
	  <i v-for="particle in 8" :key="`flight-${particle}`" :class="`flight-particle flight-${particle}`"></i>
	</div>
    <span v-for="pulse in 3" :key="pulse" :class="`signal-wave signal-wave-${pulse}`"></span>
  </div>
</template>

<script>
const ARC_GROUPS = [
  [
    { from: [31.23, 121.47], to: [40.71, -74.01] },
    { from: [35.68, 139.69], to: [37.77, -122.42] },
    { from: [1.35, 103.82], to: [51.5, -0.12] },
    { from: [25.2, 55.27], to: [-23.55, -46.63] },
    { from: [51.5, -0.12], to: [-33.87, 151.21] }
  ],
  [
    { from: [22.32, 114.17], to: [48.86, 2.35] },
    { from: [19.08, 72.88], to: [52.52, 13.4] },
    { from: [40.71, -74.01], to: [-33.92, 18.42] },
    { from: [37.77, -122.42], to: [1.35, 103.82] },
    { from: [-23.55, -46.63], to: [31.23, 121.47] }
  ],
  [
    { from: [-34.6, -58.38], to: [51.5, -0.12] },
    { from: [43.65, -79.38], to: [35.68, 139.69] },
    { from: [19.43, -99.13], to: [25.2, 55.27] },
    { from: [-6.2, 106.85], to: [40.71, -74.01] },
    { from: [-33.87, 151.21], to: [48.86, 2.35] }
  ]
]

export default {
  data () {
    return {
      globe: null,
      animationFrame: 0,
      resizeObserver: null,
      resizeTimer: 0,
      globeSize: 0,
      fallbackMode: false,
      healthTimer: 0,
      lastRenderedAt: 0,
      phi: 0.35,
      arcGroupIndex: 0,
      arcCycleStartedAt: 0,
      lastFrameAt: 0,
      frameInterval: 0,
      isIOS: false,
      reduceMotion: false
    }
  },
  mounted () {
    this.$nextTick(() => {
      const canvas = this.$refs.canvas
      canvas.addEventListener('webglcontextlost', this.handleContextLost, false)
      canvas.addEventListener('webglcontextrestored', this.handleContextRestored, false)
      document.addEventListener('visibilitychange', this.handleVisibilityChange)
      this.createGlobe()
      this.watchAnimationHealth()
      this.resizeObserver = new ResizeObserver(() => {
        const nextSize = Math.max(180, Math.round(this.$el.clientWidth))
        if (Math.abs(nextSize - this.globeSize) < 2) {
          return
        }
        window.clearTimeout(this.resizeTimer)
        this.resizeTimer = window.setTimeout(() => this.resizeGlobe(nextSize), 120)
      })
      this.resizeObserver.observe(this.$el)
    })
  },
  beforeUnmount () {
    const canvas = this.$refs.canvas
    if (canvas) {
      canvas.removeEventListener('webglcontextlost', this.handleContextLost)
      canvas.removeEventListener('webglcontextrestored', this.handleContextRestored)
    }
    document.removeEventListener('visibilitychange', this.handleVisibilityChange)
    this.destroyGlobe()
    window.clearTimeout(this.resizeTimer)
    window.clearTimeout(this.healthTimer)
    if (this.resizeObserver) {
      this.resizeObserver.disconnect()
    }
  },
  methods: {
    async createGlobe () {
      const canvas = this.$refs.canvas
      if (!canvas || !this.$el) {
        return
      }
      this.destroyGlobe()
      let createGlobe
      try {
        const globeModule = await import('cobe')
        createGlobe = globeModule.default
      } catch (error) {
        this.fallbackMode = true
        return
      }
      if (!this.$refs.canvas) {
        return
      }
      const size = Math.max(180, Math.round(this.$el.clientWidth))
      const pixelRatio = Math.min(window.devicePixelRatio || 1, 2)
      const isIOS = /iPad|iPhone|iPod/i.test(navigator.userAgent) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
      this.globeSize = size
      this.isIOS = isIOS
      this.frameInterval = isIOS || size < 250 ? 1000 / 30 : 0
      this.reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches
      const globe = createGlobe(canvas, {
        devicePixelRatio: pixelRatio,
        width: size,
        height: size,
        phi: this.phi,
        theta: 0.18,
        dark: 1,
        diffuse: 1.7,
        mapSamples: size < 250 ? 14000 : 24000,
        mapBrightness: 9,
        mapBaseBrightness: 0.08,
        baseColor: [0.09, 0.055, 0.018],
        markerColor: [1, 0.68, 0.18],
        glowColor: [0.66, 0.39, 0.08],
        arcColor: [1, 0.66, 0.16],
        arcWidth: 0.55,
        arcHeight: 0.24,
        markerElevation: 0.035,
        opacity: 0.98,
        markers: [
          { location: [31.23, 121.47], size: 0.055 },
          { location: [35.68, 139.69], size: 0.045 },
          { location: [1.35, 103.82], size: 0.04 },
		  { location: [22.32, 114.17], size: 0.038 },
		  { location: [19.08, 72.88], size: 0.038 },
          { location: [25.2, 55.27], size: 0.045 },
          { location: [51.5, -0.12], size: 0.05 },
		  { location: [48.86, 2.35], size: 0.036 },
		  { location: [52.52, 13.4], size: 0.034 },
          { location: [40.71, -74.01], size: 0.055 },
          { location: [37.77, -122.42], size: 0.045 },
		  { location: [43.65, -79.38], size: 0.035 },
		  { location: [19.43, -99.13], size: 0.032 },
          { location: [-23.55, -46.63], size: 0.04 },
		  { location: [-34.6, -58.38], size: 0.032 },
		  { location: [-33.92, 18.42], size: 0.035 },
		  { location: [-33.87, 151.21], size: 0.04 },
		  { location: [-6.2, 106.85], size: 0.034 }
        ],
        arcs: ARC_GROUPS[this.arcGroupIndex]
      })
      const webglContext = canvas.getContext('webgl2') || canvas.getContext('webgl')
      if (!webglContext) {
        globe.destroy()
        this.fallbackMode = true
        return
      }
      this.globe = globe
      this.fallbackMode = false
      this.arcCycleStartedAt = performance.now()
      this.lastFrameAt = 0
      this.lastRenderedAt = Date.now()
      this.animateGlobe()
    },
    resizeGlobe (size) {
      if (!this.globe) {
        this.createGlobe()
        return
      }
      this.globeSize = size
      this.frameInterval = this.isIOS || size < 250 ? 1000 / 30 : 0
      this.globe.update({
        width: size,
        height: size,
        mapSamples: size < 250 ? 14000 : 24000
      })
    },
    animateGlobe (frameTime) {
      if (!this.globe) {
        return
      }
      const now = Number.isFinite(frameTime) ? frameTime : performance.now()
      if (this.frameInterval && this.lastFrameAt && now - this.lastFrameAt < this.frameInterval) {
        this.animationFrame = window.requestAnimationFrame(this.animateGlobe)
        return
      }
      const delta = this.lastFrameAt ? Math.min(now - this.lastFrameAt, 64) : 16.67
      this.lastFrameAt = now
      const cycleDuration = this.reduceMotion ? 5200 : 2600
      const elapsed = now - this.arcCycleStartedAt
      if (elapsed >= cycleDuration) {
        this.arcGroupIndex = (this.arcGroupIndex + 1) % ARC_GROUPS.length
        this.arcCycleStartedAt = now
        this.globe.update({ arcs: ARC_GROUPS[this.arcGroupIndex] })
      }
      const cycleProgress = Math.min((now - this.arcCycleStartedAt) / cycleDuration, 1)
      const arcGlow = 0.06 + Math.pow(Math.sin(Math.PI * cycleProgress), 1.35) * 0.94
      const rotationSpeed = this.reduceMotion ? 0.0012 : 0.0045
      this.phi += rotationSpeed * (delta / 16.67)
      this.globe.update({
        phi: this.phi,
        arcColor: [arcGlow, arcGlow * 0.66, arcGlow * 0.16]
      })
      this.lastRenderedAt = Date.now()
      this.fallbackMode = false
      this.animationFrame = window.requestAnimationFrame(this.animateGlobe)
    },
    watchAnimationHealth () {
      window.clearTimeout(this.healthTimer)
      this.healthTimer = window.setTimeout(() => {
        if (!document.hidden && this.globe && Date.now() - this.lastRenderedAt > 2500) {
          this.fallbackMode = true
        }
        this.watchAnimationHealth()
      }, 1800)
    },
    handleVisibilityChange () {
      if (document.hidden) {
        if (this.animationFrame) {
          window.cancelAnimationFrame(this.animationFrame)
          this.animationFrame = 0
        }
        return
      }
      this.lastFrameAt = 0
      if (this.globe && !this.animationFrame) {
        this.animationFrame = window.requestAnimationFrame(this.animateGlobe)
      }
    },
    handleContextLost (event) {
      event.preventDefault()
      this.fallbackMode = true
      if (this.animationFrame) {
        window.cancelAnimationFrame(this.animationFrame)
        this.animationFrame = 0
      }
    },
    handleContextRestored () {
      window.clearTimeout(this.resizeTimer)
      this.resizeTimer = window.setTimeout(() => this.createGlobe(), 80)
    },
    destroyGlobe () {
      if (this.animationFrame) {
        window.cancelAnimationFrame(this.animationFrame)
        this.animationFrame = 0
      }
      if (this.globe) {
        this.globe.destroy()
        this.globe = null
      }
    }
  }
}
</script>

<style scoped lang="less">
.globe-stage {
  position: relative;
  width: 270px;
  height: 270px;
  margin-bottom: 12px;
  isolation: isolate;
	perspective: 800px;
}

.globe-canvas {
  position: relative;
  z-index: 2;
  display: block;
  width: 100%;
  height: 100%;
  filter: drop-shadow(0 0 22px rgba(221, 153, 43, .2));
  transition: opacity .35s ease;
}

.fallback-globe {
  position: absolute;
  z-index: 2;
  inset: 13%;
  display: none;
  overflow: hidden;
  border: 1px solid rgba(255, 209, 111, .35);
  border-radius: 50%;
  background:
    radial-gradient(circle at 33% 27%, rgba(255, 224, 151, .28), transparent 17%),
    radial-gradient(circle at 50% 55%, transparent 48%, rgba(232, 158, 38, .18) 72%, rgba(4, 3, 1, .8) 100%),
    linear-gradient(145deg, #3b250e, #120c05 66%);
  box-shadow:
    inset -22px -14px 30px rgba(0, 0, 0, .72),
    inset 12px 8px 24px rgba(255, 198, 78, .14),
    0 0 30px rgba(224, 151, 37, .26);
}

.fallback-globe::before {
  content: '';
  position: absolute;
  inset: 0;
  width: 210%;
  background:
    radial-gradient(ellipse 14% 20% at 8% 35%, rgba(246, 190, 77, .78) 0 46%, transparent 50%),
    radial-gradient(ellipse 10% 25% at 21% 62%, rgba(226, 156, 48, .72) 0 43%, transparent 48%),
    radial-gradient(ellipse 18% 16% at 38% 31%, rgba(250, 200, 91, .76) 0 45%, transparent 50%),
    radial-gradient(ellipse 12% 22% at 52% 57%, rgba(218, 143, 38, .7) 0 44%, transparent 49%),
    radial-gradient(ellipse 15% 18% at 69% 36%, rgba(246, 190, 77, .78) 0 46%, transparent 50%),
    radial-gradient(ellipse 11% 24% at 84% 61%, rgba(226, 156, 48, .72) 0 43%, transparent 48%);
  filter: drop-shadow(0 0 5px rgba(255, 195, 76, .32));
  animation: fallback-map-drift 8s linear infinite;
}

.fallback-globe::after {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: 50%;
  background: repeating-linear-gradient(0deg, transparent 0 15px, rgba(255, 213, 126, .08) 16px 17px);
}

.fallback-globe span {
  position: absolute;
  z-index: 2;
  top: 17%;
  left: 20%;
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #ffe5a1;
  box-shadow: 45px 32px 0 -1px #ffc65c, 88px 5px 0 -1px #ffe5a1, 112px 71px 0 -1px #ffc65c, 0 0 10px #ffb735;
  animation: fallback-node-pulse 1.8s ease-in-out infinite alternate;
}

.is-fallback .globe-canvas {
  opacity: 0;
}

.is-fallback .fallback-globe {
  display: block;
}

.crypto-symbols,
.traffic-layer {
  position: absolute;
  z-index: 4;
  inset: 0;
  pointer-events: none;
}

.traffic-layer {
	transform: scale(1.16);
	transform-origin: top left;
}

.crypto-chip {
  position: absolute;
	width: 56px;
	height: 56px;
	background-image: url('@/assets/images/crypto-coin-sprite.webp');
	background-repeat: no-repeat;
	background-size: 200% 200%;
	filter: drop-shadow(0 7px 6px rgba(0, 0, 0, .68)) drop-shadow(0 0 11px rgba(235, 161, 39, .38));
	transform-style: preserve-3d;
	will-change: transform, filter;
	animation: coin-float 2.4s ease-in-out infinite alternate;
}

.crypto-chip::after {
	content: '';
	position: absolute;
	inset: 5px;
	border-radius: 50%;
	background: linear-gradient(115deg, transparent 25%, rgba(255, 255, 255, .5) 43%, transparent 61%);
	background-size: 220% 100%;
	mix-blend-mode: screen;
	animation: coin-shine 2.8s ease-in-out infinite;
}

.chip-btc { top: 12%; left: -2%; background-position: 0 0; }
.chip-eth { top: 4%; right: 3%; background-position: 100% 0; animation-delay: -.6s; }
.chip-usdt { right: -3%; bottom: 17%; background-position: 0 100%; animation-delay: -1.2s; }
.chip-star { bottom: 2%; left: 11%; background-position: 100% 100%; animation-delay: -1.8s; }

.flight-particle {
  position: absolute;
  top: 0;
  left: 0;
  width: 4px;
  height: 4px;
  border-radius: 50%;
  background: #fff1be;
  box-shadow: 0 0 5px #fff0b2, 0 0 12px #ffb528, 0 0 20px rgba(255, 164, 24, .78);
	offset-rotate: auto;
	will-change: offset-distance, opacity, transform;
	animation: flight-stream 1.35s linear infinite;
}

.flight-particle::after {
	content: '';
	position: absolute;
	top: 1px;
	right: 2px;
	width: 22px;
	height: 2px;
	border-radius: 999px;
	background: linear-gradient(90deg, transparent, rgba(255, 172, 34, .3) 35%, #ffe5a1 100%);
	filter: blur(.2px);
}

.flight-1 { offset-path: path('M 41 128 Q 108 27 188 89'); animation-duration: 1.05s; }
.flight-2 { offset-path: path('M 50 80 Q 122 30 183 132'); animation-duration: 1.48s; animation-delay: -.25s; }
.flight-3 { offset-path: path('M 33 151 Q 110 77 199 118'); animation-duration: 1.18s; animation-delay: -.5s; }
.flight-4 { offset-path: path('M 74 194 Q 109 75 161 44'); animation-duration: 1.72s; animation-delay: -.75s; }
.flight-5 { offset-path: path('M 40 105 Q 126 164 191 77'); animation-duration: 1.28s; animation-delay: -1s; }
.flight-6 { offset-path: path('M 66 52 Q 137 86 177 177'); animation-duration: 1.56s; animation-delay: -.4s; }
.flight-7 { offset-path: path('M 29 119 Q 99 202 190 145'); animation-duration: 1.12s; animation-delay: -.85s; }
.flight-8 { offset-path: path('M 61 174 Q 113 25 201 111'); animation-duration: 1.64s; animation-delay: -1.15s; }

.radar-ring {
  position: absolute;
  z-index: 1;
  inset: 15%;
  border: 1px solid rgba(245, 197, 102, .2);
  border-radius: 50%;
  box-shadow: 0 0 22px rgba(211, 139, 33, .08);
}

.radar-ring::after {
  content: '';
  position: absolute;
  top: 5%;
  left: 19%;
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: #ffe19a;
  box-shadow: 0 0 8px #ffc452, 0 0 18px rgba(255, 175, 41, .78);
}

.radar-ring-one {
  animation: radar-orbit 5.5s linear infinite;
}

.radar-ring-two {
  inset: 8% 24%;
  animation: radar-orbit-alt 7s linear infinite reverse;
}

.signal-wave {
  position: absolute;
  z-index: 0;
  inset: 24%;
  border: 1px solid rgba(238, 181, 71, .24);
  border-radius: 50%;
  animation: signal-ripple 3.8s ease-out infinite;
}

.signal-wave-2 { animation-delay: -1.25s; }
.signal-wave-3 { animation-delay: -2.5s; }

@keyframes radar-orbit {
  from { transform: rotate(18deg) rotateY(68deg); }
  to { transform: rotate(378deg) rotateY(68deg); }
}

@keyframes radar-orbit-alt {
  from { transform: rotate(-28deg) rotateX(67deg); }
  to { transform: rotate(332deg) rotateX(67deg); }
}

@keyframes signal-ripple {
  0% { opacity: .7; transform: scale(.55); }
  78%, 100% { opacity: 0; transform: scale(1.75); }
}

@keyframes flight-stream {
  0% { opacity: 0; offset-distance: 0%; transform: scale(.55); }
  12% { opacity: 1; }
  82% { opacity: 1; }
  100% { opacity: 0; offset-distance: 100%; transform: scale(1.35); }
}

@keyframes coin-float {
	from { transform: translateY(-4px) rotateZ(-5deg) rotateY(-12deg) scale(.96); filter: brightness(.9) drop-shadow(0 7px 6px rgba(0, 0, 0, .68)) drop-shadow(0 0 9px rgba(235, 161, 39, .3)); }
	to { transform: translateY(5px) rotateZ(5deg) rotateY(13deg) scale(1.04); filter: brightness(1.16) drop-shadow(0 10px 8px rgba(0, 0, 0, .72)) drop-shadow(0 0 16px rgba(245, 177, 58, .5)); }
}

@keyframes coin-shine {
	0%, 24% { background-position: 130% 0; opacity: 0; }
	42% { opacity: .8; }
	65%, 100% { background-position: -90% 0; opacity: 0; }
}

@keyframes fallback-map-drift {
  from { transform: translateX(0); }
  to { transform: translateX(-48%); }
}

@keyframes fallback-node-pulse {
  from { opacity: .55; transform: scale(.8); }
  to { opacity: 1; transform: scale(1.22); }
}

@media (max-width: 767px) {
  .globe-stage {
    width: 230px;
    height: 230px;
    margin-bottom: 8px;
  }

  .crypto-chip {
	width: 46px;
	height: 46px;
  }

  .traffic-layer {
	transform: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  .radar-ring-one { animation-duration: 11s; }
  .radar-ring-two { animation-duration: 14s; }
  .signal-wave { animation-duration: 7.6s; }
  .flight-particle { animation-duration: 2.7s; }
  .crypto-chip { animation-duration: 4.8s; }
  .fallback-globe::before { animation-duration: 16s; }
}
</style>
