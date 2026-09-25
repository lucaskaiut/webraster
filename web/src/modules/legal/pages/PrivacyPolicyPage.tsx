import { AppLogo } from '@/shared/brand/AppLogo'
import { Card, CardContent, ThemeToggle } from '@/shared/design-system'

/**
 * Página pública (sem autenticação) para atender aos requisitos da Google Play:
 * URL pública, legível em navegador, específica do app e com contato.
 * https://support.google.com/googleplay/android-developer/answer/10144311
 */
const CONTACT_EMAIL = 'contato@noxtecnologias.com.br'
const UPDATED_AT = '25 de setembro de 2026'
const APP_NAME = 'Web Raster'

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className="space-y-2">
      <h2 className="text-base font-semibold text-foreground">{title}</h2>
      <div className="space-y-2 text-sm leading-relaxed text-muted">{children}</div>
    </section>
  )
}

function List({ items }: { items: string[] }) {
  return (
    <ul className="list-disc space-y-1 pl-5">
      {items.map((item) => (
        <li key={item}>{item}</li>
      ))}
    </ul>
  )
}

export default function PrivacyPolicyPage() {
  return (
    <div className="min-h-dvh bg-background">
      <header className="bg-surface px-5 py-5 shadow-card">
        <div className="mx-auto flex max-w-3xl items-center justify-between gap-4">
          <AppLogo size="sm" />
          <ThemeToggle />
        </div>
      </header>

      <main className="mx-auto max-w-3xl px-5 py-8">
        <h1 className="text-2xl font-bold text-foreground">Política de Privacidade</h1>
        <p className="mt-2 text-xs text-subtle">
          Aplicativo: {APP_NAME} (Android/iOS) e painel web · Última atualização: {UPDATED_AT}
        </p>

        <Card className="mt-6">
          <CardContent className="space-y-8">
            <Section title="1. Sobre esta política">
              <p>
                Esta Política de Privacidade descreve como o aplicativo {APP_NAME} e o painel web
                coletam, usam, armazenam, compartilham e protegem dados pessoais. Ela se aplica ao
                aplicativo disponibilizado na Google Play Store e à versão para iOS, bem como ao
                painel administrativo em {new URL('https://noxtecnologias.com.br').host}.
              </p>
              <p>
                O {APP_NAME} é um sistema de gestão e rastreamento de frotas contratado por empresas
                (transportadoras, locadoras e gestores de frota). O uso é profissional: as contas são
                criadas pela empresa contratante para seus colaboradores e clientes.
              </p>
            </Section>

            <Section title="2. Dados que coletamos">
              <p>
                <strong className="text-foreground">2.1 Cadastro e identificação.</strong> Nome,
                e-mail, telefone, documento (CPF/CNPJ), empresa/organização vinculada, perfil de
                acesso e permissões.
              </p>
              <p>
                <strong className="text-foreground">2.2 Localização de veículos.</strong> Coordenadas
                geográficas (latitude/longitude), velocidade, direção, altitude, endereço aproximado
                e data/hora, recebidas dos rastreadores instalados nos veículos monitorados.
              </p>
              <p>
                <strong className="text-foreground">2.3 Localização do dispositivo móvel.</strong> Se
                você autorizar a permissão de localização no aplicativo, usamos a posição do celular
                exclusivamente para exibir sua localização atual no mapa. Essa localização não é
                usada para rastrear você, não é compartilhada e você pode revogar a permissão a
                qualquer momento nas configurações do aparelho.
              </p>
              <p>
                <strong className="text-foreground">2.4 Veículos e operação.</strong> Placa, marca,
                modelo, cor, ano, tipo, odômetro, imagens/fotos do veículo, equipamentos (IMEI),
                cercas eletrônicas, pontos de interesse e histórico de rotas.
              </p>
              <p>
                <strong className="text-foreground">2.5 Alertas e eventos.</strong> Eventos gerados
                pelos veículos, como ignição ligada/desligada, excesso de velocidade, bateria baixa,
                dispositivo offline, SOS, alarmes e violação.
              </p>
              <p>
                <strong className="text-foreground">2.6 Contratos e assinaturas.</strong> Quando
                aplicável, nome, CPF, data de nascimento e imagem da assinatura do titular do
                contrato, para formalização e comprovação.
              </p>
              <p>
                <strong className="text-foreground">2.7 Notificações.</strong> Token de notificação
                push do aparelho (Expo/Firebase Cloud Messaging) e plataforma (Android/iOS), usado
                para enviar alertas e avisos.
              </p>
              <p>
                <strong className="text-foreground">2.8 Financeiro.</strong> Dados de cobrança
                (valores, vencimentos e situação) e informações cadastrais da empresa cliente. Dados
                de cartão de crédito são processados diretamente pela instituição de pagamento
                parceira, não sendo armazenados pelo {APP_NAME}.
              </p>
              <p>
                <strong className="text-foreground">2.9 Uso, logs e segurança.</strong> Endereço IP,
                data/hora de acesso, ações realizadas no painel (trilha de auditoria) e cookies
                estritamente necessários para a sessão no painel web.
              </p>
            </Section>

            <Section title="3. Como usamos os dados">
              <List
                items={[
                  'Prestar o serviço de rastreamento e gestão de frota, exibindo veículos e rotas no mapa.',
                  'Gerar alertas, eventos e notificações (inclusive push) relacionados aos veículos.',
                  'Gerenciar cadastros de empresas, usuários, veículos, equipamentos e contratos.',
                  'Processar cobranças e a gestão financeira das assinaturas.',
                  'Garantir segurança, prevenir fraudes e manter registros de auditoria.',
                  'Prestar suporte, responder solicitações e cumprir obrigações legais e regulatórias.',
                ]}
              />
              <p>
                As bases legais utilizadas são a execução de contrato, o cumprimento de obrigação
                legal, o legítimo interesse e, quando exigido, o seu consentimento (por exemplo, para
                a localização do celular e notificações push).
              </p>
            </Section>

            <Section title="4. Compartilhamento com terceiros">
              <p>
                Não vendemos dados pessoais. Compartilhamos apenas o necessário com operadores que
                apoiam o funcionamento do serviço:
              </p>
              <List
                items={[
                  'Google (Google Maps) — exibição de mapas e conversão de coordenadas em endereços.',
                  'Expo e Firebase Cloud Messaging (Google) — entrega de notificações push no aparelho.',
                  'Provedores de infraestrutura e hospedagem em nuvem — servidores, banco de dados e backups.',
                  'Provedores de e-mail — envio de mensagens transacionais (alertas e cobranças).',
                  'Instituição de pagamento parceira — processamento de cobranças e pagamentos.',
                  'Servidor de rastreamento (Traccar) — comunicação com os rastreadores dos veículos.',
                ]}
              />
              <p>
                Também podemos compartilhar dados mediante ordem judicial, requisição de autoridade
                competente ou para exercer direitos legais.
              </p>
            </Section>

            <Section title="5. Segurança">
              <p>
                Adotamos medidas técnicas e administrativas para proteger os dados: tráfego
                criptografado (HTTPS/TLS), controle de acesso por perfil e permissão, senhas
                armazenadas com hash, registro de auditoria das ações e backups. Apesar disso, nenhum
                sistema é totalmente imune; recomendamos manter sua senha em sigilo e não
                compartilhar seu acesso.
              </p>
            </Section>

            <Section title="6. Retenção e exclusão de dados">
              <p>
                Mantemos os dados enquanto a conta e o contrato estiverem ativos e pelo período
                necessário ao cumprimento de obrigações legais, fiscais e regulatórias. Encerrado o
                contrato, os dados são eliminados ou anonimizados, salvo quando a lei exigir
                retenção.
              </p>
              <p>
                <strong className="text-foreground">Como solicitar a exclusão:</strong> envie um
                e-mail para {CONTACT_EMAIL}, a partir do endereço cadastrado, com o assunto
                &quot;Exclusão de dados&quot;. Confirmaremos o recebimento e atenderemos a solicitação
                nos prazos aplicáveis. Alguns dados podem ser mantidos quando houver obrigação legal,
                sendo informado o motivo.
              </p>
            </Section>

            <Section title="7. Seus direitos (LGPD)">
              <p>
                Nos termos da Lei Geral de Proteção de Dados (Lei nº 13.709/2018), você pode
                solicitar:
              </p>
              <List
                items={[
                  'Confirmação da existência de tratamento e acesso aos dados.',
                  'Correção de dados incompletos, inexatos ou desatualizados.',
                  'Anonimização, bloqueio ou eliminação de dados desnecessários ou excessivos.',
                  'Portabilidade dos dados, nos termos da regulamentação.',
                  'Informação sobre compartilhamentos realizados.',
                  'Revogação do consentimento e oposição a tratamentos quando aplicável.',
                ]}
              />
              <p>
                Para exercer seus direitos, entre em contato pelo e-mail {CONTACT_EMAIL}. Você também
                pode apresentar reclamação à Autoridade Nacional de Proteção de Dados (ANPD).
              </p>
            </Section>

            <Section title="8. Crianças e adolescentes">
              <p>
                O {APP_NAME} é destinado ao uso profissional por maiores de 18 anos. Não coletamos
                intencionalmente dados de crianças ou adolescentes. Caso identifiquemos esse tipo de
                dado, ele será eliminado.
              </p>
            </Section>

            <Section title="9. Transferência internacional">
              <p>
                Os dados podem ser processados e armazenados em servidores de provedores de nuvem
                localizados fora do Brasil. Nesses casos, adotamos salvaguardas contratuais e
                técnicas para garantir proteção adequada, conforme a LGPD.
              </p>
            </Section>

            <Section title="10. Cookies">
              <p>
                O painel web utiliza cookies estritamente necessários para autenticação e segurança
                da sessão. Não utilizamos cookies de publicidade nem rastreamento para fins de
                marketing.
              </p>
            </Section>

            <Section title="11. Alterações desta política">
              <p>
                Podemos atualizar esta política para refletir mudanças no serviço ou na legislação.
                A versão vigente estará sempre disponível nesta página, com a data da última
                atualização. Alterações relevantes serão comunicadas pelos canais de atendimento ou
                dentro do aplicativo.
              </p>
            </Section>

            <Section title="12. Contato">
              <p>
                Em caso de dúvidas sobre esta política ou sobre o tratamento de dados pessoais,
                fale com nosso Encarregado de Proteção de Dados (DPO):
              </p>
              <p>
                <strong className="text-foreground">E-mail:</strong>{' '}
                <a className="text-primary underline" href={`mailto:${CONTACT_EMAIL}`}>
                  {CONTACT_EMAIL}
                </a>
              </p>
            </Section>
          </CardContent>
        </Card>

        <p className="mt-6 text-center text-xs text-subtle">
          © {new Date().getFullYear()} {APP_NAME} — Todos os direitos reservados.
        </p>
      </main>
    </div>
  )
}
