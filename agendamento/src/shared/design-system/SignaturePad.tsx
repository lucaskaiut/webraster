import { forwardRef, useEffect, useImperativeHandle, useRef, useState, type PointerEvent } from 'react'
import { Button } from './Button'
import { cn } from '@/shared/utils/cn'

export interface SignaturePadHandle {
  /** PNG da assinatura recortado ao redor do traço, ou null quando nada foi desenhado. */
  toDataURL: () => string | null
  clear: () => void
}

interface SignaturePadProps {
  className?: string
}

/**
 * Área para assinar com mouse ou toque. Use o ref para ler o PNG
 * (`toDataURL`) e para limpar (`clear`).
 */
export const SignaturePad = forwardRef<SignaturePadHandle, SignaturePadProps>(
  function SignaturePad({ className }, ref) {
    const canvasRef = useRef<HTMLCanvasElement>(null)
    const drawingRef = useRef(false)
    const [hasInk, setHasInk] = useState(false)

    useEffect(() => {
      const canvas = canvasRef.current
      if (!canvas) return

      const prepareContext = () => {
        const rect = canvas.getBoundingClientRect()
        const ratio = window.devicePixelRatio || 1

        canvas.width = Math.round(rect.width * ratio)
        canvas.height = Math.round(rect.height * ratio)

        const context = canvas.getContext('2d')
        if (!context) return

        context.scale(ratio, ratio)
        context.lineWidth = 2
        context.lineCap = 'round'
        context.lineJoin = 'round'
        context.strokeStyle = '#111827'
      }

      prepareContext()
      window.addEventListener('resize', prepareContext)

      return () => window.removeEventListener('resize', prepareContext)
    }, [])

    const clear = () => {
      const canvas = canvasRef.current
      const context = canvas?.getContext('2d')
      if (!canvas || !context) return

      context.clearRect(0, 0, canvas.width, canvas.height)
      setHasInk(false)
    }

    const pointFromEvent = (event: PointerEvent<HTMLCanvasElement>) => {
      const rect = event.currentTarget.getBoundingClientRect()

      return { x: event.clientX - rect.left, y: event.clientY - rect.top }
    }

    const handlePointerDown = (event: PointerEvent<HTMLCanvasElement>) => {
      event.preventDefault()

      const context = canvasRef.current?.getContext('2d')
      if (!context) return

      event.currentTarget.setPointerCapture(event.pointerId)
      drawingRef.current = true

      const { x, y } = pointFromEvent(event)
      context.beginPath()
      context.moveTo(x, y)
    }

    const handlePointerMove = (event: PointerEvent<HTMLCanvasElement>) => {
      if (!drawingRef.current) return

      const context = canvasRef.current?.getContext('2d')
      if (!context) return

      const { x, y } = pointFromEvent(event)
      context.lineTo(x, y)
      context.stroke()
      setHasInk(true)
    }

    const handlePointerUp = (event: PointerEvent<HTMLCanvasElement>) => {
      if (!drawingRef.current) return

      drawingRef.current = false

      if (event.currentTarget.hasPointerCapture(event.pointerId)) {
        event.currentTarget.releasePointerCapture(event.pointerId)
      }
    }

    const trimmedDataUrl = (): string | null => {
      const canvas = canvasRef.current
      const context = canvas?.getContext('2d')

      if (!canvas || !context || !hasInk) return null

      const { width, height } = canvas
      const { data } = context.getImageData(0, 0, width, height)

      let minX = width
      let minY = height
      let maxX = -1
      let maxY = -1

      for (let y = 0; y < height; y += 1) {
        for (let x = 0; x < width; x += 1) {
          if (data[(y * width + x) * 4 + 3] === 0) continue

          if (x < minX) minX = x
          if (x > maxX) maxX = x
          if (y < minY) minY = y
          if (y > maxY) maxY = y
        }
      }

      if (maxX < minX || maxY < minY) return null

      const cssWidth = canvas.getBoundingClientRect().width
      const ratio = cssWidth > 0 ? width / cssWidth : 1
      const padding = Math.round(12 * ratio)
      const inkWidth = maxX - minX + 1
      const inkHeight = maxY - minY + 1
      const output = document.createElement('canvas')

      output.width = inkWidth + padding * 2
      output.height = inkHeight + padding * 2

      const outputContext = output.getContext('2d')
      if (!outputContext) return null

      outputContext.drawImage(
        canvas,
        minX,
        minY,
        inkWidth,
        inkHeight,
        padding,
        padding,
        inkWidth,
        inkHeight,
      )

      return output.toDataURL('image/png')
    }

    useImperativeHandle(ref, () => ({
      toDataURL: trimmedDataUrl,
      clear,
    }))

    return (
      <div className={cn('space-y-2', className)}>
        <canvas
          ref={canvasRef}
          aria-label="Área para assinar"
          className="h-40 w-full touch-none rounded-xl bg-surface-2 dark:bg-white"
          onPointerDown={handlePointerDown}
          onPointerMove={handlePointerMove}
          onPointerUp={handlePointerUp}
          onPointerCancel={handlePointerUp}
        />
        <div className="flex items-center justify-between gap-3">
          <p className="text-xs text-muted">Assine com o mouse ou com o dedo.</p>
          <Button type="button" variant="secondary" size="sm" onClick={clear} disabled={!hasInk}>
            Limpar
          </Button>
        </div>
      </div>
    )
  },
)
