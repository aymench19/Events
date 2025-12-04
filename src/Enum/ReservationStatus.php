<?php

namespace App\Enum;

enum ReservationStatus: string
{
    case CONFIRMED = 'CONFIRMED';
    case PENDING = 'PENDING';
    case CANCELLED = 'CANCELLED';
}