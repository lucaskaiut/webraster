/** Telas liberadas para o cliente bloqueado por inadimplência. */
const ALLOWED_PREFIXES = ['/finance/portal']

export const BLOCKED_HOME = '/finance/portal/billings'

export function isAllowedWhileBlocked(pathname: string): boolean {
  return ALLOWED_PREFIXES.some((prefix) => pathname === prefix || pathname.startsWith(`${prefix}/`))
}
