import { Card, CardContent, CardHeader } from '@/shared/design-system'
import { AlertConfigsMatrix } from '@/modules/alerts/components/AlertConfigsMatrix'

export function VehicleAlertConfigsCard() {
  return (
    <Card id="veiculo-alertas" className="scroll-mt-24">
      <CardHeader
        title="Alertas"
        description="Habilite os alarmes deste veículo e escolha os canais de cada um. In-app notifica o cliente; Monitoramento notifica o operador; Push e e-mail notificam ambos."
      />
      <CardContent>
        <AlertConfigsMatrix name="alert_configs" />
      </CardContent>
    </Card>
  )
}
