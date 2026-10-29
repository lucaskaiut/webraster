/**
 * Identifica este painel para a API: valida a permissão de acesso a ele e
 * mantém sessão e cookie XSRF próprios (um painel não herda o login do outro).
 */
export const PANEL = 'agendamento'

export const PANEL_HEADER = 'X-Panel'

export const XSRF_COOKIE_NAME = `XSRF-TOKEN-${PANEL}`
