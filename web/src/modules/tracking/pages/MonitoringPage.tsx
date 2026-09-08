import { useCallback, useEffect, useMemo, useState } from 'react'
import { useTrackingLiveQuery, useTrackingStatusQuery } from '../hooks/useTracking'
import type { GpsPosition } from '@/shared/types/models'
import type { FleetFilter } from '../lib/tracking'
import { FleetSidebar } from '../components/FleetSidebar'
import { HistoryDrawer } from '../components/HistoryDrawer'
import { MonitoringHeader } from '../components/MonitoringHeader'
import { TrackingMap } from '../components/TrackingMap'
import { VehicleInfoPanel } from '../components/VehicleInfoPanel'
import { useGeofencesMapQuery } from '@/modules/geofences/hooks/useGeofences'
import { usePoisMapQuery } from '@/modules/pois/hooks/usePois'
import { useAlertsMapQuery } from '@/modules/alerts/hooks/useAlerts'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { Permission } from '@/shared/constants/permissions'

export default function MonitoringPage() {
  const [search, setSearch] = useState('')
  const [filter, setFilter] = useState<FleetFilter>('all')
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const [route, setRoute] = useState<GpsPosition[]>([])
  const [playbackIndex, setPlaybackIndex] = useState(0)
  const [playing, setPlaying] = useState(false)
  const [playbackSpeed, setPlaybackSpeed] = useState<0.5 | 1 | 2 | 4 | 8>(1)
  const [follow, setFollow] = useState(false)
  const [historyOpen, setHistoryOpen] = useState(false)
  const [showEvents, setShowEvents] = useState(false)
  const [now, setNow] = useState(() => Date.now())
  const [fitRequest, setFitRequest] = useState<{
    id: number
    target: 'fleet' | 'vehicle' | 'route'
  } | null>(null)
  const [showVehicles, setShowVehicles] = useState(true)
  const [showGeofences, setShowGeofences] = useState(true)
  const [showPois, setShowPois] = useState(true)
  const [showAlerts, setShowAlerts] = useState(true)
  const [selectedGeofenceId, setSelectedGeofenceId] = useState<string | null>(null)
  const [selectedPoiId, setSelectedPoiId] = useState<string | null>(null)
  const [selectedAlertId, setSelectedAlertId] = useState<string | null>(null)

  const { can } = usePermissions()
  const statusQuery = useTrackingStatusQuery()
  const liveQuery = useTrackingLiveQuery()
  const geofencesQuery = useGeofencesMapQuery(undefined, can(Permission.GEOFENCE_READ) && showGeofences)
  const poisQuery = usePoisMapQuery(undefined, can(Permission.POI_READ) && showPois)
  const alertsQuery = useAlertsMapQuery('open', can(Permission.ALERT_READ) && showAlerts)

  const vehicles = liveQuery.data ?? []
  const selected = useMemo(
    () => vehicles.find((item) => item.id === selectedId) ?? null,
    [vehicles, selectedId],
  )
  const playbackPosition = route[playbackIndex] ?? null

  useEffect(() => {
    const timer = window.setInterval(() => setNow(Date.now()), 5000)
    return () => window.clearInterval(timer)
  }, [])

  useEffect(() => {
    if (!playing || route.length < 2) return

    const timer = window.setInterval(() => {
      setPlaybackIndex((current) => {
        if (current >= route.length - 1) {
          setPlaying(false)
          return current
        }
        return current + 1
      })
    }, Math.max(80, 400 / playbackSpeed))

    return () => window.clearInterval(timer)
  }, [playing, route.length, playbackSpeed])

  const requestFit = useCallback((target: 'fleet' | 'vehicle' | 'route') => {
    setFitRequest((current) => ({ id: (current?.id ?? 0) + 1, target }))
  }, [])

  const handleSelect = useCallback((id: string) => {
    setSelectedId(id)
    setSelectedGeofenceId(null)
    setSelectedPoiId(null)
    setSelectedAlertId(null)
    setRoute([])
    setPlaybackIndex(0)
    setPlaying(false)
    setFollow(false)
    setFitRequest((current) => ({ id: (current?.id ?? 0) + 1, target: 'vehicle' }))
  }, [])

  const handleRouteChange = useCallback(
    (next: GpsPosition[]) => {
      setRoute(next)
      setPlaybackIndex(0)
      setPlaying(false)
      if (next.length > 0) requestFit('route')
    },
    [requestFit],
  )

  return (
    <div className="flex min-h-0 flex-1 flex-col gap-3">
      <MonitoringHeader
        connected={statusQuery.data ? statusQuery.data.configured : null}
        updating={liveQuery.isFetching}
        updatedAt={liveQuery.dataUpdatedAt || null}
        onRefresh={() => void liveQuery.refetch()}
        error={liveQuery.isError ? 'tracking' : null}
      />

      <div className="grid min-h-0 flex-1 grid-cols-1 gap-3 lg:grid-cols-[300px_minmax(0,1fr)]">
        <FleetSidebar
          className="order-2 max-h-56 lg:order-1 lg:max-h-none"
          vehicles={vehicles}
          search={search}
          onSearch={setSearch}
          filter={filter}
          onFilter={setFilter}
          selectedId={selectedId}
          onSelect={handleSelect}
          loading={liveQuery.isPending}
          now={now}
        />

        <div className="relative order-1 min-h-[50vh] flex-1 lg:order-2 lg:min-h-0">
          <TrackingMap
            vehicles={vehicles}
            selectedId={selectedId}
            onSelect={handleSelect}
            route={route}
            playbackPosition={playing || playbackIndex > 0 ? playbackPosition : null}
            follow={follow}
            fitRequest={fitRequest}
            onFitFleet={() => requestFit('fleet')}
            onFitSelected={() => selected && requestFit('vehicle')}
            showVehicles={showVehicles}
            showGeofences={showGeofences && can(Permission.GEOFENCE_READ)}
            showPois={showPois && can(Permission.POI_READ)}
            showAlerts={showAlerts && can(Permission.ALERT_READ)}
            onToggleVehicles={setShowVehicles}
            onToggleGeofences={setShowGeofences}
            onTogglePois={setShowPois}
            onToggleAlerts={setShowAlerts}
            geofences={geofencesQuery.data ?? []}
            pois={poisQuery.data ?? []}
            alerts={alertsQuery.data ?? []}
            selectedGeofenceId={selectedGeofenceId}
            selectedPoiId={selectedPoiId}
            selectedAlertId={selectedAlertId}
            onSelectGeofence={(id) => {
              setSelectedGeofenceId(id)
              setSelectedPoiId(null)
              setSelectedAlertId(null)
            }}
            onSelectPoi={(id) => {
              setSelectedPoiId(id)
              setSelectedGeofenceId(null)
              setSelectedAlertId(null)
            }}
            onSelectAlert={(id) => {
              setSelectedAlertId(id)
              setSelectedGeofenceId(null)
              setSelectedPoiId(null)
            }}
          />

          {selected && (
            <div className="pointer-events-none absolute inset-x-3 bottom-3 z-10 lg:right-16">
              <div className="pointer-events-auto">
                <VehicleInfoPanel
                  vehicle={selected}
                  now={now}
                  follow={follow}
                  onFollowChange={setFollow}
                  onCenter={() => requestFit('vehicle')}
                  onHistory={() => {
                    setShowEvents(false)
                    setHistoryOpen(true)
                  }}
                  onEvents={() => {
                    setShowEvents(true)
                    setHistoryOpen(true)
                  }}
                />
              </div>
            </div>
          )}
        </div>
      </div>

      <HistoryDrawer
        open={historyOpen}
        onClose={() => setHistoryOpen(false)}
        vehicle={selected}
        route={route}
        onRouteChange={handleRouteChange}
        playbackIndex={playbackIndex}
        onPlaybackIndexChange={setPlaybackIndex}
        playing={playing}
        onPlayingChange={setPlaying}
        playbackSpeed={playbackSpeed}
        onPlaybackSpeedChange={setPlaybackSpeed}
        showEvents={showEvents}
      />
    </div>
  )
}
