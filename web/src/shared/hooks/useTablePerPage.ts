import { useSearchParams } from 'react-router'
import { TABLE_PER_PAGE_OPTIONS, parseTablePerPage } from '@/shared/constants/pagination'

/**
 * Quantidade de registros por página das listagens (10, 50, 100 ou todos),
 * guardada em `per_page` na URL. Trocar a quantidade volta para a página 1.
 */
export function useTablePerPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const perPage = parseTablePerPage(searchParams.get('per_page'))

  const setPerPage = (value: number | 'all') => {
    setSearchParams(
      (params) => {
        if (value === 10) {
          params.delete('per_page')
        } else {
          params.set('per_page', String(value))
        }
        params.delete('page')
        return params
      },
      { replace: true },
    )
  }

  return {
    perPage,
    setPerPage,
    /** Props para o componente Pagination. */
    perPageProps: {
      perPage,
      perPageOptions: TABLE_PER_PAGE_OPTIONS,
      onPerPageChange: setPerPage,
    },
  }
}
