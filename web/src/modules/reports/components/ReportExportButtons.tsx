import { useState } from 'react'
import { Download, Printer } from 'lucide-react'
import { Button } from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'

interface ReportExportButtonsProps {
  onExportXlsx: () => void | Promise<void>
  onExportPdf: () => void
  disabled?: boolean
}

export function ReportExportButtons({ onExportXlsx, onExportPdf, disabled }: ReportExportButtonsProps) {
  const [exportingXlsx, setExportingXlsx] = useState(false)

  const handleExportXlsx = async () => {
    setExportingXlsx(true)
    try {
      await onExportXlsx()
    } finally {
      setExportingXlsx(false)
    }
  }

  return (
    <Can permission={Permission.REPORT_EXPORT}>
      <Button
        variant="secondary"
        onClick={handleExportXlsx}
        loading={exportingXlsx}
        disabled={disabled}
      >
        <Download className="size-4" />
        Exportar XLSX
      </Button>
      <Button variant="secondary" onClick={onExportPdf} disabled={disabled}>
        <Printer className="size-4" />
        Exportar PDF
      </Button>
    </Can>
  )
}
