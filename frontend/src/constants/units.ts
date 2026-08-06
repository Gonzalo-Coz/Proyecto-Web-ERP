/**
 * Unidades de medida disponibles en el sistema (desplegable en productos).
 * Cubren el uso típico de una tienda de motos, repuestos y accesorios.
 */
export const UNITS_OF_MEASURE = [
  'UNIDAD',
  'PAR',
  'JUEGO',
  'KIT',
  'DOCENA',
  'CIENTO',
  'MILLAR',
  'CAJA',
  'PAQUETE',
  'BOLSA',
  'ROLLO',
  'METRO',
  'CENTIMETRO',
  'PIE',
  'PULGADA',
  'LITRO',
  'GALON',
  'MILILITRO',
  'KILOGRAMO',
  'GRAMO',
  'LIBRA',
  'ONZA',
  'BALDE',
  'FRASCO',
  'TUBO',
  'LATA',
] as const

export type UnitOfMeasure = (typeof UNITS_OF_MEASURE)[number]
