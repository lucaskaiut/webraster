<?php

namespace App\Modules\Tenant\Enums;

/**
 * Fontes disponíveis para o tema do painel (carregadas do Google Fonts).
 */
enum ThemeFont: string
{
    case INTER = 'Inter';
    case ROBOTO = 'Roboto';
    case OPEN_SANS = 'Open Sans';
    case LATO = 'Lato';
    case MONTSERRAT = 'Montserrat';
    case POPPINS = 'Poppins';
    case NUNITO = 'Nunito';
    case SOURCE_SANS_3 = 'Source Sans 3';
    case WORK_SANS = 'Work Sans';
    case MANROPE = 'Manrope';
    case DM_SANS = 'DM Sans';
    case IBM_PLEX_SANS = 'IBM Plex Sans';
    case RALEWAY = 'Raleway';
    case RUBIK = 'Rubik';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
