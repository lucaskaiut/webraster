import { useCallback, useMemo } from 'react'
import { useSearchParams } from 'react-router'
import { parseSortParam, serializeSortParam, toggleSortCriterion } from '@/shared/utils/table-sort'

/**
 * Estado de ordenação das tabelas persistido na URL (`?sort=coluna:asc,...`),
 * permitindo múltiplas colunas ordenadas ao mesmo tempo.
 */
export function useTableSort() {
  const [searchParams, setSearchParams] = useSearchParams()
  const sort = useMemo(() => parseSortParam(searchParams.get('sort')), [searchParams])

  const toggleSort = useCallback(
    (column: string) => {
      setSearchParams(
        (params) => {
          const next = toggleSortCriterion(parseSortParam(params.get('sort')), column)

          if (next.length > 0) {
            params.set('sort', serializeSortParam(next))
          } else {
            params.delete('sort')
          }

          params.delete('page')

          return params
        },
        { replace: true },
      )
    },
    [setSearchParams],
  )

  const sortParam = sort.length > 0 ? serializeSortParam(sort) : undefined

  return { sort, sortParam, toggleSort }
}
