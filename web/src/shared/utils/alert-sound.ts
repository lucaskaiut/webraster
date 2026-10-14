/**
 * Disparo de som para novas notificações no painel web.
 *
 * Os arquivos ficam em `public/sounds` (mesmos do app) e o navegador só
 * permite tocar áudio depois da primeira interação do usuário. Os sons
 * solicitados antes disso ficam pendentes e são reproduzidos na primeira
 * interação da página.
 */

export type AlertSoundKey = 'default' | 'chime' | 'alert' | 'siren'

export const ALERT_SOUND_KEYS: AlertSoundKey[] = ['default', 'chime', 'alert', 'siren']

const SOUND_FILES: Record<AlertSoundKey, string> = {
  default: '/sounds/alert.wav',
  chime: '/sounds/chime.wav',
  alert: '/sounds/alert.wav',
  siren: '/sounds/siren.wav',
}

const MUTE_STORAGE_KEY = 'webraster-alert-sound-muted'

let interacted = false
let pendingSound: AlertSoundKey | null = null
const audioCache = new Map<string, HTMLAudioElement>()

function ensureInteractionTracking(): void {
  if (typeof window === 'undefined' || interacted) return

  const unlock = () => {
    interacted = true

    if (pendingSound) {
      const sound = pendingSound
      pendingSound = null
      playAlertSound(sound)
    }

    window.removeEventListener('pointerdown', unlock, true)
    window.removeEventListener('keydown', unlock, true)
  }

  window.addEventListener('pointerdown', unlock, true)
  window.addEventListener('keydown', unlock, true)
}

function audioFor(src: string): HTMLAudioElement {
  const cached = audioCache.get(src)
  if (cached) return cached

  const audio = new Audio(src)
  audio.preload = 'auto'
  audioCache.set(src, audio)

  return audio
}

export function isAlertSoundMuted(): boolean {
  if (typeof window === 'undefined') return false

  try {
    return window.localStorage.getItem(MUTE_STORAGE_KEY) === '1'
  } catch {
    return false
  }
}

export function setAlertSoundMuted(muted: boolean): void {
  if (typeof window === 'undefined') return

  try {
    window.localStorage.setItem(MUTE_STORAGE_KEY, muted ? '1' : '0')
  } catch {
    // Ignora falhas de armazenamento (modo privado, cota etc.).
  }
}

export function playAlertSound(key: AlertSoundKey = 'default'): void {
  if (typeof window === 'undefined' || isAlertSoundMuted()) return

  ensureInteractionTracking()

  if (!interacted) {
    pendingSound = key
    return
  }

  try {
    const audio = audioFor(SOUND_FILES[key] ?? SOUND_FILES.default)
    audio.currentTime = 0
    void audio.play().catch(() => {
      // Autoplay bloqueado: guarda para a próxima interação.
      pendingSound = key
      interacted = false
      ensureInteractionTracking()
    })
  } catch {
    // Ignora ambientes sem suporte a áudio.
  }
}
