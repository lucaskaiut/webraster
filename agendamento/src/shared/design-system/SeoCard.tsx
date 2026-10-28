import type { ReactNode } from 'react'
import { Card, CardContent } from './Card'

interface SeoCardProps {
  children: ReactNode
}

export function SeoCard({ children }: SeoCardProps) {
  return (
    <Card>
      <CardContent>{children}</CardContent>
    </Card>
  )
}
