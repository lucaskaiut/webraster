<?php

namespace App\Modules\Vehicle\Enums;

enum VehicleType: int
{
    case CAR = 1;
    case MOTORCYCLE = 2;
    case VAN = 3;
    case AQUATIC = 4;
    case TRUCK = 5;
    case TRACTOR = 6;
    case BUS = 7;
    case PICKUP = 8;
    case CELLPHONE = 9;
    case BAG = 10;
    case QUADRICYCLE = 11;
    case BACKHOE = 12;
    case BICYCLE = 13;
    case PAREDAO = 14;
    case TRAILER = 15;
    case BUGGY = 16;
    case ELECTRIC_MOTORCYCLE = 17;
    case FUSION_MACHINE = 995;
    case FORKLIFT = 997;
    case ANIMAL = 998;
    case PERSON = 999;
    case SEMI_TRAILER = 1000;
    case TRICYCLE = 1001;
    case DUMP_TRUCK = 1002;
    case OTHER = 1003;
    case TOW_TRUCK = 1004;
    case GENERATOR = 1005;

    public function label(): string
    {
        return match ($this) {
            self::CAR => 'Carro',
            self::MOTORCYCLE => 'Moto',
            self::VAN => 'VAN',
            self::AQUATIC => 'Veículo Aquático',
            self::TRUCK => 'Caminhão',
            self::TRACTOR => 'Trator',
            self::BUS => 'Ônibus',
            self::PICKUP => 'Pickup',
            self::CELLPHONE => 'Celular',
            self::BAG => 'BAG',
            self::QUADRICYCLE => 'Quadriciclo',
            self::BACKHOE => 'Retroescavadeira',
            self::BICYCLE => 'Bicicleta',
            self::PAREDAO => 'Paredão',
            self::TRAILER => 'Reboque',
            self::BUGGY => 'Buggy',
            self::ELECTRIC_MOTORCYCLE => 'Moto Elétrica',
            self::FUSION_MACHINE => 'Máquina de Fusão',
            self::FORKLIFT => 'Empilhadeira',
            self::ANIMAL => 'Animal',
            self::PERSON => 'Pessoa',
            self::SEMI_TRAILER => 'Carreta',
            self::TRICYCLE => 'Triciclo',
            self::DUMP_TRUCK => 'Caçamba',
            self::OTHER => 'Outros',
            self::TOW_TRUCK => 'Guincho',
            self::GENERATOR => 'Gerador',
        };
    }
}
