/** Links do Google Maps para uma posição (abrem no app do Maps no celular). */
export interface GoogleMapsLinks {
  /** Pino na posição. */
  place: string
  /** Visão de satélite centrada na posição. */
  satellite: string
  /** Street View no ponto mais próximo. */
  streetView: string
  /** Rota da localização atual de quem abre o link até a posição. */
  directions: string
}

export function googleMapsLinks(position: { latitude: number; longitude: number }): GoogleMapsLinks {
  const point = `${position.latitude},${position.longitude}`

  return {
    place: `https://www.google.com/maps/search/?api=1&query=${point}`,
    satellite: `https://www.google.com/maps/@?api=1&map_action=map&center=${point}&zoom=19&basemap=satellite`,
    streetView: `https://www.google.com/maps/@?api=1&map_action=pano&viewpoint=${point}`,
    directions: `https://www.google.com/maps/dir/?api=1&destination=${point}`,
  }
}
