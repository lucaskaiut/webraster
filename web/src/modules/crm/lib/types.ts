export interface CrmContact {
  id: string
  name: string | null
  phone: string | null
  avatar_url?: string | null
  email?: string | null
}

export interface CrmStage {
  id: string
  name: string
  color?: string | null
}

export interface CrmLead {
  id: string
  status: string
  source?: string | null
  score?: number | null
  notes?: string | null
  ai_enabled: boolean
  last_interaction_at?: string | null
  created_at?: string
  contact: CrmContact
  pipeline?: { id: string; name: string }
  stage?: CrmStage
  owner?: { id: string; name: string } | null
}

export interface CrmPipeline {
  id: string
  name: string
  description?: string | null
  active: boolean
  is_default: boolean
  stages?: CrmPipelineStage[]
}

export interface CrmPipelineStage {
  id: string
  name: string
  description?: string | null
  position: number
  color?: string | null
  is_initial: boolean
  is_final: boolean
  is_won: boolean
  is_lost: boolean
  active: boolean
}

export interface CrmPipelineStageInput {
  name: string
  description?: string | null
  color?: string | null
  is_initial?: boolean
  is_final?: boolean
  is_won?: boolean
  is_lost?: boolean
  active?: boolean
  position?: number
}

export interface CrmPipelineInput {
  name: string
  description?: string | null
  active?: boolean
  is_default?: boolean
}

export interface CrmConversation {
  id: string
  status: string
  chat_type: string
  ai_mode: string
  unread_count: number
  last_message_at?: string | null
  contact: CrmContact
  lead?: { id: string; ai_enabled: boolean; stage?: CrmStage }
  last_message?: CrmMessage | null
}

export interface CrmMessage {
  id: string
  conversation_id?: string
  direction: 'inbound' | 'outbound'
  sender_type: string
  message_type: string
  text?: string | null
  status: string
  sent_at?: string | null
}

export interface MessagingConnection {
  id: string
  provider: string
  name: string
  base_url: string
  instance_name?: string | null
  connection_status: string
  is_active: boolean
  webhook_url: string
  has_api_key: boolean
}

export interface EvolutionConnectPayload {
  state: string
  qrcode_base64?: string | null
  pairing_code?: string | null
  count?: number | null
}

export interface EvolutionBootstrapPayload {
  name?: string
}

export interface CrmAiConfiguration {
  enabled: boolean
  api_endpoint?: string | null
  has_api_key?: boolean
  system_prompt?: string | null
  model?: string | null
  temperature?: number | null
  max_tokens?: number | null
}
